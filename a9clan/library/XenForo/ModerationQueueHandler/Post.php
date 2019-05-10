<?php

/**
 * Moderation queue handler for posts.
 *
 * @package XenForo_Moderation
 */
class XenForo_ModerationQueueHandler_Post extends XenForo_ModerationQueueHandler_Abstract
{
	/**
	 * Gets visible moderation queue entries for specified user.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::getVisibleModerationQueueEntriesForUser()
	 */
	public function getVisibleModerationQueueEntriesForUser(array $contentIds, array $viewingUser)
	{
		/* @var $postModel XenForo_Model_Post */
		$postModel = XenForo_Model::create('XenForo_Model_Post');
		$posts = $postModel->getPostsByIds($contentIds, array(
			'join' => XenForo_Model_Post::FETCH_THREAD | XenForo_Model_Post::FETCH_FORUM,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));

		$output = array();
		foreach ($posts AS $post)
		{
			$post['permissions'] = XenForo_Permission::unserializePermissions($post['node_permission_cache']);

			$canManage = true;
			if (!$postModel->canViewPostAndContainer(
				$post, $post, $post, $null, $post['permissions'], $viewingUser
			))
			{
				$canManage = false;
			}
			else if (!XenForo_Permission::hasContentPermission($post['permissions'], 'editAnyPost')
				|| !XenForo_Permission::hasContentPermission($post['permissions'], 'deleteAnyPost')
			)
			{
				$canManage = false;
			}

			if ($canManage)
			{
				$output[$post['post_id']] = array(
					'message' => $post['message'],
					'user' => array(
						'user_id' => $post['user_id'],
						'username' => $post['username']
					),
					'title' => new XenForo_Phrase('post_in_thread_x', array('title' => $post['title'])),
					'link' => XenForo_Link::buildPublicLink('posts', $post),
					'contentTypeTitle' => new XenForo_Phrase('post'),
					'titleEdit' => false
				);
			}
		}

		return $output;
	}

	/**
	 * Approves the specified moderation queue entry.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::approveModerationQueueEntry()
	 */
	public function approveModerationQueueEntry($contentId, $message, $title)
	{
		$message = XenForo_Helper_String::autoLinkBbCode($message);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('message_state', 'visible');
		$dw->set('message', $message);

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction('post', $dw->getMergedData(), 'approve');
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Deletes the specified moderation queue entry.
	 *
	 * @see XenForo_ModerationQueueHandler_Abstract::deleteModerationQueueEntry()
	 */
	public function deleteModerationQueueEntry($contentId)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($contentId);
		$dw->set('message_state', 'deleted');

		if ($dw->save())
		{
			XenForo_Model_Log::logModeratorAction('post', $dw->getMergedData(), 'delete_soft', array('reason' => ''));
			return true;
		}
		else
		{
			return false;
		}
	}
}