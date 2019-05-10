<?php

/**
 * Model for users.
 *
 * @package XenForo_Users
 */
class XenForo_Model_User extends XenForo_Model
{
	const FETCH_USER_PROFILE     = 0x01;
	const FETCH_USER_OPTION      = 0x02;
	const FETCH_USER_PRIVACY     = 0x04;
	const FETCH_USER_PERMISSIONS = 0x08;
	const FETCH_LAST_ACTIVITY    = 0x10;

	/**
	 * Quick constant for fetching, profile, option, and privacy data.
	 *
	 * @var integer
	 */
	const FETCH_USER_FULL        = 0x07;

	/**
	 * Special value to use for a permanent ban
	 *
	 * @var integer
	 */
	const PERMANENT_BAN = 0;

	public static $defaultGuestGroupId = 1;
	public static $defaultRegisteredGroupId = 2;
	public static $defaultAdminGroupId = 3;
	public static $defaultModeratorGroupId = 4;

	public static $guestPermissionCombinationId = 1;

	/**
	 * Stores the unserialized value of xf_user.ignored for each user that has been inspected
	 *
	 * @var array [user id] => [ignored user id] => ignored user name
	 */
	protected $_ignoreCache = array();

	/**
	 * Simple way to update user data fields.
	 *
	 * @param integer|array $userId|$user
	 * @param array|string Either the name of a single field, or an array of field-name => field-value pairs
	 * @param mixed If the previous parameter is a string, use this as the field value
	 *
	 * @return XenForo_DataWriter_User
	 */
	public function update($user, $field, $value = null)
	{
		$userId = $this->getUserIdFromUser($user);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$writer->setExistingData($userId);

		if ($value === null)
		{
			if (is_array($field))
			{
				$writer->bulkSet($field);
			}
		}
		else if (is_string($field))
		{
			$writer->set($field, $value);
		}

		$writer->save();

		return $writer;
	}

	/**
	 * Fetches the user_id index from a user record
	 *
	 * @param integer|array $userId|$user
	 *
	 * @return integer User ID
	 */
	public static function getUserIdFromUser($user)
	{
		if (is_scalar($user))
		{
			return $user;
		}

		if (is_array($user) && isset($user['user_id']))
		{
			return $user['user_id'];
		}

		throw new XenForo_Exception('Unable to derive User ID from provided parameters.');
		return false;
	}

	/**
	 * Checks to see if the input string *might* be an email address - contains '@' after its first character
	 *
	 * @param String $email
	 *
	 * @return boolean
	 */
	public function couldBeEmail($email)
	{
		if (strlen($email) < 1)
		{
			return false;
		}

		return (strpos($email, '@', 1) !== false);
	}

	/**
	 * Gets all users. Can be restricted to valid users only with the
	 * validOnly fetch option.
	 *
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array Format: [user id] => user info
	 */
	public function getAllUsers(array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		$orderClause = $this->prepareUserOrderOptions($fetchOptions, 'user.username');
		$whereClause = (!empty($fetchOptions['validOnly']) ? 'WHERE user.user_state = \'valid\' AND user.is_banned = 0' : '');

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT user.*
					' . $joinOptions['selectFields'] . '
				FROM xf_user AS user
				' . $joinOptions['joinTables'] . '
				' . $whereClause . '
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'user_id');
	}

	/**
	 * Returns user records based on a list of usernames.
	 *
	 * @param array $usernames
	 * @param array $fetchOptions User fetch options
	 * @param array $invalidNames Returns a list of usernames that could not be found
	 *
	 * @return array Format: [user id] => info
	 */
	public function getUsersByNames(array $usernames, array $fetchOptions = array(), &$invalidNames = array())
	{
		$usernames = array_map('trim', $usernames);
		foreach ($usernames AS $key => $username)
		{
			if ($username === '')
			{
				unset($usernames[$key]);
			}
		}

		$invalidNames = array();

		if (!$usernames)
		{
			return array();
		}

		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);
		$validOnlyClause = (!empty($fetchOptions['validOnly']) ? 'AND user.user_state = \'valid\' AND user.is_banned = 0' : '');

		$users = $this->fetchAllKeyed('
			SELECT user.*
				' . $joinOptions['selectFields'] . '
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE user.username IN (' . $this->_getDb()->quote($usernames) . ')
				' . $validOnlyClause . '
		', 'user_id');

		if (count($users) != count($usernames))
		{
			$usernamesLower = array_map('strtolower', $usernames);
			$invalidNames = $usernames;

			foreach ($users AS $user)
			{
				do
				{
					$foundKey = array_search(strtolower($user['username']), $usernamesLower);
					if ($foundKey !== false)
					{
						unset($invalidNames[$foundKey]);
						unset($usernamesLower[$foundKey]);
					}
				}
				while ($foundKey !== false);
			}
		}

		return $users;
	}

	/**
	 * Get users with specified user IDs.
	 *
	 * @param array $userIds
	 * @param array $fetchOptions
	 *
	 * @return array Format: [user id] => user info
	 */
	public function getUsersByIds(array $userIds, array $fetchOptions = array())
	{
		if (!$userIds)
		{
			return array();
		}

		$orderClause = $this->prepareUserOrderOptions($fetchOptions, 'user.username');

		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
				SELECT user.*
					' . $joinOptions['selectFields'] . '
				FROM xf_user AS user
				' . $joinOptions['joinTables'] . '
				WHERE user.user_id IN (' . $this->_getDb()->quote($userIds) . ')
				' . $orderClause . '
		', 'user_id');
	}

	/**
	 * Return all users logged from a particular IP address
	 *
	 * @param string $ip
	 * @param array $fetchOptions
	 *
	 * @return array Format: [user id] => user info
	 */
	public function getUsersByIp($ip, array $fetchOptions = array())
	{
		if (!$ip)
		{
			return array();
		}

		$ip = XenForo_Helper_Ip::convertIpStringToBinary($ip);
		if (!$ip)
		{
			return array();
		}

		$orderClause = $this->prepareUserOrderOptions($fetchOptions, 'user.username');

		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT user.*, ip.ip, MAX(ip.log_date) AS log_date
				' . $joinOptions['selectFields'] . '
				FROM xf_ip AS ip
				INNER JOIN xf_user AS user ON
					(user.user_id = ip.user_id)
				' . $joinOptions['joinTables'] . '
				WHERE ip.ip = ?
				GROUP BY ip.user_id
				' . $orderClause . '
		', 'user_id', $ip);
	}

	/**
	 * Return all users logged from a range of binary IPs
	 *
	 * @param string $lowerBound Binary IP range start
	 * @param string $upperBound Binary IP range end
	 * @param array $fetchOptions
	 *
	 * @return array Format: [user id] => user info
	 */
	public function getUsersByIpRange($lowerBound, $upperBound, array $fetchOptions = array())
	{
		$orderClause = $this->prepareUserOrderOptions($fetchOptions, 'user.username');

		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT user.*, ip.ip, MAX(ip.log_date) AS log_date
				' . $joinOptions['selectFields'] . '
				FROM xf_ip AS ip
				INNER JOIN xf_user AS user ON
					(user.user_id = ip.user_id)
				' . $joinOptions['joinTables'] . '
				WHERE ip.ip >= ? AND ip.ip <= ? AND LENGTH(ip.ip) = ?
				GROUP BY ip.user_id
				' . $orderClause . '
		', 'user_id', array($lowerBound, $upperBound, strlen($lowerBound)));
	}

	/**
	 * Gets users that match the specified conditions.
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array Format: [user id] => user info
	 */
	public function getUsers(array $conditions, array $fetchOptions = array())
	{
		$whereClause = $this->prepareUserConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareUserOrderOptions($fetchOptions, 'user.username');
		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT user.*
					' . $joinOptions['selectFields'] . '
				FROM xf_user AS user
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'user_id');
	}

	/**
	 * Gets the user Ids that match the specified conditions.
	 *
	 * @param array $conditions
	 * @param integer $start
	 * @param integer $limit
	 *
	 * @return array
	 */
	public function getUserIds(array $conditions, $start = 0, $limit = 0)
	{
		$fetchOptions = array();
		$whereClause = $this->prepareUserConditions($conditions, $fetchOptions);

		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->_getDb()->fetchCol($this->limitQueryResults('
			SELECT user.user_id
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause . '
				AND user.user_id > ' . intval($start) . '
			ORDER BY user.user_id
		', $limit));
	}

	/**
	 * Gets the count of users that match the specified conditions.
	 *
	 * @param array $conditions
	 *
	 * @return integer
	 */
	public function countUsers(array $conditions)
	{
		$fetchOptions = array();
		$whereClause = $this->prepareUserConditions($conditions, $fetchOptions);

		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause
		);
	}

	public function getBirthdayUsers($month, $day, array $conditions = array(), array $fetchOptions = array(), $publicOnly = true)
	{
		$this->addFetchOptionJoin($fetchOptions, self::FETCH_USER_OPTION);
		$this->addFetchOptionJoin($fetchOptions, self::FETCH_USER_PROFILE);

		$whereClause = $this->prepareUserConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareUserOrderOptions($fetchOptions, 'user.username');
		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT user.*
					' . $joinOptions['selectFields'] . '
				FROM xf_user AS user
				' . $joinOptions['joinTables'] . '
				WHERE user_profile.dob_month = ?
					AND user_profile.dob_day = ?
					' . ($publicOnly ? ' AND user_option.show_dob_date = 1' : '') . '
					AND (' . $whereClause . ')
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'user_id', array($month, $day));
	}

	/**
	 * Gets the specified user by ID.
	 *
	 * @param integer $userId
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array|false
	 */
	public function getUserById($userId, array $fetchOptions = array())
	{
		if (empty($userId))
		{
			return false;
		}

		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT user.*
				' . $joinOptions['selectFields'] . '
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE user.user_id = ?
		', $userId);
	}

	/**
	 * Returns a user record based on an input username
	 *
	 * @param string $username
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array|false
	 */
	public function getUserByName($username, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT user.*
				' . $joinOptions['selectFields'] . '
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE user.username = ?
		', $username);
	}

	/**
	 * Returns a user record based on an input email
	 *
	 * @param string $email
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array|false
	 */
	public function getUserByEmail($email, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareUserFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT user.*
				' . $joinOptions['selectFields'] . '
			FROM xf_user AS user
			' . $joinOptions['joinTables'] . '
			WHERE user.email = ?
		', $email);
	}

	/**
	 * Returns a user record based on an input username OR email
	 *
	 * @param string $input
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array|false
	 */
	public function getUserByNameOrEmail($input, array $fetchOptions = array())
	{
		if ($this->couldBeEmail($input))
		{
			if ($user = $this->getUserByEmail($input, $fetchOptions))
			{
				return $user;
			}
		}

		return $this->getUserByName($input, $fetchOptions);
	}

	/**
	 * Prepares join-related fetch options.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys.
	 */
	public function prepareUserFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_USER_PROFILE)
			{
				$selectFields .= ',
					user_profile.*';
				$joinTables .= '
					LEFT JOIN xf_user_profile AS user_profile ON
						(user_profile.user_id = user.user_id)';
			}

			// TODO: optimise the join on user_option with serialization to user or user_profile
			if ($fetchOptions['join'] & self::FETCH_USER_OPTION)
			{
				$selectFields .= ',
					user_option.*';
				$joinTables .= '
					LEFT JOIN xf_user_option AS user_option ON
						(user_option.user_id = user.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_USER_PRIVACY)
			{
				$selectFields .= ',
					user_privacy.*';
				$joinTables .= '
					LEFT JOIN xf_user_privacy AS user_privacy ON
						(user_privacy.user_id = user.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_USER_PERMISSIONS)
			{
				$selectFields .= ',
					permission_combination.cache_value AS global_permission_cache';
				$joinTables .= '
					LEFT JOIN xf_permission_combination AS permission_combination ON
						(permission_combination.permission_combination_id = user.permission_combination_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_LAST_ACTIVITY)
			{
				$selectFields .= ',
					IF (session_activity.view_date IS NULL, user.last_activity, session_activity.view_date) AS effective_last_activity,
					session_activity.view_date, session_activity.controller_name, session_activity.controller_action, session_activity.params, session_activity.ip';
				$joinTables .= '
					LEFT JOIN xf_session_activity AS session_activity ON
						(session_activity.user_id = user.user_id AND session_activity.unique_key = user.user_id)';
			}
		}

		if (isset($fetchOptions['followingUserId']))
		{
			$fetchOptions['followingUserId'] = intval($fetchOptions['followingUserId']);
			if ($fetchOptions['followingUserId'])
			{
				// note: quoting is skipped; intval'd above
				$selectFields .= ',
					IF(user_follow.user_id IS NOT NULL, 1, 0) AS following_' . $fetchOptions['followingUserId'];
				$joinTables .= '
					LEFT JOIN xf_user_follow AS user_follow ON
						(user_follow.user_id = user.user_id AND user_follow.follow_user_id = ' . $fetchOptions['followingUserId'] . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS following_0';
			}
		}

		if (isset($fetchOptions['nodeIdPermissions']))
		{
			$fetchOptions['nodeIdPermissions'] = intval($fetchOptions['nodeIdPermissions']);
			$selectFields .= ',
				permission.cache_value AS node_permission_cache';
			$joinTables .= '
				LEFT JOIN xf_permission_cache_content AS permission
					ON (permission.permission_combination_id = user.permission_combination_id
						AND permission.content_type = \'node\'
						AND permission.content_id = ' . $fetchOptions['nodeIdPermissions'] .')';
		}

		if (!empty($fetchOptions['customFields']) && is_array($fetchOptions['customFields']))
		{
			foreach ($fetchOptions['customFields'] AS $customFieldId => $value)
			{
				if ($value === '' || (is_array($value) && !$value))
				{
					continue;
				}

				$isExact = !empty($fetchOptions['customFieldsExact'][$customFieldId]);
				$customFieldId = preg_replace('/[^a-z0-9_]/i', '', $customFieldId);
				$selectFields .= ", user_field_value_$customFieldId.field_value AS custom_field_$customFieldId";

				if ($value === true)
				{
					$joinTables .= "
						LEFT JOIN xf_user_field_value AS user_field_value_$customFieldId ON
							(user_field_value_$customFieldId.user_id = user.user_id
							AND user_field_value_$customFieldId.field_id = " . $this->_getDb()->quote($customFieldId) . ")";
				}
				else
				{
					$possibleValues = array();
					foreach ((array)$value AS $possible)
					{
						if ($isExact)
						{
							$possibleValues[] = "user_field_value_$customFieldId.field_value = " . $this->_getDb()->quote($possible);
						}
						else
						{
							$possibleValues[] = "user_field_value_$customFieldId.field_value LIKE " . XenForo_Db::quoteLike($possible, 'lr');
						}
					}

					$joinTables .= "
						INNER JOIN xf_user_field_value AS user_field_value_$customFieldId ON
							(user_field_value_$customFieldId.user_id = user.user_id
							AND user_field_value_$customFieldId.field_id = " . $this->_getDb()->quote($customFieldId) . "
							AND (" . implode(' OR ', $possibleValues) . "))";
				}
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Prepares a set of conditions to select users against.
	 *
	 * @param array $conditions List of conditions. (TODO: make list)
	 * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	 *
	 * @return string Criteria as SQL for where clause
	 */
	public function prepareUserConditions(array $conditions, array &$fetchOptions)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'user.user_id IN(' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'user.user_id = ' . $db->quote($conditions['user_id']);
			}
		}

		if (!empty($conditions['username']))
		{
			if (is_array($conditions['username']))
			{
				$sqlConditions[] = 'user.username LIKE ' . XenForo_Db::quoteLike($conditions['username'][0], $conditions['username'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'user.username LIKE ' . XenForo_Db::quoteLike($conditions['username'], 'lr', $db);
			}
		}

		// this is mainly for dynamically filtering a search that already matches user names
		if (!empty($conditions['username2']))
		{
			if (is_array($conditions['username2']))
			{
				$sqlConditions[] = 'user.username LIKE ' . XenForo_Db::quoteLike($conditions['username2'][0], $conditions['username2'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'user.username LIKE ' . XenForo_Db::quoteLike($conditions['username2'], 'lr', $db);
			}
		}

		if (!empty($conditions['usernames']) && is_array($conditions['usernames']))
		{
			$sqlConditions[] = 'user.username IN (' . $db->quote($conditions['usernames']) . ')';
		}

		if (!empty($conditions['email']))
		{
			if (is_array($conditions['email']))
			{
				$sqlConditions[] = 'user.email LIKE ' . XenForo_Db::quoteLike($conditions['email'][0], $conditions['email'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'user.email LIKE ' . XenForo_Db::quoteLike($conditions['email'], 'lr', $db);
			}
		}
		if (!empty($conditions['emails']) && is_array($conditions['emails']))
		{
			$sqlConditions[] = 'user.email IN (' . $db->quote($conditions['emails']) . ')';
		}

		if (!empty($conditions['user_group_id']))
		{
			if (is_array($conditions['user_group_id']))
			{
				$sqlConditions[] = 'user.user_group_id IN (' . $db->quote($conditions['user_group_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'user.user_group_id = ' . $db->quote($conditions['user_group_id']);
			}
		}

		if (isset($conditions['gender']))
		{
			if (is_array($conditions['gender']))
			{
				if ($conditions['gender'])
				{
					$sqlConditions[] = 'user.gender IN (' . $db->quote($conditions['gender']) . ')';
				}
			}
			else
			{
				$sqlConditions[] = 'user.gender = ' . $db->quote($conditions['gender']);
			}
		}

		if (!empty($conditions['secondary_group_ids']))
		{
			if (is_array($conditions['secondary_group_ids']))
			{
				$groupConds = array();
				foreach ($conditions['secondary_group_ids'] AS $groupId)
				{
					$groupConds[] = 'FIND_IN_SET(' . $db->quote($groupId) . ', user.secondary_group_ids)';
				}
				$sqlConditions[] = '(' . implode(' OR ', $groupConds) . ')';
			}
			else
			{
				$sqlConditions[] = 'FIND_IN_SET(' . $db->quote($conditions['secondary_group_ids']) . ', user.secondary_group_ids)';
			}
		}

		if (!empty($conditions['not_secondary_group_ids']))
		{
			if (is_array($conditions['not_secondary_group_ids']))
			{
				$groupConds = array();
				foreach ($conditions['not_secondary_group_ids'] AS $groupId)
				{
					$groupConds[] = 'FIND_IN_SET(' . $db->quote($groupId) . ', user.secondary_group_ids) = 0';
				}
				$sqlConditions[] = '(' . implode(' AND ', $groupConds) . ')';
			}
			else
			{
				$sqlConditions[] = 'FIND_IN_SET(' . $db->quote($conditions['not_secondary_group_ids']) . ', user.secondary_group_ids) = 0';
			}
		}

		if (isset($conditions['no_secondary_group_ids']))
		{
			if ($conditions['no_secondary_group_ids'])
			{
				$sqlConditions[] = "user.secondary_group_ids = ''";
			}
			else
			{
				$sqlConditions[] = "user.secondary_group_ids <> ''";
			}
		}

		if (!empty($conditions['last_activity']) && is_array($conditions['last_activity']))
		{
			$sqlConditions[] = $this->getCutOffCondition("user.last_activity", $conditions['last_activity']);
		}

		if (!empty($conditions['active_recently']))
		{
			if ($conditions['active_recently'] === true)
			{
				// general definition of recently active: 6 months
				$conditions['active_recently'] = 30 * 6 * 86400;
			}

			$sqlConditions[] = "user.last_activity > "
				. (XenForo_Application::$time - intval($conditions['active_recently']));
		}

		if (!empty($conditions['register_date']) && is_array($conditions['register_date']))
		{
			$sqlConditions[] = $this->getCutOffCondition("user.register_date", $conditions['register_date']);
		}

		if (!empty($conditions['message_count']) && is_array($conditions['message_count']))
		{
			$sqlConditions[] = $this->getCutOffCondition("user.message_count", $conditions['message_count']);
		}

		if (!empty($conditions['like_count']) && is_array($conditions['like_count']))
		{
			$sqlConditions[] = $this->getCutOffCondition("user.like_count", $conditions['like_count']);
		}

		if (!empty($conditions['trophy_points']) && is_array($conditions['trophy_points']))
		{
			$sqlConditions[] = $this->getCutOffCondition("user.trophy_points", $conditions['trophy_points']);
		}

		if (!empty($conditions['user_state']) && $conditions['user_state'] !== 'any')
		{
			if (is_array($conditions['user_state']))
			{
				$sqlConditions[] = 'user.user_state IN (' . $db->quote($conditions['user_state']) . ')';
			}
			else
			{
				$sqlConditions[] = 'user.user_state = ' . $db->quote($conditions['user_state']);
			}
		}

		if (isset($conditions['is_admin']))
		{
			$sqlConditions[] = 'user.is_admin = ' . ($conditions['is_admin'] ? 1 : 0);
		}

		if (isset($conditions['is_moderator']))
		{
			$sqlConditions[] = 'user.is_moderator = ' . ($conditions['is_moderator'] ? 1 : 0);
		}

		if (isset($conditions['is_banned']))
		{
			$sqlConditions[] = 'user.is_banned = ' . ($conditions['is_banned'] ? 1 : 0);
		}

		if (isset($conditions['is_staff']))
		{
			$sqlConditions[] = 'user.is_staff = ' . ($conditions['is_staff'] ? 1 : 0);
		}

		if (isset($conditions['is_discouraged']))
		{
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_USER_OPTION);
			$sqlConditions[] = 'user_option.is_discouraged = ' . ($conditions['is_discouraged'] ? 1 : 0);
		}

		if (!empty($conditions['receive_admin_email']))
		{
			$sqlConditions[] = 'user_option.receive_admin_email = 1';
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_USER_OPTION);
		}

		if (!empty($conditions['adminQuickSearch']))
		{
			$quotedString = XenForo_Db::quoteLike($conditions['adminQuickSearch'], 'lr', $db);

			$sqlConditions[] = 'user.username LIKE ' . $quotedString . ' OR user.email LIKE ' . $quotedString;
		}

		// these are conditions, but implemented via fetch options as they need a bunch of joins
		if (!empty($conditions['customFields']) && empty($fetchOptions['customFields']))
		{
			$fetchOptions['customFields'] = $conditions['customFields'];
		}
		if (!empty($conditions['customFieldsExact']) && empty($fetchOptions['customFieldsExact']))
		{
			$fetchOptions['customFieldsExact'] = $conditions['customFieldsExact'];
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Construct 'ORDER BY' clause
	 *
	 * @param array $fetchOptions (uses 'order' key)
	 * @param string $defaultOrderSql Default order SQL
	 *
	 * @return string
	 */
	public function prepareUserOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
	{
		$choices = array(
			'username' => 'user.username',
			'register_date' => 'user.register_date',
			'message_count' => 'user.message_count',
			'trophy_points' => 'user.trophy_points',
			'like_count' => 'user.like_count',
			'last_activity' => 'user.last_activity'
		);
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}

	/**
	 * Returns a full user record based on an input user ID. Equivalent to
	 * calling getUserById including the FETCH_USER_FULL constanct
	 *
	 * @param integer $userId
	 * @param array $fetchOptions User fetch options
	 *
	 * @return array|false
	 */
	public function getFullUserById($userId, array $fetchOptions = array())
	{
		if (!empty($fetchOptions['join']))
		{
			$fetchOptions['join'] |= self::FETCH_USER_FULL;
		}
		else
		{
			$fetchOptions['join'] = self::FETCH_USER_FULL;
		}

		return $this->getUserById($userId, $fetchOptions);
	}

	/**
	 * Gets the visiting user's information based on their user ID.
	 *
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getVisitingUserById($userId)
	{
		$userinfo = $this->getUserById($userId, array(
			'join' => self::FETCH_USER_FULL | self::FETCH_USER_PERMISSIONS
		));
		if (!$userinfo)
		{
			return false;
		}

		$userinfo['csrf_token_page'] = $userinfo['user_id'] . ',' . XenForo_Application::$time
			. ',' . sha1(XenForo_Application::$time . $userinfo['csrf_token']);

		if ($userinfo['user_state'] != 'valid')
		{
			// user is not valid yet, give them guest permissions
			$userinfo = $this->setPermissionsOnVisitorArray($userinfo);
		}

		return $userinfo;
	}

	/**
	 * Get the visiting user information for a guest.
	 *
	 * @return array
	 */
	public function getVisitingGuestUser()
	{
		$options = XenForo_Application::get('options');

		$userinfo = array(
			// xf_user
			'user_id' => 0,
			'username' => '',
			'email' => '',
			'gender' => '',
			'language_id' => 0,
			'style_id' => 0,
			'timezone' => $options->guestTimeZone,
			'visible' => 1,
			'activity_visible' => 1,
			'user_group_id' => self::$defaultGuestGroupId,
			'secondary_group_ids' => '',
			'display_style_group_id' => self::$defaultGuestGroupId,
			'permission_combination_id' => 0,
			'message_count' => 0,
			'conversations_unread' => 0,
			'register_date' => 0,
			'last_activity' => 0,
			'trophy_points' => 0,
			'alerts_unread' => 0,
			'avatar_date' => 0,
			'avatar_width' => 0,
			'avatar_height' => 0,
			'gravatar' => '',
			'user_state' => 'valid',
			'is_moderator' => 0,
			'is_admin' => 0,
			'is_staff' => 0,
			'is_banned' => 0,
			'like_count' => 0,
			'custom_title' => '',
			'warning_points' => '',

			// xf_user_profile
			'dob_day' => 0,
			'dob_month' => 0,
			'dob_year' => 0,
			'status' => '',
			'status_date' => 0,
			'status_profile_post_id' => 0,
			'signature' => '',
			'homepage' => '',
			'location' => '',
			'occupation' => '',
			'following' => '',
			'ignored' => '',
			'csrf_token' => '',
			'avatar_crop_x' => 0,
			'avatar_crop_y' => 0,
			'about' => '',
			'custom_fields' => '',
			'external_auth' => '',

			// xf_user_option
			'show_dob_year' => 0,
			'show_dob_date' => 0,
			'content_show_signature' => $options->guestShowSignatures,
			'receive_admin_email' => 0,
			'email_on_conversation' => 0,
			'is_discouraged' => 0,
			'default_watch_state' => '',
			'alert_optout' => '',
			'enable_rte' => 1,
			'enable_flash_uploader' => 1,

			// xf_user_privacy
			'allow_view_profile' => 'everyone',
			'allow_post_profile' => 'everyone',
			'allow_send_personal_conversation' => 'everyone',
			'allow_view_identities' => 'everyone',
			'allow_receive_news_feed' => 'everyone',

			// other tables/data
			'csrf_token_page' => '',
			'global_permission_cache' => ''
		);

		$userinfo = $this->setPermissionsOnVisitorArray($userinfo);
		return $userinfo;
	}

	/**
	 * Sets the specified permissions (combination and permissions string) on visitor array.
	 * Defaults to setting guest permissions.
	 *
	 * @param array $userinfo Visitor record
	 *
	 * @return array Visitor record with permissions
	 */
	public function setPermissionsOnVisitorArray(array $userinfo, $permissionCombinationId = false)
	{
		if (!$permissionCombinationId)
		{
			$permissionCombinationId = self::$guestPermissionCombinationId;
		}

		$userinfo['permission_combination_id'] = $permissionCombinationId;

		$userinfo['global_permission_cache'] = $this->_getDb()->fetchOne('
			SELECT cache_value
			FROM xf_permission_combination
			WHERE permission_combination_id = ?
		', $permissionCombinationId);

		return $userinfo;
	}

	/**
	 * Sets the permission info from the specified user ID into an array of user info
	 * (likely for a visitor array).
	 *
	 * @param array $userInfo
	 * @param integer $permUserId
	 *
	 * @return array User info with changed permissions
	 */
	public function setPermissionsFromUserId(array $userInfo, $permUserId)
	{
		$permUser = $this->getUserById($permUserId, array(
			'join' => self::FETCH_USER_PERMISSIONS
		));
		if ($permUser)
		{
			$userInfo['permission_combination_id'] = $permUser['permission_combination_id'];
			$userInfo['global_permission_cache'] = $permUser['global_permission_cache'];
		}

		return $userInfo;
	}

	/**
	 * Updates the session activity of a user.
	 *
	 * @param integer $userId
	 * @param string $ip IP of visiting user
	 * @param string $controllerName Last controller class that was invoked
	 * @param string $action Last action that was invoked
	 * @param string $viewState Either "valid" or "error"
	 * @param array $inputParams List of special input params, to include to help get more info on current activity
	 * @param integer|null $viewDate The timestamp of the last page view; defaults to now
	 * @param string $robotKey
	 */
	public function updateSessionActivity($userId, $ip, $controllerName, $action, $viewState, array $inputParams, $viewDate = null, $robotKey = '')
	{
		$userId = intval($userId);
		$ipNum = XenForo_Helper_Ip::getBinaryIp(null, $ip, '');
		$uniqueKey = ($userId ? $userId : $ipNum);

		if ($userId)
		{
			$robotKey = '';
		}

		if (!$viewDate)
		{
			$viewDate = XenForo_Application::$time;
		}

		$logParams = array();
		foreach ($inputParams AS $paramKey => $paramValue)
		{
			if (!strlen($paramKey) || $paramKey[0] == '_' || !is_scalar($paramValue))
			{
				continue;
			}

			$logParams[] = "$paramKey=" . urlencode($paramValue);
		}
		$paramList = implode('&', $logParams);
		$paramList = substr($paramList, 0, 100);

		$controllerName = substr($controllerName, 0, 50);
		$action = substr($action, 0, 50);

		try
		{
			$this->_getDb()->query('
				INSERT INTO xf_session_activity
					(user_id, unique_key, ip, controller_name, controller_action, view_state, params, view_date, robot_key)
				VALUES
					(?, ?, ?, ?, ?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					ip = VALUES(ip),
					controller_name = VALUES(controller_name),
					controller_action = VALUES(controller_action),
					view_state = VALUES(view_state),
					params = VALUES(params),
					view_date = VALUES(view_date),
					robot_key = VALUES(robot_key)
			', array($userId, $uniqueKey, $ipNum, $controllerName, $action, $viewState, $paramList, $viewDate, $robotKey));
		}
		catch (Zend_Db_Exception $e) {} // ignore db errors here, not that important
	}

	/**
	 * Deletes the session activity record for the specified user / IP address
	 *
	 * @param integer $userId
	 * @param string $ip
	 */
	public function deleteSessionActivity($userId, $ip)
	{
		$userId = intval($userId);
		$ipNum = XenForo_Helper_Ip::convertIpStringToBinary($ip);
		$uniqueKey = ($userId ? $userId : $ipNum);

		$db = $this->_getDb();
		$db->delete('xf_session_activity', 'user_id = ' . $db->quote($userId) . ' AND unique_key = ' . $db->quote($uniqueKey));
	}

	/**
	 * Gets the latest (valid) user to join.
	 *
	 * @return array|false
	 */
	public function getLatestUser()
	{
		return $this->_getDb()->fetchRow($this->limitQueryResults('
			SELECT *
			FROM xf_user
			WHERE user_state = \'valid\'
				 AND is_banned = 0
			ORDER BY register_date DESC
		', 1));
	}

	/**
	 * Fetch the most recently-registered users
	 *
	 * @param array $criteria
	 * @param array $fetchOptions
	 *
	 * @return array User records
	 */
	public function getLatestUsers(array $criteria, array $fetchOptions = array())
	{
		$fetchOptions['order'] = 'register_date';
		$fetchOptions['direction'] = 'desc';

		return $this->getUsers($criteria, $fetchOptions);
	}

	/**
	 * Fetch the most active users
	 *
	 * @param array $criteria
	 * @param array $fetchOptions
	 *
	 * @return array User records
	 */
	public function getMostActiveUsers(array $criteria, array $fetchOptions = array())
	{
		$fetchOptions['order'] = 'message_count';
		$fetchOptions['direction'] = 'desc';

		return $this->getUsers($criteria, $fetchOptions);
	}

	/**
	 * Gets the count of total users.
	 *
	 * @return integer
	 */
	public function countTotalUsers()
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user
			WHERE user_state = \'valid\'
				 AND is_banned = 0
		');
	}

	/**
	 * Gets the user authentication record by user ID.
	 *
	 * @param integer $userId
	 *
	 * @return array|false
	 */
	public function getUserAuthenticationRecordByUserId($userId)
	{
		return $this->_getDb()->fetchRow('

			SELECT *
			FROM xf_user_authenticate
			WHERE user_id = ?

		', $userId);
	}

	/**
	 * Returns an auth object based on an input userid
	 *
	 * @param integer Userid
	 *
	 * @return XenForo_Authentication_Abstract|false
	 */
	public function getUserAuthenticationObjectByUserId($userId)
	{
		$authenticate = $this->getUserAuthenticationRecordByUserId($userId);
		if (!$authenticate)
		{
			return false;
		}

		$auth = XenForo_Authentication_Abstract::create($authenticate['scheme_class']);
		if (!$auth)
		{
			return false;
		}

		$auth->setData($authenticate['data']);
		return $auth;
	}

	/**
	 * Logs the given user in (as the visiting user). Exceptions are thrown on errors.
	 *
	 * @param string $nameOrEmail User name or email address
	 * @param string $password
	 * @param string $error Error string (by ref)
	 *
	 * @return integer|false User ID auth'd as; false on failure
	 */
	public function validateAuthentication($nameOrEmail, $password, &$error = '')
	{
		$user = $this->getUserByNameOrEmail($nameOrEmail);
		if (!$user)
		{
			$error = new XenForo_Phrase('requested_user_x_not_found', array('name' => $nameOrEmail));
			return false;
		}

		$authentication = $this->getUserAuthenticationObjectByUserId($user['user_id']);
		if (!$authentication || !$authentication->authenticate($user['user_id'], $password))
		{
			$error = new XenForo_Phrase('incorrect_password');
			return false;
		}

		$upgraded = $authentication->getUpgradedAuthenticationObject();
		if ($upgraded)
		{
			$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
			$userDw->setExistingData($user['user_id']);
			$userDw->setOption(XenForo_DataWriter_User::OPTION_LOG_CHANGES, false);
			$userDw->setPassword($password, false, $upgraded);
			$userDw->save();
		}

		return $user['user_id'];
	}

	/**
	 * Logs a user in based on their remember key from a cookie.
	 *
	 * @param integer $userId
	 * @param string $rememberKey
	 * @param array|false|null $auth User's auth record (retrieved if null)
	 *
	 * @return boolean
	 */
	public function loginUserByRememberKeyFromCookie($userId, $rememberKey, $auth = null)
	{
		if ($auth === null)
		{
			$auth = $this->getUserAuthenticationRecordByUserId($userId);
		}

		if (!$auth || $this->prepareRememberKeyForCookie($auth['remember_key']) !== $rememberKey)
		{
			return false;
		}

		return true;
	}

	/**
	 * Logs a user in based on the raw value of the remember cookie.
	 *
	 * @param string $userCookie
	 *
	 * @return false|integer
	 */
	public function loginUserByRememberCookie($userCookie)
	{
		if (!$userCookie)
		{
			return false;
		}

		$userCookieParts = explode(',', $userCookie);
		if (count($userCookieParts) < 2)
		{
			return false;
		}

		$userId = intval($userCookieParts[0]);
		$rememberKey = $userCookieParts[1];
		if (!$userId || !$rememberKey)
		{
			return false;
		}

		$auth = $this->getUserAuthenticationRecordByUserId($userId);
		$loggedIn = $this->loginUserByRememberKeyFromCookie($userId, $rememberKey, $auth);
		if ($loggedIn)
		{
			return $userId;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Sets the user remember cookie for the specified user ID.
	 *
	 * @param integer $userId
	 * @param array|false|null $auth User's auth record (retrieved if null)
	 *
	 * @return boolean
	 */
	public function setUserRememberCookie($userId, $auth = null)
	{
		$value = $this->getUserRememberKeyForCookie($userId, $auth);
		if (!$value)
		{
			return false;
		}

		XenForo_Helper_Cookie::setCookie('user', $value, 30 * 86400, true);

		return true;
	}

	public function getUserRememberKeyForCookie($userId, $auth = null)
	{
		if ($auth === null)
		{
			$auth = $this->getUserAuthenticationRecordByUserId($userId);
		}

		if (!$auth)
		{
			return false;
		}

		return intval($userId) . ',' . $this->prepareRememberKeyForCookie($auth['remember_key']);
	}

	/**
	 * Prepares the remember key for use in a cookie (or for comparison against the cookie).
	 *
	 * @param string $rememberKey Key from DB
	 *
	 * @return string
	 */
	public function prepareRememberKeyForCookie($rememberKey)
	{
		return sha1(XenForo_Application::get('config')->globalSalt . $rememberKey);
	}

	public function getUserEmailConfirmKey(array $user)
	{
		return md5(XenForo_Application::get('config')->globalSalt . $user['user_id'] . $user['email']);
	}

	/**
	 * Prepares a user record for display. Note that this may be called on incomplete guest records.
	 *
	 * @param array $user User info
	 *
	 * @return array Prepared user info
	 */
	public function prepareUser(array $user)
	{
		if (empty($user['user_group_id']))
		{
			$user['display_style_group_id'] = self::$defaultGuestGroupId;
		}

		$user['customFields'] = (!empty($user['custom_fields']) ? @unserialize($user['custom_fields']) : array());
		$user['externalAuth'] = (!empty($user['external_auth']) ? @unserialize($user['external_auth']) : array());

		// "trusted" user check - used to determine if no follow is enabled
		$user['isTrusted'] = (!empty($user['user_id']) && (!empty($user['is_admin']) || !empty($user['is_moderator'])));

		if (XenForo_Visitor::hasInstance())
		{
			$user['isIgnored'] = XenForo_Visitor::getInstance()->isIgnoring($user['user_id']);
		}

		return $user;
	}

	/**
	 * Prepares the data needed for the simple user card-like output.
	 *
	 * @param array $user
	 *
	 * @return array
	 */
	public function prepareUserCard(array $user)
	{
		$user['age'] = $this->_getUserProfileModel()->getUserAge($user);
		$user['customFields'] = (!empty($user['custom_fields']) ? @unserialize($user['custom_fields']) : array());

		return $user;
	}

	/**
	 * Prepares a batch of user cards.
	 *
	 * @param array $users
	 *
	 * @return array
	 */
	public function prepareUserCards(array $users)
	{
		foreach ($users AS &$user)
		{
			$user = $this->prepareUserCard($user);
		}

		return $users;
	}

	/**
	 * Inserts (or updates an existing) user group change set.
	 *
	 * @param integer $userId
	 * @param string $key Unique identifier for change set
	 * @param string|array $addGroups Comma delimited string or array of user groups to add
	 *
	 * @return boolean True on change success
	 */
	public function addUserGroupChange($userId, $key, $addGroups)
	{
		if (is_array($addGroups))
		{
			$addGroups = implode(',', $addGroups);
		}

		if (!$addGroups)
		{
			return true;
		}

		$oldGroups = $this->getUserGroupChangesForUser($userId);

		$newGroups = $oldGroups;

		if (isset($newGroups[$key]) && !$addGroups)
		{
			// already exists and we're removing the groups, so we can just remove the record
			return $this->removeUserGroupChange($userId, $key);
		}

		$newGroups[$key] = $addGroups;

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$success = $this->_applyUserGroupChanges($userId, $oldGroups, $newGroups);
		if ($success)
		{
			$db->query('
				INSERT INTO xf_user_group_change
					(user_id, change_key, group_ids)
				VALUES
					(?, ?, ?)
				ON DUPLICATE KEY UPDATE
					group_ids = VALUES(group_ids)
			', array($userId, $key, $addGroups));

			XenForo_Db::commit($db);
		}
		else
		{
			XenForo_Db::rollback($db);
		}


		return $success;
	}

	/**
	 * Removes the specified user group change set.
	 *
	 * @param integer $userId
	 * @param string $key Change set key
	 *
	 * @return boolean True on success
	 */
	public function removeUserGroupChange($userId, $key)
	{
		$oldGroups = $this->getUserGroupChangesForUser($userId);
		if (!isset($oldGroups[$key]))
		{
			// already removed?
			return true;
		}

		$newGroups = $oldGroups;
		unset($newGroups[$key]);

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$success = $this->_applyUserGroupChanges($userId, $oldGroups, $newGroups);
		if ($success)
		{
			$db->delete('xf_user_group_change',
				'user_id = ' . $db->quote($userId) . ' AND change_key = ' . $db->quote($key)
			);

			XenForo_Db::commit($db);
		}
		else
		{
			XenForo_Db::rollback($db);
		}

		return $success;
	}

	public function removeUserGroupChangeLogByKey($key)
	{
		$db = $this->_getDb();
		$db->delete('xf_user_group_change', 'change_key = ' . $db->quote($key));
	}

	/**
	 * Gets the user group change sets for the specified user.
	 *
	 * @param integer $userId
	 *
	 * @return array [change key] => comma list of group IDs
	 */
	public function getUserGroupChangesForUser($userId)
	{
		return $this->_getDb()->fetchPairs('
			SELECT change_key, group_ids
			FROM xf_user_group_change
			WHERE user_id = ?
		', $userId);
	}

	/**
	 * Applies a set of user group changes.
	 *
	 * @param integer $userId
	 * @param array $oldGroupStrings Array of comma-delimited strings of existing (accounted for) user group change sets
	 * @param array $newGroupStrings Array of comma-delimited strings for new list of user group change sets
	 *
	 * @return boolean
	 */
	protected function _applyUserGroupChanges($userId, array $oldGroupStrings, array $newGroupStrings)
	{
		$oldGroups = array();
		foreach ($oldGroupStrings AS $string)
		{
			$oldGroups = array_merge($oldGroups, explode(',', $string));
		}
		$oldGroups = array_unique($oldGroups);

		$newGroups = array();
		foreach ($newGroupStrings AS $string)
		{
			$newGroups = array_merge($newGroups, explode(',', $string));
		}
		$newGroups = array_unique($newGroups);

		$addGroups = array_diff($newGroups, $oldGroups);
		$removeGroups = array_diff($oldGroups, $newGroups);

		if (!$addGroups && !$removeGroups)
		{
			return true;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
		if (!$dw->setExistingData($userId))
		{
			return false;
		}

		$secondaryGroups = explode(',', $dw->get('secondary_group_ids'));
		if ($removeGroups)
		{
			foreach ($secondaryGroups AS $key => $secondaryGroup)
			{
				if (in_array($secondaryGroup, $removeGroups))
				{
					unset($secondaryGroups[$key]);
				}
			}
		}
		if ($addGroups)
		{
			$secondaryGroups = array_merge($secondaryGroups, $addGroups);
		}

		$dw->setSecondaryGroups($secondaryGroups);
		return $dw->save();
	}

	/**
	 * Determines if a user is a member of a particular user group
	 *
	 * @param array $user
	 * @param integer|array $userGroupId either a single user group ID or an array thereof
	 * @param boolean Also check secondary groups
	 *
	 * @return boolean
	 */
	public function isMemberOfUserGroup(array $user, $userGroupId, $includeSecondaryGroups = true)
	{
		if (!$userGroupId)
		{
			return false;
		}

		if (is_array($userGroupId))
		{
			if (in_array($user['user_group_id'], $userGroupId))
			{
				return true;
			}

			if ($includeSecondaryGroups && array_intersect($userGroupId, explode(',', $user['secondary_group_ids'])))
			{
				return true;
			}
		}
		else
		{
			if ($user['user_group_id'] == $userGroupId)
			{
				return true;
			}

			if ($includeSecondaryGroups && strpos(",{$user['secondary_group_ids']},", ",{$userGroupId},") !== false)
			{
				return true;
			}
		}

		return false;
	}

	public function isUserSuperAdmin(array $user)
	{
		$superAdmins = preg_split(
			'#\s*,\s*#', XenForo_Application::get('config')->superAdmins,
			-1, PREG_SPLIT_NO_EMPTY
		);
		return ($user['is_admin'] && in_array($user['user_id'], $superAdmins));
	}

	/**
	 * Creates a new follower record for $userId following $followUserId(s)
	 *
	 * @param array Users being followed
	 * @param boolean Check for and prevent duplicate followers
	 * @param array $user User doing the following
	 *
	 * @return string Comma-separated list of all users now being followed by $userId
	 */
	public function follow(array $followUsers, $dupeCheck = true, array $user = null)
	{
		if ($user === null)
		{
			$user = XenForo_Visitor::getInstance();
		}

		// if we have only a single user, build the multi-user array structure
		if (isset($followUsers['user_id']))
		{
			$followUsers = array($followUsers['user_id'] => $followUsers);
		}

		if ($dupeCheck)
		{
			$followUsers = $this->removeDuplicateFollowUserIds($user['user_id'], $followUsers);
		}

		$db = $this->_getDb();
		$errors = false;

		XenForo_Db::beginTransaction($db);

		foreach ($followUsers AS $followUser)
		{
			if ($user['user_id'] == $followUser['user_id'])
			{
				continue;
			}

			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Follower');
			$writer->setOption(XenForo_DataWriter_Follower::OPTION_POST_WRITE_UPDATE_USER_FOLLOWING, false);
			$writer->set('user_id', $user['user_id']);
			$writer->set('follow_user_id', $followUser['user_id']);
			$success = $writer->save();

			if ($success
				&& !$this->isUserIgnored($followUser, $user['user_id'])
				&& XenForo_Model_Alert::userReceivesAlert($followUser, 'user', 'following')
			)
			{
				XenForo_Model_Alert::alert(
					$followUser['user_id'],
					$user['user_id'], $user['username'],
					'user', $followUser['user_id'],
					'following'
				);
			}
		}

		$return = $this->updateFollowingDenormalizedValue($user['user_id']);

		XenForo_Db::commit($db);

		return $return;
	}

	/**
	 * Deletes an existing follower record for $userId following $followUserId
	 *
	 * @param integer $followUserId User being followed
	 * @param integer $userId User doing the following
	 *
	 * @return string Comma-separated list of all users now being followed by $userId
	 */
	public function unfollow($followUserId, $userId = null)
	{
		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Follower', XenForo_DataWriter::ERROR_SILENT);
		$writer->setOption(XenForo_DataWriter_Follower::OPTION_POST_WRITE_UPDATE_USER_FOLLOWING, false);
		$writer->setExistingData(array($userId, $followUserId));
		$writer->delete();

		$value = $this->updateFollowingDenormalizedValue($userId);

		// delete alerts
		$this->getModelFromCache('XenForo_Model_Alert')->deleteAlerts('user', $followUserId, $userId, 'follow');

		XenForo_Db::commit($db);

		return $value;
	}

	/**
	 * Compares an array of user IDs to be followed with the existing value and removes any duplicates
	 * to prevent duplicate key errors on insertion
	 *
	 * @param integer $userId
	 * @param array $newUsers (full user arrays)
	 * @param string $existingUserIds '3,6,42,....'
	 *
	 * @return array
	 */
	public function removeDuplicateFollowUserIds($userId, array $newUsers, $existingUserIds = null)
	{
		if ($existingUserIds === null)
		{
			$existingUserIds = $this->getFollowingDenormalizedValue($userId);
		}

		$existingUserIds = explode(',', $existingUserIds);

		foreach ($newUsers AS $i => $newUser)
		{
			if (in_array($newUser['user_id'], $existingUserIds))
			{
				unset($newUsers[$i]);
			}
		}

		return $newUsers;
	}

	/**
	 * Fetches a single user-following-user record.
	 *
	 * @param integer|array $userId - the user doing the following
	 * @param integer|array $followUserId - the user being followed
	 *
	 * @return array
	 */
	public function getFollowRecord($userId, $followUserId)
	{
		return $this->_getDb()->fetchRow('

			SELECT *
			FROM xf_user_follow
			WHERE user_id = ?
			AND follow_user_id = ?

		', array(
			$this->getUserIdFromUser($userId),
			$this->getUserIdFromUser($followUserId)
		));
	}

	/**
	 * Gets an array of all users being followed by the specified user
	 *
	 * @param integer|array $userId|$user
	 * @param integer $maxResults (0 = all)
	 * @param string $orderBy
	 *
	 * @return array
	 */
	public function getFollowedUserProfiles($userId, $maxResults = 0, $orderBy = 'user.username')
	{
		$sql = '
			SELECT
				user.*,
				user_profile.*,
				user_option.*
			FROM xf_user_follow AS user_follow
			INNER JOIN xf_user AS user ON
				(user.user_id = user_follow.follow_user_id AND user.is_banned = 0)
			INNER JOIN xf_user_profile AS user_profile ON
				(user_profile.user_id = user.user_id)
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			WHERE user_follow.user_id = ?
			ORDER BY ' . $orderBy . '
		';

		if ($maxResults)
		{
			$sql = $this->limitQueryResults($sql, $maxResults);
		}

		return $this->fetchAllKeyed($sql, 'user_id', $this->getUserIdFromUser($userId));
	}

	/**
	 * Gets users followed by the user ID.
	 *
	 * @param integer $userId
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getFollowedUsers($userId, array $fetchOptions = array())
	{
		$orderClause = $this->prepareUserOrderOptions($fetchOptions, 'user.username');
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT user.*,
					user_profile.*,
					user_option.*
				FROM xf_user_follow AS user_follow
				INNER JOIN xf_user AS user ON
					(user.user_id = user_follow.follow_user_id AND user.is_banned = 0)
				INNER JOIN xf_user_profile AS user_profile ON
					(user_profile.user_id = user.user_id)
				INNER JOIN xf_user_option AS user_option ON
					(user_option.user_id = user.user_id)
				WHERE user_follow.user_id = ?
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'user_id', $userId);
	}

	/**
	 * Generates the denormalized, comma-separated version of a user's following
	 *
	 * @param $userId
	 *
	 * @return string
	 */
	public function getFollowingDenormalizedValue($userId)
	{
		return implode(',', $this->_getDb()->fetchCol('

			SELECT follow_user_id
			FROM xf_user_follow AS user_follow
			INNER JOIN xf_user AS user ON
				(user.user_id = user_follow.follow_user_id)
			WHERE user_follow.user_id = ?
			ORDER BY user.username

		', $this->getUserIdFromUser($userId)));
	}

	/**
	 * Returns whether or not the specified user is being followed by the follower
	 *
	 * @param integer $userId User being followed
	 * @param array $follower User doing the following
	 *
	 * @return boolean
	 */
	public function isFollowing($userId, array $follower = null)
	{
		if ($follower === null)
		{
			$follower = XenForo_Visitor::getInstance();
		}

		if (!$follower['user_id'] || $follower['user_id'] == $userId)
		{
			return false;
		}

		return (strpos(",{$follower['following']},", ",{$userId},") !== false);
	}

	/**
	 * Updates the denormalized, comma-separated version of a user's following.
	 * Will query for the value if it is not provided
	 *
	 * @param integer|array $userId|$user
	 * @param string Denormalized following value
	 *
	 * @return string
	 */
	public function updateFollowingDenormalizedValue($userId, $following = false)
	{
		$userId = $this->getUserIdFromUser($userId);

		if ($following === false)
		{
			$following = $this->getFollowingDenormalizedValue($userId);
		}

		$this->update($userId, 'following', $following);

		return $following;
	}

	/**
	 * Gets the user information for all users following the specified user.
	 *
	 * @param integer $userId
	 * @param integer $maxResults (0 = all)
	 * @param string $orderBy
	 *
	 * @return array Format: [user id] => following user info
	 */
	public function getUsersFollowingUserId($userId, $maxResults = 0, $orderBy = 'user.username')
	{
		$sql = '
			SELECT user.*,
				user_profile.*,
				user_option.*
			FROM xf_user_follow AS user_follow
			INNER JOIN xf_user AS user ON
				(user.user_id = user_follow.user_id AND user.is_banned = 0)
			INNER JOIN xf_user_profile AS user_profile ON
				(user_profile.user_id = user.user_id)
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			WHERE user_follow.follow_user_id = ?
			ORDER BY ' . $orderBy . '
		';

		if ($maxResults)
		{
			$sql = $this->limitQueryResults($sql, $maxResults);
		}

		return $this->fetchAllKeyed($sql, 'user_id', $userId);
	}

	/**
	 * Gets users following the specified user ID.
	 *
	 * @param integer $userId
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getUsersFollowing($userId, array $fetchOptions = array())
	{
		$orderClause = $this->prepareUserOrderOptions($fetchOptions, 'user.username');
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT user.*,
					user_profile.*,
					user_option.*
				FROM xf_user_follow AS user_follow
				INNER JOIN xf_user AS user ON
					(user.user_id = user_follow.user_id AND user.is_banned = 0)
				INNER JOIN xf_user_profile AS user_profile ON
					(user_profile.user_id = user.user_id)
				INNER JOIN xf_user_option AS user_option ON
					(user_option.user_id = user.user_id)
				WHERE user_follow.follow_user_id = ?
				' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'user_id', $userId);
	}

	/**
	 * Gets the count of users following the specified user.
	 *
	 * @param integer $userId
	 *
	 * @return array Format: [user id] => following user info
	 */
	public function countUsersFollowingUserId($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user_follow
			WHERE follow_user_id = ?
		', $userId);
	}

	/**
	 * Rebuilds the custom field cache for a specific user, using the canonical data.
	 *
	 * @param integer $userId
	 */
	public function rebuildCustomFieldCache($userId)
	{
		$db = $this->_getDb();

		$cache = $this->_getFieldModel()->getUserFieldValues($userId);

		$db->update('xf_user_profile',
			array('custom_fields' => serialize($cache)),
			'user_id = '. $db->quote($userId)
		);
	}

	/**
	 * Returns an array containing the user ids found from the complete result given the range specified,
	 * along with the total number of users found.
	 *
	 * @param integer Find users with user_id greater than...
	 * @param integer Maximum users to return at once
	 *
	 * @return array
	 */
	public function getUserIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT user_id
			FROM xf_user
			WHERE user_id > ?
			ORDER BY user_id
		', $limit), $start);
	}

	/**
	 * Returns the number of unread alerts belonging to a user - following fresh recalculation
	 *
	 * @param integer $userId
	 *
	 * @return integer
	 */
	public function getUnreadAlertsCount($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*) AS total
			FROM xf_user_alert
			WHERE alerted_user_id = ?
				AND view_date = 0
		', $userId);
	}

	/**
	 * Determines if the user has permission to bypass users' privacy preferences, including online status and activity feed
	 *
	 * @param string $errorPhraseKey
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canBypassUserPrivacy(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'bypassUserPrivacy'))
		{
			return true;
		}

		return false;
	}

	/**
	 * Determines if permissions are sufficient to view on the specified
	 * user's online status. This refers to time but not necessarily the specific page.
	 *
	 * @param array $user User being viewed
	 * @param string $errorPhraseKey Returned by ref. Phrase key of more specific error
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return boolean
	 */
	public function canViewUserOnlineStatus(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!$user['user_id'] || !$user['last_activity'])
		{
			return false;
		}
		else if ($user['visible'])
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);

		if ($user['user_id'] == $viewingUser['user_id'])
		{
			// can always view own
			return true;
		}

		return $this->canBypassUserPrivacy($errorPhraseKey, $viewingUser);
	}

	/**
	 * Determines if permissions are sufficient to view the user's
	 * current activity (page).
	 *
	 * @param array $user User being viewed
	 * @param string $errorPhraseKey Returned by ref. Phrase key of more specific error
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return boolean
	 */
	public function canViewUserCurrentActivity(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!$user['user_id'])
		{
			return true;
		}
		if ($user['visible'] && $user['activity_visible'])
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);

		if ($user['user_id'] == $viewingUser['user_id'])
		{
			// can always view own
			return true;
		}

		return $this->canBypassUserPrivacy($errorPhraseKey, $viewingUser);
	}

	/**
	 * Determines if permissions are sufficient to warn the given user.
	 *
	 * @param array $user User being viewed
	 * @param string $errorPhraseKey Returned by ref. Phrase key of more specific error
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return boolean
	 */
	public function canWarnUser(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!empty($user['is_admin']) || !empty($user['is_moderator']) || empty($user['user_id']))
		{
			return false;
		}

		$this->standardizeViewingUserReference($viewingUser);

		return ($viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'warn'));
	}

	/**
	 * Checks that the viewing user may report the specified user
	 *
	 * @param array $user User being viewed
	 * @param string $errorPhraseKey Returned by ref. Phrase key of more specific error
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return boolean
	 */
	public function canReportUser(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if ($user['is_staff'])
		{
			return false;
		}

		return $this->canReportContent($errorPhraseKey, $viewingUser);
	}

	/**
	 * Determines if the viewing user can edit a user's basic details
	 * (moderator-level permission for editing)
	 *
	 * @param array $user
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditUser(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		$viewerIsSuperAdmin = $this->isUserSuperAdmin($viewingUser);
		if ($viewerIsSuperAdmin)
		{
			return true;
		}

		if ($user['is_admin'] && $this->isUserSuperAdmin($user) && !$viewerIsSuperAdmin)
		{
			return false;
		}

		if ($viewingUser['is_admin'])
		{
			$adminPermissions = XenForo_Model::create('XenForo_Model_Admin')->getAdminPermissionCacheForUser(
				$viewingUser['user_id']
			);
			if ($adminPermissions && !empty($adminPermissions['user']))
			{
				return true;
			}
		}

		if ($user['is_admin'] || $user['is_moderator'] || $user['is_staff'])
		{
			// moderators can't edit admins/mods/staff
			return false;
		}

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'editBasicProfile');
	}

	/**
	 * Determines if the viewing user can start a conversation with the given user.
	 * Does not check standard conversation permissions.
	 *
	 * @param array $user
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canStartConversationWithUser(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		if (!$user['user_id'])
		{
			return false;
		}

		return $this->getModelFromCache('XenForo_Model_Conversation')->canStartConversationWithUser(
			$user, $errorPhraseKey, $viewingUser
		);
	}

	/**
	 * Determines if the viewing user can view IPs logged with posts, profile posts etc.
	 *
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewIps(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return ($viewingUser['user_id'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'viewIps'));
	}

	/**
	 * Determines if the viewing user can view any member list (notable members, full list, online, etc).
	 *
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewMemberList(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'viewMemberList');
	}

	/**
	 * Determines if a user can report the specified content
	 *
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canReportContent(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'] || !XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'report'))
		{
			$errorPhraseKey = 'you_may_not_report_this_content';
			return false;
		}

		return true;
	}

	/**
	 * Determines if a user can view the warnings
	 *
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewWarnings(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'] || !XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'viewWarning'))
		{
			return false;
		}

		return true;
	}

	/**
	 * Determines if the viewing user can start conversations in general.
	 *
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canStartConversations(&$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($viewingUser['user_state'] != 'valid')
		{
			return false;
		}

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'start'))
		{
			$maxRecipients = XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'maxRecipients');
			return ($maxRecipients == -1 || $maxRecipients > 0);
		}

		return false;
	}

	/**
	 * Determines if the viewing user passes the specified privacy check.
	 * This must include the following status for the viewing user.
	 *
	 * @param string $privacyRequirement The required privacy: everyone, none, members, followed
	 * @param array $user User info, including following status for viewing user
	 * @param array|null $viewingUser Viewing user ref
	 * @return unknown_type
	 */
	public function passesPrivacyCheck($privacyRequirement, array $user, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!isset($user['following_' . $viewingUser['user_id']]) && !isset($user['following']))
		{
			throw new XenForo_Exception('Missing following state for user ID ' . $viewingUser['user_id'] . ' in user ' . $user['user_id']);
		}

		if ($user['user_id'] == $viewingUser['user_id'])
		{
			return true;
		}

		if ($this->canBypassUserPrivacy($null, $viewingUser))
		{
			return true;
		}

		switch ($privacyRequirement)
		{
			case 'everyone': return true;
			case 'none':     return false;
			case 'members':  return ($viewingUser['user_id'] > 0);

			case 'followed':
				if (isset($user['following_' . $viewingUser['user_id']]))
				{
					return ($user['following_' . $viewingUser['user_id']] > 0);
				}
				else if (!empty($user['following']))
				{
					return in_array($viewingUser['user_id'], explode(',', $user['following']));
				}
				else
				{
					return false;
				}


			default:
				return true;
		}
	}

	/**
	 * Fetches the logged registration IP addresses for the specified user, if available.
	 *
	 * @param integer $userId
	 *
	 * @return array [ register: string, account-confirmation: string ]
	 */
	public function getRegistrationIps($userId)
	{
		$ips = $this->_getDb()->fetchPairs('
			SELECT action, ip
			FROM xf_ip
			WHERE user_id = ?
			AND content_type = \'user\'
			AND action IN(\'register\', \'account-confirmation\')
		', $userId);

		return array_map(array('XenForo_Helper_Ip', 'convertIpBinaryToString'), $ips);
	}

	/**
	 * Determines whether or not the specified user may have the spam cleaner applied against them.
	 *
	 * @param array $user
	 * @param string|array Error phrase key - may become an array if the phrase requires parameters
	 *
	 * @return boolean
	 */
	public function couldBeSpammer(array $user, &$errorKey = '')
	{
		// self
		if ($user['user_id'] == XenForo_Visitor::getUserId())
		{
			$errorKey = 'sorry_dave';
			return false;
		}

		// staff
		if ($user['is_admin'] || $user['is_moderator'])
		{
			$errorKey = 'spam_cleaner_no_admins_or_mods';
			return false;
		}

		$criteria = XenForo_Application::get('options')->spamUserCriteria;

		if ($criteria['message_count'] && $user['message_count'] > $criteria['message_count'])
		{
			$errorKey = array('spam_cleaner_too_many_messages', 'message_count' => $criteria['message_count']);
			return false;
		}

		if ($criteria['register_date'] && $user['register_date'] < (XenForo_Application::$time - $criteria['register_date'] * 86400))
		{
			$errorKey = array('spam_cleaner_registered_too_long', 'register_days' => $criteria['register_date']);
			return false;
		}

		if ($criteria['like_count'] && $user['like_count'] > $criteria['like_count'])
		{
			$errorKey = array('spam_cleaner_too_many_likes', 'like_count' => $criteria['like_count']);
			return false;
		}

		return true;
	}

	/**
	 * Bans a user or updates an existing ban.
	 *
	 * @param integer $userId ID of user to ban
	 * @param integer $endDate Date at which ban will be lifted. Use XenForo_Model_User::PERMANENT_BAN for a permanent ban.
	 * @param $reason
	 * @param $update
	 * @param $errorKey
	 * @param $viewingUser
	 * @param $triggered
	 *
	 * @return boolean
	 */
	public function ban($userId, $endDate, $reason, $update = false, &$errorKey = null, array $viewingUser = null, $triggered = false)
	{
		if ($endDate < XenForo_Application::$time && $endDate !== self::PERMANENT_BAN)
		{
			$errorKey = 'please_enter_a_date_in_the_future';
			return false;
		}

		$this->standardizeViewingUserReference($viewingUser);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserBan');
		if ($update)
		{
			$dw->setExistingData($userId);
		}
		else
		{
			$dw->set('user_id', $userId);
			$dw->set('ban_user_id', $viewingUser['user_id']);
		}

		$dw->set('end_date', $endDate);
		$dw->set('user_reason', $reason);
		if ($triggered || $dw->isChanged('end_date'))
		{
			$dw->set('triggered', $triggered ? 1 : 0);
		}
		$dw->preSave();

		if ($dw->hasErrors())
		{
			$errors = $dw->getErrors();
			$errorKey = reset($errors);
			return false;
		}

		$dw->save();
		return true;
	}

	/**
	 * Lifts the ban on the specified user
	 *
	 * @param integer $userId
	 *
	 * @return boolean
	 */
	public function liftBan($userId)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserBan', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($userId);
		return $dw->delete();
	}

	/**
	 * Gets the date of the earliest user registration
	 *
	 * @return integer
	 */
	public function getEarliestRegistrationDate()
	{
		return (int)$this->_getDb()->fetchOne('SELECT MIN(register_date) FROM xf_user');
	}

	/**
	 * Returns true if the specified user ID or user name is in the ignored (cache) of the given user.
	 *
	 * @param array $user
	 * @param integer|string User ID or user name
	 *
	 * @return array|boolean
	 */
	public function isUserIgnored(array $user, $ignoredUser)
	{
		if ((isset($user['ignored']) && !$user['ignored']) || !$ignoredUser)
		{
			return false;
		}

		$userId = $user['user_id'];

		if (!isset($this->_ignoreCache[$userId]))
		{
			if (!isset($user['ignored']))
			{
				$user['ignored'] = $this->_getDb()->fetchOne('
					SELECT ignored
					FROM xf_user_profile
					WHERE user_id = ?
				', $userId);
			}

			$this->_ignoreCache[$userId] = unserialize($user['ignored']);
		}

		if (is_int($ignoredUser) && isset($this->_ignoreCache[$userId][$ignoredUser]))
		{
			return array($ignoredUser, $this->_ignoreCache[$userId][$ignoredUser]);
		}

		if (is_string($ignoredUser))
		{
			$ignoredUserId = array_search($ignoredUser, $this->_ignoreCache[$userId]);

			if ($ignoredUserId !== false)
			{
				return array($ignoredUserId, $this->_ignoreCache[$userId][$ignoredUserId]);
			}
		}

		return false;
	}

	/**
	 * List of user IDs/names to be changed when appropriate (name changes,
	 * user deletes, user merges).
	 *
	 * Key is the table name. Value is an array of arrays. Inner array can have 1-3 elements:
	 * 0. user_id column name
	 * 1. username column name (unspecified/false if there isn't one)
	 * 2. boolean to control whether this is updated when deleting a user (unspecified defaults to true).
	 *
	 * Delete updates should only be turned off when the user_id record is absolutely needed or the data has already been removed.
	 *
	 * @var array
	 */
	public static $userContentChanges = array(
		'xf_admin_log' => array(array('user_id', false, false)),
		'xf_attachment_data' => array(array('user_id')),
		'xf_conversation_master' => array(array('user_id', 'username'), array('last_message_user_id', 'last_message_username')),
		'xf_conversation_message' => array(array('user_id', 'username')),
		'xf_conversation_recipient' => array(array('user_id', false, false)),
		'xf_conversation_user' => array(array('owner_user_id', false, false), array('last_message_user_id', 'last_message_username')),
		'xf_deletion_log' => array(array('delete_user_id', 'delete_username')),
		'xf_edit_history' => array(array('edit_user_id')),
		'xf_ip' => array(array('user_id')),
		'xf_liked_content' => array(array('like_user_id'), array('content_user_id')),
		'xf_news_feed' => array(array('user_id', 'username', false)),
		'xf_poll_vote' => array(array('user_id', false, false)),
		'xf_post' => array(array('user_id', 'username')),
		'xf_profile_post' => array(array('profile_user_id', false, false), array('user_id', 'username')),
		'xf_profile_post_comment' => array(array('user_id', 'username')),
		'xf_report' => array(array('content_user_id'), array('last_modified_user_id', 'last_modified_username')),
		'xf_report_comment' => array(array('user_id', 'username')),
		'xf_spam_cleaner_log' => array(array('user_id', 'username'), array('applying_user_id', 'applying_username')),
		'xf_thread' => array(array('user_id', 'username'), array('last_post_user_id', 'last_post_username')),
		'xf_forum' => array(array('last_post_user_id', 'last_post_username')),
		'xf_thread_reply_ban' => array(array('user_id'), array('ban_user_id')),
		'xf_thread_user_post' => array(array('user_id')),
		'xf_thread_watch' => array(array('user_id')),
		'xf_user_alert' => array(array('user_id', 'username')),
		'xf_user_follow' => array(array('user_id', false, false), array('follow_user_id', false, false)),
		'xf_user_ignored' => array(array('user_id', false, false), array('ignored_user_id', false, false)),
		'xf_user_trophy' => array(array('user_id')),
		'xf_user_upgrade_active' => array(array('user_id', false, false)),
		'xf_user_upgrade_expired' => array(array('user_id', false, false)),
		'xf_warning' => array(array('user_id'), array('warning_user_id')),
	);

	/**
	 * Updates existing content with a new username, or updated user ID
	 *
	 * @param integer $existingUserId
	 * @param string $newUserName
	 * @param string $oldUserName
	 * @param integer $newUserId (set if the user ownership has changed)
	 */
	public function changeContentUser($existingUserId, $newUserName = null, $oldUserName = null, $newUserId = null)
	{
		if ($existingUserId === $newUserId)
		{
			$newUserId = null;
		}

		if ($newUserName === null && $newUserId === null)
		{
			return;
		}

		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		foreach (self::$userContentChanges AS $table => $changes)
		{
			foreach ($changes AS $change)
			{
				$userIdCol = $change[0];
				$userNameCol = isset($change[1]) ? $change[1] : false;
				$updateEmptyUserId = isset($change[2]) ? $change[2] : true;

				$update = array();
				if ($newUserId !== null && ($updateEmptyUserId || $newUserId))
				{
					$update[] = "$userIdCol = " . $db->quote($newUserId);
				}
				if ($newUserName !== null && $userNameCol)
				{
					$update[] = "$userNameCol = " . $db->quote($newUserName);
				}
				if ($update)
				{
					$db->query('
						UPDATE IGNORE ' . $table . ' SET
							' . implode(',', $update) . '
						WHERE ' . $userIdCol . ' = ?
					', $existingUserId);
				}
			}
		}

		if ($newUserName || $newUserId !== null)
		{
			$updatedUserId = ($newUserId !== null) ? $newUserId : $existingUserId;
			$updatedUsername = $newUserName ? $newUserName : $oldUserName;

			$likeHandlers = $this->getModelFromCache('XenForo_Model_Like')->getLikeHandlers();
			foreach ($likeHandlers AS $contentType => $likeHandler)
			{
				$likeHandler->batchUpdateContentUser($existingUserId, $updatedUserId, $oldUserName, $updatedUsername);
			}
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Rebuilds the user moderation queue cache.
	 *
	 * @return array Cache, [total, lastModifiedDate]
	 */
	public function rebuildUserModerationQueueCache()
	{
		$cache = array(
			'total' => $this->countUsers(array('user_state' => 'moderated')),
			'lastModifiedDate' => XenForo_Application::$time
		);

		$this->_getDataRegistryModel()->set('userModerationCounts', $cache);

		return $cache;
	}

	public function mergeUsers(array $target, array $source)
	{
		if ($target['user_id'] == $source['user_id'])
		{
			return true;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData($target))
		{
			XenForo_Db::beginTransaction();

			// these are going to end up being the same person
			// and you can't like your own content so delete them
			$this->_getDb()->query("
				DELETE FROM xf_liked_content
				WHERE like_user_id = ?
					AND content_user_id = ?
			", array($source['user_id'], $target['user_id']));

			$dw->set('message_count', $dw->get('message_count') + $source['message_count']);
			$dw->set('like_count', $dw->get('like_count') + $source['like_count']); // this isn't 100% perfect
			$dw->set('conversations_unread', $dw->get('conversations_unread') + $source['conversations_unread']);
			$dw->set('alerts_unread', $dw->get('alerts_unread') + $source['alerts_unread']);
			$dw->set('warning_points', $dw->get('warning_points') + $source['warning_points']);
			$dw->set('register_date', min($dw->get('register_date'), $source['register_date']));
			$dw->set('last_activity', max($dw->get('last_activity'), $source['last_activity']));
			$dw->save();

			$this->changeContentUser($source['user_id'], $target['username'], $source['username'], $target['user_id']);

			$deleteDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
			$deleteDw->setExistingData($source);
			$deleteDw->delete();

			$this->getModelFromCache('XenForo_Model_Trophy')->updateTrophyPointsForUser($target['user_id']);

			// this will survive the user delete - anything left over is where both users
			// were in the same conversation so we can remove the old records
			$this->_getDb()->query("
				DELETE FROM xf_conversation_recipient
				WHERE user_id = ?
			", $source['user_id']);

			XenForo_Db::commit();

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @see XenForo_Model_UserChangeLog::logChanges
	 */
	public function logChanges($userId, array $changedFields, $editUserId = null)
	{
		return $this->getModelFromCache('XenForo_Model_UserChangeLog')->logChanges($userId, $changedFields, $editUserId);
	}

	/**
	 * @return XenForo_Model_UserProfile
	 */
	protected function _getUserProfileModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserProfile');
	}

	/**
	 * @return XenForo_Model_UserField
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserField');
	}

	/**
	 * @return XenForo_Model_Ip
	 */
	protected function _getIpModel()
	{
		return $this->getModelFromCache('XenForo_Model_Ip');
	}
}