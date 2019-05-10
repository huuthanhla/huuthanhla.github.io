<?php

class XenForo_AdminSearchHandler_Feed extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_feeds';
	}

	public function getPhraseKey()
	{
		return 'registered_feeds';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $feedModel XenForo_Model_Feed */
		$feedModel = $this->getModelFromCache('XenForo_Model_Feed');

		return $feedModel->getFeedsForAdminQuickSearch($searchText);
	}

	public function getAdminPermission()
	{
		return 'node';
	}
}