<?php

class XenForo_AdminSearchHandler_UserUpgrade extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_user_upgrades';
	}

	public function getPhraseKey()
	{
		return 'user_upgrades';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $upgradeModel XenForo_Model_UserUpgrade */
		$upgradeModel = $this->getModelFromCache('XenForo_Model_UserUpgrade');

		return $upgradeModel->getUserUpgradesForAdminQuickSearch($searchText);
	}

	public function getAdminPermission()
	{
		return 'userUpgrade';
	}
}