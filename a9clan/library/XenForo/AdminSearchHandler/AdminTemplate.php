<?php

class XenForo_AdminSearchHandler_AdminTemplate extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_admin_templates';
	}

	public function getPhraseKey()
	{
		return 'admin_templates';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		if (!XenForo_Application::debugMode())
		{
			return array();
		}

		/* @var $templateModel XenForo_Model_AdminTemplate */
		$templateModel = $this->getModelFromCache('XenForo_Model_AdminTemplate');

		return $templateModel->getAdminTemplatesForAdminQuickSearch($searchText);
	}

	public function getAdminPermission()
	{
		return 'dev';
	}
}