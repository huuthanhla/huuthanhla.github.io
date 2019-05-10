<?php

class XenForo_AdminSearchHandler_Node extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_nodes';
	}

	public function getPhraseKey()
	{
		return 'nodes';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $nodeModel XenForo_Model_Node */
		$nodeModel = $this->getModelFromCache('XenForo_Model_Node');

		return $nodeModel->getNodesForAdminQuickSearch($searchText);
	}

	public function getAdminPermission()
	{
		return 'node';
	}
}