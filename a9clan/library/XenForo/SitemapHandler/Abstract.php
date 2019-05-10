<?php

abstract class XenForo_SitemapHandler_Abstract
{
	protected $_canonicalPaths;

	/**
	 * Gets the next batch of records to possibly add to the sitemap.
	 * Should start from one beyond the previous last value, if the process is
	 * interruptable. If uninterruptable, must return all records for the type
	 * at once. Otherwise, return an empty array to signal completion.
	 *
	 * @param string|int $previousLast
	 * @param int $limit Maximum number of records to fetch (mostly for memory limits)
	 * @param array $viewingUser Sitemap context user (should always be guest)
	 *
	 * @return array Array of records
	 */
	abstract public function getRecords($previousLast, $limit, array $viewingUser);

	/**
	 * Determine if a particular record should be included in the sitemap.
	 *
	 * @param array $entry
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	abstract public function isIncluded(array $entry, array $viewingUser);

	/**
	 * Gets the sitemap data for an entry. Can either return an array with keys:
	 * 	loc (required, canonical URL), lastmod (unix timestamp, last modification time),
	 *	priority (0.0-1.0, higher value more important), changefreq (daily/weekly/etc value),
	 *  image (array with sub-options for image sitemap)
	 * Or may return an array of multiple such arrays.
	 *
	 * @param array $entry
	 *
	 * @return array
	 */
	abstract public function getData(array $entry);

	/**
	 * Should return true if the process can be interrupted at any record and
	 * picked up from there in another request. Types with potentially
	 * large amounts of content must allow this to be true.
	 *
	 * @return boolean
	 */
	abstract public function isInterruptable();

	/**
	 * Performs the base, global permission check before checking for records. This
	 * can be bypassed on a per-content basis if needed.
	 *
	 * @param array $viewingUser
	 *
	 * @return bool
	 */
	public function basePermissionCheck(array $viewingUser)
	{
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'view');
	}

	/**
	 * Key of the phrase that represents this content type.
	 *
	 * @param string $key Name of content type pointing to this handler
	 *
	 * @return string
	 */
	public function getPhraseKey($key)
	{
		return $key . 's';
	}

	/**
	 * @return array
	 */
	public function getCanonicalPaths()
	{
		if (!$this->_canonicalPaths)
		{
			$url = rtrim(XenForo_Application::getOptions()->boardUrl, '/ ') . '/';
			$parts = parse_url($url);

			$this->_canonicalPaths = array(
				'basePath' => $parts['path'],
				'host' => $parts['host'],
				'protocol' => $parts['scheme'],
				'fullBasePath' => $parts['scheme'] . '://' . $parts['host'] . $parts['path'],
				'requestUri' => $parts['path'],
				'fullUri' => $url
			);
		}

		return $this->_canonicalPaths;
	}
}