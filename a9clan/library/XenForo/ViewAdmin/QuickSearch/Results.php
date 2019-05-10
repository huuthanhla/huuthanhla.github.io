<?php

class XenForo_ViewAdmin_QuickSearch_Results extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		/* @var $adminSearchModel XenForo_Model_AdminSearch */
		$adminSearchModel = XenForo_Model::create('XenForo_Model_AdminSearch');

		$output = array();

		foreach ($this->_params['results'] AS $searchType => $results)
		{
			$output[$searchType] = $adminSearchModel->getHandler($searchType)->renderResults($results, $this);
		}

		$this->_params['output'] = $output;
	}
}