<?php

class XenForo_ControllerAdmin_Search extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		$results = array();

		if ($queryString = $this->_input->filterSingle('q', XenForo_Input::STRING))
		{
			$results = $this->_getAdminSearchModel()->search($queryString);
		}

		$viewParams = array(
			'queryString' => $queryString,
			'results' => $results
		);

		return $this->responseView('XenForo_ViewAdmin_QuickSearch_Results', 'quicksearch_results', $viewParams);
	}

	/**
	 * @return XenForo_Model_AdminSearch
	 */
	protected function _getAdminSearchModel()
	{
		return $this->getModelFromCache('XenForo_Model_AdminSearch');
	}
}