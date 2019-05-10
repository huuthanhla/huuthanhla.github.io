<?php

class XenForo_ViewPublic_Watched_Forums extends XenForo_ViewPublic_Base
{
	/**
	 * Renders the HTML page.
	 *
	 * @return mixed
	 */
	public function renderHtml()
	{
		$nodeList = $this->_params['nodeList'];

		if (!$nodeList)
		{
			$this->_params['subForums'] = array();
			$this->_params['forums'] = array();
			return;
		}

		$nodeParents = $nodeList['nodeParents'];
		$nodesGrouped = $nodeList['nodesGrouped'];
		$nodePermissions = $nodeList['nodePermissions'];
		$nodeHandlers = $nodeList['nodeHandlers'];

		$subForums = array();
		$forums = array();

		foreach ($nodeParents AS $nodeId => $parentId)
		{
			if (!isset($this->_params['forumsWatched'][$nodeId]))
			{
				continue;
			}

			$node = $nodesGrouped[$parentId][$nodeId];

			$renderedChildren = XenForo_ViewPublic_Helper_Node::renderNodeTree(
				$this, $node['node_id'], $nodesGrouped, $nodePermissions, $nodeHandlers, 3
			);
			$subForums[$node['node_id']] = $renderedChildren;
			$forums[$node['node_id']] = $node;
		}

		$this->_params['subForums'] = $subForums;
		$this->_params['forums'] = $forums;
	}
}