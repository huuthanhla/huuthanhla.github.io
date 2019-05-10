<?php

class XenForo_AdminSearchHandler_Template extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_templates';
	}

	public function getPhraseKey()
	{
		return 'templates';
	}

	/**
	 * Creates a template object in which to display the search results.
	 *
	 * @param array $results
	 * @param XenForo_View $view
	 *
	 * @return XenForo_Template_Admin
	 */
	public function renderResults($results, XenForo_View $view)
	{
		/* @var $styleModel XenForo_Model_Style */
		$styleModel = $this->getModelFromCache('XenForo_Model_Style');

		$styleId = $styleModel->getStyleIdFromCookie();

		return $view->createTemplateObject(
			$this->_getTemplateName(),
			array('results' => $this->_limitResults($results), 'styleId' => ($styleId ? $styleId : false))
		);
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $templateModel XenForo_Model_Template */
		$templateModel = $this->getModelFromCache('XenForo_Model_Template');

		/* @var $styleModel XenForo_Model_Style */
		$styleModel = $this->getModelFromCache('XenForo_Model_Style');

		return $templateModel->getEffectiveTemplateListForStyle(
			$styleModel->getStyleIdFromCookie(),
			array('title' => $searchText)
		);
	}

	public function getAdminPermission()
	{
		return 'style';
	}
}