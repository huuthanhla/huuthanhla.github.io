<?php

class XenForo_Model_Sitemap extends XenForo_Model
{
	public function insertPendingSitemap($id, $fileCount, $entryCount)
	{
		$this->_getDb()->query("
			INSERT INTO xf_sitemap
				(sitemap_id, is_active, file_count, entry_count, is_compressed, complete_date)
			VALUES
				(?, 0, ?, ?, 0, NULL)
			ON DUPLICATE KEY UPDATE
				is_active = VALUES(is_active),
				file_count = VALUES(file_count),
				entry_count = VALUES(entry_count),
				is_compressed = VALUES(is_compressed),
				complete_date = VALUES(complete_date)
		", array($id, $fileCount, $entryCount));
	}

	public function completeSitemap($id, $isCompressed, $fileCount, $entryCount)
	{
		$res = $this->_getDb()->query("
			UPDATE xf_sitemap
			SET is_active = 1,
				is_compressed = ?,
				file_count = ?,
				entry_count = ?,
				complete_date = ?
			WHERE sitemap_id = ?
		", array(
			$isCompressed ? 1 : 0,
			$fileCount,
			$entryCount,
			XenForo_Application::$time,
			$id
		));

		return $res->rowCount() ? true : false;
	}

	public function getCurrentSitemap()
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM xf_sitemap
			WHERE is_active = 1
			ORDER BY sitemap_id DESC
			LIMIT 1
		");
	}

	public function getSitemapHistory()
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM xf_sitemap
			ORDER BY sitemap_id DESC
		", 'sitemap_id');
	}

	public function cleanUpOldSitemaps($skipId = null)
	{
		$sitemaps = $this->fetchAllKeyed("
			SELECT *
			FROM xf_sitemap
			WHERE is_active = 1
			ORDER BY sitemap_id
		", 'sitemap_id');
		if ($skipId)
		{
			unset($sitemaps[$skipId]);
		}

		foreach ($sitemaps AS $sitemap)
		{
			$this->cleanUpSitemap($sitemap);
		}

		return $sitemaps;
	}

	public function cleanUpSitemap(array $sitemap)
	{
		$this->_getDb()->query("
			UPDATE xf_sitemap
			SET is_active = 0
			WHERE sitemap_id = ?
		", $sitemap['sitemap_id']);

		for ($i = 1; $i <= $sitemap['file_count']; $i++)
		{
			$fileName = $this->getSitemapFileName($sitemap['sitemap_id'], $i);
			@unlink($fileName);

			$compressedFileName = $this->getSitemapFileName($sitemap['sitemap_id'], $i, true);
			@unlink($compressedFileName);
		}
	}

	public function deleteOldSitemapLogs($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = XenForo_Application::$time - 86400 * 30;
		}

		$res = $this->_getDb()->query("
			DELETE
			FROM xf_sitemap
			WHERE sitemap_id < ?
				AND is_active = 0
		", $cutOff);
		return $res->rowCount();
	}

	public function getSitemapContentTypes($includeDisabled = false)
	{
		$types = array('core' => 'XenForo_SitemapHandler_Core');
		$excluded = XenForo_Application::getOptions()->sitemapExclude;

		foreach ($this->getContentTypesWithField('sitemap_handler_class') AS $type => $class)
		{
			if (empty($excluded[$type]) || $includeDisabled)
			{
				if (!class_exists($class))
				{
					continue;
				}

				$types[$type] = $class;
			}
		}

		return $types;
	}

	public function getSitemapFileName($setId, $counter, $compressed = false)
	{
		$path = XenForo_Helper_File::getInternalDataPath() . '/sitemaps';
		if (!XenForo_Helper_File::createDirectory($path, true))
		{
			throw new XenForo_Exception("Sitemap directory $path could not be created");
		}

		return "$path/sitemap-{$setId}-{$counter}.xml" . ($compressed ? '.gz' : '');
	}

	public function compressSitemapFile($setId, $counter)
	{
		$readFileName = $this->getSitemapFileName($setId, $counter);
		if (!file_exists($readFileName))
		{
			return false;
		}

		$readFile = fopen($readFileName, 'rb');

		$compressedFileName = $this->getSitemapFileName($setId, $counter, true);
		$compressedFile = gzopen($compressedFileName, 'wb1');

		$blockSize = 512 * 1024;

		while (!feof($readFile))
		{
			gzwrite($compressedFile, fread($readFile, $blockSize));
		}

		fclose($readFile);
		gzclose($compressedFile);

		unlink($readFileName);

		return $compressedFileName;
	}

	public function getSitemapPreamble()
	{
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
			. ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
	}

	public function getSitemapSuffix()
	{
		return '</urlset>';
	}

	public function buildSitemapEntry(array $result)
	{
		$content = '<url>'
			. '<loc>' . htmlspecialchars($result['loc'], ENT_QUOTES, 'UTF-8') . '</loc>';
		if (!empty($result['lastmod']))
		{
			$content .= '<lastmod>' . gmdate(DateTime::W3C, $result['lastmod']) . '</lastmod>';
		}
		if (isset($result['priority']))
		{
			$content .= '<priority>' . htmlspecialchars($result['priority'], ENT_QUOTES, 'UTF-8') . '</priority>';
		}
		if (isset($result['changefreq']))
		{
			$content .= '<changefreq>' . htmlspecialchars($result['changefreq'], ENT_QUOTES, 'UTF-8') . '</changefreq>';
		}
		if (!empty($result['image']))
		{
			if (!is_array($result['image']) || isset($result['image']['loc']))
			{
				$result['image'] = array($result['image']);
			}
			foreach ($result['image'] AS $image)
			{
				if (!is_array($image))
				{
					$image = array('loc' => $image);
				}
				$content .= '<image:image>';
				foreach ($image AS $tag => $value)
				{
					$content .= "<image:$tag>" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "</image:$tag>";
				}
				$content .= '</image:image>';
			}
		}
		$content .= '</url>';

		return $content;
	}

	public function buildSitemapIndex(array $sitemap)
	{
		$output = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		$sitemapBase = XenForo_Application::getOptions()->boardUrl . '/sitemap.php?c=';

		for ($i = 1; $i <= $sitemap['file_count']; $i++)
		{
			$url = $sitemapBase . $i;
			$output .= "\t" . '<sitemap><loc>' . htmlspecialchars($url) . '</loc><lastmod>' . gmdate(DateTime::W3C, $sitemap['complete_date']) . '</lastmod></sitemap>' . "\n";
		}

		$output .= '</sitemapindex>';

		return $output;
	}

	protected $_sitemapPingUrls = array(
		'Google' => 'http://www.google.com/webmasters/tools/ping?sitemap=%s',
		'Bing' => 'http://www.bing.com/ping?sitemap=%s'
	);

	public function sendSitemapPing()
	{
		$sitemapUrl = urlencode(XenForo_Application::getOptions()->boardUrl . '/sitemap.php');

		foreach ($this->_sitemapPingUrls AS $serviceName => $pingUrl)
		{
			$url = sprintf($pingUrl, $sitemapUrl);
			$client = XenForo_Helper_Http::getClient($url);
			try
			{
				$client->request('GET');
			}
			catch (Exception $e)
			{
				XenForo_Error::logException($e, false, "Error submitting sitemap to $serviceName: ");
			}
		}
	}
}