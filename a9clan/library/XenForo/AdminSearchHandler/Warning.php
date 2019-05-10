<?php

class XenForo_AdminSearchHandler_Warning extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_warnings';
	}

	public function getPhraseKey()
	{
		return 'warnings';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $warningModel XenForo_Model_Warning */
		$warningModel = $this->getModelFromCache('XenForo_Model_Warning');

		if ($warnings = $warningModel->getWarningDefinitionsForAdminQuickSearch($phraseMatches))
		{
			return $warningModel->prepareWarningDefinitions($warnings);
		}

		return array();
	}

	public function getPhraseConditions()
	{
		return array(
			'like' => XenForo_Db::quoteLike('warning_definition_', 'r'),
			'regex' => '/^warning_definition_(\d+)_title$/U'
		);
	}

	public function getAdminPermission()
	{
		return 'warning';
	}
}