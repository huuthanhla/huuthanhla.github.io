<?php

class XenForo_Model_SpamPrevention extends XenForo_Model
{
	const RESULT_ALLOWED = 'allowed';
	const RESULT_MODERATED = 'moderated';
	const RESULT_DENIED = 'denied';

	protected $_checkParams = array();

	protected $_lastResult = null;
	protected $_resultDetails = array();

	/**
	 * Gets the highest priority decision from a list of possibilities.
	 * Allowed < Moderated < Denied
	 *
	 * @param array $decisions
	 *
	 * @return string
	 */
	public function getFinalDecision(array $decisions)
	{
		$priorities = array(
			self::RESULT_ALLOWED => 1,
			self::RESULT_MODERATED => 2,
			self::RESULT_DENIED => 3
		);

		$output = self::RESULT_ALLOWED;
		$priority = $priorities[$output];

		foreach ($decisions AS $decision)
		{
			if ($priorities[$decision] > $priority)
			{
				$output = $decision;
				$priority = $priorities[$decision];
			}
		}

		return $output;
	}

	/**
	 * Return gets the results of the last check. Null if no check.
	 *
	 * @return null|string
	 */
	public function getLastCheckResult()
	{
		return $this->_lastResult;
	}

	/**
	 * Gets the details from the last spam check operation.
	 * Each entry will have a "phrase" key and an optional "data"
	 * key for data that will be passed into the phrase.
	 *
	 * @return array
	 */
	public function getLastCheckDetails()
	{
		return $this->_resultDetails;
	}

	/**
	 * Determines whether a registration should be allowed, moderated or denied
	 * based on its likelihood to be a spam bot.
	 *
	 * @param array $user
	 * @param Zend_Controller_Request_Http $request
	 *
	 * @return string One of the REGISTRATION_x constants from XenForo_Model_SpamPrevention
	 */
	public function allowRegistration(array $user, Zend_Controller_Request_Http $request)
	{
		$user = $this->_getSpamCheckData($user, $request);
		$decisions = $this->_allowRegistration($user, $request);

		$decision = $this->getFinalDecision($decisions);
		$this->_lastResult = $decision;

		return $decision;
	}

	protected function _allowRegistration(array $user, Zend_Controller_Request_Http $request)
	{
		$this->_resultDetails = array();

		$decisions = array(self::RESULT_ALLOWED);
		$decisions[] = $this->_checkDnsBlResult($user, $request);
		$decisions[] = $this->_checkSfsResult($user, $request);
		$decisions[] = $this->_checkBannedUsers($user, $request);

		return $decisions;
	}

	protected function _checkDnsBlResult(array $user, Zend_Controller_Request_Http $request)
	{
		$options = XenForo_Application::getOptions();
		$sfsOptions = $this->_getSfsSpamCheckOptions();
		$decision = self::RESULT_ALLOWED;

		if (!empty($user['ip']))
		{
			$ip = $user['ip'];

			/** @var $dataRegistryModel XenForo_Model_DataRegistry */
			$dataRegistryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');

			$dnsBlCache = $dataRegistryModel->get('dnsBlCache');
			if (!$dnsBlCache)
			{
				$dnsBlCache = array();
			}

			$block = false;
			$log = false;

			if ($options->get('registrationCheckDnsBl', 'check'))
			{
				$httpBlKey = $options->get('registrationCheckDnsBl', 'projectHoneyPotKey');

				if (!empty($dnsBlCache[$ip]) && $dnsBlCache[$ip]['expiry'] < XenForo_Application::$time)
				{
					// seen before
					$block = $dnsBlCache[$ip]['type'];
					$log = false;
				}
				else if (
					(!$sfsOptions['enabled'] && XenForo_DnsBl::checkTornevall($ip)
					|| ($httpBlKey && XenForo_DnsBl::checkProjectHoneyPot($ip, $httpBlKey)))
				)
				{
					// not seen before, block
					$block = true;
					$log = true;
				}
				else
				{
					// not seen before, ok
					$block = false;
					$log = true;
				}
			}

			if ($block)
			{
				if ($options->get('registrationCheckDnsBl', 'action') == 'block')
				{
					$decision = self::RESULT_DENIED;
				}
				else
				{
					$decision = self::RESULT_MODERATED;
				}

				$this->_resultDetails[] = array(
					'phrase' => 'dnsbl_matched'
				);
			}

			if ($log)
			{
				$dnsBlCache[$ip] = array('type' => $block, 'expiry' => XenForo_Application::$time + 3600);
				foreach ($dnsBlCache AS $key => $expiry)
				{
					if ($expiry <= XenForo_Application::$time)
					{
						unset($dnsBlCache[$key]);
					}
				}
				$dataRegistryModel->set('dnsBlCache', $dnsBlCache);
			}
		}

		return $decision;
	}

	protected function _checkSfsResult(array $user, Zend_Controller_Request_Http $request)
	{
		$sfsOptions = $this->_getSfsSpamCheckOptions();
		$decision = self::RESULT_ALLOWED;

		if ($sfsOptions['enabled'])
		{
			$apiResponse = $this->_getSfsApiResponse($user, $apiUrl, $fromCache);
			if (is_array($apiResponse))
			{
				$flagCount = $this->_getSfsSpamFlagCount($apiResponse, $counts);
				if ($sfsOptions['moderateThreshold'] && $flagCount >= (int)$sfsOptions['moderateThreshold'])
				{
					$decision = self::RESULT_MODERATED;
				}

				if ($sfsOptions['denyThreshold'] && $flagCount >= (int)$sfsOptions['denyThreshold'])
				{
					$decision = self::RESULT_DENIED;
				}

				if (!$fromCache)
				{
					// only update the cache if we didn't pull from the cache - this
					// prevents the cache from being kept indefinitely
					$cacheKey = $this->_getSfsCacheKey($apiUrl);
					$this->_cacheRegistrationResponse($cacheKey, $apiResponse, $decision);
				}

				if ($decision != self::RESULT_ALLOWED)
				{
					$parts = array();
					foreach ($counts AS $flag => $count)
					{
						$parts[] = "$flag: $count";
					}
					$this->_resultDetails[] = array(
						'phrase' => 'sfs_matched_x',
						'data' => array(
							'matches' => implode(', ', $parts)
						)
					);
				}
			}
		}

		return $decision;
	}

	protected function _checkBannedUsers(array $user, Zend_Controller_Request_Http $request)
	{
		$option = XenForo_Application::getOptions()->registerModerateSharedBannedIp;
		if (empty($option['enabled']))
		{
			return self::RESULT_ALLOWED;
		}

		/** @var XenForo_Model_Ip $ipModel */
		$ipModel = $this->getModelFromCache('XenForo_Model_Ip');
		$ipUsers = $ipModel->getUsersByIp($request->getClientIp(), $option['days']);

		$bannedNames = array();
		foreach ($ipUsers AS $user)
		{
			if ($user['is_banned'])
			{
				$bannedNames[] = $user['username'];
			}
		}

		if ($bannedNames)
		{
			$this->_resultDetails[] = array(
				'phrase' => 'shared_ip_banned_user_x',
				'data' => array(
					'users' => implode(', ', $bannedNames)
				)
			);

			return self::RESULT_MODERATED;
		}

		return self::RESULT_ALLOWED;
	}

	/**
	 * Submits rejected data back to the spam database
	 *
	 * @param array $user
	 */
	public function submitSpamUserData(array $user)
	{
		$sfsSpamOptions = $this->_getSfsSpamCheckOptions();

		if ($sfsSpamOptions['apiKey'] && !empty($user['username']) && !empty($user['email']) && !empty($user['ip']))
		{
			$submitUrl = 'http://www.stopforumspam.com/add.php'
				. '?api_key=' . $sfsSpamOptions['apiKey']
				. (isset($user['username']) ? '&username=' . urlencode($user['username']) : '')
				. (isset($user['email']) ? '&email=' . urlencode($user['email']) : '')
				. (isset($user['ip']) ? '&ip_addr=' . urlencode($user['ip']) : '');

			$client = XenForo_Helper_Http::getClient($submitUrl);
			try
			{
				$response = $client->request('GET');
				if ($response && $response->getStatus() >= 400)
				{
					if (preg_match('#<p>(.+)</p>#siU', $response->getBody(), $match))
					{
						// don't log this race condition
						if ($match[1] != 'recent duplicate entry')
						{
							$e = new XenForo_Exception("Error reporting to StopForumSpam: $match[1]");
							XenForo_Error::logException($e, false);
						}
					}
				}
			}
			catch (Zend_Http_Exception $e)
			{
				// SFS can go down frequently, so don't log this
				//XenForo_Error::logException($e, false);
			}
		}
	}

	public function submitSpamCommentData($contentType, $contentIds)
	{
		if (!is_array($contentIds))
		{
			$contentIds = array($contentIds);
		}

		foreach ($this->getContentSpamCheckParams($contentType, $contentIds) AS $contentId => $params)
		{
			if ($params)
			{
				$this->_submitSpamCommentData($contentType, $contentId, $params);
			}
		}
	}

	protected function _submitSpamCommentData($contentType, $contentId, array $params)
	{
		if (XenForo_Application::getOptions()->akismetKey
			&& empty($params['akismetIsSpam'])
			&& !empty($params['akismet'])
		)
		{
			$akismet = new Zend_Service_Akismet(
				XenForo_Application::getOptions()->akismetKey,
				XenForo_Application::getOptions()->boardUrl
			);

			try
			{
				$akismet->submitSpam($params['akismet']);
			}
			catch (Zend_Http_Exception $e) {}
			catch (Zend_Service_Exception $e) {}
		}
	}

	public function submitHamCommentData($contentType, $contentIds)
	{
		if (!is_array($contentIds))
		{
			$contentIds = array($contentIds);
		}

		foreach ($this->getContentSpamCheckParams($contentType, $contentIds) AS $contentId => $params)
		{
			if ($params)
			{
				$this->_submitHamCommentData($contentType, $contentId, $params);
			}
		}
	}

	protected function _submitHamCommentData($contentType, $contentId, array $params)
	{
		if (XenForo_Application::getOptions()->akismetKey
			&& !empty($params['akismetIsSpam'])
			&& !empty($params['akismet'])
		)
		{
			$akismet = new Zend_Service_Akismet(
				XenForo_Application::getOptions()->akismetKey,
				XenForo_Application::getOptions()->boardUrl
			);

			try
			{
				$akismet->submitHam($params['akismet']);
			}
			catch (Zend_Http_Exception $e) {}
			catch (Zend_Service_Exception $e) {}
		}
	}

	public function checkMessageSpam($content, array $extraParams = array(), Zend_Controller_Request_Http $request = null)
	{
		if (!$request)
		{
			$request = new Zend_Controller_Request_Http();
		}

		$results = $this->_allowMessage($content, $extraParams, $request);

		$decision = $this->getFinalDecision($results);
		$this->_lastResult = $decision;

		return $decision;
	}

	protected function _allowMessage($content, array $extraParams = array(), Zend_Controller_Request_Http $request)
	{
		$this->_checkParams = array();
		$this->_resultDetails = array();

		$results = array(self::RESULT_ALLOWED);
		$results[] = $this->_checkAkismet($content, $extraParams, $request);
		$results[] = $this->_checkSpamPhrases($content, $extraParams, $request);

		return $results;
	}

	protected function _checkAkismet($content, array $extraParams, Zend_Controller_Request_Http $request)
	{
		$options = XenForo_Application::getOptions();
		$visitor = XenForo_Visitor::getInstance();
		$result = self::RESULT_ALLOWED;

		if ($options->akismetKey)
		{
			$akismetParams = array(
				'user_ip' => $request->getClientIp(false),
				'user_agent' => $request->getServer('HTTP_USER_AGENT', 'Unknown'),
				'referrer' => $request->getServer('HTTP_REFERER'),
				'comment_type' => 'comment',
				'comment_author' => $visitor['username'],
				'comment_author_email' => $visitor['email'],
				'comment_author_url' => $visitor['homepage'],
				'comment_content' => $content
			);
			if (isset($extraParams['permalink']))
			{
				$akismetParams['permalink'] = $extraParams['permalink'];
			}

			$akismet = new Zend_Service_Akismet($options->akismetKey, $options->boardUrl);

			try
			{
				$this->_checkParams['akismetIsSpam'] = $akismet->isSpam($akismetParams);
				$this->_checkParams['akismet'] = $akismetParams;

				if ($this->_checkParams['akismetIsSpam'])
				{
					$result = self::RESULT_MODERATED;

					$this->_resultDetails[] = array(
						'phrase' => 'akismet_matched'
					);
				}
			}
			catch (Zend_Http_Exception $e) {}
			catch (Zend_Service_Exception $e) {}
		}

		return $result;
	}

	protected function _checkSpamPhrases($content, array $extraParams, Zend_Controller_Request_Http $request)
	{
		$options = XenForo_Application::getOptions();
		$result = self::RESULT_ALLOWED;

		if ($options->spamPhrases['phrases'])
		{
			$phrases = preg_split('/\r?\n/', trim($options->spamPhrases['phrases']), -1, PREG_SPLIT_NO_EMPTY);
			foreach ($phrases AS $phrase)
			{
				$phrase = trim($phrase);
				if (!strlen($phrase))
				{
					continue;
				}

				$origPhrase = $phrase;

				if ($phrase[0] != '/')
				{
					$phrase = preg_quote($phrase, '#');
					$phrase = str_replace('\\*', '[\w"\'/ \t]*', $phrase);
					$phrase = '#(?<=\W|^)(' . $phrase . ')(?=\W|$)#iu';
				}
				else
				{
					if (preg_match('/\W[\s\w]*e[\s\w]*$/', $phrase))
					{
						// can't run a /e regex
						continue;
					}
				}

				try
				{
					if (preg_match($phrase, $content))
					{
						$result = ($options->spamPhrases['action'] == 'moderate' ? self::RESULT_MODERATED : self::RESULT_DENIED);

						$this->_resultDetails[] = array(
							'phrase' => 'spam_phrase_matched_x',
							'data' => array(
								'phrase' => $origPhrase
							)
						);

						break;
					}
				}
				catch (ErrorException $e) {}
			}
		}

		return $result;
	}

	public function getCurrentSpamCheckParams()
	{
		return $this->_checkParams;
	}

	public function getContentSpamCheckParams($contentType, $contentIds)
	{
		if (is_array($contentIds))
		{
			if (!$contentIds)
			{
				return array();
			}

			$db = $this->_getDb();
			$pairs = $db->fetchPairs("
				SELECT content_id, spam_params
				FROM xf_content_spam_cache
				WHERE content_type = ?
					AND content_id IN (" . $db->quote($contentIds) . ")
			", $contentType);
			foreach ($pairs AS &$value)
			{
				$value = @unserialize($value);
			}

			return $pairs;
		}
		else
		{
			$params = $this->_getDb()->fetchOne('
				SELECT spam_params
				FROM xf_content_spam_cache
				WHERE content_type = ?
					AND content_id = ?
			', array($contentType, $contentIds));

			return $params ? @unserialize($params) : false;
		}
	}

	public function logContentSpamCheck($contentType, $contentId, array $params = null)
	{
		if ($params === null)
		{
			$params = $this->getCurrentSpamCheckParams();
		}
		if (!$params)
		{
			return;
		}

		$this->_getDb()->query("
			INSERT INTO xf_content_spam_cache
				(content_type, content_id, spam_params, insert_date)
			VALUES
				(?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				spam_params = VALUES(spam_params),
				insert_date = VALUES(insert_date)
		", array($contentType, $contentId, serialize($params), XenForo_Application::$time));
	}

	public function cleanupContentSpamCheck($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = XenForo_Application::$time - 14 * 86400;
		}

		$this->_getDb()->delete('xf_content_spam_cache', 'insert_date < ' . intval($cutOff));
	}

	public function visitorRequiresSpamCheck($viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return (
			!$viewingUser['is_admin']
			&& !$viewingUser['is_moderator']
			&& XenForo_Application::getOptions()->maxContentSpamMessages
			&& $viewingUser['message_count'] < XenForo_Application::getOptions()->maxContentSpamMessages
		);
	}

	/**
	 * Logs that the spam handler was triggered, if the result was not allowed.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 * @param string|null $result
	 * @param array|null $details
	 * @param null|integer $userId
	 * @param null|string $ipAddress
	 *
	 * @return bool|int True if updated, false if no change, int ID if inserted
	 */
	public function logSpamTrigger($contentType, $contentId, $result = null, array $details = null, $userId = null, $ipAddress = null)
	{
		if ($result === null)
		{
			$result = $this->getLastCheckResult();
		}

		switch ($result)
		{
			case self::RESULT_DENIED:
			case self::RESULT_MODERATED:
				break;

			default:
				return false;
		}

		$ipAddress = XenForo_Helper_Ip::getBinaryIp(null, $ipAddress);

		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		if (!$contentId)
		{
			$contentId = null;
		}

		if ($contentType == 'user')
		{
			$userId = $contentId ? $contentId : 0;
		}

		if ($details === null)
		{
			$details = $this->getLastCheckDetails();
		}

		$requestPaths = XenForo_Application::get('requestPaths');
		$request = array(
			'url' => $requestPaths['fullUri'],
			'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
			'_GET' => $_GET,
			'_POST' => $_POST
		);

		// don't log passwords
		foreach ($request['_POST'] AS $key => &$value) {
			if (strpos($key, 'password') !== false || $key == '_xfToken')
			{
				$value = '********';
			}
		}

		$query = $this->_getDb()->query("
			INSERT INTO xf_spam_trigger_log
				(content_type, content_id, log_date, user_id, ip_address, result, details, request_state)
			VALUES
				(?, ?, ?, ?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				log_date = VALUES(log_date),
				user_id = VALUES(user_id),
				ip_address = VALUES(ip_address),
				result = VALUES(result),
				details = VALUES(details),
				request_state = VALUES(request_state)
		", array(
			$contentType, $contentId,
			XenForo_Application::$time, $userId, $ipAddress,
			$result, serialize($details), serialize($request)
		));

		return $query->rowCount() == 1 ? $this->_getDb()->lastInsertId() : true;
	}

	public function getSpamTriggerLogById($id)
	{
		return $this->_getDb()->fetchRow("
			SELECT log.*,
				user.*
			FROM xf_spam_trigger_log AS log
			LEFT JOIN xf_user AS user ON (log.user_id = user.user_id)
			WHERE log.trigger_log_id = ?
		", $id);
	}

	public function getSpamTriggerLogForContent($contentType, $contentId)
	{
		return $this->_getDb()->fetchRow("
			SELECT log.*,
				user.*
			FROM xf_spam_trigger_log AS log
			LEFT JOIN xf_user AS user ON (log.user_id = user.user_id)
			WHERE log.content_type = ?
				AND log.content_id = ?
		", array($contentType, $contentId));
	}

	public function getSpamTriggerLogsByContentIds($contentType, array $contentIds)
	{
		if (!$contentIds)
		{
			return array();
		}

		$db = $this->_getDb();

		return $this->fetchAllKeyed("
			SELECT log.*,
				user.*
			FROM xf_spam_trigger_log AS log
			LEFT JOIN xf_user AS user ON (log.user_id = user.user_id)
			WHERE log.content_type = ?
				AND log.content_id IN (" . $db->quote($contentIds) . ")
		", 'content_id', $contentType);
	}

	public function getSpamTriggerLogs(array $conditions = array(), array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults("
			SELECT log.*,
				user.*
			FROM xf_spam_trigger_log AS log
			LEFT JOIN xf_user AS user ON (log.user_id = user.user_id)
			ORDER BY log.log_date DESC
		", $limitOptions['limit'], $limitOptions['offset']), 'trigger_log_id');
	}

	public function countSpamTriggerLogs(array $conditions = array())
	{
		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM xf_spam_trigger_log AS log
		");
	}

	public function prepareSpamTriggerLog(array $log)
	{
		$log['detailsPrintable'] = array();
		foreach (unserialize($log['details']) AS $detail)
		{
			$log['detailsPrintable'][] = new XenForo_Phrase(
				$detail['phrase'], isset($detail['data']) ? $detail['data'] : array()
			);
		}

		return $log;
	}

	public function prepareSpamTriggerLogs(array $logs)
	{
		foreach ($logs AS &$log)
		{
			$log = $this->prepareSpamTriggerLog($log);
		}

		return $logs;
	}

	public function cleanupSpamTriggerLog($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = XenForo_Application::$time - 30 * 86400;
		}

		$this->_getDb()->delete('xf_spam_trigger_log', 'log_date < ' . intval($cutOff));
	}

	/**
	 * Push a spam/not spam registration result to the cache
	 *
	 * @param string $cacheKey
	 * @param mixed $result
	 * @param string $decision
	 */
	protected function _cacheRegistrationResponse($cacheKey, $result, $decision)
	{
		$cacheLifetime = ($decision == self::RESULT_ALLOWED ? 30 : 3600);

		$this->_getDb()->query("
			INSERT INTO xf_registration_spam_cache
				(cache_key, result, timeout)
			VALUES
				(?, ?, ?)
			ON DUPLICATE KEY UPDATE
				result = VALUES(result),
				timeout = VALUES(timeout)
		", array(
			$cacheKey,
			is_scalar($result) ? $result : serialize($result),
			XenForo_Application::$time + $cacheLifetime
		));
	}

	/**
	 * Attempt to fetch a spam/not spam registration decision from the cache
	 *
	 * @param string $cacheKey
	 *
	 * @return string|boolean
	 */
	protected function _getRegistrationResultFromCache($cacheKey)
	{
		return $this->_getDb()->fetchOne('
			SELECT result
			FROM xf_registration_spam_cache
			WHERE cache_key = ?
				AND timeout >= ?
		', array($cacheKey, XenForo_Application::$time));
	}

	/**
	 * Cleans up expired registration result cache entries
	 *
	 * @param int|null $date
	 */
	public function cleanUpRegistrationResultCache($date = null)
	{
		if ($date === null)
		{
			$date = XenForo_Application::$time;
		}

		$this->_getDb()->delete('xf_registration_spam_cache', 'timeout < ' . intval($date));
	}

	/**
	 * Build the unique cache key for a SFS spam/not spam decision
	 *
	 * @param string $apiUrl
	 *
	 * @return string
	 */
	protected function _getSfsCacheKey($apiUrl)
	{
		return 'stopForumSpam_' . sha1($apiUrl);
	}

	/**
	 * Takes the info passed to allowRegistration() and extracts the necessary data for the spam check
	 *
	 * @param array $user
	 * @param Zend_Controller_Request_Http $request
	 *
	 * @return array
	 */
	protected function _getSpamCheckData(array $user, Zend_Controller_Request_Http $request)
	{
		if (!isset($user['ip']))
		{
			$user['ip'] = $request->getClientIp(false);
		}

		return $user;
	}

	/**
	 * Fetches the options for the SFS spam check system
	 *
	 * @return array
	 */
	protected function _getSfsSpamCheckOptions()
	{
		return XenForo_Application::getOptions()->stopForumSpam;
	}

	/**
	 * Queries the SFS spam check API with the spam check data and returns an array of response data
	 *
	 * @param array $user
	 * @param string $apiUrl
	 * @param boolean $fromCache
	 *
	 * @return array
	 */
	protected function _getSfsApiResponse(array $user, &$apiUrl = '', &$fromCache = false)
	{
		$apiUrl = $this->_getSfsApiUrl($user);
		$cacheKey = $this->_getSfsCacheKey($apiUrl);
		$fromCache = false;

		if ($result = $this->_getRegistrationResultFromCache($cacheKey))
		{
			$fromCache = true;
			return unserialize($result);
		}

		$client = XenForo_Helper_Http::getClient($apiUrl);
		try
		{
			$response = $client->request('GET');
			$body = $response->getBody();

			$contents = $this->_decodeSfsApiData($body);

			return is_array($contents) ? $contents : false;
		}
		catch (Zend_Http_Exception $e)
		{
			//XenForo_Error::logException($e, false);
			return false;
		}
	}

	/**
	 * Builds the URL for the SFS spam check API
	 *
	 * @param array $user
	 *
	 * @return string
	 */
	protected function _getSfsApiUrl(array $user)
	{
		return 'http://www.stopforumspam.com/api?f=json&unix=1'
			. (isset($user['username']) ? '&username=' . urlencode($user['username']) : '')
			. (isset($user['email']) ? '&email=' . urlencode($user['email']) : '')
			. (isset($user['ip']) ? '&ip=' . urlencode($user['ip']) : '');
	}

	/**
	 * Takes the raw data returned by the SFS spam check API and turns it into a usable array
	 *
	 * @param string $data
	 *
	 * @return array
	 */
	protected function _decodeSfsApiData($data)
	{
		try
		{
			return json_decode($data, true);
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Counts the number of warning flags in the returned SFS spam check API data
	 *
	 * @param array $data
	 * @param array $counts Returns the counts for flags that were found
	 *
	 * @return integer
	 */
	protected function _getSfsSpamFlagCount(array $data, &$counts = array())
	{
		$option = $this->_getSfsSpamCheckOptions();

		$flagCount = 0;
		$counts = array();

		if (!empty($data['success']))
		{
			foreach (array('username', 'email', 'ip') AS $flagName)
			{
				if (!empty($data[$flagName]))
				{
					$flag = $data[$flagName];

					if (!empty($flag['appears']))
					{
						if ($flag['frequency'])
						{
							$counts[$flagName] = $flag['frequency'];
						}

						if (empty($option['frequencyCutOff']) || $flag['frequency'] >= $option['frequencyCutOff'])
						{
							if (empty($option['lastSeenCutOff']) || $flag['lastseen'] >= XenForo_Application::$time - $option['lastSeenCutOff'] * 86400)
							{
								$flagCount++;
							}
						}
					}
				}
			}
		}

		return $flagCount;
	}
}