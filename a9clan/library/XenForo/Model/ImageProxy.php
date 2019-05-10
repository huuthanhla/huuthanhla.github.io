<?php

class XenForo_Model_ImageProxy extends XenForo_Model
{
	/**
	 * Fetches image info from the cache, or requests it if it is not available
	 *
	 * @param string $url
	 * @param bool $forceRefresh If true, the image is always refreshed
	 *
	 * @return array
	 *
	 * @throws InvalidArgumentException
	 */
	public function getImage($url, $forceRefresh = false)
	{
		$image = $this->getImageByUrl($url);
		if ($image)
		{
			if ($forceRefresh)
			{
				$image = $this->_fetchAndCacheImage($url, $image);
			}
			else
			{
				$image = $this->refreshImageIfRequired($image);
			}
		}
		else
		{
			$image = $this->_fetchAndCacheImage($url);
		}

		return $image;
	}

	/**
	 * Gets image info for an image known by ID
	 *
	 * @param integer $imageId
	 *
	 * @return array
	 */
	public function getImageById($imageId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_image_proxy
			WHERE image_id = ?
		', $imageId);
	}

	/**
	 * Gets the cached image for a URL
	 *
	 * @param string $url
	 *
	 * @return array
	 */
	public function getImageByUrl($url)
	{
		if (!$url || !preg_match('#^https?://#i', $url))
		{
			throw new InvalidArgumentException('Invalid URL');
		}

		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_image_proxy
			WHERE url_hash = ?
		', md5($url));
	}

	/**
	 * Prepares an image for output
	 *
	 * @param array $image
	 *
	 * @return array
	 */
	public function prepareImage(array $image)
	{
		$image['file_path'] = $this->getImagePath($image);
		$image['use_file'] = file_exists($image['file_path']);
		$image['refreshable'] = $this->_requiresRefetch($image);

		return $image;
	}

	/**
	 * @param array $images
	 * @return array
	 */
	public function prepareImages(array $images)
	{
		foreach ($images AS &$image)
		{
			$image = $this->prepareImage($image);
		}

		return $images;
	}

	/**
	 * Refreshes the image if required
	 *
	 * @param array $image
	 *
	 * @return array
	 */
	public function refreshImageIfRequired(array $image)
	{
		if ($this->_requiresRefetch($image))
		{
			$image = $this->_fetchAndCacheImage($image['url'], $image);
		}

		return $image;
	}

	/**
	 * Logs an image view
	 *
	 * @param array $image
	 *
	 * @return bool
	 */
	public function logImageView(array $image)
	{
		if (empty($image['image_id']))
		{
			return false;
		}

		$this->_getDb()->query('
			UPDATE xf_image_proxy SET
				views = views + 1,
				last_request_date = ?
			WHERE image_id = ?
		', array(XenForo_Application::$time, $image['image_id']));

		return true;
	}

	/**
	 * Determines if a refresh is needed
	 *
	 * @param array $image
	 *
	 * @return bool
	 */
	protected function _requiresRefetch(array $image)
	{
		$filePath = $this->getImagePath($image);

		if ($image['is_processing'] && XenForo_Application::$time - $image['is_processing'] < 5)
		{
			if (file_exists($filePath))
			{
				// likely being refreshed
				return false;
			}

			sleep(5 - (XenForo_Application::$time - $image['is_processing']));

			$newImage = $this->getImageByUrl($image['url']);
			if ($newImage)
			{
				$image = $newImage;
			}
		}

		if ($image['failed_date'] && $image['fail_count'])
		{
			$nextCheck = $this->_failedGetNextCheckDate($image['failed_date'], $image['fail_count']);
			return (XenForo_Application::$time >= $nextCheck);
		}

		if ($image['pruned'])
		{
			return true;
		}

		if (XenForo_Application::getOptions()->imageCacheTTL)
		{
			if ($image['fetch_date'] < XenForo_Application::$time - 86400 * XenForo_Application::getOptions()->imageCacheTTL)
			{
				return true;
			}
		}

		if (!file_exists($filePath))
		{
			return true;
		}

		if (XenForo_Application::getOptions()->imageCacheRefresh && !$image['fail_count'])
		{
			if ($image['fetch_date'] < XenForo_Application::$time - 86400 * XenForo_Application::getOptions()->imageCacheRefresh)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Based on the last failure and the number of consecutive failures, determine
	 * the next time we can refresh a failed image. After 10, we stop trying
	 *
	 * @param integer $failDate Last fail date
	 * @param integer $failCount Total failures
	 *
	 * @return int
	 */
	protected function _failedGetNextCheckDate($failDate, $failCount)
	{
		if (!$failCount)
		{
			// not failed - may need to check now
			return XenForo_Application::$time;
		}

		if ($failCount > 10)
		{
			// too many failures, always in the future
			return XenForo_Application::$time + 86400;
		}

		switch ($failCount)
		{
			case 1: $delay = 60; break; // 1 minute
			case 2: $delay = 5 * 60; break; // 5 minutes
			case 3: $delay = 30 * 60; break; // 30 minutes
			case 4: $delay = 3600; break; // 1 hour
			case 5: $delay = 6 * 3600; break; // 6 hours

			default:
				$delay = ($failCount - 5) * 86400; // 1, 2, 3... days
		}

		return $failDate + $delay;
	}

	/**
	 * Fetches a remote image, stores it in the file system and records it in the database
	 *
	 * @param string $url
	 * @param array|null $image
	 *
	 * @return array
	 */
	protected function _fetchAndCacheImage($url, array $image = null)
	{
		$urlHash = md5($url);
		$time = XenForo_Application::$time;

		if (!$image || empty($image['image_id']))
		{
			$image = array(
				'url' => $url,
				'url_hash' => $urlHash,
				'fetch_date' => $time,
				'file_size' => 0,
				'file_name' => '',
				'mime_type' => '',
				'views' => 0,
				'first_request_date' => $time,
				'last_request_date' => $time,
				'pruned' => 1,
				'failed_date' => 0,
				'fail_count' => 0
			);
		}

		$image['is_processing'] = time(); // intentionally time() as we might have slept

		$db = $this->_getDb();

		if (empty($image['image_id']))
		{
			try
			{
				$db->insert('xf_image_proxy', $image);
				$image['image_id'] = $db->lastInsertId();
			}
			catch (Exception $e)
			{
				$image['image_id'] = 0;
				$image['is_processing'] = 0;
				$image['failed_date'] = time();
				$image['fail_count'] = 1;
				return $image;
			}
		}
		else
		{
			$db->query("
				UPDATE xf_image_proxy
				SET is_processing = ?
				WHERE image_id = ?
			", array($image['is_processing'], $image['image_id']));
		}

		$results = $this->_fetchImageForProxy($url);
		$requestFailed = $results['failed'];
		$streamFile = $results['tempFile'];
		$fileName = $results['fileName'];
		$mimeType = $results['mimeType'];
		$fileSize = $results['fileSize'];

		if (!$requestFailed)
		{
			$filePath = $this->getImagePath($image);
			$dirName = dirname($filePath);
			@unlink($filePath);

			if (XenForo_Helper_File::createDirectory($dirName, true)
				&& XenForo_Helper_File::safeRename($streamFile, $filePath)
			)
			{
				// ensure the filename fits -- if it's too long, take off from the beginning to keep extension
				if (!preg_match('/./u', $fileName))
				{
					$fileName = preg_replace('/[\x80-\xFF]/', '?', $fileName);
				}
				$fileName = XenForo_Input::cleanString($fileName);
				$length = utf8_strlen($fileName);
				if ($length > 250)
				{
					$fileName = utf8_substr($fileName, $length - 250);
				}

				$data = array(
					'fetch_date' => time(),
					'file_size' => $fileSize,
					'file_name' => $fileName,
					'mime_type' => $mimeType,
					'pruned' => 0,
					'is_processing' => 0,
					'failed_date' => 0,
					'fail_count' => 0
				);
				$image = array_merge($image, $data);

				$db->update('xf_image_proxy', $data, 'image_id = ' . $db->quote($image['image_id']));
			}
		}

		@unlink($streamFile);

		if ($requestFailed)
		{
			$data = array(
				'is_processing' => 0,
				'failed_date' => time(),
				'fail_count' => $image['fail_count'] + 1
			);
			$image = array_merge($image, $data);

			$db->update('xf_image_proxy', $data, 'image_id = ' . $db->quote($image['image_id']));
		}

		return $image;
	}

	/**
	 * Does a test fetch for the specified image for debugging purposes.
	 * The image will always be fetched and the temporary file will be removed.
	 *
	 * @param string $url
	 *
	 * @return array Associative array of information about the fetch
	 */
	public function testImageProxyFetch($url)
	{
		$results = $this->_fetchImageForProxy($url);
		@unlink($results['tempFile']);
		unset($results['tempFile']);

		return $results;
	}

	/**
	 * Fetches the image at the specified URL using the standard proxy config.
	 *
	 * @param string $url
	 *
	 * @return array
	 */
	protected function _fetchImageForProxy($url)
	{
		$urlHash = md5($url);
		$urlParts = parse_url($url);

		XenForo_ImageProxyStream::register();

		// convert kilobytes to bytes
		XenForo_ImageProxyStream::setMaxSize(XenForo_Application::getOptions()->imageProxyMaxSize * 1024);

		$streamUri = 'xf-image-proxy://' . $urlHash . '-' . uniqid();
		$streamFile = XenForo_ImageProxyStream::getTempFile($streamUri);

		$requestFailed = true;
		$error = false;
		$imageMeta = null;
		$fileName = !empty($urlParts['path']) ? basename($urlParts['path']) : '';
		$mimeType = '';
		$fileSize = 0;
		$image = false;

		$requestUrl = strtr($url, array(
			' ' => '+'
		));
		if (preg_match_all('/[^A-Za-z0-9._~:\/?#\[\]@!$&\'()*+,;=%-]/', $requestUrl, $matches))
		{
			foreach ($matches[0] AS $match)
			{
				$requestUrl = str_replace($match[0], '%' . strtoupper(dechex(ord($match[0]))), $requestUrl);
			}
		}

		try
		{
			$response = XenForo_Helper_Http::getClient($requestUrl, array(
				'output_stream' => $streamUri,
				'timeout' => 10
			))->setHeaders('Accept-encoding', 'identity')->request('GET');
			if ($response->isSuccessful())
			{
				$disposition = $response->getHeader('Content-Disposition');
				if (is_array($disposition))
				{
					$disposition = end($disposition);
				}
				if ($disposition && preg_match('/filename=(\'|"|)(.+)\\1/siU', $disposition, $match))
				{
					$fileName = $match[2];
				}
				if (!$fileName)
				{
					$fileName = 'image';
				}

				$mimeHeader = $response->getHeader('Content-Type');
				if (is_array($mimeHeader))
				{
					$mimeHeader = end($mimeHeader);
				}
				$mimeType = $mimeHeader ? $mimeHeader : 'unknown/unknown';

				$imageMeta = XenForo_ImageProxyStream::getMetaData($streamUri);
				if (!empty($imageMeta['error']))
				{
					switch ($imageMeta['error'])
					{
						case 'not_image':
							$error = new XenForo_Phrase('file_not_an_image');
							break;

						case 'too_large':
							$error = new XenForo_Phrase('file_is_too_large');
							break;

						case 'invalid_type':
							$error = new XenForo_Phrase('image_is_invalid_type');
							break;

						default:
							$error = $imageMeta['error'];
					}
				}
				else
				{
					$requestFailed = false;
					$image = $imageMeta['image'];
					$mimeType = $image['mime'];
					$fileSize = $imageMeta['length'];

					$extension = XenForo_Helper_File::getFileExtension($fileName);
					$extensionMap = array(
						IMAGETYPE_GIF => array('gif'),
						IMAGETYPE_JPEG => array('jpg', 'jpeg', 'jpe'),
						IMAGETYPE_PNG => array('png')
					);
					$validExtensions = $extensionMap[$image[2]];
					if (!in_array($extension, $validExtensions))
					{
						$extensionStart = strrpos($fileName, '.');
						$fileName = ($extensionStart ? substr($fileName, 0, $extensionStart) : $fileName) . '.' . $validExtensions[0];
					}
				}
			}
			else
			{
				$error = new XenForo_Phrase('received_unexpected_response_code_x_message_y', array(
					'code' => $response->getStatus(),
					'message' => $response->getMessage()
				));
			}
		}
		catch (Exception $e)
		{
			$error = $e->getMessage();
			$response = null;
		}

		$response = null;

		return array(
			'url' => $url,
			'failed' => $requestFailed,
			'error' => $error,
			'image' => $image,
			'fileName' => $fileName,
			'mimeType' => $mimeType,
			'fileSize' => $fileSize,
			'tempFile' => $streamFile
		);
	}

	/**
	 * Deletes an image from the file system image cache
	 *
	 * @param array $image
	 */
	protected function _deleteFile(array $image)
	{
		$filePath = $this->getImagePath($image);

		@unlink($filePath);
	}

	/**
	 * Gets the path to an image in the file system image cache
	 *
	 * @param array $image
	 *
	 * @return string
	 */
	public function getImagePath(array $image)
	{
		return sprintf('%s/image_cache/%d/%d-%s.data',
			XenForo_Helper_File::getInternalDataPath(),
			floor($image['image_id'] / 1000),
			$image['image_id'],
			$image['url_hash']
		);
	}

	/**
	 * Prunes images from the file system cache that have expired
	 *
	 * @param integer|null $pruneDate
	 */
	public function pruneImageCache($pruneDate = null)
	{
		$db = $this->_getDb();

		if ($pruneDate === null)
		{
			if (!XenForo_Application::getOptions()->imageCacheTTL)
			{
				return;
			}

			$pruneDate = XenForo_Application::$time - (86400 * XenForo_Application::getOptions()->imageCacheTTL);
		}

		$images = $this->fetchAllKeyed('
			SELECT *
			FROM xf_image_proxy
			WHERE fetch_date < ?
				AND pruned = 0
		', 'image_id', $pruneDate);

		if ($images)
		{
			foreach ($images AS $imageId => $image)
			{
				$this->_deleteFile($image);
			}

			$db->update('xf_image_proxy', array(
				'pruned' => 1
			), 'image_id IN (' . $db->quote(array_keys($images)) . ')');
		}
	}

	/**
	 * Prunes unused image proxy log entries.
	 *
	 * @param null|int $pruneDate
	 *
	 * @return int
	 */
	public function pruneImageProxyLogs($pruneDate = null)
	{
		if ($pruneDate === null)
		{
			$options = XenForo_Application::getOptions();

			if (!$options->imageLinkProxyLogLength)
			{
				return 0;
			}
			if (!$options->imageCacheTTL)
			{
				// we're keeping images forever - can't prune
				return 0;
			}

			$maxTtl = max($options->imageLinkProxyLogLength, $options->imageCacheTTL);
			$pruneDate = XenForo_Application::$time - (86400 * $maxTtl);
		}

		// we can only remove logs where we've pruned the image
		return $this->_getDb()->delete('xf_image_proxy',
			'pruned = 1 AND last_request_date < ' . intval($pruneDate)
		);
	}

	/**
	 * Prepares a collection of image proxy fetching related conditions into an SQL clause
	 *
	 * @param array $conditions List of conditions
	 * @param array $fetchOptions Modifiable set of fetch options (may have joins pushed on to it)
	 *
	 * @return string SQL clause (at least 1=1)
	 */
	public function prepareImageProxyConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['url']))
		{
			if (is_array($conditions['url']))
			{
				$sqlConditions[] = 'image_proxy.url LIKE ' . XenForo_Db::quoteLike($conditions['url'][0], $conditions['url'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'image_proxy.url LIKE ' . XenForo_Db::quoteLike($conditions['url'], 'lr', $db);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Fetches image proxy items for log display
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getImageProxyLogs(array $conditions = array(), array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$whereConditions = $this->prepareImageProxyConditions($conditions, $fetchOptions);

		$orderBy = 'last_request_date';
		if (!empty($fetchOptions['order']))
		{
			switch ($fetchOptions['order'])
			{
				case 'last_request_date':
				case 'first_request_date':
				case 'views':
				case 'file_size':
					$orderBy = $fetchOptions['order'];
			}
		}

		return $this->fetchAllKeyed($this->limitQueryResults(
			"
				SELECT image_proxy.*
				FROM xf_image_proxy AS image_proxy
				WHERE $whereConditions
				ORDER BY image_proxy.$orderBy DESC
			", $limitOptions['limit'], $limitOptions['offset']
		), 'image_id');
	}

	/**
	 * Counts all image proxy items
	 *
	 * @param array $conditions
	 *
	 * @return integer
	 */
	public function countImageProxyItems(array $conditions = array())
	{
		$fetchOptions = array();
		$whereConditions = $this->prepareImageProxyConditions($conditions, $fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM xf_image_proxy AS image_proxy
			WHERE $whereConditions
		");
	}

	/**
	 * Gets the placeholder image fallback for errors.
	 *
	 * @return array
	 */
	public function getPlaceHolderImage()
	{
		$path = 'styles/default/xenforo/icons/missing-image.png';
		$url = XenForo_Application::getOptions()->boardUrl . '/' . $path;
		$filePath = XenForo_Application::getInstance()->getRootDir() . '/' . $path;
		$lastModified = filemtime($filePath);

		return array(
			'url' => $url,
			'url_hash' => md5($url),
			'file_size' => filesize($filePath),
			'file_name' => 'missing-image.png',
			'mime_type' => 'image/png',
			'fetch_date' => $lastModified,
			'first_request_date' => $lastModified,
			'last_request_date' => XenForo_Application::$time,
			'views' => 1,
			'pruned' => 0,
			'is_processing' => 0,
			'failed_date' => 0,
			'fail_count' => 0,
			'file_path' => $filePath,
			'use_file' => true
		);
	}
}