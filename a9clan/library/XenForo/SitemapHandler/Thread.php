<?php

class XenForo_SitemapHandler_Thread extends XenForo_SitemapHandler_Abstract
{
	protected $_threadModel;

	public function getRecords($previousLast, $limit, array $viewingUser)
	{
		$threadModel = $this->_getThreadModel();
		$ids = $threadModel->getThreadIdsInRange($previousLast, $limit);

		$threads = $threadModel->getThreadsByIds($ids, array(
			'join' => XenForo_Model_Thread::FETCH_FORUM,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));
		ksort($threads);

		return $threadModel->unserializePermissionsInList($threads, 'node_permission_cache');
	}

	public function isIncluded(array $entry, array $viewingUser)
	{
		if ($entry['discussion_type'] == 'redirect')
		{
			return false;
		}

		return $this->_getThreadModel()->canViewThreadAndContainer(
			$entry, $entry, $null, $entry['permissions'], $viewingUser
		);
	}

	public function getData(array $entry)
	{
		$entry['title'] = XenForo_Helper_String::censorString($entry['title']);

		return array(
			'loc' => XenForo_Link::buildPublicLink('canonical:threads', $entry),
			'lastmod' => $entry['last_post_date']
		);
	}

	public function isInterruptable()
	{
		return true;
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