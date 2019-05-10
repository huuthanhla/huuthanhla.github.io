<?php

class XenForo_ControllerHelper_UserCriteria extends XenForo_ControllerHelper_Abstract
{
	public function filterUserSearchCriteria(array $criteria)
	{
		foreach ($criteria AS $key => $value)
		{
			if ($value === '')
			{
				unset($criteria[$key]);
			}
			else
			{
				switch ($key)
				{
					case 'user_group_id':
					case 'message_count_start':
					case 'trophy_points_start':
						if ($value === '0' || $value === 0 || (is_array($value) && in_array(0, $value)))
						{
							unset($criteria[$key]);
						}

					case 'message_count_end':
					case 'trophy_points_end':
						if ($value === '-1' || $value === -1)
						{
							unset($criteria[$key]);
						}
						break;

					case 'register_date_start':
					case 'register_date_end':
					case 'last_activity_start':
					case 'last_activity_end':
						if (!preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', strval($value)))
						{
							unset($criteria[$key]);
						}
						break;
				}
			}
		}

		if (!empty($criteria['custom']) && is_array($criteria['custom']))
		{
			foreach ($criteria['custom'] AS $key => &$custom)
			{
				if (is_array($custom))
				{
					foreach ($custom AS $subKey => &$subValue)
					{
						$subValue = strval($subValue);
						if ($subValue === '')
						{
							unset($custom[$subKey]);
						}
					}
					if (!$custom)
					{
						unset($criteria['custom'][$key]);
					}
				}
				else
				{
					$custom = strval($custom);
					if ($custom === '')
					{
						unset($criteria['custom'][$key]);
					}
				}

			}
		}
		if (empty($criteria['custom']))
		{
			unset($criteria['custom']);
		}

		if (!empty($criteria['custom_exact']) && is_array($criteria['custom_exact']))
		{
			$criteria['custom_exact'] = array_map('intval', $criteria['custom_exact']);
		}

		if (isset($criteria['user_state']) && is_array($criteria['user_state']) && count($criteria['user_state']) == 5)
		{
			// all types selected, no filtering
			unset($criteria['user_state']);
		}
		if (isset($criteria['is_banned']) && is_array($criteria['is_banned']) && count($criteria['is_banned']) == 2)
		{
			// both options selected, no filtering
			unset($criteria['is_banned']);
		}
		if (isset($criteria['is_discouraged']) && is_array($criteria['is_discouraged']) && count($criteria['is_discouraged']) == 2)
		{
			// both options selected, no filtering
			unset($criteria['is_discouraged']);
		}
		if (isset($criteria['is_staff']) && is_array($criteria['is_staff']) && count($criteria['is_staff']) == 2)
		{
			// both options selected, no filtering
			unset($criteria['is_staff']);
		}
		if (isset($criteria['gender']) && is_array($criteria['gender']))
		{
			if (count($criteria['gender']) == 3)
			{
				unset($criteria['gender']);
			}
			else
			{
				$unspecifiedKey = array_search('unspecified', $criteria['gender']);
				if ($unspecifiedKey !== false)
				{
					unset($criteria['gender'][$unspecifiedKey]);
					$criteria['gender'][] = '';
				}
			}
		}

		if (!empty($criteria['no_secondary_group_ids']))
		{
			unset($criteria['secondary_group_ids'], $criteria['not_secondary_group_ids']);
		}
		else
		{
			unset($criteria['no_secondary_group_ids']);
		}

		return $criteria;
	}

	public function prepareUserSearchCriteria(array $criteria)
	{
		if (isset($criteria['is_banned']) && is_array($criteria['is_banned']))
		{
			$criteria['is_banned'] = reset($criteria['is_banned']);
		}
		if (isset($criteria['is_discouraged']) && is_array($criteria['is_discouraged']))
		{
			$criteria['is_discouraged'] = reset($criteria['is_discouraged']);
		}
		if (isset($criteria['is_staff']) && is_array($criteria['is_staff']))
		{
			$criteria['is_staff'] = reset($criteria['is_staff']);
		}

		foreach (array('register_date', 'last_activity') AS $field)
		{
			if (!empty($criteria["{$field}_start"]))
			{
				$date = new DateTime($criteria["{$field}_start"], XenForo_Locale::getDefaultTimeZone());
				$criteria["{$field}_start"]= $date->format('U');
			}

			if (!empty($criteria["{$field}_end"]))
			{
				$date = new DateTime($criteria["{$field}_end"], XenForo_Locale::getDefaultTimeZone());
				$date->setTime(23, 59, 59);
				$criteria["{$field}_end"] = $date->format('U');
			}
		}

		foreach (array('message_count' => 0, 'trophy_points' => 0, 'register_date' => 1, 'last_activity' => 1) AS $field => $upperMin)
		{
			$lower = null;
			$upper = null;

			if (!empty($criteria["{$field}_start"]) && intval($criteria["{$field}_start"]))
			{
				$lower = intval($criteria["{$field}_start"]);
			}

			if (isset($criteria["{$field}_end"]) && intval($criteria["{$field}_end"]) >= $upperMin)
			{
				$upper = intval($criteria["{$field}_end"]);
			}

			unset($criteria["{$field}_start"], $criteria["{$field}_end"]);

			if ($lower !== null && $upper !== null)
			{
				$criteria[$field] = array('>=<', $lower, $upper);
			}
			else if ($lower !== null)
			{
				$criteria[$field] = array('>=', $lower);
			}
			else if ($upper !== null)
			{
				$criteria[$field] = array('<=', $upper);
			}
		}

		foreach (array('username', 'username2', 'email') AS $field)
		{
			if (isset($criteria[$field]) && is_string($criteria[$field]))
			{
				$criteria[$field] = trim($criteria[$field]);
			}
		}

		if (isset($criteria['custom']))
		{
			if (!empty($criteria['custom']) && is_array($criteria['custom']))
			{
				$criteria['customFields'] = $criteria['custom'];
				if (!empty($criteria['custom_exact']) && is_array($criteria['custom_exact']))
				{
					$criteria['customFieldsExact'] = $criteria['custom_exact'];
				}
			}

			unset($criteria['custom'], $criteria['custom_exact']);
		}

		return $criteria;
	}

	public function getDataForUserSearchForm()
	{
		/** @var $userGroupModel XenForo_Model_UserGroup */
		$userGroupModel = XenForo_Model::create('XenForo_Model_UserGroup');

		/** @var $fieldModel XenForo_Model_UserField */
		$fieldModel = XenForo_Model::create('XenForo_Model_UserField');

		return array(
			'userGroups' => $userGroupModel->getAllUserGroupTitles(),
			'customFields' => $fieldModel->prepareUserFields($fieldModel->getUserFields(), true)
		);
	}

	public function getDefaultUserSearchCriteria()
	{
		return array(
			'user_state' => array(
				'valid' => true,
				'email_confirm' => true,
				'email_confirm_edit' => true,
				'email_bounce' => true,
				'moderated' => true
			),
			'is_banned' => array(0 => true, 1 => true),
			'is_discouraged' => array(0 => true, 1 => true),
			'is_staff' => array(0 => true, 1 => true),
			'gender' => array('male' => true, 'female' => true, 'unspecified' => true),
			'no_secondary_group_ids' => 0,
			'message_count_end' => -1,
			'trophy_points_end' => -1
		);
	}
}