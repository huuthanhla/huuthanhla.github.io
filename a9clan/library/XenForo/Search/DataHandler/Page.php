<?php

/**
 * Handles searching of pages.
 *
 * @package XenForo_Search
 */
class XenForo_Search_DataHandler_Page extends XenForo_Search_DataHandler_Abstract
{
	/**
	 * @var XenForo_Model_Page
	 */
	protected $_pageModel = null;

	/**
	 * Inserts into (or replaces a record) in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_insertIntoIndex()
	 */
	protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
	{
		$metadata = array(
			'node' => $data['node_id']
		);

		if (!isset($data['content']))
		{
			$data['content'] = $this->_getPageModel()->getPageContent($data['node_id']);
		}

		$indexer->insertIntoIndex(
			'page', $data['node_id'],
			$data['title'], strip_tags($data['description'] . ' ' . $data['content']),
			$data['publish_date'], 0, 0, $metadata
		);
	}

	/**
	 * Updates a record in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_updateIndex()
	 */
	protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
	{
		$indexer->updateIndex('page', $data['node_id'], $fieldUpdates);
	}

	/**
	 * Deletes one or more records from the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_deleteFromIndex()
	 */
	protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
	{
		$ids = array();
		foreach ($dataList AS $data)
		{
			$ids[] = is_array($data) ? $data['node_id'] : $data;
		}

		$indexer->deleteFromIndex('page', $ids);
	}

	/**
	 * Rebuilds the index for a batch.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::rebuildIndex()
	 */
	public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
	{
		$pageIds = $this->_getPageModel()->getPageIdsInRange($lastId, $batchSize);
		if (!$pageIds)
		{
			return false;
		}

		$this->quickIndex($indexer, $pageIds);

		return max($pageIds);
	}

	/**
	 * Rebuilds the index for the specified content.

	 * @see XenForo_Search_DataHandler_Abstract::quickIndex()
	 */
	public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
	{
		$pages = $this->_getPageModel()->getPagesByIds($contentIds, array(
			'join' => XenForo_Model_Page::FETCH_TEMPLATE
		));
		foreach ($pages AS $page)
		{
			$this->insertIntoIndex($indexer, $page);
		}

		return true;
	}

	/**
	 * Gets the type-specific data for a collection of results of this content type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getDataForResults()
	 */
	public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
	{
		$pageModel = $this->_getPageModel();

		$pages = $pageModel->getPagesByIds($ids, array(
			'join' => XenForo_Model_Page::FETCH_TEMPLATE,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));

		return $pageModel->unserializePermissionsInList($pages, 'node_permission_cache');
	}

	/**
	 * Determines if this result is viewable.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::canViewResult()
	 */
	public function canViewResult(array $result, array $viewingUser)
	{
		return $this->_getPageModel()->canViewPage(
			$result, $null, $result['permissions'], $viewingUser
		);
	}

	/**
	 * Prepares a result for display.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::prepareResult()
	 */
	public function prepareResult(array $result, array $viewingUser)
	{
		return $result;
	}

	/**
	 * Gets the date of the result (from the result's content).
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getResultDate()
	 */
	public function getResultDate(array $result)
	{
		return $result['publish_date'];
	}

	/**
	 * Renders a result to HTML.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::renderResult()
	 */
	public function renderResult(XenForo_View $view, array $result, array $search)
	{
		return $view->createTemplateObject('search_result_page', array(
			'page' => $result,
			'search' => $search
		));
	}

	/**
	 * Gets the content types searched in a type-specific search.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getSearchContentTypes()
	 */
	public function getSearchContentTypes()
	{
		return array('page');
	}

	/**
	 * @return XenForo_Model_Page
	 */
	protected function _getPageModel()
	{
		if (!$this->_pageModel)
		{
			$this->_pageModel = XenForo_Model::create('XenForo_Model_Page');
		}

		return $this->_pageModel;
	}
}