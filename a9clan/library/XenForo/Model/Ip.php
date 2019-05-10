<?php

/**
 * Model for logging IPs and querying them.
 *
 * @package XenForo_Ip
 */
class XenForo_Model_Ip extends XenForo_Model
{
	/**
	 * Stores resolved host names from IPs
	 *
	 * @var array
	 */
	protected $_hostCache = array();

	/**
	 * Logs an IP for an action.
	 *
	 * @param integer $userId User causing action
	 * @param string $contentType Type of content (user, post)
	 * @param integer $contentId ID of content
	 * @param string $action Action (insert, login)
	 * @param string|null $ipAddress IP address or null to pull from request
	 * @param integer|null $date Timestamp to tag IP with
	 *
	 * @return integer ID of inserted IP; 0 if no insert
	 */
	public function logIp($userId, $contentType, $contentId, $action, $ipAddress = null, $date = null)
	{
		$ipAddress = XenForo_Helper_Ip::getBinaryIp(null, $ipAddress);
		if (!$ipAddress)
		{
			return 0;
		}

		if ($date === null)
		{
			$date = XenForo_Application::$time;
		}

		$this->_getDb()->insert('xf_ip', array(
			'user_id' => $userId,
			'content_type' => $contentType,
			'content_id' => $contentId,
			'action' => $action,
			'ip' => $ipAddress,
			'log_date' => max(0, $date)
		));

		return $this->_getDb()->lastInsertId();
	}

	/**
	 * Static helper to log IPs without creating the model first.
	 *
	 * @see XenForo_Model_Ip::logIp()
	 */
	public static function log($userId, $contentType, $contentId, $action, $ipAddress = null, $date = null)
	{
		return XenForo_Model::create(__CLASS__)->logIp(
			$userId, $contentType, $contentId, $action, $ipAddress, $date
		);
	}

	/**
	 * Fetches an IP record by its id
	 *
	 * @param integer $ipId
	 *
	 * @return array
	 */
	public function getIpById($ipId)
	{
		$ip = $this->_getDb()->fetchRow('
			SELECT * FROM xf_ip
			WHERE ip_id = ?
		', $ipId);

		$ip['ip_address'] = XenForo_Helper_Ip::convertIpBinaryToString($ip['ip']);

		return $ip;
	}

	/**
	 * Static version of getIpById
	 *
	 * @see XenForo_Model_Ip::getIpById()
	 */
	public static function getById($ipId)
	{
		return XenForo_Model::create(__CLASS__)->getIpById($ipId);
	}

	/**
	 * Returns the first IP logged for the given parameters
	 *
	 * @param integer $userId
	 * @param string $contentType
	 * @param integer $contentId
	 *
	 * @return string IP binary/empty string
	 */
	public function getIp($userId, $contentType, $contentId)
	{
		$ip = $this->_getDb()->fetchOne('
			SELECT ip
			FROM xf_ip
			WHERE user_id = ?
			AND content_type = ?
			AND content_id = ?
		', array($userId, $contentType, $contentId));

		return ($ip ? XenForo_Helper_Ip::convertIpBinaryToString($ip) : '');
	}

	/**
	 * Static helper to get a logged ip wihtout creating the model first
	 *
	 * @see XenForo_Model_Ip::getIp()
	 */
	public static function get($userId, $contentType, $contentId)
	{
		return XenForo_Model::create(__CLASS__)->getIp(
			$userId, $contentType, $contentId
		);
	}

	/**
	 * Deletes all IPs that belong to the specified content.
	 *
	 * @param string $contentType
	 * @param int|array $contentIds One or more content Ids to delete from
	 */
	public function deleteByContent($contentType, $contentIds)
	{
		if (!is_array($contentIds))
		{
			$contentIds = array($contentIds);
		}
		if (!$contentIds)
		{
			return;
		}

		$db = $this->_getDb();

		$db->delete('xf_ip',
			'content_type = ' . $db->quote($contentType) . ' AND content_id IN (' . $db->quote($contentIds) . ')'
		);
	}

	protected static $_lookupCache = array();

	/**
	 * Resolves the host name of an IP address
	 *
	 * @param string $ip
	 *
	 * @return string
	 */
	protected function _getHost($ip)
	{
		if (isset(self::$_lookupCache[$ip]))
		{
			return self::$_lookupCache[$ip];
		}

		if (strpos($ip, ':') !== false)
		{
			// we need to uncompress the hex address to split this up
			$binary = XenForo_Helper_Ip::convertIpStringToBinary($ip);
			if (!$binary)
			{
				return '';
			}
			$checkIp = XenForo_Helper_Ip::convertIpBinaryToString($binary, false);
			if (!$checkIp)
			{
				return false;
			}

			$checkIp = str_replace(':', '', $checkIp);
			$parts = str_split($checkIp);
			$dnsRecord = implode('.', array_reverse($parts)) . '.ip6.arpa';
		}
		else
		{
			$parts = explode('.', $ip);
			if (count($parts) != 4)
			{
				return '';
			}

			$dnsRecord = implode('.', array_reverse($parts)) . '.in-addr.arpa';
		}

		$lookup = false;

		try
		{
			if (function_exists('dns_get_record'))
			{
				$host = dns_get_record($dnsRecord, DNS_PTR);
				if (isset($host[0]['target']))
				{
					$lookup = $host[0]['target'];
				}
			}
			else
			{
				$lookup = gethostbyaddr($ip);
			}
		}
		catch (Exception $e) {} // bad lookup

		if (!$lookup)
		{
			$lookup = $ip;
		}

		self::$_lookupCache[$ip] = $lookup;
		return $lookup;
	}

	/**
	 * Resolves the host name of an IP address
	 *
	 * @param string $ip
	 *
	 * @return string
	 */
	public static function getHost($ip)
	{
		return XenForo_Model::create(__CLASS__)->_getHost($ip);
	}

	/**
	 * Gets IP info for a content item and the member who created it
	 *
	 * @param array $content
	 * @param boolean $resolveHosts
	 *
	 * @return array (contentIp, contentHost, registrationIp, registrationHost, confirmationIp, confirmationHost)
	 */
	public function getContentIpInfo(array $content)
	{
		if ($content['ip_id'])
		{
			$ip = $this->getIpById($content['ip_id']);
			$contentIp = $ip['ip_address'];
			$contentHost = $this->_getHost($contentIp);
		}

		return $this->getRegistrationIps($content['user_id']) + array(
			'contentIp'    => (empty($contentIp) ? false : $contentIp),
			'contentHost'  => (empty($contentHost) ? false : $contentHost),
		);
	}

	/**
	 * Gets IP info for an online user
	 *
	 * @param array $onlineUser
	 *
	 * @return array (contentIp, contentHost, registrationIp, registrationHost, confirmationIp, confirmationHost)
	 */
	public function getOnlineUserIp($onlineUser)
	{
		if ($onlineUser['ip'])
		{
			$contentIp = XenForo_Helper_Ip::convertIpBinaryToString($onlineUser['ip']);
			$contentHost = $this->_getHost($contentIp);
		}

		return $this->getRegistrationIps($onlineUser['user_id']) + array(
			'contentIp'   => (empty($contentIp) ? false : $contentIp),
			'contentHost' => (empty($contentHost) ? false : $contentHost),
		);
	}

	/**
	 * Fetches an array containing IP info for the registration and confirmation IPs of the given user
	 *
	 * @param integer $userId
	 *
	 * @return array (registrationIp, registrationHost, confirmationIp, confirmationHost)
	 */
	protected function getRegistrationIps($userId)
	{
		$userIps = $this->getModelFromCache('XenForo_Model_User')->getRegistrationIps($userId);

		return array(
			'registrationIp'   => (empty($userIps['register']) ? false : $userIps['register']),
			'registrationHost' => (empty($userIps['register']) ? false : $this->_getHost($userIps['register'])),

			'confirmationIp'   => (empty($userIps['account-confirmation']) ? false : $userIps['account-confirmation']),
			'confirmationHost' => (empty($userIps['account-confirmation']) ? false : $this->_getHost($userIps['account-confirmation'])),
		);
	}

	/**
	 * Returns an array all IPs used by a user, keyed by the most recent date recorded for that IP
	 *
	 * @param integer $userId
	 *
	 * @return array [$unixDate => $ip]
	 */
	public function getIpsByUserId($userId)
	{
		$ips = $this->_getDb()->fetchPairs('
			SELECT MAX(log_date), ip
			FROM xf_ip
			WHERE user_id = ?
			GROUP BY ip
			ORDER BY log_date DESC
		', $userId);

		foreach ($ips AS &$ip)
		{
			$ip = XenForo_Helper_Ip::convertIpBinaryToString($ip);
		}

		return $ips;
	}

	/**
	 * Searches for records of users using any of the IP addresses logged by the specified user
	 *
	 * @param integer ID of the user to check against
	 * @param integer Number of days to look back in the logs
	 *
	 * @return array
	 */
	public function getSharedIpUsers($userId, $logDays)
	{
		$db = $this->_getDb();

		// written this way due to mysql's ridiculous sub-query performance
		$recentIps = $db->fetchCol($db->limit(
			'
				SELECT DISTINCT ip
				FROM xf_ip
				WHERE user_id = ?
					AND log_date > ?
			', 500
		), array($userId, XenForo_Application::$time - $logDays * 86400));
		if (!$recentIps)
		{
			return array();
		}

		$ipLogs = $db->fetchAll($db->limit(
			'
				SELECT *
				FROM xf_ip
				WHERE ip IN (' . $db->quote($recentIps) . ')
					AND user_id <> ?
					AND user_id > 0
					AND log_date > ?
				ORDER BY log_date DESC
			', 1000
		), array($userId, XenForo_Application::$time - $logDays * 86400));

		$userIpLogs = array();
		foreach ($ipLogs AS $ipLog)
		{
			$ipLog['ip_address'] = XenForo_Helper_Ip::convertIpBinaryToString($ipLog['ip']);

			$userIpLogs[$ipLog['user_id']][] = $ipLog;
		}

		$userRecords = $this->getModelFromCache('XenForo_Model_User')->getUsersByIds(
			array_keys($userIpLogs),
			array('join' => XenForo_Model_User::FETCH_LAST_ACTIVITY)
		);

		$users = array();

		foreach ($userIpLogs AS $userId => $ipLog)
		{
			if (!isset($userRecords[$userId]))
			{
				continue;
			}

			$users[$userId] = $userRecords[$userId];
			$users[$userId]['ipLogs'] = $ipLog;
		}

		return $users;
	}

	public function getUsersByIp($ip, $daysLimit = null)
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

		return $this->fetchAllKeyed('
			SELECT user.*, ip.ip, MAX(ip.log_date) AS log_date
			FROM xf_ip AS ip
			INNER JOIN xf_user AS user ON
				(user.user_id = ip.user_id)
			WHERE ip.ip = ?
				' . ($daysLimit ? ' AND ip.log_date > ' . (XenForo_Application::$time - $daysLimit * 86400) : '') . '
			GROUP BY ip.user_id
			ORDER BY user.username
		', 'user_id', $ip);
	}

	public function pruneIps($cutOff = null)
	{
		if ($cutOff === null)
		{
			if (!XenForo_Application::get('options')->ipLogCleanUp['enabled'])
			{
				return 0;
			}

			$cutOff = XenForo_Application::$time - 86400 * XenForo_Application::get('options')->ipLogCleanUp['delay'];
		}

		$db = $this->_getDb();
		return $db->delete('xf_ip', 'log_date < ' . $db->quote($cutOff));
	}
}