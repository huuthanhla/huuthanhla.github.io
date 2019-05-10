<?php

/**
 * Model for BB code related behaviors.
 *
 * @package XenForo_BbCode
 */
class XenForo_Model_BbCode extends XenForo_Model
{
	/**
	 * Gets the specified BB code.
	 *
	 * @param string $id
	 *
	 * @return array|false
	 */
	public function getBbCodeById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_bb_code
			WHERE bb_code_id = ?
		', $id);
	}

	/**
	 * Gets all BB codes, ordered by title.
	 *
	 * @return array [BB code id] => info
	 */
	public function getAllBbCodes()
	{
		return $this->getBbCodes();
	}

	/**
	 * Gets all BB codes by IDs
	 *
	 * @param array $bbCodeIds
	 *
	 * @return array [BB code id] => info
	 */
	public function getBbCodesByIds(array $bbCodeIds)
	{
		if (!$bbCodeIds)
		{
			return array();
		}
		return $this->getBbCodes(array('bb_code_id' => $bbCodeIds));
	}

	/**
	 * Gets all BB codes belonging to a particular add-on
	 *
	 * @param string $addOnId
	 *
	 * @return array [BB code id] => info
	 */
	public function getBbCodesByAddOnId($addOnId)
	{
		return $this->getBbCodes(array('addon_id' => $addOnId));
	}

	/**
	 * Get all BB codes that are active
	 *
	 * @return array
	 */
	public function getActiveBbCodes()
	{
		return $this->getBbCodes(array(
			'active' => true,
			'addOnActive' => true
		));
	}

	/**
	 * Gets BB codes matching the specified conditions

	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array [site id] => info
	 */
	public function getBbCodes(array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareBbCodeConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareBbCodeOrderOptions($fetchOptions, 'bb_code.bb_code_id');
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults('
			SELECT bb_code.*
			FROM xf_bb_code AS bb_code
			LEFT JOIN xf_addon AS addon ON (addon.addon_id = bb_code.addon_id)
			WHERE ' . $whereClause . '
			' . $orderClause . '
		', $limitOptions['limit'], $limitOptions['offset']
		), 'bb_code_id');
	}

	/**
	 * Prepares an SQL 'WHERE' clause for use in getBbCodess()
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return string
	 */
	public function prepareBbCodeConditions(array $conditions = array(), array $fetchOptions = array())
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['all']))
		{
			$sqlConditions[] = '1=1';
		}

		if (!empty($conditions['bb_code_id']))
		{
			if (is_array($conditions['bb_code_id']))
			{
				$sqlConditions[] = 'bb_code.bb_code_id IN (' . $db->quote($conditions['bb_code_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'bb_code.bb_code_id = ' . $db->quote($conditions['bb_code_id']);
			}
		}

		if (!empty($conditions['addon_id']))
		{
			$sqlConditions[] = 'bb_code.addon_id = ' . $db->quote($conditions['addon_id']);
		}

		if (isset($conditions['active']))
		{
			$sqlConditions[] = 'bb_code.active = ' . ($conditions['active'] ? 1 : 0);
		}

		if (!empty($conditions['addOnActive']))
		{
			$sqlConditions[] = '(addon.active IS NULL OR addon.active = 1)';
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Gets the BB code media site data for the cache.
	 *
	 * @return array
	 */
	public function getBbCodesForCache()
	{
		$bbCodes = $this->getActiveBbCodes();
		foreach ($bbCodes AS &$bbCode)
		{
			unset($bbCode['bb_code_id'], $bbCode['example'], $bbCode['active'], $bbCode['addon_id']);
			$bbCode['sprite_params'] = ($bbCode['sprite_mode'] ?
				(is_array($bbCode['sprite_params']) ? $bbCode['sprite_params'] : @unserialize($bbCode['sprite_params']))
				: false
			);
		}

		return $bbCodes;
	}

	/**
	 * Prepares an SQL 'ORDER' clause for use in getBbCodes()
	 *
	 * @param array $fetchOptions
	 *
	 * @return string
	 */
	public function prepareBbCodeOrderOptions(array $fetchOptions = array(), $defaultOrderSql = '')
	{
		$choices = array(
			'bb_code_id' => 'bb_code.bb_code_id'
		);
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}

	public function prepareBbCode(array $bbCode)
	{
		$bbCode['title'] = new XenForo_Phrase($this->getBbCodeTitlePhraseName($bbCode['bb_code_id']));
		$bbCode['description'] = new XenForo_Phrase($this->getBbCodeDescriptionPhraseName($bbCode['bb_code_id']));
		$bbCode['spriteParams'] = @unserialize($bbCode['sprite_params']);

		return $bbCode;
	}

	public function prepareBbCodes(array $bbCodes)
	{
		foreach ($bbCodes AS &$bbCode)
		{
			$bbCode = $this->prepareBbCode($bbCode);
		}

		return $bbCodes;
	}

	/**
	 * Gets the name of an BB code title phrase.
	 *
	 * @param string $bbCodeId
	 *
	 * @return string
	 */
	public function getBbCodeTitlePhraseName($bbCodeId)
	{
		return 'custom_bb_code_' . $bbCodeId . '_title';
	}

	/**
	 * Gets the name of an BB code description phrase.
	 *
	 * @param string $bbCodeId
	 *
	 * @return string
	 */
	public function getBbCodeDescriptionPhraseName($bbCodeId)
	{
		return 'custom_bb_code_' . $bbCodeId . '_desc';
	}

	/**
	 * Gets the master title phrase value for the specified BB code title.
	 *
	 * @param string $bbCodeId
	 *
	 * @return string
	 */
	public function getBbCodeMasterTitlePhraseValue($bbCodeId)
	{
		$phraseName = $this->getBbCodeTitlePhraseName($bbCodeId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the master title phrase value for the specified BB code title.
	 *
	 * @param string $bbCodeId
	 *
	 * @return string
	 */
	public function getBbCodeMasterDescriptionPhraseValue($bbCodeId)
	{
		$phraseName = $this->getBbCodeDescriptionPhraseName($bbCodeId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Appends the add-on BB codes XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendBbCodesAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$document = $rootNode->ownerDocument;

		foreach ($this->getBbCodesByAddOnId($addOnId) AS $bbCode)
		{
			$bbCodeNode = $this->_getBbCodeXmlNode($document, $bbCode);
			$rootNode->appendChild($bbCodeNode);
		}
	}

	/**
	 * Gets the XML to export the specified BB codes. This is add-on
	 * independent, so normal usage would only allow exporting BB codes
	 * separate from an add-on.
	 *
	 * @param array $bbCodes
	 *
	 * @return DOMDocument
	 */
	public function getBbCodeExportXml(array $bbCodes)
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('bb_codes');
		$document->appendChild($rootNode);

		foreach ($bbCodes AS $bbCode)
		{
			$bbCodeNode = $this->_getBbCodeXmlNode($document, $bbCode);

			$bbCodeNode->setAttribute('title', $this->getBbCodeMasterTitlePhraseValue($bbCode['bb_code_id']));
			$bbCodeNode->setAttribute('description', $this->getBbCodeMasterDescriptionPhraseValue($bbCode['bb_code_id']));

			$rootNode->appendChild($bbCodeNode);
		}

		return $document;
	}

	protected function _getBbCodeXmlNode(DOMDocument $document, array $bbCode)
	{
		$attributes = array(
			'bb_code_id',
			'bb_code_mode',
			'has_option',
			'callback_class',
			'callback_method',
			'option_regex',
			'trim_lines_after',
			'plain_children',
			'disable_smilies',
			'disable_nl2br',
			'disable_autolink',
			'allow_empty',
			'allow_signature',
			'editor_icon_url',
			'sprite_mode',
			'active'
		);
		$children = array(
			'replace_html',
			'replace_html_email',
			'replace_text',
			'example'
		);

		$bbCodeNode = $document->createElement('bb_code');

		foreach ($attributes AS $attribute)
		{
			$bbCodeNode->setAttribute($attribute, $bbCode[$attribute]);
		}
		foreach ($children AS $child)
		{
			$fieldNode = $document->createElement($child);
			$fieldNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $bbCode[$child]));
			$bbCodeNode->appendChild($fieldNode);
		}

		if ($bbCode['sprite_mode'])
		{
			$params = @unserialize($bbCode['sprite_params']);
			if ($params && isset($params['x']) && isset($params['y']))
			{
				$bbCodeNode->setAttribute('sprite_params_x', $params['x']);
				$bbCodeNode->setAttribute('sprite_params_y', $params['y']);
			}
			else
			{
				$bbCodeNode->setAttribute('sprite_params_x', 0);
				$bbCodeNode->setAttribute('sprite_params_y', 0);
			}
		}

		return $bbCodeNode;
	}

	/**
	 * Imports the BB codes for an add-on.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the event data
	 * @param string $addOnId Add-on to import for
	 */
	public function importBbCodesAddOnXml(SimpleXMLElement $xml, $addOnId)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$db->delete('xf_bb_code', 'addon_id = ' . $db->quote($addOnId));

		$xmlBbCodes = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->bb_code);

		$bbCodeIds = array();
		foreach ($xmlBbCodes AS $bbCode)
		{
			$bbCodeIds[] = (string)$bbCode['bb_code_id'];
		}

		$existing = $this->getBbCodes(array('bb_code_id' => $bbCodeIds));

		foreach ($xmlBbCodes AS $bbCode)
		{
			$data = $this->_getBbCodeDataFromXml($bbCode);
			$data['addon_id'] = $addOnId;
			$bbCodeId = $data['bb_code_id'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_BbCode', XenForo_DataWriter::ERROR_SILENT);
			if (isset($existing[$bbCodeId]))
			{
				$dw->setExistingData($existing[$bbCodeId]);
			}
			$dw->setOption(XenForo_DataWriter_BbCode::OPTION_REBUILD_CACHE, false);
			$dw->bulkSet($data);
			$dw->save();
		}

		XenForo_Db::commit($db);
	}

	public function importCustomBbCodeXml(SimpleXMLElement $xml)
	{
		if ($xml->getName() != 'bb_codes')
		{
			throw new XenForo_Exception(new XenForo_Phrase('please_provide_valid_bb_code_xml_file'), true);
		}

		$db = $this->_getDb();

		$xmlBbCodes = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->bb_code);

		$bbCodeIds = array();
		foreach ($xmlBbCodes AS $bbCode)
		{
			$bbCodeIds[] = (string)$bbCode['bb_code_id'];
		}

		$existing = $this->getBbCodes(array('bb_code_id' => $bbCodeIds));

		XenForo_Db::beginTransaction($db);

		foreach ($xmlBbCodes AS $bbCode)
		{
			$data = $this->_getBbCodeDataFromXml($bbCode);
			$bbCodeId = $data['bb_code_id'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_BbCode', XenForo_DataWriter::ERROR_SILENT);
			if (isset($existing[$bbCodeId]))
			{
				$dw->setExistingData($existing[$bbCodeId]);
			}
			$dw->setOption(XenForo_DataWriter_BbCode::OPTION_REBUILD_CACHE, false);
			$dw->bulkSet($data);
			$dw->setExtraData(XenForo_DataWriter_BbCode::DATA_TITLE, (string)$bbCode['title']);
			$dw->setExtraData(XenForo_DataWriter_BbCode::DATA_DESCRIPTION, (string)$bbCode['description']);
			$dw->save();
		}

		XenForo_Db::commit($db);

		$this->rebuildBbCodeCache();
		$this->updateBbCodeParseCacheVersion();
		$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
	}

	protected function _getBbCodeDataFromXml($xmlBbCode)
	{
		return array(
			'bb_code_id' => (string)$xmlBbCode['bb_code_id'],
			'bb_code_mode' => (string)$xmlBbCode['bb_code_mode'],
			'has_option' => (string)$xmlBbCode['has_option'],
			'replace_html' => (string)XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($xmlBbCode->replace_html),
			'replace_html_email' => (string)XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($xmlBbCode->replace_html_email),
			'replace_text' => (string)XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($xmlBbCode->replace_text),
			'callback_class' => (string)$xmlBbCode['callback_class'],
			'callback_method' => (string)$xmlBbCode['callback_method'],
			'option_regex' => (string)$xmlBbCode['option_regex'],
			'trim_lines_after' => (integer)$xmlBbCode['trim_lines_after'],
			'plain_children' => (integer)$xmlBbCode['plain_children'],
			'disable_smilies' => (integer)$xmlBbCode['disable_smilies'],
			'disable_nl2br' => (integer)$xmlBbCode['disable_nl2br'],
			'disable_autolink' => (integer)$xmlBbCode['disable_autolink'],
			'allow_empty' => (integer)$xmlBbCode['allow_empty'],
			'allow_signature' => (integer)$xmlBbCode['allow_signature'],
			'editor_icon_url' => (string)$xmlBbCode['editor_icon_url'],
			'sprite_mode' => (integer)$xmlBbCode['sprite_mode'],
			'sprite_params' => array(
				'x' => (integer)$xmlBbCode['sprite_params_x'],
				'y' => (integer)$xmlBbCode['sprite_params_y'],
			),
			'example' => (string)XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($xmlBbCode->example),
			'active' => (integer)$xmlBbCode['active']
		);
	}

	/**
	 * Deletes all BB codes for the specified add-on
	 *
	 * @param string $addOnId
	 */
	public function deleteBbCodesForAddOn($addOnId)
	{
		$db = $this->_getDb();

		$db->delete('xf_bb_code', 'addon_id = ' . $db->quote($addOnId));
	}

	/**
	 * Gets the specified BB code media site.
	 *
	 * @param string $id
	 *
	 * @return array|false
	 */
	public function getBbCodeMediaSiteById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_bb_code_media_site
			WHERE media_site_id = ?
		', $id);
	}

	/**
	 * Gets all BB code media sites, ordered by title.
	 *
	 * @return array [site id] => info
	 */
	public function getAllBbCodeMediaSites()
	{
		return $this->getBbCodeMediaSites();
	}

	/**
	 * Gets all BB code media sites belonging to a particular add-on
	 *
	 * @param string $addOnId
	 *
	 * @return array [site id] => info
	 */
	public function getBbCodeMediaSitesByAddOnId($addOnId)
	{
		return $this->getBbCodeMediaSites(array('addOnId' => $addOnId));
	}

	/**
	 * Gets BB code media sites matching the specified conditions

	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array [site id] => info
	 */
	public function getBbCodeMediaSites(array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareOptionConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareOptionOrderOptions($fetchOptions, 'bb_code_media_site.site_title');
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults('
			SELECT bb_code_media_site.*
			FROM xf_bb_code_media_site AS bb_code_media_site
			LEFT JOIN xf_addon AS addon ON (addon.addon_id = bb_code_media_site.addon_id)
			WHERE ' . $whereClause . '
			' . $orderClause . '
		', $limitOptions['limit'], $limitOptions['offset']
		), 'media_site_id');
	}

	public function getBbCodeMediaSitesForAdminQuickSearch($searchText)
	{
		$quotedString = XenForo_Db::quoteLike($searchText, 'lr', $this->_getDb());

		return $this->fetchAllKeyed('
			SELECT * FROM xf_bb_code_media_site
			WHERE site_title LIKE ' . $quotedString . '
			ORDER BY site_title',
		'media_site_id');
	}

	/**
	 * Prepares an SQL 'WHERE' clause for use in getBbCodeMediaSites()
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return string
	 */
	public function prepareOptionConditions(array $conditions = array(), array $fetchOptions = array())
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['all']))
		{
			$sqlConditions[] = '1=1';
		}

		if (!empty($conditions['mediaSiteIds']))
		{
			$sqlConditions[] = 'bb_code_media_site.media_site_id IN (' . $db->quote($conditions['mediaSiteIds']) . ')';
		}

		if (!empty($conditions['addOnId']))
		{
			$sqlConditions[] = 'bb_code_media_site.addon_id = ' . $db->quote($conditions['addOnId']);
		}

		if (!empty($conditions['addOnActive']))
		{
			$sqlConditions[] = '(addon.active IS NULL OR addon.active = 1)';
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Prepares an SQL 'ORDER' clause for use in getBbCodeMediaSites()
	 *
	 * @param array $fetchOptions
	 *
	 * @return string
	 */
	public function prepareOptionOrderOptions(array $fetchOptions = array(), $defaultOrderSql = '')
	{
		$choices = array(
			'media_site_id' => 'bb_code_media_site.media_site_id',
			'site_title' => 'bb_code_media_site.site_title',
		);
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}

	/**
	 * Converts a sring of line-break-separated BB code media site match URLs into
	 * an array of regexes to match against.
	 *
	 * @param string $urls
	 * @param boolean $urlsAreRegex - If true, the individual entries are already regular expressions
	 *
	 * @return array
	 */
	public function convertMatchUrlsToRegexes($urls, $urlsAreRegex = false)
	{
		if (!$urls)
		{
			return array();
		}

		$urls = preg_split('/(\r?\n)+/', $urls, -1, PREG_SPLIT_NO_EMPTY);
		$regexes = array();
		foreach ($urls AS $url)
		{
			if (!$urlsAreRegex)
			{
				$url = preg_quote($url, '#');
				$url = str_replace('\\*', '.*', $url);
				$url = str_replace('\{\$id\}', '(?P<id>[^"\'?&;/<>\#\[\]]+)', $url);
				$url = str_replace('\{\$id\:digits\}', '(?P<id>[0-9]+)', $url);
				$url = str_replace('\{\$id\:alphanum\}', '(?P<id>[a-z0-9]+)', $url);
				$url = '#' . $url . '#i';
			}
			else if (preg_match('/\W[\s\w]*e[\s\w]*$/', $url))
			{
				// no e modifier allowed
				continue;
			}

			$regexes[] = $url;
		}

		return $regexes;
	}

	/**
	 * Gets the BB code media site data for the cache.
	 *
	 * @return array
	 */
	public function getBbCodeMediaSitesForCache()
	{
		$sites = $this->getBbCodeMediaSites(array(
			'addOnActive' => true
		));
		$cache = array();
		foreach ($sites AS &$site)
		{
			$cache[$site['media_site_id']] = array(
				'embed_html' => $site['embed_html']
			);

			if ($site['embed_html_callback_class'] && $site['embed_html_callback_method'])
			{
				$cache[$site['media_site_id']]['callback'] = array($site['embed_html_callback_class'], $site['embed_html_callback_method']);
			}
		}

		return $cache;
	}

	/**
	 * Gets the BB code cache data.
	 *
	 * @return array
	 */
	public function getBbCodeCache()
	{
		return array(
			'mediaSites' => $this->getBbCodeMediaSitesForCache(),
			'bbCodes' => $this->getBbCodesForCache()
		);
	}

	/**
	 * Rebuilds the BB code cache.
	 *
	 * @return array
	 */
	public function rebuildBbCodeCache()
	{
		$cache = $this->getBbCodeCache();

		$this->_getDataRegistryModel()->set('bbCode', $cache);
		return $cache;
	}

	/**
	 * Appends the add-on BB code media sites XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendBbCodeMediaSitesAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$document = $rootNode->ownerDocument;

		$siteFields = XenForo_DataWriter::create('XenForo_DataWriter_BbCodeMediaSite')->getFieldNames();

		$childTags = array('match_urls', 'embed_html');

		foreach ($this->getBbCodeMediaSitesByAddOnId($addOnId) AS $site)
		{
			$siteNode = $document->createElement('site');

			foreach ($siteFields AS $fieldName)
			{
				if ($fieldName != 'addon_id')
				{
					if (in_array($fieldName, $childTags))
					{
						$fieldNode = $document->createElement($fieldName);
						$fieldNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $site[$fieldName]));

						$siteNode->appendChild($fieldNode);
					}
					else
					{
						$siteNode->setAttribute($fieldName, $site[$fieldName]);
					}
				}
			}

			$rootNode->appendChild($siteNode);
		}
	}

	/**
	 * Imports the BB code media sites for an add-on.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the event data
	 * @param string $addOnId Add-on to import for
	 */
	public function importBbCodeMediaSitesAddOnXml(SimpleXMLElement $xml, $addOnId)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$db->delete('xf_bb_code_media_site', 'addon_id = ' . $db->quote($addOnId));

		$xmlSites = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->site);

		$siteIds = array();
		foreach ($xmlSites AS $site)
		{
			$siteIds[] = (string)$site['media_site_id'];
		}

		$sites = $this->getBbCodeMediaSites(array('mediaSiteIds' => $siteIds));

		foreach ($xmlSites AS $site)
		{
			$siteId = (string)$site['media_site_id'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_BbCodeMediaSite');
			if (isset($sites[$siteId]))
			{
				$dw->setExistingData($sites[$siteId]);
			}
			$dw->bulkSet(array(
				'media_site_id' => $siteId,
				'site_title' => (string)$site['site_title'],
				'site_url' => (string)$site['site_url'],
				'match_urls' => (string)XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($site->match_urls),
				'match_is_regex' => (int)$site['match_is_regex'],
				'match_callback_class' => (string)$site['match_callback_class'],
				'match_callback_method' => (string)$site['match_callback_method'],
				'embed_html' => (string)XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($site->embed_html),
				'embed_html_callback_class' => (string)$site['embed_html_callback_class'],
				'embed_html_callback_method' => (string)$site['embed_html_callback_method'],
				'supported' => (int)$site['supported'],
				'addon_id' => $addOnId,
			));
			$dw->save();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Deletes all BB code media sites for the specified add-on
	 *
	 * @param string $addOnId
	 */
	public function deleteBbCodeMediaSitesForAddOn($addOnId)
	{
		$db = $this->_getDb();

		$db->delete('xf_bb_code_media_site', 'addon_id = ' . $db->quote($addOnId));
	}

	public function deleteBbCodeParseCacheForContent($contentType, $contentIds)
	{
		$db = $this->_getDb();

		$contentIds = (array)$contentIds;
		if (!$contentIds)
		{
			return ;
		}

		$db->delete('xf_bb_code_parse_cache',
			'content_type = ' . $db->quote($contentType). ' AND content_id IN (' . $db->quote($contentIds) . ')'
		);
	}

	public function trimBbCodeCache($trimDays = null)
	{
		if ($trimDays === null)
		{
			$trimDays = XenForo_Application::getOptions()->bbCodeCacheTrimDays;
		}

		$trimDays = floatval($trimDays);
		if ($trimDays > 0)
		{
			$this->_getDb()->delete('xf_bb_code_parse_cache',
				'cache_date < ' . (XenForo_Application::$time - 86400 * $trimDays)
			);
		}
	}

	public function updateBbCodeParseCacheVersion()
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Option', XenForo_DataWriter::ERROR_SILENT);
		if ($dw->setExistingData('bbCodeCacheVersion'))
		{
			$dw->set('option_value', XenForo_Application::$time);
			return $dw->save();
		}

		return false;
	}

	/**
	 * Attempts to read HTML that has been selected from XenForo messages,
	 * and turn it back into its source BB code.
	 *
	 * @param $html
	 *
	 * @return string
	 */
	public function getBbCodeFromSelectionHtml($html)
	{
		// attempt to parse the selected HTML into BB code
		$html = trim(strip_tags($html, '<b><i><u><a><img><span><ul><ol><li><pre><code><br>'));

		// handle PHP/CODE/HTML output and turn it back into BB code
		$html = preg_replace_callback(
			'/<(pre|code) data-type="(\w+)">(.*)<\/\\1>/siU',
			array($this, '_bbCodeTagsHtmlToBbCode'), $html);

		$html = XenForo_Html_Renderer_BbCode::renderFromHtml($html);

		return trim(XenForo_Input::cleanString($html));
	}

	/**
	 * Replaces the HTML versions of [PHP], [CODE] and [HTML] with their BB code equivalents, as best it can
	 *
	 * @param array $matches
	 *
	 * @return string
	 */
	protected function _bbCodeTagsHtmlToBbCode(array $matches)
	{
		$matches[2] = strtoupper($matches[2]);

		if ($matches[2] == 'PHP') // working with a [PHP] tag
		{
			$matches[3] = str_replace('<br>', "\n", strip_tags($matches[3], '<br>'));
		}

		return "[$matches[2]]" . str_replace("\n", '<br>', trim($matches[3])) . "[/$matches[2]]";
	}

	/**
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}
}