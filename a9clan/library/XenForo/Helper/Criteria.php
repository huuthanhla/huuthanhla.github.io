<?php

/**
 * Helper to manage/check the criteria that are used in things
 * like trophies and user notices.
 *
 * @package XenForo_Criteria
 */
class XenForo_Helper_Criteria
{
	protected static $_userFieldPrefix = '__userField_';
	protected static $_userFieldPrefixLength = 12;

	/**
	 * Determines if the given user matches the criteria. The provided
	 * user should be a full user record; if fields are missing, an error
	 * will not be thrown, and the criteria check will fail.
	 *
	 * @param array|string $criteria List of criteria, format: [] with keys rule and data; may be serialized
	 * @param boolean $matchOnEmpty If true and there's no criteria, true is returned; otherwise, false
	 * @param array|null $user Full user record to check against; if null, user visitor
	 *
	 * @return boolean
	 */
	public static function userMatchesCriteria($criteria, $matchOnEmpty = false, array $user = null)
	{
		if (!$criteria = self::unserializeCriteria($criteria))
		{
			return (boolean)$matchOnEmpty;
		}

		if (!$user)
		{
			$user = XenForo_Visitor::getInstance()->toArray();
		}

		if (!isset($user['customFields']))
		{
			$user['customFields'] = !empty($user['custom_fields']) ? @unserialize($user['custom_fields']) : array();
		}
		if (!isset($user['externalAuth']))
		{
			$user['externalAuth'] = !empty($user['external_auth']) ? @unserialize($user['external_auth']) : array();
		}

		foreach ($criteria AS $criterion)
		{
			$data = $criterion['data'];

			// custom user fields
			if (strpos($criterion['rule'], self::$_userFieldPrefix) === 0)
			{
				$userFieldId = substr($criterion['rule'], self::$_userFieldPrefixLength);

				if (!isset($user['customFields'][$userFieldId]))
				{
					return false;
				}

				$userField = $user['customFields'][$userFieldId];

				// text fields - check that data exists within the text value
				if (isset($data['text']))
				{
					if (stripos($userField, $data['text']) === false)
					{
						return false;
					}
				}
				// choice fields - check that data is in the choice array
				else if (isset($data['choices']))
				{
					// multi-choice
					if (is_array($userField))
					{
						if (!array_intersect($userField, $data['choices']))
						{
							return false;
						}
					}
					// single choice
					else
					{
						if (!in_array($userField, $data['choices']))
						{
							return false;
						}
					}
				}
			}
			// regular user info
			else
			{
				switch ($criterion['rule'])
				{
					// username
					case 'username':
						$names = preg_split('/\s*,\s*/', utf8_strtolower($data['names']), -1, PREG_SPLIT_NO_EMPTY);
						if (!in_array(utf8_strtolower($user['username']), $names))
						{
							return false;
						}
					break;

					// username-search
					case 'username_search':
						if (self::_arrayStringSearch($data['needles'], $user['username']) === false)
						{
							return false;
						}
					break;

					// email-search
					case 'email_search':
						if (self::_arrayStringSearch($data['needles'], $user['email']) === false)
						{
							return false;
						}
					break;


					// days since registration
					case 'registered_days':
						if (!isset($user['register_date']))
						{
							return false;
						}
						$daysRegistered = floor((XenForo_Application::$time - $user['register_date']) / 86400);
						if ($daysRegistered < $data['days'])
						{
							return false;
						}
					break;

					// total messages posted
					case 'messages_posted':
						if (!isset($user['message_count']) || $user['message_count'] < $data['messages'])
						{
							return false;
						}
					break;

					// maximum messages posted
					case 'messages_maximum':
						if (!isset($user['message_count']) || $user['message_count'] > $data['messages'])
						{
							return false;
						}
					break;

					// total likes received
					case 'like_count':
						if (!isset($user['like_count']) || $user['like_count'] < $data['likes'])
						{
							return false;
						}
					break;

					// like:message ratio
					case 'like_ratio':
						if (empty($user['message_count']) || empty($user['like_count']))
						{
							return false;
						}
						if ($user['like_count'] / $user['message_count'] < $data['ratio'])
						{
							return false;
						}
					break;

					// total trophy points accumulated
					case 'trophy_points':
						if (!isset($user['trophy_points']) || $user['trophy_points'] < $data['points'])
						{
							return false;
						}
					break;

					// days since last activity
					case 'inactive_days':
						if (!isset($user['last_activity']) || empty($user['user_id']))
						{
							return false;
						}
						$daysInactive = floor((XenForo_Application::$time - $user['last_activity']) / 86400);
						if ($daysInactive < $data['days'])
						{
							return false;
						}
					break;

					// current browsing style ID
					case 'style':
						if (!isset($user['style_id']))
						{
							return false;
						}
						$styleId = (empty($user['style_id']) ? XenForo_Application::get('options')->defaultStyleId : $user['style_id']);
						if ($styleId != $data['style_id'])
						{
							return false;
						}
					break;

					// current browsing language ID
					case 'language':
						if (!isset($user['language_id']))
						{
							return false;
						}
						$languageId = (empty($user['language_id']) ? XenForo_Application::get('options')->defaultLanguageId : $user['language_id']);
						if ($languageId != $data['language_id'])
						{
							return false;
						}
					break;

					// gender of user
					case 'gender':
						if (!isset($user['gender']) || $user['gender'] != $data['gender'])
						{
							return false;
						}
					break;

					// user is logged in
					case 'is_logged_in':
						if (empty($user['user_id']))
						{
							return false;
						}
					break;

					// user is a guest
					case 'is_guest':
						if (!empty($user['user_id']))
						{
							return false;
						}
					break;

					// user is a moderator
					case 'is_moderator':
						if (empty($user['is_moderator']))
						{
							return false;
						}
					break;

					// user is an admin
					case 'is_admin':
						if (empty($user['is_admin']))
						{
							return false;
						}
					break;

					// user is banned
					case 'is_banned':
						if (empty($user['is_banned']))
						{
							return false;
						}
					break;

					// search referer
					case 'from_search':
						if (empty($user['from_search']))
						{
							return false;
						}
					break;

					// associated with Facebook
					case 'facebook':
						if (empty($user['externalAuth']['facebook']))
						{
							return false;
						}
					break;

					// associated with Twitter
					case 'twitter':
						if (empty($user['externalAuth']['twitter']))
						{
							return false;
						}
					break;

					// associated with Google
					case 'google':
						if (empty($user['externalAuth']['google']))
						{
							return false;
						}
					break;

					// has no avatar (and is not a guest)
					case 'no_avatar':
						if (empty($user['user_id']) || !empty($user['avatar_date']) || !empty($user['gravatar']))
						{
							return false;
						}
					break;

					// user group membership
					case 'user_groups':
					case 'not_user_groups':
						if (!isset($user['user_group_id'], $user['secondary_group_ids']))
						{
							return false;
						}

						$userGroups = ($user['secondary_group_ids'] ? explode(',', $user['secondary_group_ids']) : array());

						$matched = false;
						if (!empty($data['user_group_ids']))
						{
							foreach ($data['user_group_ids'] AS $matchUgId)
							{
								if ($user['user_group_id'] == $matchUgId || in_array($matchUgId, $userGroups))
								{
									$matched = true;
									break;
								}
							}
						}

						if ($criterion['rule'] == 'user_groups' && !$matched)
						{
							// failed to match at least 1 group
							return false;
						}
						else if ($criterion['rule'] == 'not_user_groups' && $matched)
						{
							// matched at least one group and shouldn't have
							return false;
						}
					break;

					// user state
					case 'user_state':
						if (!isset($user['user_state']) || $user['user_state'] != $data['state'])
						{
							return false;
						}
					break;

					// date criteria

					// birthday
					case 'birthday':
						if (empty($user['user_id']) || !isset($user['dob_day'], $user['dob_month']))
						{
							return false;
						}
						$today = XenForo_Locale::date(
							XenForo_Application::$time, 'j.n', null,
							(isset($user['timezone']) ? $user['timezone'] : null)
						);

						if ("$user[dob_day].$user[dob_month]" !== $today)
						{
							return false;
						}
					break;

					// before date / time
					case 'before':
					{
						$datetime = new DateTime("$data[ymd] $data[hh]:$data[mm]",
							new DateTimeZone(($data['user_tz'] ? $user['timezone'] : $data['timezone'])));

						if (XenForo_Application::$time >= $datetime->format('U'))
						{
							return false;
						}
					}
					break;

					// after date / time
					case 'after':
					{
						$datetime = new DateTime("$data[ymd] $data[hh]:$data[mm]",
							new DateTimeZone(($data['user_tz'] ? $user['timezone'] : $data['timezone'])));

						if (XenForo_Application::$time < $datetime->format('U'))
						{
							return false;
						}
					}
					break;

					// unknown criteria, assume failed unless something from a code event takes it
					default:
					{
						$eventReturnValue = false;

						XenForo_CodeEvent::fire('criteria_user', array($criterion['rule'], $data, $user, &$eventReturnValue));

						if ($eventReturnValue === false)
						{
							return false;
						}
					}
				}
			}
		}

		return true;
	}


	/**
	 * Determines if the given page matches the criteria. The provided page data takes the form of
	 * $params and $containerData from XenForo_ViewRenderer_HtmlPublic::_getNoticesContainerParams().
	 *
	 * @param array|string $criteria List of criteria, format: [] with keys rule and data; may be serialized
	 * @param boolean $matchOnEmpty If true and there's no criteria, true is returned; otherwise, false
	 * @param array $params
	 * @param array $containerData
	 *
	 * @return boolean
	 */
	public static function pageMatchesCriteria($criteria, $matchOnEmpty = false, array $params, array $containerData)
	{
		if (!$criteria = self::unserializeCriteria($criteria))
		{
			return (boolean)$matchOnEmpty;
		}

		foreach ($criteria AS $criterion)
		{
			$data = $criterion['data'];

			switch ($criterion['rule'])
			{
				// browsing within one of the specified nodes
				case 'nodes':
				{
					if (!isset($containerData['navigation']) || !is_array($containerData['navigation']))
					{
						return false;
					}
					if (empty($data['node_ids']))
					{
						return false; // no node ids specified
					}

					if (empty($data['node_only']))
					{
						foreach ($containerData['navigation'] AS $i => $navItem)
						{
							if (isset($navItem['node_id']) && in_array($navItem['node_id'], $data['node_ids']))
							{
								break 2; // break out of case 'nodes'...
							}
						}
					}

					if (isset($containerData['quickNavSelected']))
					{
						$quickNavSelected = $containerData['quickNavSelected'];
					}
					else
					{
						$quickNavSelected = false;
						foreach ($containerData['navigation'] AS $i => $navItem)
						{
							if (isset($navItem['node_id']))
							{
								$quickNavSelected = 'node-' . $navItem['node_id'];
							}
						}
					}

					if ($quickNavSelected && in_array(preg_replace('/^.+-(\d+)$/', '$1', $quickNavSelected), $data['node_ids']))
					{
						break 1;
					}

					return false;
				}
				break;

				// browsing within the specified controller (and action)
				case 'controller':
				{
					if (!isset($params['controllerName']) || strtolower($params['controllerName']) != strtolower($data['name']))
					{
						return false;
					}
					if (!empty($data['action']) && isset($params['controllerAction']))
					{
						if (strtolower($params['controllerAction']) != strtolower($data['action']))
						{
							return false;
						}
					}
				}
				break;

				// browsing within the specified view
				case 'view':
				{
					if (!isset($params['viewName']) || strtolower($params['viewName']) != strtolower($data['name']))
					{
						return false;
					}
				}
				break;

				// viewing the specified content template
				case 'template':
				{
					if (!isset($params['contentTemplate']) || strtolower($params['contentTemplate']) != strtolower($data['name']))
					{
						return false;
					}
				}
				break;

				// browsing within the specified tab
				case 'tab':
				{
					if (!isset($params['selectedTabId']) || strtolower($params['selectedTabId']) != strtolower($data['id']))
					{
						return false;
					}
				}
				break;

				// unknown criteria, assume failed unless something from a code event takes it
				default:
				{
					$eventReturnValue = false;

					XenForo_CodeEvent::fire('criteria_page', array($criterion['rule'], $data, $params, $containerData, &$eventReturnValue));

					if ($eventReturnValue === false)
					{
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Prepares a list of criteria for selection by a user via the UI.
	 * This will change if a criteria is repeatable.
	 *
	 * @param array|string $criteria Criteria in format: [], with keys rule and data; may be serialized
	 *
	 * @return array Format: [rule] => rule data or true if none
	 */
	public static function prepareCriteriaForSelection($criteria)
	{
		$criteria = self::unserializeCriteria($criteria);

		$output = array();
		foreach ($criteria AS $criterion)
		{
			$data = (!empty($criterion['data']) ? $criterion['data'] : true);
			$output[$criterion['rule']] = $data;
		}

		return $output;
	}

	public static function unserializeCriteria($criteria)
	{
		if (!is_array($criteria))
		{
			$criteria = @unserialize($criteria);
			if (!is_array($criteria))
			{
				return array();
			}
		}

		return $criteria;
	}

	/**
	 * Formats the criteria for a DW.
	 * Expected input format: [] with children: [rule] => name, [data] => info
	 *
	 * @param array|string $criteria Criteria array or serialize string; see above for format.
	 *
	 * @return array criteria
	 */
	public static function prepareCriteriaForSave($criteria)
	{
		$criteria = self::unserializeCriteria($criteria);

		$criteriaFiltered = array();
		foreach ($criteria AS $criterion)
		{
			if (!empty($criterion['rule']))
			{
				if (empty($criterion['data']) || !is_array($criterion['data']))
				{
					$criterion['data'] = array();
				}

				$criteriaFiltered[] = array(
					'rule' => $criterion['rule'],
					'data' => $criterion['data']
				);
			}
		}

		return $criteriaFiltered;
	}

	/**
	 * Gets the data that is needed to display a list of criteria options for user selection.
	 *
	 * Tied with the helper_criteria_user admin template, via $userCriteriaData.
	 *
	 * @return array
	 */
	public static function getDataForUserCriteriaSelection()
	{
		$hours = array();
		for ($i = 0; $i < 24; $i++)
		{
			$hh = str_pad($i, 2, '0', STR_PAD_LEFT);
			$hours[$hh] = $hh;
		}

		$minutes = array();
		for ($i = 0; $i < 60; $i += 5)
		{
			$mm = str_pad($i, 2, '0', STR_PAD_LEFT);
			$minutes[$mm] = $mm;
		}

		return array(
			'userGroups' => XenForo_Model::create('XenForo_Model_UserGroup')->getAllUserGroups(),
			'styles' => XenForo_Model::create('XenForo_Model_Style')->getAllStylesAsFlattenedTree(),
			'languages' => XenForo_Model::create('XenForo_Model_Language')->getAllLanguagesAsFlattenedTree(),
			'timezones' => XenForo_Helper_TimeZone::getTimeZones(),
			'hours' => $hours,
			'minutes' => $minutes,
			'userFieldGroups' => self::getUserFields(),
		);
	}

	/**
	 * Gets the data that is needed to display a list of criteria options for user selection.
	 *
	 * Tied with the helper_criteria_page admin template, via $pageCriteriaData.
	 *
	 * @return array
	 */
	public static function getDataForPageCriteriaSelection()
	{
		return array(
			'nodes' => XenForo_Model::create('XenForo_Model_Node')->getAllNodes()
		);
	}

	/**
	 * Performs a case-insensitive search within $haystack for any of the $needles found in the comma-separated $needleList
	 *
	 * Example:
	 * 	haystack = 'user@gmail.com'
	 * 	needleList = '@yahoo, @gmail, @hotmail'
	 *
	 * @param string $needleList
	 * @param string $haystack
	 *
	 * @return string|boolean Matched needle on success, false on failure
	 */
	protected static function _arrayStringSearch($needleList, $haystack)
	{
		$haystack = utf8_strtolower($haystack);

		foreach (preg_split('/\s*,\s*/', utf8_strtolower($needleList), -1, PREG_SPLIT_NO_EMPTY) AS $needle)
		{
			if (strpos($haystack, $needle) !== false)
			{
				return $needle;
			}
		}

		return false;
	}

	public static function getUserFields()
	{
		/* @var $fieldModel XenForo_Model_UserField */
		$fieldModel = XenForo_Model::create('XenForo_Model_UserField');

		$userFieldGroups = $fieldModel->getUserFieldGroups();

		$userFields = $fieldModel->prepareUserFields($fieldModel->getUserFields());

		foreach ($userFields AS $userFieldId => $userField)
		{
			$userField['fieldName'] = self::$_userFieldPrefix . $userFieldId;
			$userField['choices'] = $fieldModel->getUserFieldChoices($userFieldId, $userField['field_choices'], true);
			$userField['choiceCount'] = count($userField['choices']);

			$userFieldGroups[$userField['display_group']]['userFields'][$userFieldId] = $userField;
		}

		return $userFieldGroups;
	}
}