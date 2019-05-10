<?php

class XenForo_AdminSearchHandler_Style extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_styles';
	}

	public function getPhraseKey()
	{
		return 'styles';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $styleModel XenForo_Model_Style */
		$styleModel = $this->getModelFromCache('XenForo_Model_Style');

		return $styleModel->getStylesForAdminQuickSearch($searchText);
	}

	public function getAdminPermission()
	{
		return 'style';
	}
}