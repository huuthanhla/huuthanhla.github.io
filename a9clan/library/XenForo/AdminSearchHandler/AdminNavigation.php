<?php

class XenForo_AdminSearchHandler_AdminNavigation extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_admin_navigation';
	}

	public function getPhraseKey()
	{
		return 'admin_navigation';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		if ($phraseMatches)
		{
			/* @var $adminNavigationModel XenForo_Model_AdminNavigation */
			$adminNavigationModel = $this->getModelFromCache('XenForo_Model_AdminNavigation');

			if ($navEntries = $adminNavigationModel->getAdminNavigationEntriesByIds($phraseMatches))
			{
				$navEntries = $this->_removeUnlinkedEntries($navEntries);

				return $adminNavigationModel->prepareAdminNavigationEntries($navEntries);
			}
		}

		return array();
	}

	public function getAdminPermission()
	{
		return 'dev';
	}

	protected function _removeUnlinkedEntries(array $navEntries)
	{
		foreach ($navEntries AS $navId => $navEntry)
		{
			if ($navEntry['link'] == ''
				|| ($navEntry['debug_only'] && !XenForo_Application::debugMode())
			)
			{
				unset($navEntries[$navId]);
			}
		}

		return $navEntries;
	}

	public function getPhraseConditions()
	{
		return array(
			'like' => XenForo_Db::quoteLike('admin_navigation_', 'r'),
			'regex' => '/^admin_navigation_(.*)$/'
		);
	}

	protected function _limitResults(array $results)
	{
		// don't cut this one off
		return $results;
	}
}