<?php

class XenForo_AdminSearchHandler_Phrase extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_phrases';
	}

	public function getPhraseKey()
	{
		return 'phrases';
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
		/* @var $languageModel XenForo_Model_Language */
		$languageModel = $this->getModelFromCache('XenForo_Model_Language');

		$languageId = $languageModel->getLanguageIdFromCookie();

		return $view->createTemplateObject(
			$this->_getTemplateName(),
			array('results' => $this->_limitResults($results), 'languageId' => ($languageId ? $languageId : false))
		);
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $phraseModel XenForo_Model_Phrase */
		$phraseModel = $this->getModelFromCache('XenForo_Model_Phrase');

		/* @var $languageModel XenForo_Model_Language */
		$languageModel = $this->getModelFromCache('XenForo_Model_Language');

		return $phraseModel->getEffectivePhraseListForLanguage(
			$languageModel->getLanguageIdFromCookie(),
			array('title' => $searchText)
		);
	}

	public function getAdminPermission()
	{
		return 'language';
	}
}