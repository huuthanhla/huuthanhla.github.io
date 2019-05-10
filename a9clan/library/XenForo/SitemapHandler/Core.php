<?php

class XenForo_SitemapHandler_Core extends XenForo_SitemapHandler_Abstract
{
	public function getRecords($previousLast, $limit, array $viewingUser)
	{
		if ($previousLast)
		{
			return array();
		}

		$result = array(
			1 => array(
				'loc' => XenForo_Link::buildPublicLink('canonical:index')
			)
		);

		$canonicalPaths = $this->getCanonicalPaths();
		$extras = preg_split('/\r?\n/', XenForo_Application::getOptions()->sitemapExtraUrls, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($extras AS $extra)
		{
			$url = XenForo_Link::convertUriToAbsoluteUri($extra, true, $canonicalPaths);
			if (strpos($url, $canonicalPaths['fullBasePath']) === 0)
			{
				// right prefix
				$result[] = array('loc' => $url);
			}
		}

		return $result;
	}

	public function basePermissionCheck(array $viewingUser)
	{
		return true;
	}

	public function isIncluded(array $entry, array $viewingUser)
	{
		return true;
	}

	public function getData(array $entry)
	{
		return $entry;
	}

	public function getPhraseKey($key)
	{
		return 'core_master_data';
	}

	public function isInterruptable()
	{
		return false;
	}
}