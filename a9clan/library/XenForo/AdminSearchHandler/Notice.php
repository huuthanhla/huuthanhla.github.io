<?php

class XenForo_AdminSearchHandler_Notice extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_notices';
	}

	public function getPhraseKey()
	{
		return 'notices';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $noticeModel XenForo_Model_Notice */
		$noticeModel = $this->getModelFromCache('XenForo_Model_Notice');

		return $noticeModel->getNoticesForAdminQuickSearch($searchText);
	}

	public function getAdminPermission()
	{
		return 'notice';
	}
}