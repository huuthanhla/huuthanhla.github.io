<?php

class XenForo_Model_UserChangeTemp extends XenForo_Model
{
	public function applyTempUserChange($userId, $actionType, $actionModifier, $newValue = null, $expiryDate = null, $changeKey = null)
	{
		/** @var XenForo_DataWriter_User $dw */
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
		if (!$dw->setExistingData($userId))
		{
			return false;
		}

		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		if ($changeKey !== null)
		{
			// the same change keys could change different fields, so need to expire
			// the old one to replace it
			$this->expireTempUserChangeByKey($userId, $changeKey);
		}

		$oldValue = null;

		switch ($actionType)
		{
			case 'groups':
				if (!$actionModifier)
				{
					$actionModifier = 'user_change_' . substr(md5(uniqid()), 0, 16);
				}
				$this->_getUserModel()->addUserGroupChange($userId, $actionModifier, $newValue);
				break;

			case 'field':
				$originalChange = $db->fetchRow("
					SELECT *
					FROM xf_user_change_temp
					WHERE user_id = ?
						AND action_type = 'field'
						AND action_modifier = ?
					ORDER BY create_date
					LIMIT 1
				", array($userId, $actionModifier));
				$oldValue = $originalChange ? $originalChange['old_value'] : $dw->get($actionModifier);
				$dw->set($actionModifier, $newValue);
				if ($dw->isChanged($actionModifier))
				{
					$dw->save();
				}
		}

		if (!is_scalar($newValue))
		{
			$newValue = serialize($newValue);
		}
		if (!is_scalar($oldValue))
		{
			$oldValue = serialize($oldValue);
		}
		if (!$expiryDate)
		{
			$expiryDate = null;
		}

		$record = array(
			'user_id' => $userId,
			'change_key' => $changeKey,
			'action_type' => $actionType,
			'action_modifier' => $actionModifier,
			'new_value' => $newValue,
			'old_value' => $oldValue,
			'create_date' => XenForo_Application::$time,
			'expiry_date' => $expiryDate
		);

		$db->insert('xf_user_change_temp', $record);
		$record['user_change_temp_id'] = $db->lastInsertId();

		XenForo_Db::commit($db);

		return $record;
	}

	public function getTempUserChangeByKey($userId, $changeKey)
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM xf_user_change_temp
			WHERE user_id = ?
				AND change_key = ?
		", array($userId, $changeKey));
	}

	public function getTempUserChangeById($id)
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM xf_user_change_temp
			WHERE user_change_temp_id = ?
		", $id);
	}

	public function getExpiredTempUserChanges($expiry = null)
	{
		if ($expiry === null)
		{
			$expiry = XenForo_Application::$time;
		}

		return $this->fetchAllKeyed("
			SELECT *
			FROM xf_user_change_temp
			WHERE expiry_date < ?
				AND expiry_date IS NOT NULL
			ORDER BY expiry_date
		", 'user_change_temp_id', $expiry);
	}

	public function expireTempUserChangeByKey($userId, $changeKey)
	{
		$existing = $this->getTempUserChangeByKey($userId, $changeKey);
		if ($existing)
		{
			return $this->expireTempUserChange($existing);
		}

		return false;
	}

	public function expireTempUserChange(array $change)
	{
		$userId = $change['user_id'];
		$actionType = $change['action_type'];
		$actionModifier = $change['action_modifier'];

		/** @var XenForo_DataWriter_User $dw */
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
		if (!$dw->setExistingData($userId))
		{
			return false;
		}

		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$res = $db->query("
			DELETE FROM xf_user_change_temp
			WHERE user_change_temp_id = ?
		", $change['user_change_temp_id']);

		if (!$res->rowCount())
		{
			// already deleted
			XenForo_Db::rollback($db);
			return false;
		}

		switch ($actionType)
		{
			case 'groups':
				$this->_getUserModel()->removeUserGroupChange($userId, $actionModifier);
				break;

			case 'field':
				// if the field was changed to the current value, revert it
				// to the most recent new value or the original value if none
				if (strval($dw->get($actionModifier)) === $change['new_value'])
				{
					$lastChange = $db->fetchRow("
						SELECT *
						FROM xf_user_change_temp
						WHERE user_id = ?
							AND action_type = 'field'
							AND action_modifier = ?
						ORDER BY create_date DESC
						LIMIT 1
					", array($userId, $actionModifier));
					$oldValue = $lastChange ? $lastChange['new_value'] : $change['old_value'];
					$dw->set($actionModifier, $oldValue);
					if ($dw->isChanged($actionModifier))
					{
						$dw->save();
					}
				}
		}

		XenForo_Db::commit($db);

		return true;
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}