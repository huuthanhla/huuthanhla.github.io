<?php

class XenForo_AdminSearchHandler_User extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_users';
	}

	public function getPhraseKey()
	{
		return 'users';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');

		return $userModel->getUsers(array('adminQuickSearch' => $searchText));
	}

	public function getAdminPermission()
	{
		return 'user';
	}
}