<?php

/**
 * Model for user change logs.
 *
 * @package XenForo_Users
 */
class XenForo_Model_UserChangeLog extends XenForo_Model
{
	/**
	 * Logs changes made to the given user
	 *
	 * @param integer $userId
	 * @param array $changedFields
	 * @param integer $editUserId
	 */
	public function logChanges($userId, array $changedFields, $editUserId = null)
	{
		$db = $this->_getDb();

		if (!$userId)
		{
			return false;
		}

		if (!$editUserId)
		{
			$editUserId = XenForo_Visitor::getUserId();
		}
		if (!$editUserId)
		{
			// there's a rare case where guests can appear to edit a user, such as a lost password
			// request or with system actions; some of these auth the user in a different manner
			// and some are not really being edited by a particular user, so treat that as a self edit
			// for logging purposes
			$editUserId = $userId;
		}

		XenForo_Db::beginTransaction();

		foreach ($changedFields AS $field => $values)
		{
			if ($field == 'custom_fields')
			{
				$oldValues = unserialize($values[0]);
				if (!is_array($oldValues))
				{
					$oldValues = array();
				}
				$newValues = unserialize($values[1]);
				if (!is_array($newValues))
				{
					$newValues = array();
				}

				$changes = array();

				foreach ($oldValues AS $customField => $oldValue)
				{
					if (!isset($newValues[$customField]))
					{
						continue;
					}

					$newValue = $newValues[$customField];

					if ($oldValue !== $newValue)
					{
						if (is_array($oldValue) && is_array($newValue))
						{
							$changes["$field:$customField"] = array(serialize($oldValue), serialize($newValue));
						}
						else
						{
							$changes["$field:$customField"] = array($oldValue, $newValue);
						}
					}
				}

				// catch places where a new field value was added
				foreach ($newValues AS $customField => $newValue)
				{
					if (isset($oldValues[$customField]))
					{
						// already tested
						continue;
					}

					if (is_array($newValue))
					{
						$oldValue = serialize(array());
						$newValue = serialize($newValue);
					}
					else
					{
						$oldValue = '';
					}

					if ($oldValue !== $newValue)
					{
						$changes["$field:$customField"] = array($oldValue, $newValue);
					}
				}
			}
			else
			{
				$changes = array($field => array($values[0], $values[1]));
			}

			foreach ($changes AS $fieldName => $change)
			{
				$change[0] = strval($change[0]);
				$change[1] = strval($change[1]);
				if ($change[0] === $change[1])
				{
					// extra check in case we went null <-> empty string - we want to ignore that case
					continue;
				}

				$db->insert('xf_user_change_log', array(
					'user_id' => $userId,
					'edit_user_id' => $editUserId,
					'edit_date' => XenForo_Application::$time,
					'field' => $fieldName,
					'old_value' => $change[0],
					'new_value' => $change[1],
				));
			}
		}

		XenForo_Db::commit();

		return true;
	}

	public function getChangeLogs(array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareChangeLogConditions($conditions, $fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$db = $this->_getDb();

		$logs = $this->fetchAllKeyed($this->limitQueryResults('
			SELECT logs.*,

				user.username,
				user.avatar_date,
				user.avatar_width,
				user.avatar_height,
				user.gravatar,

				edit_user.username AS edit_username,
				edit_user.avatar_date AS edit_avatar_date,
				edit_user.avatar_width AS edit_avatar_width,
				edit_user.avatar_height AS edit_avatar_height,
				edit_user.gravatar AS edit_gravatar

			FROM xf_user_change_log AS logs
			LEFT JOIN xf_user AS user ON
				(user.user_id = logs.user_id)
			LEFT JOIN xf_user AS edit_user ON
				(edit_user.user_id = logs.edit_user_id)
			WHERE ' . $whereClause . '
			ORDER BY logs.edit_date DESC
		', $limitOptions['limit'], $limitOptions['offset']), 'log_id');

		$groupedLogs = array();

		if (!empty($logs))
		{
			foreach ($logs AS $logId => $log)
			{
				$groupKey = "$log[edit_date]-$log[user_id]-$log[edit_user_id]";

				if (!isset($groupedLogs[$groupKey]))
				{
					$groupedLogs[$groupKey] = array(
						'user' => array(
							'user_id' => $log['user_id'],
							'username' => $log['username'],
							'avatar_date' => $log['avatar_date'],
							'avatar_width' => $log['avatar_width'],
							'avatar_height' => $log['avatar_height'],
							'gravatar' => $log['gravatar'],
						),
						'editUser' => array(
							'user_id' => $log['edit_user_id'],
							'username' => $log['edit_username'],
							'avatar_date' => $log['edit_avatar_date'],
							'avatar_width' => $log['edit_avatar_width'],
							'avatar_height' => $log['edit_avatar_height'],
							'gravatar' => $log['edit_gravatar'],
						),
						'edit_date' => $log['edit_date'],
						'fields' => array(),
					);
				}

				$groupedLogs[$groupKey]['fields'][$logId] = $this->prepareField($log);
			}
		}

		return $groupedLogs;
	}

	public function countChangeLogs(array $conditions)
	{
		$whereClause = $this->prepareChangeLogConditions($conditions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM xf_user_change_log AS logs
			WHERE " . $whereClause
		);
	}

	public function countChangeLogsGrouped(array $conditions)
	{
		$whereClause = $this->prepareChangeLogConditions($conditions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(DISTINCT CONCAT(logs.edit_date, '-', logs.user_id, '-', logs.edit_user_id))
			FROM xf_user_change_log AS logs
			WHERE " . $whereClause
		);
	}

	public function prepareField(array $field)
	{
		XenForo_CodeEvent::fire('prepare_user_change_log_field', array($this, &$field));

		$field = $this->_getHelper()->prepareField($field);

		return array(
			'field' => $field['field'],
			'name' => $field['name'],
			'old_value' => $field['old_value'],
			'new_value' => $field['new_value'],
		);
	}

	public function prepareChangeLogConditions(array $conditions, array $fetchOptions = array())
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['user_id']))
		{
			$sqlConditions[] = 'logs.user_id = ' . $db->quote($conditions['user_id']);
		}

		if (!empty($conditions['edit_user_id']))
		{
			$sqlConditions[] = 'logs.edit_user_id = ' . $db->quote($conditions['edit_user_id']);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function pruneChangeLog($cutOff = null)
	{
		if ($cutOff === null)
		{
			$length = XenForo_Application::getOptions()->userChangeLogLength;
			if (!$length)
			{
				return 0;
			}

			$cutOff = XenForo_Application::$time - 86400 * $length;
		}

		$db = $this->_getDb();
		return $db->delete('xf_user_change_log', 'edit_date < ' . $db->quote($cutOff));
	}

	protected $_helperObject = null;

	/**
	 * @return XenForo_Helper_UserChangeLog
	 */
	protected function _getHelper()
	{
		if ($this->_helperObject === null)
		{
			$class = XenForo_Application::resolveDynamicClass('XenForo_Helper_UserChangeLog');
			$this->_helperObject = new $class();
		}

		return $this->_helperObject;
	}
}