<?php

class XenForo_AlertHandler_Thread extends XenForo_AlertHandler_DiscussionMessage
{
	/**
	 * @var XenForo_Model_Thread
	 */
	protected $_threadModel = null;

	/**
	 * Gets the post content.
	 * @see XenForo_AlertHandler_Abstract::getContentByIds()
	 */
	public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
	{
		$threadModel = $this->_getThreadModel();

		$threads = $threadModel->getThreadsByIds($contentIds, array(
			'join' => XenForo_Model_Thread::FETCH_FORUM,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));
		return $threadModel->unserializePermissionsInList($threads, 'node_permission_cache');
	}

	/**
	 * Determines if the post is viewable.
	 * @see XenForo_AlertHandler_Abstract::canViewAlert()
	 */
	public function canViewAlert(array $alert, $content, array $viewingUser)
	{
		return $this->_getThreadModel()->canViewThreadAndContainer(
			$content, $content, $null, $content['permissions'], $viewingUser
		);
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		if (!$this->_threadModel)
		{
			$this->_threadModel = XenForo_Model::create('XenForo_Model_Thread');
		}

		return $this->_threadModel;
	}
}