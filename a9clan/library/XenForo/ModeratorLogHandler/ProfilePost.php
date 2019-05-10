<?php

class XenForo_ModeratorLogHandler_ProfilePost extends XenForo_ModeratorLogHandler_Abstract
{
	protected $_skipLogSelfActions = array(
		'edit'
	);

	public function isLoggable(array $logUser, array $content, $action)
	{
		if ($logUser['user_id'] == $content['profile_user_id'])
		{
			return false;
		}

		return parent::isLoggable($logUser, $content, $action);
	}

	protected function _log(array $logUser, array $content, $action, array $actionParams = array(), $parentContent = null)
	{
		if (is_array($parentContent) && isset($parentContent['username']))
		{
			$contentTitle = $parentContent['username'];
		}
		else
		{
			$user = XenForo_Model::create('XenForo_Model_User')->getUserById($content['profile_user_id']);
			$contentTitle = ($user ? $user['username'] : '');
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorLog');
		$dw->bulkSet(array(
			'user_id' => $logUser['user_id'],
			'content_type' => 'profile_post',
			'content_id' => $content['profile_post_id'],
			'content_user_id' => $content['user_id'],
			'content_username' => $content['username'],
			'content_title' => $contentTitle,
			'content_url' => XenForo_Link::buildPublicLink('profile-posts', $content),
			'discussion_content_type' => 'user',
			'discussion_content_id' => $content['profile_user_id'],
			'action' => $action,
			'action_params' => $actionParams
		));
		$dw->save();

		return $dw->get('moderator_log_id');
	}

	protected function _prepareEntry(array $entry)
	{
		// will be escaped in template
		$entry['content_title'] = new XenForo_Phrase('profile_post_for_x', array('username' => $entry['content_title']));

		return $entry;
	}
}