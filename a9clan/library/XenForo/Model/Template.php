<?php

/**
 * Model for templates
 *
 * @package XenForo_Templates
 */
class XenForo_Model_Template extends XenForo_Model
{
	/**
	 * Returns all templates customized in a style in alphabetical title order
	 *
	 * @param integer $styleId Style ID
	 * @param boolean $basicData If true, gets basic data only
	 *
	 * @return array Format: [title] => (array) template
	 */
	public function getAllTemplatesInStyle($styleId, $basicData = false)
	{
		return $this->fetchAllKeyed('
			SELECT ' . ($basicData ? 'template_id, title, style_id, addon_id' : '*') . '
			FROM xf_template
			WHERE style_id = ?
			ORDER BY CONVERT(title USING utf8)
		', 'title', $styleId);
	}

	/**
	 * Get the effective template list for a style. "Effective" means a merged/flattened
	 * system where every valid template has a record.
	 *
	 * This only returns data appropriate for a list view (map id, template id, title).
	 * Template_state is also calculated based on whether this template has been customized.
	 * State options: default, custom, inherited.
	 *
	 * @param integer $styleId
	 *
	 * @return array Format: [] => (array) template list info
	 */
	public function getEffectiveTemplateListForStyle($styleId, array $conditions = array(), $fetchOptions = array())
	{
		$whereClause = $this->prepareTemplateConditions($conditions, $fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchAll($this->limitQueryResults('
			SELECT template_map.template_map_id,
				template_map.style_id AS map_style_id,
				template.template_id,
				template.title,
				addon.addon_id, addon.title AS addonTitle,
				IF(template.style_id = 0, \'default\', IF(template.style_id = template_map.style_id, \'custom\', \'inherited\')) AS template_state,
				IF(template.style_id = template_map.style_id, 1, 0) AS canDelete
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template_map.template_id = template.template_id)
			LEFT JOIN xf_addon AS addon ON
				(addon.addon_id = template.addon_id)
			WHERE template_map.style_id = ?
				AND ' . $whereClause . '
			ORDER BY CONVERT(template_map.title USING utf8)
		', $limitOptions['limit'], $limitOptions['offset']), $styleId);
	}

	/**
	 * Prepares conditions for searching templates. Often, this search will
	 * be done on an effective template set (using the map). Some conditions
	 * may require this.
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return string SQL conditions
	 */
	public function prepareTemplateConditions(array $conditions, array &$fetchOptions)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['title']))
		{
			if (is_array($conditions['title']))
			{
				$sqlConditions[] = 'CONVERT(template.title USING utf8) LIKE ' . XenForo_Db::quoteLike($conditions['title'][0], $conditions['title'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'CONVERT(template.title USING utf8) LIKE ' . XenForo_Db::quoteLike($conditions['title'], 'lr', $db);
			}
		}

		if (!empty($conditions['template']))
		{
			$caseSensitive = (empty($conditions['template_case_sensitive']) ? '' : 'BINARY ');

			if (is_array($conditions['template']))
			{
				$sqlConditions[] = 'template.template LIKE ' . $caseSensitive . XenForo_Db::quoteLike($conditions['template'][0], $conditions['phrase_text'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'template.template LIKE ' . $caseSensitive . XenForo_Db::quoteLike($conditions['template'], 'lr', $db);
			}
		}

		if (!empty($conditions['template_state']))
		{
			$stateIf = 'IF(template.style_id = 0, \'default\', IF(template.style_id = template_map.style_id, \'custom\', \'inherited\'))';
			if (is_array($conditions['template_state']))
			{
				$sqlConditions[] = $stateIf . ' IN (' . $db->quote($conditions['template_state']) . ')';
			}
			else
			{
				$sqlConditions[] = $stateIf . ' = ' . $db->quote($conditions['template_state']);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Gets all effective templates in a style. "Effective" means a merged/flattened system
	 * where every valid template has a record.
	 *
	 * @param integer $styleId
	 *
	 * @return array Format: [] => (array) effective template info
	 */
	public function getAllEffectiveTemplatesInStyle($styleId)
	{
		return $this->_getDb()->fetchAll('
			SELECT template_map.template_map_id,
				template_map.style_id AS map_style_id,
				template.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template_map.template_id = template.template_id)
			WHERE template_map.style_id = ?
			ORDER BY CONVERT(template_map.title USING utf8)
		', $styleId);
	}

	/**
	 * Gets style ID/template ID pairs for all styles where the named template
	 * is modified.
	 *
	 * @param string $templateTitle
	 *
	 * @return array Format: [style_id] => template_id
	 */
	public function getTemplateIdInStylesByTitle($templateTitle)
	{
		return $this->_getDb()->fetchPairs('
			SELECT style_id, template_id
			FROM xf_template
			WHERE title = ?
		', $templateTitle);
	}

	/**
	 * Gets the effective template in a style by its title. This includes all
	 * template information and the map ID.
	 *
	 * @param string $title
	 * @param integer $styleId
	 *
	 * @return array|false Effective template info.
	 */
	public function getEffectiveTemplateByTitle($title, $styleId)
	{
		return $this->_getDb()->fetchRow('
			SELECT template_map.template_map_id,
				template_map.style_id AS map_style_id,
				template.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template.template_id = template_map.template_id)
			WHERE template_map.title = ? AND template_map.style_id = ?
		', array($title, $styleId));
	}

	/**
	 * Gets effective templates in a style by their titles
	 *
	 * @param array $titles
	 * @param integer $styleId
	 *
	 * @return array|false Effective template info
	 */
	public function getEffectiveTemplatesByTitles(array $titles, $styleId)
	{
		if (empty($titles))
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT template.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template.template_id = template_map.template_id)
			WHERE template_map.title IN(' . $this->_getDb()->quote($titles) . ') AND template_map.style_id = ?
		', 'title', $styleId);
	}

	/**
	 * Gets the effective template based on a known map idea. Returns all template
	 * information and the map ID.
	 *
	 * @param integer $templateMapId
	 *
	 * @return array|false Effective template info.
	 */
	public function getEffectiveTemplateByMapId($templateMapId)
	{
		return $this->_getDb()->fetchRow('
			SELECT template_map.template_map_id,
				template_map.style_id AS map_style_id,
				template.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template.template_id = template_map.template_id)
			WHERE template_map.template_map_id = ?
		', $templateMapId);
	}

	/**
	 * Gets multiple effective templates based on 1 or more map IDs. Returns all template
	 * information and the map ID.
	 *
	 * @param integery|array $templateMapIds Either one map ID as a scalar or any array of map IDs
	 *
	 * @return array Format: [] => (array) effective template info
	 */
	public function getEffectiveTemplatesByMapIds($templateMapIds)
	{
		if (!is_array($templateMapIds))
		{
			$templateMapIds = array($templateMapIds);
		}

		if (!$templateMapIds)
		{
			return array();
		}

		$db = $this->_getDb();

		return $db->fetchAll('
			SELECT template_map.template_map_id,
				template_map.style_id AS map_style_id,
				template.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template.template_id = template_map.template_id)
			WHERE template_map.template_map_id IN (' . $db->quote($templateMapIds) . ')
		');
	}

	/**
	 * Gets all the unique templates pointed to by a set of template map IDs.
	 *
	 * @param array $templateMapIds
	 *
	 * @return array
	 */
	public function getUniqueTemplatesByMapIds($templateMapIds)
	{
		if (!is_array($templateMapIds))
		{
			$templateMapIds = array($templateMapIds);
		}

		if (!$templateMapIds)
		{
			return array();
		}

		$db = $this->_getDb();

		return $this->fetchAllKeyed('
			SELECT DISTINCT template.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_template AS template ON
				(template.template_id = template_map.template_id)
			WHERE template_map.template_map_id IN (' . $db->quote($templateMapIds) . ')
		', 'template_id');
	}

	public function getMapIdsByTemplateTitles(array $titles)
	{
		if (!$titles)
		{
			return array();
		}

		$db = $this->_getDb();
		return $db->fetchCol("
			SELECT template_map_id
			FROM xf_template_map
			WHERE title IN (" . $db->quote($titles) . ")
		");
	}

	/**
	 * Returns the template specified by template_id
	 *
	 * @param integer $templateId Template ID
	 *
	 * @return array|false Template
	 */
	public function getTemplateById($templateId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_template
			WHERE template_id = ?
		', $templateId);
	}

	/**
	 * Returns the templates specified by template IDs
	 *
	 * @param array $templateIds
	 *
	 * @return array
	 */
	public function getTemplatesByIds(array $templateIds)
	{
		if (!$templateIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_template
				WHERE template_id IN(' . $this->_getDb()->quote($templateIds) . ')
		', 'template_id');
	}

	/**
	 * Fetches a template from a particular style based on its title.
	 * Note that if a version of the requested template does not exist
	 * in the specified style, nothing will be returned.
	 *
	 * @param string Title
	 * @param integer Style ID (defaults to master style)
	 *
	 * @return array
	 */
	public function getTemplateInStyleByTitle($title, $styleId = 0)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_template
			WHERE title = ?
				AND style_id = ?
		', array($title, $styleId));
	}

	/**
	 * Fetches templates from a particular style based on their titles.
	 * Note that if a version of the requested template does not exist
	 * in the specified style, nothing will be returned for it.
	 *
	 * @param array $titles List of titles
	 * @param integer $styleId Style ID (defaults to master style)
	 *
	 * @return array Format: [title] => info
	 */
	public function getTemplatesInStyleByTitles(array $titles, $styleId = 0)
	{
		if (!$titles)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_template
			WHERE title IN (' . $this->_getDb()->quote($titles) . ')
				AND style_id = ?
		', 'title', $styleId);
	}

	/**
	 * Gets all templates with a specific title
	 *
	 * @param array $titles
	 *
	 * @return array Format: [template_id] => info
	 */
	public function getTemplatesByTitles(array $titles)
	{
		if (!$titles)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_template
			WHERE title IN (' . $this->_getDb()->quote($titles) . ')
		', 'template_id');
	}

	/**
	 * Gets all templates that are outdated (parent version edited more recently).
	 * Does not include contents of template.
	 *
	 * @return array [template id] => template info, including master_version_string
	 */
	public function getOutdatedTemplates()
	{
		return $this->fetchAllKeyed('
			SELECT template.template_id, template.title, template.style_id,
				template.addon_id, template.version_string, template.last_edit_date,
				parent.style_id AS parent_style_id,
				parent.version_string AS parent_version_string,
				parent.last_edit_date AS parent_last_edit_date,
				IF(parent.last_edit_date > 0 AND parent.last_edit_date >= template.last_edit_date, 1, 0) AS outdated_by_date
			FROM xf_template AS template
			INNER JOIN xf_style AS style ON (style.style_id = template.style_id)
			INNER JOIN xf_template_map AS map ON (map.style_id = style.parent_id AND map.title = template.title)
			INNER JOIN xf_template AS parent ON (map.template_id = parent.template_id
				AND (
					(parent.last_edit_date > 0 AND parent.last_edit_date >= template.last_edit_date)
					OR parent.version_id > template.version_id
				)
			)
			WHERE template.style_id > 0
		', 'template_id');
	}

	public function getLatestTemplateHistoryForTemplate($title, $styleId, $priorTo = null)
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM xf_template_history
			WHERE title = ? AND style_id = ?
				" . ($priorTo ? " AND edit_date <= " . intval($priorTo) : '') . "
			ORDER BY edit_date DESC
			LIMIT 1
		", array($title, $styleId));
	}

	public function getHistoryForTemplate($title, $styleId)
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM xf_template_history
			WHERE title = ? AND style_id = ?
			ORDER BY edit_date DESC
		", 'template_history_id', array($title, $styleId));
	}

	public function pruneEditHistory($cutOff = null)
	{
		if ($cutOff === null)
		{
			$logLength = XenForo_Application::get('options')->templateHistoryLength;
			if (!$logLength)
			{
				return 0;
			}

			$cutOff = XenForo_Application::$time - 86400 * $logLength;
		}

		$db = $this->_getDb();
		return $db->delete('xf_template_history', 'log_date < ' . $db->quote($cutOff));
	}

	public function autoMergeTemplate(array $template, XenForo_Diff3 $diff)
	{
		/** @var $styleModel XenForo_Model_Style */
		$styleModel = $this->getModelFromCache('XenForo_Model_Style');

		$style = $styleModel->getStyleById($template['style_id']);
		if (!$style)
		{
			return false;
		}

		$parentStyle = $styleModel->getStyleById($style['parent_id'], true);
		if (!$parentStyle)
		{
			return false;
		}

		if ($parentStyle['style_id'])
		{
			$parentTemplate = $this->getEffectiveTemplateByTitle($template['title'], $parentStyle['style_id']);
		}
		else
		{
			$parentTemplate = $this->getTemplateInStyleByTitle($template['title'], 0);
		}
		if (!$parentTemplate)
		{
			return false;
		}

		if (!$parentTemplate['last_edit_date'] || $parentTemplate['last_edit_date'] < $template['last_edit_date'])
		{
			return false;
		}

		$previousVersion = $this->getLatestTemplateHistoryForTemplate(
			$template['title'], $parentTemplate['style_id'], $template['last_edit_date']
		);
		if (!$previousVersion)
		{
			return false;
		}

		if (!isset($template['template']))
		{
			$template = $this->getTemplateById($template['template_id']);
		}
		if (!$template)
		{
			return false;
		}

		$final = $diff->mergeToFinal(
			$template['template'], $previousVersion['template'], $parentTemplate['template']
		);
		if ($final === false)
		{
			return false;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
		$dw->setExistingData($template);
		$dw->set('template', $final);
		$dw->set('last_edit_date', XenForo_Application::$time);
		return $dw->save();
	}

	/**
	 * Returns all the templates that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [title] => info
	 */
	public function getMasterTemplatesInAddOn($addOnId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_template
			WHERE addon_id = ?
				AND style_id = 0
			ORDER BY CONVERT(title USING utf8)
		', 'title', $addOnId);
	}

	/**
	 * Gets the template map IDs of any templates that include the source
	 * map IDs. For example, this would pass in the map ID of _header
	 * and get the map ID of the PAGE_CONTAINER.
	 *
	 * @param integer|array $mapIds One map ID as a scalar or an array of many.
	 *
	 * @return array Array of map IDs
	 */
	public function getIncludingTemplateMapIds($mapIds)
	{
		if (!is_array($mapIds))
		{
			$mapIds = array($mapIds);
		}

		if (!$mapIds)
		{
			return array();
		}

		$db = $this->_getDb();

		return $db->fetchCol('
			SELECT source_map_id
			FROM xf_template_include
			WHERE target_map_id IN (' . $db->quote($mapIds) . ')
		');
	}

	/**
	 * Gets the template map information for all templates that are mapped
	 * to the specified template ID.
	 *
	 * @param integer $templateId
	 *
	 * @return array Format: [] => (array) template map info
	 */
	public function getMappedTemplatesByTemplateId($templateId)
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_template_map
			WHERE template_id = ?
		', $templateId);
	}

	/**
	 * Gets mapped template information from the parent style of the named
	 * template. If the named style is 0 (or invalid), returns false.
	 *
	 * @param string $title
	 * @param integer $styleId
	 *
	 * @return array|false
	 */
	public function getParentMappedTemplateByTitle($title, $styleId)
	{
		if ($styleId == 0)
		{
			return false;
		}

		return $this->_getDb()->fetchRow('
			SELECT parent_template_map.*
			FROM xf_template_map AS template_map
			INNER JOIN xf_style AS style ON
				(template_map.style_id = style.style_id)
			INNER JOIN xf_template_map AS parent_template_map ON
				(parent_template_map.style_id = style.parent_id AND parent_template_map.title = template_map.title)
			WHERE template_map.title = ? AND template_map.style_id = ?
		', array($title, $styleId));
	}

	/**
	 * Gets the list of all template map IDs that include the named phrase.
	 *
	 * @param string $phraseTitle
	 *
	 * @return array List of template map IDs
	 */
	public function getTemplateMapIdsThatIncludePhrase($phraseTitle)
	{
		return $this->_getDb()->fetchCol('
			SELECT template_map_id
			FROM xf_template_phrase
			WHERE phrase_title = ?
		', $phraseTitle);
	}

	/**
	 * Returns the path to the template development directory, if it has been configured and exists
	 *
	 * @return string Path to templates directory
	 */
	public function getTemplateDevelopmentDirectory()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/templates';
	}

	/**
	 * Checks that the templates directory has been configured and exists
	 *
	 * @return boolean
	 */
	public function canImportTemplatesFromDevelopment()
	{
		$dir = $this->getTemplateDevelopmentDirectory();
		return ($dir && is_dir($dir));
	}

	/**
	 * Deletes the templates that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deleteTemplatesForAddOn($addOnId)
	{
		$db = $this->_getDb();

		$titles = $db->fetchPairs('
			SELECT template_id, title
			FROM xf_template
			WHERE style_id = 0
				AND addon_id = ?
		', $addOnId);
		if (!$titles)
		{
			return;
		}

		$templateIds = array_keys($titles);

		$this->_deleteTemplates($templateIds);

		$db->query('
			DELETE FROM xf_template_compiled
			WHERE style_id = 0
				AND title IN (' . $db->quote($titles) . ')
		');
		if (XenForo_Application::get('options')->templateFiles)
		{
			XenForo_Template_FileHandler::delete($titles, 0, null);
		}
		XenForo_Template_Compiler::resetTemplateCache();
	}

	public function deleteTemplatesInStyle($styleId)
	{
		$db = $this->_getDb();

		$titles = $db->fetchPairs('
			SELECT template_id, title
			FROM xf_template
			WHERE style_id = ?
		', $styleId);
		if (!$titles)
		{
			return;
		}

		$templateIds = array_keys($titles);

		$this->_deleteTemplates($templateIds);

		$db->query('
			DELETE FROM xf_template_compiled
			WHERE style_id = ?
				AND title IN (' . $db->quote($titles) . ')
		', $styleId);
		if (XenForo_Application::get('options')->templateFiles)
		{
			XenForo_Template_FileHandler::delete($titles, $styleId, null);
		}

		XenForo_Template_Compiler::resetTemplateCache();
	}

	protected function _deleteTemplates(array $templateIds)
	{
		$db = $this->_getDb();
		$quotedIds = $db->quote($templateIds);

		$db->query('
			DELETE FROM xf_template_include
			WHERE source_map_id IN (
				SELECT template_map_id
				FROM xf_template AS template
				INNER JOIN xf_template_map AS template_map ON
					(template.template_id = template_map.template_id)
				WHERE template.template_id IN ('. $quotedIds . ')
			)
		');
		$db->query('
			DELETE FROM xf_template_phrase
			WHERE template_map_id IN (
				SELECT template_map_id
				FROM xf_template AS template
				INNER JOIN xf_template_map AS template_map ON
					(template.template_id = template_map.template_id)
				WHERE template.template_id IN ('. $quotedIds . ')
			)
		');

		$db->delete('xf_template', "template_id IN ($quotedIds)");
		$db->delete('xf_template_map', "template_id IN ($quotedIds)");
		$db->delete('xf_template_modification_log', "template_id IN ($quotedIds)");
	}

	/**
	 * Imports all templates from the templates directory into the database
	 */
	public function importTemplatesFromDevelopment()
	{
		$db = $this->_getDb();

		$templateDir = $this->getTemplateDevelopmentDirectory();
		if (!$templateDir && !is_dir($templateDir))
		{
			throw new XenForo_Exception("Template development directory not enabled or doesn't exist");
		}

		$files = glob("$templateDir/*.html");
		if (!$files)
		{
			throw new XenForo_Exception("Template development directory does not have any templates");
		}

		$metaData = XenForo_Helper_DevelopmentXml::readMetaDataFile($templateDir . '/_metadata.xml');
		$addOnTemplates = $this->getMasterTemplatesInAddOn('XenForo');

		XenForo_Db::beginTransaction($db);

		$titles = array();
		foreach ($files AS $templateFile)
		{
			$filename = basename($templateFile);
			if (preg_match('/^(.+)\.html$/', $filename, $match))
			{
				$titles[] = $match[1];
			}
		}

		$existingTemplates = $this->getTemplatesInStyleByTitles($titles, 0);

		foreach ($files AS $templateFile)
		{
			if (!is_readable($templateFile))
			{
				throw new XenForo_Exception("Template file '$templateFile' not readable");
			}

			$filename = basename($templateFile);
			if (preg_match('/^(.+)\.html$/', $filename, $match))
			{
				$templateName = $match[1];
				$data = file_get_contents($templateFile);

				$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
				if (isset($existingTemplates[$templateName]))
				{
					$dw->setExistingData($existingTemplates[$templateName], true);
				}
				$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
				$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
				$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
				$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
				$dw->setOption(XenForo_DataWriter_Template::OPTION_REBUILD_TEMPLATE_MAP, false);
				$dw->bulkSet(array(
					'style_id' => 0,
					'title' => $templateName,
					'template' => $data,
					'addon_id' => 'XenForo',
					'version_id' => 0,
					'version_string' => ''
				));
				if (isset($metaData[$templateName]))
				{
					$dw->bulkSet($metaData[$templateName]);
				}
				$dw->save();

				unset($addOnTemplates[$templateName]);
			}
		}

		// removed templates
		foreach ($addOnTemplates AS $addOnTemplate)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$dw->setExistingData($addOnTemplate, true);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_REBUILD_TEMPLATE_MAP, false);
			$dw->delete();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Imports the add-on templates XML.
	 *
	 * @param SimpleXMLElement $xml XML element pointing to the root of the data
	 * @param string $addOnId Add-on to import for
	 * @param integer $maxExecution Maximum run time in seconds
	 * @param integer $offset Number of elements to skip
	 *
	 * @return boolean|integer True on completion; false if the XML isn't correct; integer otherwise with new offset value
	 */
	public function importTemplatesAddOnXml(SimpleXMLElement $xml, $addOnId, $maxExecution = 0, $offset = 0)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$startTime = microtime(true);

		$templates = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->template);

		$titles = array();
		$current = 0;
		foreach ($templates AS $template)
		{
			$current++;
			if ($current <= $offset)
			{
				continue;
			}

			$titles[] = (string)$template['title'];
		}

		$existingTemplates = $this->getTemplatesInStyleByTitles($titles, 0);

		$current = 0;
		$restartOffset = false;
		foreach ($templates AS $template)
		{
			$current++;
			if ($current <= $offset)
			{
				continue;
			}

			$templateName = (string)$template['title'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			if (isset($existingTemplates[$templateName]))
			{
				$dw->setExistingData($existingTemplates[$templateName], true);
			}
			$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_REBUILD_TEMPLATE_MAP, false);
			try
			{
				$dw->bulkSet(array(
					'style_id' => 0,
					'title' => $templateName,
					'template' => XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($template),
					'addon_id' => $addOnId,
					'version_id' => (int)$template['version_id'],
					'version_string' => (string)$template['version_string']
				));
				$dw->save();
			} catch (XenForo_Exception $e)
			{
				throw new XenForo_Exception("$templateName: " . $e->getMessage(), true);
			}

			if ($maxExecution && (microtime(true) - $startTime) > $maxExecution)
			{
				$restartOffset = $current;
				break;
			}
		}

		if (!$restartOffset)
		{
			unset($existingTemplates); // just save memory

			// now look for templates that have been removed
			$addOnTemplates = $this->getMasterTemplatesInAddOn($addOnId);
			foreach ($templates AS $template)
			{
				$title = (string)$template['title'];
				unset($addOnTemplates[$title]);
			}

			foreach ($addOnTemplates AS $addOnTemplate)
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
				$dw->setExistingData($addOnTemplate, true);
				$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
				$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
				$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
				$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
				$dw->setOption(XenForo_DataWriter_Template::OPTION_REBUILD_TEMPLATE_MAP, false);
				$dw->delete();
			}
		}

		XenForo_Db::commit($db);

		return ($restartOffset ? $restartOffset : true);
	}

	/**
	 * Imports templates into a given style. Note that this assumes the style is already empty.
	 * It does not check for conflicts.
	 *
	 * @param SimpleXMLElement $xml
	 * @param integer $styleId
	 * @param string|null $addOnId If non-null, consider only templates from this add-on to be imported
	 */
	public function importTemplatesStyleXml(SimpleXMLElement $xml, $styleId, $addOnId = null)
	{
		$db = $this->_getDb();

		if ($xml->template === null)
		{
			return;
		}

		$existingTemplates = $this->getAllTemplatesInStyle($styleId);

		XenForo_Db::beginTransaction($db);

		foreach ($xml->template AS $template)
		{
			$templateName = (string)$template['title'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			if (isset($existingTemplates[$templateName]))
			{
				$dw->setExistingData($existingTemplates[$templateName], true);
			}
			$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_REBUILD_TEMPLATE_MAP, false);
			$dw->bulkSet(array(
				'style_id' => $styleId,
				'title' => (string)$template['title'],
				'template' => XenForo_Helper_DevelopmentXml::processSimpleXmlCdata($template),
				'addon_id' => (string)$template['addon_id'],
				'version_id' => (int)$template['version_id'],
				'version_string' => (string)$template['version_string'],
				'disable_modifications' => (int)$template['disable_modifications']
			));
			$dw->save();

			unset($existingTemplates[$templateName]);
		}

		// removed templates
		foreach ($existingTemplates AS $existingTemplate)
		{
			if ($addOnId !== null && $existingTemplate['addon_id'] !== $addOnId)
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$dw->setExistingData($existingTemplate, true);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_CHECK_DUPLICATE, false);
			$dw->setOption(XenForo_DataWriter_Template::OPTION_REBUILD_TEMPLATE_MAP, false);
			$dw->delete();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Appends the add-on template XML to a given DOM element.
	 *
	 * @param DOMElement $rootNode Node to append all elements to
	 * @param string $addOnId Add-on ID to be exported
	 */
	public function appendTemplatesAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$document = $rootNode->ownerDocument;

		$templates = $this->getMasterTemplatesInAddOn($addOnId);
		foreach ($templates AS $template)
		{
			$templateNode = $document->createElement('template');
			$templateNode->setAttribute('title', $template['title']);
			$templateNode->setAttribute('version_id', $template['version_id']);
			$templateNode->setAttribute('version_string', $template['version_string']);
			$templateNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $template['template']));

			$rootNode->appendChild($templateNode);
		}
	}

	/**
	 * Appends the template XML for templates in the specified style.
	 *
	 * @param DOMElement $rootNode
	 * @param integer $styleId
	 * @param string|null $limitAddOnId If non-null, limits only to templates in this add-on
	 * @param boolean $independent If true, all customizations from parent styles will be included in this
	 */
	public function appendTemplatesStyleXml(DOMElement $rootNode, $styleId, $limitAddOnId = null, $independent = false)
	{
		$document = $rootNode->ownerDocument;

		if (!$styleId)
		{
			// getting master data
			$independent = false;
		}

		if ($independent)
		{
			$templates = $this->getAllEffectiveTemplatesInStyle($styleId);
		}
		else
		{
			$templates = $this->getAllTemplatesInStyle($styleId);
		}

		foreach ($templates AS $template)
		{
			if ($limitAddOnId !== null && $template['addon_id'] !== $limitAddOnId)
			{
				// wrong add-on
				continue;
			}

			if ($independent && !$template['style_id'])
			{
				// master version of a template
				continue;
			}

			$templateNode = $document->createElement('template');
			$templateNode->setAttribute('title', $template['title']);
			$templateNode->setAttribute('addon_id', $template['addon_id']);
			$templateNode->setAttribute('version_id', $template['version_id']);
			$templateNode->setAttribute('version_string', $template['version_string']);
			$templateNode->setAttribute('disable_modifications', $template['disable_modifications']);
			$templateNode->appendChild(XenForo_Helper_DevelopmentXml::createDomCdataSection($document, $template['template']));

			$rootNode->appendChild($templateNode);
		}
	}

	/**
	 * Gets the templates development XML.
	 *
	 * @return DOMDocument
	 */
	public function getTemplatesDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement('templates');
		$document->appendChild($rootNode);

		$this->appendTemplatesAddOnXml($rootNode, 'XenForo');

		return $document;
	}

	public function reparseTemplate($templateId, $fullCompile = true)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($templateId);
		$dw->reparseTemplate();
		$dw->setOption(XenForo_DataWriter_Template::OPTION_DEV_OUTPUT_DIR, '');
		$dw->setOption(XenForo_DataWriter_Template::OPTION_FULL_COMPILE, $fullCompile);
		$dw->save();
		return $dw;
	}

	public function getMapIdsToCompileByTitles(array $titles)
	{
		$mapIds = $this->getMapIdsByTemplateTitles($titles);
		$mapIds = array_merge($mapIds, $this->getIncludingTemplateMapIds($mapIds));

		return array_unique($mapIds);
	}

	/**
	 * Reparses all templates.
	 *
	 * @param integer $maxExecution The approx maximum length of time this function will run for
	 * @param integer $startStyle The ID of the style to start with
	 * @param integer $startTemplate The number of the template to start with in that style (not ID, just counter)
	 *
	 * @return boolean|array True if completed successful, otherwise array of where to restart (values start style ID, start template counter)
	 */
	public function reparseAllTemplates($maxExecution = 0, $startStyle = 0, $startTemplate = 0)
	{
		$db = $this->_getDb();

		$styles = $this->getModelFromCache('XenForo_Model_Style')->getAllStyles();
		$styleIds = array_merge(array(0), array_keys($styles));
		sort($styleIds);

		$lastStyle = 0;
		$startTime = microtime(true);
		$complete = true;

		XenForo_Db::beginTransaction($db);

		foreach ($styleIds AS $styleId)
		{
			if ($styleId < $startStyle)
			{
				continue;
			}

			$lastStyle = $styleId;
			$lastTemplate = 0;

			$templates = $this->getAllTemplatesInStyle($styleId, true);
			foreach ($templates AS $template)
			{
				$lastTemplate++;
				if ($styleId == $startStyle && $lastTemplate < $startTemplate)
				{
					continue;
				}

				$this->reparseTemplate($template, false);

				if ($maxExecution && (microtime(true) - $startTime) > $maxExecution)
				{
					$complete = false;
					break 2;
				}
			}
		}

		XenForo_Db::commit($db);

		if ($complete)
		{
			return true;
		}
		else
		{
			return array($lastStyle, $lastTemplate + 1);
		}
	}

	/**
	 * Recompiles all templates.
	 *
	 * @param integer $maxExecution The approx maximum length of time this function will run for
	 * @param integer $startStyle The ID of the style to start with
	 * @param integer $startTemplate The number of the template to start with in that style (not ID, just counter)
	 *
	 * @return boolean|array True if completed successfull, otherwise array of where to restart (values start style ID, start template counter)
	 */
	public function compileAllTemplates($maxExecution = 0, $startStyle = 0, $startTemplate = 0)
	{
		$db = $this->_getDb();

		$styles = $this->getModelFromCache('XenForo_Model_Style')->getAllStyles();
		$styleIds = array_merge(array(0), array_keys($styles));
		sort($styleIds);

		$lastStyle = 0;
		$startTime = microtime(true);
		$complete = true;

		XenForo_Db::beginTransaction($db);

		foreach ($styleIds AS $styleId)
		{
			if ($styleId < $startStyle)
			{
				continue;
			}

			$lastStyle = $styleId;
			$lastTemplate = 0;

			$templates = $this->getAllTemplatesInStyle($styleId, true);
			foreach ($templates AS $template)
			{
				$lastTemplate++;
				if ($styleId == $startStyle && $lastTemplate < $startTemplate)
				{
					continue;
				}

				$this->compileNamedTemplateInStyleTree($template['title'], $template['style_id']);

				if ($maxExecution && (microtime(true) - $startTime) > $maxExecution)
				{
					$complete = false;
					break 2;
				}
			}
		}

		if ($complete)
		{
			$compiledRemove = $db->fetchAll("
				SELECT DISTINCT c.title, c.style_id
				FROM xf_template_compiled AS c
				LEFT JOIN xf_template_map AS m ON (c.title = m.title AND c.style_id = m.style_id)
				WHERE m.title IS NULL
			");
			foreach ($compiledRemove AS $remove)
			{
				$db->delete('xf_template_compiled',
					"style_id = " . $db->quote($remove['style_id']) . " AND title = " . $db->quote($remove['title'])
				);
				if (XenForo_Application::get('options')->templateFiles)
				{
					XenForo_Template_FileHandler::delete($remove['title'], $remove['style_id'], null);
				}
			}

			$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
			$this->getModelFromCache('XenForo_Model_AdminTemplate')->updateAdminStyleLastModifiedDate();
		}

		XenForo_Db::commit($db);

		if ($complete)
		{
			return true;
		}
		else
		{
			return array($lastStyle, $lastTemplate + 1);
		}
	}

	/**
	 * Compiles the named template in the style tree. Any child templates that
	 * use this template will be recompiled as well.
	 *
	 * @param string $title
	 * @param integer $styleId
	 *
	 * @return array A list of template map IDs that were compiled
	 */
	public function compileNamedTemplateInStyleTree($title, $styleId)
	{
		$parsedRecord = $this->getEffectiveTemplateByTitle($title, $styleId);
		if (!$parsedRecord)
		{
			return array();
		}
		return $this->compileTemplateInStyleTree($parsedRecord);
	}

	/**
	 * Compiles the list of template map IDs and any child templates that are using
	 * the same core template.
	 *
	 * @param integer|array $templateMapIds One map ID as a scalar or many as an array
	 *
	 * @return array A list of template map IDs that were compiled
	 */
	public function compileMappedTemplatesInStyleTree($templateMapIds)
	{
		$templates = $this->getUniqueTemplatesByMapIds($templateMapIds);
		$mapIds = array();

		foreach ($templates AS $template)
		{
			$mapIds = array_merge($mapIds, $this->compileTemplateInStyleTree($template));
		}

		return $mapIds;
	}

	/**
	 * Compiles the specified template data in the style tree. This compiles this template
	 * in any style that is actually using this template.
	 *
	 * @param array $parsedRecord Full template information
	 *
	 * @return array List of template map IDs that were compiled
	 */
	public function compileTemplateInStyleTree(array $parsedRecord)
	{
		$parsedTemplate = unserialize($parsedRecord['template_parsed']);

		$dependentTemplates = array();

		$templateMaps = $this->getMappedTemplatesByTemplateId($parsedRecord['template_id']);
		$compileResults = false;
		foreach ($templateMaps AS $templateMap)
		{
			if ($templateMap['style_id'] == $parsedRecord['style_id'])
			{
				// only compile the root version and then figure out if we need to compile children
				$compileResults = $this->compileAndInsertParsedTemplate(
					$templateMap['template_map_id'],
					$parsedTemplate,
					$parsedRecord['title'],
					$templateMap['style_id']
				);
				if ($compileResults)
				{
					$compileResults['templateMapId'] = $templateMap['template_map_id'];
				}
				break;
			}
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$includeDeleteIds = array();
		$includeInserts = array();
		$phraseDeleteIds = array();
		$phraseInserts = array();

		if ($compileResults && !$compileResults['failedTemplateIncludes'])
		{
			if ($compileResults['includedTemplateIds'])
			{
				$includedTemplates = $db->fetchPairs("
					SELECT title, template_id
					FROM xf_template_map
					WHERE template_map_id IN (" . $db->quote($compileResults['includedTemplateIds']) . ")
				");
			}
			else
			{
				$includedTemplates = array();
			}

			// each template here is using the same parent version.
			// if we have no includes or all of the includes point to the same
			// template ID, then one compilation is all that's necessary and we
			// can simply copy the results between styles
			foreach ($templateMaps AS $templateMap)
			{
				$dependentTemplates[] = $templateMap['template_map_id'];

				if ($templateMap['style_id'] == $parsedRecord['style_id'])
				{
					// already handled
					continue;
				}

				$isSame = true;

				if ($includedTemplates)
				{
					$localIncludedTemplates = $db->fetchAll("
						SELECT title, template_id, template_map_id
						FROM xf_template_map
						WHERE style_id = ?
							AND title IN (" . $db->quote(array_keys($includedTemplates)) . ")
					", array($templateMap['style_id']));
					foreach ($localIncludedTemplates AS $localInclude)
					{
						if (!isset($includedTemplates[$localInclude['title']])
							|| $includedTemplates[$localInclude['title']] != $localInclude['template_id']
						)
						{
							// including a different template, so we need to do a full compile
							$isSame = false;
							break;
						}
					}
				}
				else
				{
					$localIncludedTemplates = array();
				}

				if ($isSame)
				{
					$includeDeleteIds[] = $templateMap['template_map_id'];
					foreach ($localIncludedTemplates AS $localInclude)
					{
						$includeInserts[] = '(' . $db->quote($templateMap['template_map_id']) . ', ' . $db->quote($localInclude['template_map_id']) . ')';
					}

					$phraseDeleteIds[] = $templateMap['template_map_id'];
					foreach ($compileResults['includedPhraseTitles'] AS $phraseTitles)
					{
						$phraseInserts[] = '(' . $db->quote($templateMap['template_map_id']) . ', ' . $db->quote($phraseTitles) . ')';
					}

					foreach ($compileResults['compiledCache'] AS $languageId => $compiled)
					{
						$this->_insertCompiledTemplateRecord(
							$templateMap['style_id'], $languageId, $parsedRecord['title'], $compiled
						);
					}
				}
				else
				{
					$this->compileAndInsertParsedTemplate(
						$templateMap['template_map_id'],
						$parsedTemplate,
						$parsedRecord['title'],
						$templateMap['style_id']
					);
				}
			}
		}
		else
		{
			foreach ($templateMaps AS $templateMap)
			{
				$this->compileAndInsertParsedTemplate(
					$templateMap['template_map_id'],
					$parsedTemplate,
					$parsedRecord['title'],
					$templateMap['style_id']
				);
				$dependentTemplates[] = $templateMap['template_map_id'];
			}
		}

		if ($includeDeleteIds)
		{
			$db->delete('xf_template_include', 'source_map_id IN (' . $db->quote($includeDeleteIds) . ')');
		}
		if ($includeInserts)
		{
			$db->query("
				INSERT IGNORE INTO xf_template_include
					(source_map_id, target_map_id)
				VALUES " . implode(',', $includeInserts)
			);
		}
		if ($phraseDeleteIds)
		{
			$db->delete('xf_template_phrase', 'template_map_id IN (' . $db->quote($phraseDeleteIds) . ')');
		}
		if ($phraseInserts)
		{
			$db->query("
				INSERT IGNORE INTO xf_template_phrase
					(template_map_id, phrase_title)
				VALUES " . implode(',', $phraseInserts)
			);
		}

		XenForo_Db::commit($db);

		return $dependentTemplates;
	}

	/**
	 * Compiles and inserts the specified effective templates.
	 *
	 * @param array $templates Array of effective template info
	 */
	public function compileAndInsertEffectiveTemplates(array $templates)
	{
		foreach ($templates AS $template)
		{
			$this->compileAndInsertParsedTemplate(
				$template['template_map_id'],
				unserialize($template['template_parsed']),
				$template['title'],
				isset($template['map_style_id']) ? $template['map_style_id'] : $template['style_id']
			);
		}
	}

	/**
	 * Recompiles all templates that include the named phrase.
	 *
	 * @param string $phraseTitle
	 * @param bool $deferred If true, defer this
	 *
	 * @return array List of template map IDs including the phrase
	 */
	public function compileTemplatesThatIncludePhrase($phraseTitle, $deferred = false)
	{
		$mapIds = $this->getTemplateMapIdsThatIncludePhrase($phraseTitle);
		if ($deferred)
		{
			XenForo_Application::defer('TemplatePartialCompile', array(
				'recompileMapIds' => $mapIds
			), null, true);
		}
		else
		{
			$this->compileMappedTemplatesInStyleTree($mapIds);
		}

		return $mapIds;
	}

	/**
	 * Compiles the specified parsed template and updates the compiled table
	 * and included templates list.
	 *
	 * @param integer $templateMapId The map ID of the template being compiled (for includes)
	 * @param string|array $parsedTemplate Parsed form of the template
	 * @param string $title Title of the template
	 * @param integer $compileStyleId Style ID of the template
	 * @param boolean $doDbWrite If non null, controls whether the DB write/template cache is written
	 *
	 * @return array|bool
	 */
	public function compileAndInsertParsedTemplate($templateMapId, $parsedTemplate, $title, $compileStyleId, $doDbWrite = null)
	{
		$isCss = (substr($title, -4) == '.css');

		if ($doDbWrite === null)
		{
			$doDbWrite = ($isCss || $compileStyleId);
		}

		$compiler = new XenForo_Template_Compiler('');
		$languages = $this->getModelFromCache('XenForo_Model_Language')->getAllLanguages();

		$db = $this->_getDb();

		$compiledCache = array();

		if ($isCss)
		{
			$compiledTemplate = $compiler->compileParsed($parsedTemplate, $title, $compileStyleId, 0);
			$compiledCache[0] = $compiledTemplate;
			if ($doDbWrite)
			{
				$this->_insertCompiledTemplateRecord($compileStyleId, 0, $title, $compiledTemplate);
			}
		}
		else
		{
			foreach ($languages AS $language)
			{
				$compiledTemplate = $compiler->compileParsed($parsedTemplate, $title, $compileStyleId, $language['language_id']);
				$compiledCache[$language['language_id']] = $compiledTemplate;

				if ($doDbWrite)
				{
					$this->_insertCompiledTemplateRecord($compileStyleId, $language['language_id'], $title, $compiledTemplate);
				}
			}
		}

		$mapIdQuoted = $db->quote($templateMapId);

		$ins = array();
		$includedTemplateIds = array();

		foreach ($compiler->getIncludedTemplates() AS $includedMapId)
		{
			$ins[] = '(' . $mapIdQuoted . ', ' . $db->quote($includedMapId) . ')';
			$includedTemplateIds[] = $includedMapId;
		}

		if ($doDbWrite)
		{
			$db->delete('xf_template_include', 'source_map_id = ' . $db->quote($templateMapId));
			if ($ins)
			{
				$db->query("
					INSERT IGNORE INTO xf_template_include
						(source_map_id, target_map_id)
					VALUES
						" . implode(',', $ins)
				);
			}
		}

		$ins = array();
		$includedPhraseTitles = array();

		foreach ($compiler->getIncludedPhrases() AS $includedPhrase)
		{
			if (strlen($includedPhrase) > 75)
			{
				continue; // too long, can't be a valid phrase
			}

			$ins[] = '(' . $mapIdQuoted . ', ' . $db->quote($includedPhrase) . ')';
			$includedPhraseTitles[] = $includedPhrase;
		}

		if ($doDbWrite)
		{
			$db->delete('xf_template_phrase', 'template_map_id = ' . $db->quote($templateMapId));
			if ($ins)
			{
				$db->query("
					INSERT IGNORE INTO xf_template_phrase
						(template_map_id, phrase_title)
					VALUES
						" . implode(',', $ins)
				);
			}
		}

		return array(
			'includedTemplateIds' => $includedTemplateIds,
			'failedTemplateIncludes' => $compiler->getFailedTemplateIncludes(),
			'includedPhraseTitles' => $includedPhraseTitles,
			'compiledCache' => $compiledCache,
			'doDbWrite' => $doDbWrite
		);
	}

	protected function _insertCompiledTemplateRecord($styleId, $languageId, $title, $compiledTemplate)
	{
		$this->_getDb()->query("
			INSERT INTO xf_template_compiled
				(style_id, language_id, title, template_compiled)
			VALUES
				(?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE template_compiled = VALUES(template_compiled)
		", array($styleId, $languageId, $title, $compiledTemplate));
		if (XenForo_Application::get('options')->templateFiles)
		{
			XenForo_Template_FileHandler::save($title, $styleId, $languageId, $compiledTemplate);
		}
	}

	/**
	 * Determines if the visiting user can modify a template in the specified style.
	 * If debug mode is not enabled, users can't modify templates in the master style.
	 *
	 * @param integer $styleId
	 *
	 * @return boolean
	 */
	public function canModifyTemplateInStyle($styleId)
	{
		return ($styleId != 0 || XenForo_Application::debugMode());
	}

	/**
	 * Builds (and inserts) the template map for a specified template, from
	 * the root of the style tree.
	 *
	 * @param string $title Title of the template being build
	 * @param array $data Injectable data. Supports styleTree and styleTemplateMap.
	 */
	public function buildTemplateMap($title, array $data = array())
	{
		if (!isset($data['styleTree']))
		{
			/* @var $styleModel XenForo_Model_Style */
			$styleModel = $this->getModelFromCache('XenForo_Model_Style');
			$data['styleTree'] = $styleModel->getStyleTreeAssociations($styleModel->getAllStyles());
		}

		if (!isset($data['styleTemplateMap']))
		{
			$data['styleTemplateMap'] = $this->getTemplateIdInStylesByTitle($title);
		}

		$mapUpdates = $this->findTemplateMapUpdates(0, $data['styleTree'], $data['styleTemplateMap']);
		if ($mapUpdates)
		{
			$db = $this->_getDb();
			$toDeleteInStyleIds = array();

			foreach ($mapUpdates AS $styleId => $newTemplateId)
			{
				if ($newTemplateId == 0)
				{
					$toDeleteInStyleIds[] = $styleId;
					continue;
				}

				$db->query('
					INSERT INTO xf_template_map
						(style_id, title, template_id)
					VALUES
						(?, ?, ?)
					ON DUPLICATE KEY UPDATE
						template_id = ?
				', array($styleId, $title, $newTemplateId, $newTemplateId));
			}

			if ($toDeleteInStyleIds)
			{
				$db->delete('xf_template_map',
					'title = ' . $db->quote($title) . ' AND style_id IN (' . $db->quote($toDeleteInStyleIds) . ')'
				);
				$db->delete('xf_template_compiled',
					'title = ' . $db->quote($title) . ' AND style_id IN (' . $db->quote($toDeleteInStyleIds) . ')'
				);
				if (XenForo_Application::get('options')->templateFiles)
				{
					XenForo_Template_FileHandler::delete($title, $toDeleteInStyleIds, null);
				}
			}
		}
	}

	/**
	 * Finds the necessary template map updates for the specified template within the
	 * sub-tree.
	 *
	 * If {$defaultTemplateId} is non-0, a return entry will be inserted for {$parentId}.
	 *
	 * @param integer $parentId Parent of the style sub-tree to search.
	 * @param array $styleTree Tree of styles
	 * @param array $styleTemplateMap List of styleId => templateId pairs for the places where this template has been customized.
	 * @param integer $defaultTemplateId The default template ID that non-customized template in the sub-tree should get.
	 *
	 * @return array Format: [style id] => [effective template id]
	 */
	public function findTemplateMapUpdates($parentId, array $styleTree, array $styleTemplateMap, $defaultTemplateId = 0)
	{
		$output = array();

		if (isset($styleTemplateMap[$parentId]))
		{
			$defaultTemplateId = $styleTemplateMap[$parentId];
		}

		$output[$parentId] = $defaultTemplateId;

		if (!isset($styleTree[$parentId]))
		{
			return $output;
		}

		foreach ($styleTree[$parentId] AS $styleId)
		{
			$output += $this->findTemplateMapUpdates($styleId, $styleTree, $styleTemplateMap, $defaultTemplateId);
		}

		return $output;
	}

	/**
	 * Inserts the template map records for all elements of various styles.
	 *
	 * @param array $styleMapList Format: [style id][title] => template id
	 * @param bolean $truncate If true, all map data is truncated (quicker that way)
	 */
	public function insertTemplateMapForStyles(array $styleMapList, $truncate = false)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		if ($truncate)
		{
			$db->query('TRUNCATE TABLE xf_template_map');
			$db->query('TRUNCATE TABLE xf_template_include');
			$db->query('TRUNCATE TABLE xf_template_phrase');
		}

		foreach ($styleMapList AS $builtStyleId => $map)
		{
			if (!$truncate)
			{
				$db->delete('xf_template_map', 'style_id = ' . $db->quote($builtStyleId));
			}

			foreach ($map AS $title => $templateId)
			{
				$db->insert('xf_template_map', array(
					'style_id' => $builtStyleId,
					'title' => $title,
					'template_id' => $templateId
				));
			}
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Builds the full template map data for an entire style sub-tree.
	 *
	 * @param integer $styleId Starting style. This style and all children will be built.
	 *
	 * @return array Format: [style id][title] => template id
	 */
	public function buildTemplateMapForStyleTree($styleId)
	{
		/* @var $styleModel XenForo_Model_Style */
		$styleModel = $this->getModelFromCache('XenForo_Model_Style');

		$styles = $styleModel->getAllStyles();
		$styleTree = $styleModel->getStyleTreeAssociations($styles);
		$styles[0] = true;

		if ($styleId && !isset($styles[$styleId]))
		{
			return array();
		}

		$map = array();
		if ($styleId)
		{
			$style = $styles[$styleId];

			$templates = $this->getEffectiveTemplateListForStyle($style['parent_id']);
			foreach ($templates AS $template)
			{
				$map[$template['title']] = $template['template_id'];
			}
		}

		return $this->_buildTemplateMapForStyleTree($styleId, $map, $styles, $styleTree);
	}

	/**
	 * Internal handler to build the template map data for a style sub-tree.
	 * Calls itself recursively.
	 *
	 * @param integer $styleId Style to build (builds children automatically)
	 * @param array $map Base template map data. Format: [title] => template id
	 * @param array $styles List of styles
	 * @param array $styleTree Style tree
	 *
	 * @return array Format: [style id][title] => template id
	 */
	protected function _buildTemplateMapForStyleTree($styleId, array $map, array $styles, array $styleTree)
	{
		if (!isset($styles[$styleId]))
		{
			return array();
		}

		$customTemplates = $this->getAllTemplatesInStyle($styleId);
		foreach ($customTemplates AS $template)
		{
			$map[$template['title']] = $template['template_id'];
		}

		$output = array($styleId => $map);

		if (isset($styleTree[$styleId]))
		{
			foreach ($styleTree[$styleId] AS $childStyleId)
			{
				$output += $this->_buildTemplateMapForStyleTree($childStyleId, $map, $styles, $styleTree);
			}
		}

		return $output;
	}

	/**
	 * Replaces <xen:require/include/edithint with <link rel="xenforo_x"
	 * for the purposes of easy WebDAV editing.
	 *
	 * @param string $templateText
	 *
	 * @return string
	 */
	public static function replaceIncludesWithLinkRel($templateText)
	{
		$search = array(
			'#<xen:require\s+css="([^"]+)"\s*/>#siU'
			=>	'<link rel="xenforo_stylesheet" type="text/css" href="\1" />',

			'#<xen:edithint\s+template="([^"]+\.css)"\s*/>#siU'
			=> '<link rel="xenforo_stylesheet_hint" type="text/css" href="\1" />',

			'#<xen:edithint\s+template="([^"]+)"\s*/>#siU'
			=> '<link rel="xenforo_template_hint" type="text/html" href="\1.html" />',

			'#<xen:include\s+template="([^"]+)"(\s*/)?>#siU'
			=> '<link rel="xenforo_template" type="text/html" href="\1.html"\2>',

			'#</xen:include>#siU'
			=> '</link>',
		);

		return preg_replace(array_keys($search), $search, $templateText);
	}

	/**
	 * Replaces <link rel="xenforo_x" with <xen:require/include/edithint
	 * for the purposes of easy WebDAV editing.
	 *
	 * @param string $templateText
	 *
	 * @return string
	 */
	public static function replaceLinkRelWithIncludes($templateText)
	{
		$search = array(
			'#</link>#siU'
			=> '</xen:include>',

			'#<link rel="xenforo_template" type="text/html" href="([^"]+)(\.html)?"(\s*/)?>#siU'
			=> '<xen:include template="\1"\3>',

			'#<link rel="xenforo_template_hint" type="text/html" href="([^"]+)(\.html)?"\s/>#siU'
			=> '<xen:edithint template="\1" />',

			'#<link rel="xenforo_stylesheet_hint" type="text/css" href="([^"]+)"\s*/>#siU'
			=> '<xen:edithint template="\1" />',

			'#<link rel="xenforo_stylesheet" type="text/css" href="([^"]+)"\s*/>#siU'
			=> '<xen:require css="\1" />'
		);

		return preg_replace(array_keys($search), $search, $templateText);
	}

	/**
	 * Writes out the complete set of template files to the file system
	 *
	 * @param boolean Enable the templateFiles option after completion.
	 * @param boolean Manipulate the option values to ensure failsafe operation.
	 */
	public function writeTemplateFiles($enable = false, $handleOptions = true)
	{
		if ($handleOptions && XenForo_Application::get('options')->templateFiles)
		{
			$this->getModelFromCache('XenForo_Model_Option')->updateOptions(array('templateFiles' => 0));
		}

		$this->deleteTemplateFiles();

		$templates = $this->_getDb()->query('SELECT * FROM xf_template_compiled');
		while ($template = $templates->fetch())
		{
			XenForo_Template_FileHandler::save($template['title'], $template['style_id'], $template['language_id'], $template['template_compiled']);
		}

		if ($handleOptions && $enable)
		{
			$this->getModelFromCache('XenForo_Model_Option')->updateOptions(array('templateFiles' => 1));
		}
	}

	/**
	 * Deletes the file versions of all templates
	 */
	public function deleteTemplateFiles()
	{
		XenForo_Template_FileHandler::delete(null, null, null);
	}
}