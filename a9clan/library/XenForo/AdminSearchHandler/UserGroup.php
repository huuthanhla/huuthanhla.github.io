<?php

class XenForo_AdminSearchHandler_UserGroup extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_user_groups';
	}

	public function getPhraseKey()
	{
		return 'user_groups';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $userGroupModel XenForo_Model_UserGroup */
		$userGroupModel = $this->getModelFromCache('XenForo_Model_UserGroup');

		return $userGroupModel->getUserGroupsForAdminQuickSearch($searchText);
	}

	public function getAdminPermission()
	{
		return 'userGroup';
	}
}