<?php

class XenForo_SitemapHandler_Node extends XenForo_SitemapHandler_Abstract
{
	protected $_nodeModel;

	public function getRecords($previousLast, $limit, array $viewingUser)
	{
		if ($previousLast)
		{
			return array();
		}

		$nodeModel = $this->_getNodeModel();

		$permissions = $nodeModel->getNodePermissionsForPermissionCombination($viewingUser['permission_combination_id']);
		$nodes = $nodeModel->getViewableNodeList($permissions);

		return $nodes;
	}

	public function isIncluded(array $entry, array $viewingUser)
	{
		if ($entry['node_type_id'] == 'Category'
			&& $entry['depth'] == 0
			&& !XenForo_Application::getOptions()->categoryOwnPage
		)
		{
			// don't include categories that are just anchors on the forum list
			return false;
		}

		// already filtered for permissions
		return true;
	}

	public function getData(array $entry)
	{
		$nodeTypes = $this->_getNodeModel()->getAllNodeTypes();
		if (!isset($nodeTypes[$entry['node_type_id']]))
		{
			return null;
		}

		return array(
			'loc' => XenForo_Link::buildPublicLink('canonical:' . $nodeTypes[$entry['node_type_id']]['public_route_prefix'], $entry),
		);
	}

	public function isInterruptable()
	{
		return false;
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		if (!$this->_nodeModel)
		{
			$this->_nodeModel = XenForo_Model::create('XenForo_Model_Node');
		}

		return $this->_nodeModel;
	}
}