<?php

abstract class XenForo_ModeratorLogHandler_Abstract
{
	protected $_skipLogSelfActions = array();

	abstract protected function _log(array $logUser, array $content, $action, array $actionParams = array(), $parentContent = null);

	public function isLoggable(array $logUser, array $content, $action)
	{
		if (isset($content['user_id']) && $content['user_id'] == $logUser['user_id']
			&& in_array($action, $this->_skipLogSelfActions))
		{
			return false;
		}

		return true;
	}

	public function log(array $content, $action, array $actionParams = array(), $parentContent = null, array $logUser = null)
	{
		if ($logUser === null)
		{
			$logUser = XenForo_Visitor::getInstance()->toArray();
		}

		if (!$logUser['user_id'] || !$logUser['is_moderator'] || !$this->isLoggable($logUser, $content, $action))
		{
			return false;
		}

		return $this->_log($logUser, $content, $action, $actionParams, $parentContent);
	}

	final public function prepareEntry(array $entry)
	{
		$entry['content_title'] = XenForo_Helper_String::censorString($entry['content_title']);
		$entry['ipAddress'] = ($entry['ip_address'] ? XenForo_Helper_Ip::convertIpBinaryToString($entry['ip_address']) : '');

		$entry = $this->_prepareEntry($entry);

		if (!isset($entry['actionText']))
		{
			$entry['actionText'] = new XenForo_Phrase(
				'moderator_log_' . $entry['content_type'] . '_' . $entry['action'],
				json_decode($entry['action_params'], true)
			);
		}

		if (!isset($entry['actionText']))
		{
			$entry['contentUser'] = array(
				'user_id' => $entry['content_user_id'],
				'username' => $entry['content_username']
			);
		}

		return $entry;
	}

	protected function _prepareEntry(array $entry)
	{
		return $entry;
	}
}