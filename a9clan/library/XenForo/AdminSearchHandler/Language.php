<?php

class XenForo_AdminSearchHandler_Language extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_languages';
	}

	public function getPhraseKey()
	{
		return 'languages';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $languageModel XenForo_Model_Language */
		$languageModel = $this->getModelFromCache('XenForo_Model_Language');

		return $languageModel->getLanguagesForAdminQuickSearch($searchText);
	}

	public function getAdminPermission()
	{
		return 'language';
	}
}