<?php

class XenForo_Model_Log extends XenForo_Model
{
	public function getServerErrorLogById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT user.*, error_log.*
			FROM xf_error_log AS error_log
			LEFT JOIN xf_user AS user ON (user.user_id = error_log.user_id)
			WHERE error_log.error_id = ?
		', $id);
	}

	public function getServerErrorLogs(array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT *
				FROM xf_error_log
				ORDER BY exception_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'error_id');
	}

	public function countServerErrors()
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_error_log
		');
	}

	public function deleteServerErrorLog($id)
	{
		$db = $this->_getDb();
		$db->delete('xf_error_log', 'error_id = ' . $db->quote($id));
	}

	public function clearServerErrorLog()
	{
		$this->_getDb()->query('TRUNCATE TABLE xf_error_log');
	}

	public function logAdminRequest(Zend_Controller_Request_Http $request, array $requestData = null, $ipAddress = null)
	{
		$baseUrl = $request->getBaseUrl();
		$requestUri = $request->getRequestUri();

		if (substr($requestUri, 0, strlen($baseUrl)) == $baseUrl)
		{
			$routeBase = substr($requestUri, strlen($baseUrl));
			$routeBase = preg_replace('/^\?/', '', $routeBase);
		}
		else
		{
			$routeBase = $requestUri;
		}

		if ($requestData === null)
		{
			$requestData = $this->_filterAdminLogRequestData($_POST);
		}

		$ipAddress = XenForo_Helper_Ip::getBinaryIp(null, $ipAddress, '');

		$this->_getDb()->insert('xf_admin_log', array(
			'request_date' => XenForo_Application::$time,
			'user_id' => XenForo_Visitor::getUserId(),
			'ip_address' => $ipAddress,
			'request_url' => $routeBase,
			'request_data' => json_encode($requestData)
		));
	}

	protected function _filterAdminLogRequestData(array $data)
	{
		foreach ($data AS $key => $value)
		{
			if (is_array($value))
			{
				$data[$key] = $this->_filterAdminLogRequestData($value);
			}
			else if (strpos($key, 'password') !== false || $key === '_xfToken')
			{
				unset($data[$key]);
			}
		}

		return $data;
	}

	public function getAdminLogEntries($userId = 0, array $fetchOptions = array())
	{
		$db = $this->_getDb();

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT admin_log.*, user.username
				FROM xf_admin_log AS admin_log
				INNER JOIN xf_user AS user ON (user.user_id = admin_log.user_id)
				WHERE ' . ($userId ? 'admin_log.user_id = ' . $db->quote($userId) : '1=1') . '
				ORDER BY admin_log.request_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'admin_log_id');
	}

	public function countAdminLogEntries($userId = 0)
	{
		$db = $this->_getDb();

		return $db->fetchOne('
			SELECT COUNT(*)
			FROM xf_admin_log
			WHERE ' . ($userId ? 'user_id = ' . $db->quote($userId) : '1=1')
		);
	}

	public function pruneAdminLogEntries($pruneDate = null)
	{
		if ($pruneDate === null)
		{
			$pruneDate = XenForo_Application::$time - 86400 * XenForo_Application::get('config')->adminLogLength;
			if (!XenForo_Application::get('config')->adminLogLength)
			{
				return;
			}
		}

		$this->_getDb()->query('
			DELETE FROM xf_admin_log
			WHERE request_date <= ?
		', $pruneDate);
	}

	public function getAdminLogById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT admin_log.*, user.username
			FROM xf_admin_log AS admin_log
			INNER JOIN xf_user AS user ON (user.user_id = admin_log.user_id)
			WHERE admin_log.admin_log_id = ?
		', $id);
	}

	public function getUsersWithAdminLogs()
	{
		$userIds = $this->_getDb()->fetchCol('
			SELECT DISTINCT user_id
			FROM xf_admin_log
		');
		if (!$userIds)
		{
			return array();
		}

		return $this->getModelFromCache('XenForo_Model_User')->getUsersByIds($userIds);
	}

	public function prepareAdminLogEntries(array $entries)
	{
		foreach ($entries AS &$entry)
		{
			$entry = $this->prepareAdminLogEntry($entry);
		}

		return $entries;
	}

	public function prepareAdminLogEntry(array $entry)
	{
		$entry['ipAddress'] = ($entry['ip_address'] ? XenForo_Helper_Ip::convertIpBinaryToString($entry['ip_address']) : '');

		return $entry;
	}

	public static function logModeratorAction(
		$contentType, array $content, $action, array $actionParams = array(),
		$parentContent = null, array $logUser = null
	)
	{
		return XenForo_Model::create(__CLASS__)->logModeratorActionLocal(
			$contentType, $content, $action, $actionParams, $parentContent, $logUser
		);
	}

	public function logModeratorActionLocal(
		$contentType, array $content, $action, array $actionParams = array(),
		$parentContent = null, array $logUser = null
	)
	{
		$handler = $this->getModeratorLogHandler($contentType);
		if (!$handler)
		{
			return false;
		}

		return $handler->log($content, $action, $actionParams, $parentContent, $logUser);
	}

	/**
	 * Gets the mod log handler for a specific type of content.
	 *
	 * @param string $contentType
	 *
	 * @return XenForo_ModeratorLogHandler_Abstract|false
	 */
	public function getModeratorLogHandler($contentType)
	{
		$handlerClass = $this->getContentTypeField($contentType, 'moderator_log_handler_class');
		if (!$handlerClass || !class_exists($handlerClass))
		{
			return false;
		}

		$handlerClass = XenForo_Application::resolveDynamicClass($handlerClass);
		return new $handlerClass();
	}

	/**
	 * Gets the mod log handlers for all content types.
	 *
	 * @return array Array of XenForo_ModerationLogHandler_Abstract objects
	 */
	public function getModeratorLogHandlers()
	{
		$handlerClasses = $this->getContentTypesWithField('moderator_log_handler_class');
		$handlers = array();
		foreach ($handlerClasses AS $contentType => $handlerClass)
		{
			if (!class_exists($handlerClass))
			{
				continue;
			}

			$handlerClass = XenForo_Application::resolveDynamicClass($handlerClass);
			$handlers[$contentType] = new $handlerClass();
		}

		return $handlers;
	}

	public function getModeratorLogForDiscussionContent($discussionType, $discussionId)
	{
		return $this->fetchAllKeyed('
			SELECT moderator_log.*, user.username
			FROM xf_moderator_log AS moderator_log
			INNER JOIN xf_user AS user ON (user.user_id = moderator_log.user_id)
			WHERE moderator_log.discussion_content_type = ?
				AND moderator_log.discussion_content_id = ?
			ORDER BY moderator_log.log_date DESC
		', 'moderator_log_id', array($discussionType, $discussionId));
	}

	public function getModeratorLogEntries($userId = 0, array $fetchOptions = array())
	{
		$db = $this->_getDb();

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT moderator_log.*, user.username
				FROM xf_moderator_log AS moderator_log
				INNER JOIN xf_user AS user ON (user.user_id = moderator_log.user_id)
				WHERE ' . ($userId ? 'moderator_log.user_id = ' . $db->quote($userId) : '1=1') . '
				ORDER BY moderator_log.log_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'moderator_log_id');
	}

	public function countModeratorLogEntries($userId = 0)
	{
		$db = $this->_getDb();

		return $db->fetchOne('
			SELECT COUNT(*)
			FROM xf_moderator_log
			WHERE ' . ($userId ? 'user_id = ' . $db->quote($userId) : '1=1')
		);
	}

	public function pruneModeratorLogEntries($pruneDate = null)
	{
		if ($pruneDate === null)
		{
			$logLength = XenForo_Application::get('options')->moderatorLogLength;
			if (!$logLength)
			{
				return;
			}

			$pruneDate = XenForo_Application::$time - 86400 * $logLength;
		}

		$this->_getDb()->query('
			DELETE FROM xf_moderator_log
			WHERE log_date <= ?
		', $pruneDate);
	}

	public function prepareModeratorLogEntries(array $entries)
	{
		$handlers = $this->getModeratorLogHandlers();

		foreach ($entries AS $key => &$entry)
		{
			if (isset($handlers[$entry['content_type']]))
			{
				$entry = $handlers[$entry['content_type']]->prepareEntry($entry);
			}
			else
			{
				unset($entries[$key]);
			}
		}

		return $entries;
	}

	public function prepareModeratorLogEntry(array $entry)
	{
		$handler = $this->getModeratorLogHandler($entry['content_type']);
		if ($handler)
		{
			$entry = $handler->prepareEntry($entry);
		}

		return $entry;
	}

	public function getModeratorLogById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT moderator_log.*, user.username
			FROM xf_moderator_log AS moderator_log
			INNER JOIN xf_user AS user ON (user.user_id = moderator_log.user_id)
			WHERE moderator_log.moderator_log_id = ?
		', $id);
	}

	public function getUsersWithModeratorLogs()
	{
		$userIds = $this->_getDb()->fetchCol('
			SELECT DISTINCT user_id
			FROM xf_moderator_log
		');
		if (!$userIds)
		{
			return array();
		}

		return $this->getModelFromCache('XenForo_Model_User')->getUsersByIds($userIds);
	}
}