<?php

class XenForo_AdminSearchHandler_BbCodeMediaSite extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_bb_code_media_site';
	}

	public function getPhraseKey()
	{
		return 'bb_code_media_sites';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $bbCodeModel XenForo_Model_BbCode */
		$bbCodeModel = $this->getModelFromCache('XenForo_Model_BbCode');

		return $bbCodeModel->getBbCodeMediaSitesForAdminQuickSearch($searchText);
	}

	public function getAdminPermission()
	{
		return 'bbCodeSmilie';
	}
}