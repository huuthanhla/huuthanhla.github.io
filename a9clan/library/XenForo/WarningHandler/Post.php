<?php

class XenForo_WarningHandler_Post extends XenForo_WarningHandler_Abstract
{
	protected function _canView(array $content, array $viewingUser)
	{
		return $this->_getPostModel()->canViewPostAndContainer(
			$content, $content, $content, $null, $content['permissions'], $viewingUser
		);
	}

	protected function _canWarn($userId, array $content, array $viewingUser)
	{
		return $this->_getPostModel()->canWarnPost(
			$content, $content, $content, $null, $content['permissions'], $viewingUser
		);
	}

	protected function _canDeleteContent(array $content, array $viewingUser)
	{
		return $this->_getPostModel()->canDeletePost(
			$content, $content, $content, 'soft', $null, $content['permissions'], $viewingUser
		);
	}

	protected function _getContent(array $contentIds, array $viewingUser)
	{
		$postModel = $this->_getPostModel();

		$posts = $postModel->getPostsByIds($contentIds, array(
			'join' => XenForo_Model_Post::FETCH_USER | XenForo_Model_Post::FETCH_THREAD | XenForo_Model_Post::FETCH_FORUM,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));
		return $postModel->unserializePermissionsInList($posts, 'node_permission_cache');
	}

	public function getContentTitle(array $content)
	{
		return $content['title'];
	}

	public function getContentDetails(array $content)
	{
		return $content['message'];
	}

	public function getContentUrl(array $content, $canonical = false)
	{
		return XenForo_Link::buildPublicLink(($canonical ? 'canonical:' : '') . 'posts', $content);
	}

	public function getContentTitleForDisplay($title)
	{
		// will be escaped in template
		return new XenForo_Phrase('post_in_thread_x', array('title' => $title), false);
	}

	protected function _warn(array $warning, array $content, $publicMessage, array $viewingUser)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData($content))
		{
			$dw->set('warning_id', $warning['warning_id']);
			$dw->set('warning_message', $publicMessage);
			$dw->save();
		}
	}

	protected function _reverseWarning(array $warning, array $content)
	{
		if ($content)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($content))
			{
				$dw->set('warning_id', 0);
				$dw->set('warning_message', '');
				$dw->save();
			}
		}
	}

	protected function _deleteContent(array $content, $reason, array $viewingUser)
	{
		$this->_getPostModel()->deletePost($content['post_id'], 'soft', array('reason' => $reason));

		XenForo_Model_Log::logModeratorAction('post', $content, 'delete_soft', array(
			'reason' => $reason), $content);

		XenForo_Helper_Cookie::clearIdFromCookie($content['post_id'], 'inlinemod_posts');
	}

	public function canPubliclyDisplayWarning()
	{
		return true;
	}

	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return XenForo_Model::create('XenForo_Model_Post');
	}
}