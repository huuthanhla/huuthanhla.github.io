<?php

/**
 * Model for banning.
 *
 * @package XenForo_Banning
 */
class XenForo_Model_Banning extends XenForo_Model
{
	/**
	 * Gets banned users.
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array Format: [user id] => info
	 */
	public function getBannedUsers(array $conditions = array(), array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$whereConditions = $this->prepareUserBanConditions($conditions, $fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT user_ban.*,
					user.username,
					banning_user.username AS banning_username,
					user.avatar_date, user.gravatar, user.gender
				FROM xf_user_ban AS user_ban
				LEFT JOIN xf_user AS user ON (user.user_id = user_ban.user_id)
				LEFT JOIN xf_user AS banning_user ON (banning_user.user_id = user_ban.ban_user_id)
				WHERE ' . $whereConditions . '
				ORDER BY user_ban.ban_date DESC, user.username
			', $limitOptions['limit'], $limitOptions['offset']
		), 'user_id');
	}

	/**
	 * Counts the number of banned users.
	 *
	 * @return integer
	 */
	public function countBannedUsers(array $conditions = array())
	{
		$fetchOptions = array();
		$whereConditions = $this->prepareUserBanConditions($conditions, $fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user_ban AS user_ban
			LEFT JOIN xf_user AS user ON (user.user_id = user_ban.user_id)
			WHERE ' . $whereConditions . '
		');
	}

	/**
	 * Gets the banned user record for the specified user ID.
	 *
	 * @param integer $userId
	 * @param array $fetchOptions
	 *
	 * @return array|false
	 */
	public function getBannedUserById($userId, array $fetchOptions = array())
	{
		return $this->_getDb()->fetchRow('
			SELECT user_ban.*,
				user.username,
				banning_user.username AS banning_username
			FROM xf_user_ban AS user_ban
			LEFT JOIN xf_user AS user ON (user.user_id = user_ban.user_id)
			LEFT JOIN xf_user AS banning_user ON (banning_user.user_id = user_ban.ban_user_id)
			WHERE user_ban.user_id = ?
		', $userId);
	}

	/**
	 * Gets all user ban records that have expired.
	 *
	 * @param integer|null $cutOff Cut-off date. Defaults to now.
	 *
	 * @return array Format: [user id] => info
	 */
	public function getExpiredUserBans($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = XenForo_Application::$time;
		}

		return $this->fetchAllKeyed('
			SELECT user_ban.*
			FROM xf_user_ban AS user_ban
			WHERE user_ban.end_date > 0
				AND user_ban.end_date <= ?
		', 'user_id', $cutOff);
	}

	/**
	 * Prepares a set of conditions against which to select prefixes.
	 *
	 * @param array $conditions List of conditions.
	 * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	 *
	 * @return string Criteria as SQL for where clause
	 */
	public function prepareUserBanConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();

		$db = $this->_getDb();

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

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Deletes all user ban records that have expired.
	 *
	 * @param integer|null $cutOff Cut-off date. Defaults to now.
	 */
	public function deleteExpiredUserBans($cutOff = null)
	{
		foreach ($this->getExpiredUserBans($cutOff) AS $ban)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserBan');
			$dw->setExistingData($ban, true);
			$dw->delete();
		}
	}

	/**
	 * Gets all banned IPs.
	 *
	 * @return array Format: [] => info
	 */
	public function getBannedIps()
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_ip_match
			WHERE match_type = ?
			ORDER BY start_range
		', 'banned');
	}

	/**
	 * Counts all banned IPs
	 *
	 * @return integer
	 */
	public function countBannedIps()
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_ip_match
			WHERE match_type = ?
		', 'banned');
	}

	/**
	 * Bans the specified IP or IP range. Ranges may have wildcards but only at the end (10.*, 192.168.1.*).
	 * The wildcard is optional; 192.168 is equivalent to 192.168.*.
	 *
	 * Malformed input throws exceptions.
	 *
	 * @param string $ip
	 *
	 * @return boolean Returns true if the ban is inserted
	 */
	public function banIp($ip)
	{
		list($niceIp, $firstByte, $startRange, $endRange) = $this->_getIpRecord($ip);

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$result = $this->_getDb()->query('
			INSERT IGNORE INTO xf_ip_match
				(ip, match_type, first_byte, start_range, end_range)
			VALUES
				(?, ?, ?, ?, ?)
		', array($niceIp, 'banned', $startRange, $startRange, $endRange));

		$inserted = ($result->rowCount() ? true : false);
		if ($inserted)
		{
			$this->rebuildBannedIpCache();
		}

		XenForo_Db::commit($db);

		return $inserted;
	}

	/**
	 * Deletes the specified banned IPs. Array values should be the "nice" IP address values
	 * (banned_ip column in the DB).
	 *
	 * @param array $ips
	 */
	public function deleteBannedIps(array $ips)
	{
		if (!$ips)
		{
			return;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$db->delete('xf_ip_match', 'ip IN (' . $db->quote($ips) . ') AND match_type = ' . $db->quote('banned'));
		$this->rebuildBannedIpCache();

		XenForo_Db::commit($db);
	}

	/**
	 * Rebuilds the cache of banned IPs.
	 *
	 * @return array Banned IP cache
	 */
	public function rebuildBannedIpCache()
	{
		$cache = array();
		try
		{
			foreach ($this->getBannedIps() AS $bannedIp)
			{
				$cache[$bannedIp['first_byte']][] = array($bannedIp['start_range'], $bannedIp['end_range']);
			}
		}
		catch (Zend_Db_Statement_Exception $e) {} // happens if table doesn't exist

		$save = array(
			'version' => XenForo_Application::$time,
			'data' => $cache
		);

		$this->_getDataRegistryModel()->set('bannedIps', $save);

		return $save;
	}

	/**
	 * Gets all discouraged IPs.
	 *
	 * @return array Format: [] => info
	 */
	public function getDiscouragedIps()
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_ip_match
			WHERE match_type = ?
			ORDER BY start_range
		', 'discouraged');
	}

	/**
	 * Counts all discouraged IPs
	 *
	 * @return integer
	 */
	public function countDiscouragedIps()
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_ip_match
			WHERE match_type = ?
		', 'discouraged');
	}

	/**
	 * Discourages the specified IP or IP range. Ranges may have wildcards but only at the end (10.*, 192.168.1.*).
	 * The wildcard is optional; 192.168 is equivalent to 192.168.*.
	 *
	 * Malformed input throws exceptions.
	 *
	 * @param string $ip
	 *
	 * @return boolean Returns true if the ban is inserted
	 */
	public function discourageIp($ip)
	{
		list($niceIp, $firstByte, $startRange, $endRange) = $this->_getIpRecord($ip);

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$result = $this->_getDb()->query('
			INSERT IGNORE INTO xf_ip_match
				(ip, match_type, first_byte, start_range, end_range)
			VALUES
				(?, ?, ?, ?, ?)
		', array($niceIp, 'discouraged', $firstByte, $startRange, $endRange));

		$inserted = ($result->rowCount() ? true : false);
		if ($inserted)
		{
			$this->rebuildDiscouragedIpCache();
		}

		XenForo_Db::commit($db);

		return $inserted;
	}

	/**
	 * Deletes the specified discouraged IPs. Array values should be the "nice" IP address values
	 * (discouraged_ip column in the DB).
	 *
	 * @param array $ips
	 */
	public function deleteDiscouragedIps(array $ips)
	{
		if (!$ips)
		{
			return;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$db->delete('xf_ip_match', 'ip IN (' . $db->quote($ips) . ') AND match_type = ' . $db->quote('discouraged'));
		$this->rebuildDiscouragedIpCache();

		XenForo_Db::commit($db);
	}

	/**
	 * Rebuilds the cache of discouraged IPs.
	 *
	 * @return array Discouraged IP cache
	 */
	public function rebuildDiscouragedIpCache()
	{
		$cache = array();

		try
		{
			foreach ($this->getDiscouragedIps() AS $discouragedIp)
			{
				$cache[$discouragedIp['first_byte']][] = array($discouragedIp['start_range'], $discouragedIp['end_range']);
			}
		}
		catch (Zend_Db_Statement_Exception $e) {} // happens if table doesn't exist

		$save = array(
			'version' => XenForo_Application::$time,
			'data' => $cache
		);

		$this->_getDataRegistryModel()->set('discouragedIps', $save);

		return $save;
	}

	protected function _getIpRecord($ip)
	{
		$results = XenForo_Helper_Ip::parseIpRangeString($ip);
		if (!$results)
		{
			throw new XenForo_Exception(new XenForo_Phrase('please_enter_valid_ip_or_ip_range'), true);
		}

		return array(
			$results['printable'],
			$results['binary'][0],
			$results['startRange'],
			$results['endRange']
		);
	}

	/**
	 * Gets all banned emails.
	 *
	 * @return array Format: [] => info
	 */
	public function getBannedEmails()
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_ban_email
			ORDER BY banned_email
		');
	}

	/**
	 * Returns the total number of banned email addresses and address snippets
	 *
	 * @return integer
	 */
	public function countBannedEmails()
	{
		return $this->_getDb()->fetchOne('SELECT COUNT(*) FROM xf_ban_email');
	}

	/**
	 * Bans the specified email. Wildcards are allowed. If no wildcards are given, they are automatically
	 * added in logical places (no or leading @ gives wildcard prefix; no or trailing . gives wildcard suffix).
	 *
	 * Throws exceptions on malformed input.
	 *
	 * @param string $email
	 *
	 * @return boolean Returns true if the ban is inserted
	 */
	public function banEmail($email)
	{
		if ($email == '*' || $email === '')
		{
			throw new XenForo_Exception(new XenForo_Phrase('you_must_enter_at_least_one_non_wildcard_character'), true);
		}

		if (strpos($email, '*') === false)
		{
			if (strpos($email, '@') === false)
			{
				$email = '*' . $email;
			}
			if (strpos($email, '.') === false)
			{
				$email .= '*';
			}
		}

		if ($email[0] == '@')
		{
			$email = '*' . $email;
		}

		$lastChar = substr($email, -1);
		if ($lastChar == '.' || $lastChar == '@')
		{
			$email .= '*';
		}

		$atPos = strpos($email, '@');
		if ($atPos !== false && strpos($email, '.', $atPos) === false && strpos($email, '*', $atPos) === false)
		{
			$email .= '*';
		}

		if ($email == '*@*' || $email == '*.*')
		{
			throw new XenForo_Exception(new XenForo_Phrase('this_would_ban_all_email_addresses'), true);
		}

		$email = preg_replace('/\*{2,}/', '*', $email);

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$result = $this->_getDb()->query('
			INSERT IGNORE INTO xf_ban_email
				(banned_email)
			VALUES
				(?)
		', array($email));

		$inserted = ($result->rowCount() ? true : false);
		if ($inserted)
		{
			$this->rebuildBannedEmailCache();
		}

		XenForo_Db::commit($db);

		return $inserted;
	}

	/**
	 * Deletes the specified banned emails. Array values should be the "nice" email address values
	 * (banned_email column in the DB).
	 *
	 * @param array $emails
	 */
	public function deleteBannedEmails(array $emails)
	{
		if (!$emails)
		{
			return;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$db->delete('xf_ban_email', 'banned_email IN (' . $db->quote($emails) . ')');
		$this->rebuildBannedEmailCache();

		XenForo_Db::commit($db);
	}

	/**
	 * Rebuilds the cache of banned emails.
	 *
	 * @return array Banned email cache
	 */
	public function rebuildBannedEmailCache()
	{
		$cache = array();
		foreach ($this->getBannedEmails() AS $bannedEmail)
		{
			$cache[] = $bannedEmail['banned_email'];
		}

		$this->_getDataRegistryModel()->set('bannedEmails', $cache);

		return $cache;
	}
}