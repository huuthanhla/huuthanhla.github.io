<?php

class XenForo_ModeratorLogHandler_Post extends XenForo_ModeratorLogHandler_Abstract
{
	protected $_skipLogSelfActions = array(
		'edit'
	);

	protected function _log(array $logUser, array $content, $action, array $actionParams = array(), $parentContent = null)
	{
		if (isset($content['title']))
		{
			$title = $content['title'];
		}
		else if (is_array($parentContent) && isset($parentContent['title']))
		{
			$title = $parentContent['title'];
		}
		else
		{
			$thread = XenForo_Model::create('XenForo_Model_Thread')->getThreadById($content['thread_id']);
			$title = ($thread ? $thread['title'] : '');
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorLog');
		$dw->bulkSet(array(
			'user_id' => $logUser['user_id'],
			'content_type' => 'post',
			'content_id' => $content['post_id'],
			'content_user_id' => $content['user_id'],
			'content_username' => $content['username'],
			'content_title' => $title,
			'content_url' => XenForo_Link::buildPublicLink('posts', $content),
			'discussion_content_type' => 'thread',
			'discussion_content_id' => $content['thread_id'],
			'action' => $action,
			'action_params' => $actionParams
		));
		$dw->save();

		return $dw->get('moderator_log_id');
	}

	protected function _prepareEntry(array $entry)
	{
		// will be escaped in template
		$entry['content_title'] = new XenForo_Phrase('post_in_thread_x', array('title' => $entry['content_title']));

		return $entry;
	}
}