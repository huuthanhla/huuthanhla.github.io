<?php

class XenForo_Deferred_Sitemap extends XenForo_Deferred_Abstract
{
	protected $_contentTypes;

	protected $_setId;

	protected $_fileCounter;
	protected $_fileLength;
	protected $_fileEntryCount;
	protected $_file;

	protected $_totalEntryCount;

	/**
	 * @var XenForo_Model_Sitemap
	 */
	protected $_sitemapModel;

	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'content_types' => null,
			'current_type' => null,
			'last_id' => 0,

			'set_id' => null,

			'file_counter' => 1,
			'file_length' => 0,
			'file_entry_count' => 0,

			'total_entry_count' => 0,

			'finalize_file' => 1
		), $data);

		/** @var XenForo_Model_Sitemap $siteMapModel */
		$siteMapModel = XenForo_Model::create('XenForo_Model_Sitemap');
		$this->_sitemapModel = $siteMapModel; // workaround IDE completion quirk
		$allContentTypes = $this->_sitemapModel->getSitemapContentTypes();

		$this->_contentTypes = $data['content_types'];
		if (!is_array($this->_contentTypes))
		{
			$this->_contentTypes = array_keys($allContentTypes);
		}

		$this->_fileCounter = $data['file_counter'];
		$this->_fileLength = $data['file_length'];
		$this->_fileEntryCount = $data['file_entry_count'];
		$this->_totalEntryCount = $data['total_entry_count'];

		$this->_setId = $data['set_id'];
		if (!$this->_setId)
		{
			$this->_setId = XenForo_Application::$time;
		}

		$contentType = $this->_getContentType($data['current_type'], $allContentTypes);
		if (!$contentType)
		{
			$finalizeFile = $this->_finalizeSitemap($data['finalize_file'], $targetRunTime);
			if ($finalizeFile === false)
			{
				return false;
			}

			$data['finalize_file'] = $finalizeFile;

			$actionPhrase = new XenForo_Phrase('rebuilding');
			$typePhrase = new XenForo_Phrase(new XenForo_Phrase('sitemap'));
			$text = new XenForo_Phrase(new XenForo_Phrase('finalizing'));

			$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, "$text $data[finalize_file]");

			$newLast = $data['last_id'];
		}
		else
		{
			if ($contentType != $data['current_type'])
			{
				$data['last_id'] = 0;
			}

			$handlerClass = XenForo_Application::resolveDynamicClass($allContentTypes[$contentType]);
			$handler = new $handlerClass();

			$newLast = $this->_buildSitemap($handler, $data['last_id'], $targetRunTime);

			$this->_sitemapModel->insertPendingSitemap($this->_setId, $this->_fileCounter, $this->_totalEntryCount);

			$actionPhrase = new XenForo_Phrase('rebuilding');
			$typePhrase = new XenForo_Phrase(new XenForo_Phrase('sitemap'));
			$text = new XenForo_Phrase($handler->getPhraseKey($contentType));

			$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, "$text $data[last_id]");
		}

		return array(
			'content_types' => $this->_contentTypes,
			'current_type' => $newLast ? $contentType : null,
			'last_id' => $newLast,

			'set_id' => $this->_setId,

			'file_counter' => $this->_fileCounter,
			'file_length' => $this->_fileLength,
			'file_entry_count' => $this->_fileEntryCount,

			'total_entry_count' => $this->_totalEntryCount,

			'finalize_file' => $data['finalize_file']
		);
	}

	protected function _getContentType($currentType, array $allContentTypes)
	{
		while (!$currentType || !isset($allContentTypes[$currentType]))
		{
			if (!$this->_contentTypes)
			{
				return false;
			}

			$currentType = array_shift($this->_contentTypes);
		}

		return $currentType;
	}

	protected function _buildSitemap(XenForo_SitemapHandler_Abstract $handler, $lastId, $targetRunTime)
	{
		$start = microtime(true);

		$viewingUser = XenForo_Model::create('XenForo_Model_User')->getVisitingGuestUser();
		$viewingUser['permissions'] = XenForo_Permission::unserializePermissions($viewingUser['global_permission_cache']);

		if (!$handler->basePermissionCheck($viewingUser))
		{
			return false;
		}

		$records = $handler->getRecords($lastId, 2000, $viewingUser);
		if (!$records)
		{
			return false;
		}

		$isInterruptable = $handler->isInterruptable();

		$newLast = false;
		foreach ($records AS $key => $record)
		{
			$newLast = $key;

			if ($handler->isIncluded($record, $viewingUser))
			{
				$result = $handler->getData($record);
				if ($result)
				{
					if (isset($result['loc']))
					{
						$this->_writeResult($result);
					}
					else
					{
						foreach ($result AS $row)
						{
							$this->_writeResult($row);
						}
					}
				}
			}

			if ($isInterruptable && $targetRunTime && microtime(true) - $start > $targetRunTime)
			{
				break;
			}
		}

		if ($this->_file)
		{
			$this->_closeFile();
		}

		return $isInterruptable ? $newLast : false;
	}

	protected function _writeResult(array $result)
	{
		if ($this->_fileEntryCount >= 50000)
		{
			$this->_completeFile();
		}

		$this->_fileEntryCount++;
		$this->_totalEntryCount++;

		$content = $this->_sitemapModel->buildSitemapEntry($result);
		$this->_writeSitemapString("\t" . trim($content) . "\n");
	}

	protected function _openFile()
	{
		if (!$this->_file)
		{
			$fileName = $this->_sitemapModel->getSitemapFileName($this->_setId, $this->_fileCounter);
			$this->_file = fopen($fileName, 'a');
			flock($this->_file, LOCK_EX);
		}
	}

	protected function _closeFile()
	{
		if ($this->_file)
		{
			fflush($this->_file);
			flock($this->_file, LOCK_UN);
			fclose($this->_file);
			$this->_file = null;
		}
	}

	protected function _writeSitemapString($content, $allowComplete = true)
	{
		if (!$this->_file)
		{
			$this->_openFile();
		}

		if ($this->_fileLength == 0)
		{
			$preamble = $this->_sitemapModel->getSitemapPreamble();
			fwrite($this->_file, $preamble);
			$this->_fileLength += strlen($preamble);
		}

		fwrite($this->_file, $content);
		$this->_fileLength += strlen($content);

		if ($this->_fileLength > 10000000 && $allowComplete)
		{
			$this->_completeFile();
		}
	}

	protected function _completeFile()
	{
		if ($this->_fileLength == 0)
		{
			return;
		}

		$this->_writeSitemapString($this->_sitemapModel->getSitemapSuffix(), false);

		$this->_closeFile();

		$this->_fileCounter++;
		$this->_fileLength = 0;
		$this->_fileEntryCount = 0;
	}

	protected function _finalizeSitemap($finalizeFile, $targetRunTime)
	{
		$this->_completeFile();

		$fileCount = $this->_fileCounter - 1;
		$canCompress = function_exists('gzopen');

		if ($finalizeFile <= $fileCount && $canCompress)
		{
			// gzip a file at a time
			$success = $this->_sitemapModel->compressSitemapFile($this->_setId, $finalizeFile);
			if (!$success && $finalizeFile == 1)
			{
				// if we failed on the first file, just bail out
				$canCompress = false;
			}
			else
			{
				return $finalizeFile + 1;
			}
		}

		// final clean up, rotation and search engine ping
		$this->_sitemapModel->completeSitemap($this->_setId, $canCompress, $fileCount, $this->_totalEntryCount);
		$this->_sitemapModel->cleanUpOldSitemaps($this->_setId);
		$this->_sitemapModel->deleteOldSitemapLogs();

		if (XenForo_Application::getOptions()->sitemapAutoSubmit && $this->_totalEntryCount > 1)
		{
			// an entry count of 1 really just means the main URL, so it's almost certainly
			// a totally private board
			$this->_sitemapModel->sendSitemapPing();
		}

		return false;
	}

	public function canCancel()
	{
		return true;
	}
}