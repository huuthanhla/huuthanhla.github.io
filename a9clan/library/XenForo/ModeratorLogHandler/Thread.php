<?php

class XenForo_ModeratorLogHandler_Thread extends XenForo_ModeratorLogHandler_Abstract
{
	protected function _log(array $logUser, array $content, $action, array $actionParams = array(), $parentContent = null)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorLog');
		$dw->bulkSet(array(
			'user_id' => $logUser['user_id'],
			'content_type' => 'thread',
			'content_id' => $content['thread_id'],
			'content_user_id' => $content['user_id'],
			'content_username' => $content['username'],
			'content_title' => $content['title'],
			'content_url' => XenForo_Link::buildPublicLink('threads', $content),
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
		$elements = json_decode($entry['action_params'], true);

		if ($entry['action'] == 'edit')
		{
			$entry['actionText'] = new XenForo_Phrase(
				'moderator_log_thread_edit',
				array('elements' => implode(', ', array_keys($elements)))
			);
		}

		return $entry;
	}
}