<?php

/**
 * Model for phrases
 *
 * @package XenForo_Phrase
 */
class XenForo_Model_Phrase extends XenForo_Model
{
	/**
	 * Returns all phrases customized in a language in alphabetical title order
	 *
	 * @param integer Language ID
	 *
	 * @return array Format: [title] => (array) language
	 */
	public function getAllPhrasesInLanguage($languageId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_phrase
			WHERE language_id = ?
			ORDER BY CONVERT(title USING utf8)
		', 'title', $languageId);
	}

	/**
	 * Get the effective phrase list for a language. "Effective" means a merged/flattened
	 * system where every valid phrase has a record.
	 *
	 * This only returns data appropriate for a list view (map id, phrase id, title).
	 * phrase_state is also calculated based on whether this phrase has been customized.
	 * State options: default, custom, inherited.
	 *
	 * @param integer $language
	 *
	 * @return array Format: [] => (array) phrase list info
	 */
	public function getEffectivePhraseListForLanguage($languageId,
		array $conditions = array(), array $fetchOptions = array()
	)
	{
		$whereClause = $this->preparePhraseConditions($conditions, $fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchAll($this->limitQueryResults(
			'
				SELECT phrase_map.phrase_map_id,
					phrase_map.language_id AS map_language_id,
					phrase.phrase_id,
					phrase_map.title,
					IF(phrase.language_id = 0, \'default\', IF(phrase.language_id = phrase_map.language_id, \'custom\', \'inherited\')) AS phrase_state,
					IF(phrase.language_id = phrase_map.language_id, 1, 0) AS canDelete,
					addon.addon_id, addon.title AS addonTitle
				FROM xf_phrase_map AS phrase_map
				INNER JOIN xf_phrase AS phrase ON
					(phrase_map.phrase_id = phrase.phrase_id)
				LEFT JOIN xf_addon AS addon ON
					(addon.addon_id = phrase.addon_id)
				WHERE phrase_map.language_id = ?
					AND ' . $whereClause . '
				ORDER BY CONVERT(phrase_map.title USING utf8)
			', $limitOptions['limit'], $limitOptions['offset']
		), $languageId);
	}

	public function countEffectivePhrasesInLanguage($languageId, array $conditions = array())
	{
		$fetchOptions = array();
		$whereClause = $this->preparePhraseConditions($conditions, $fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_phrase_map AS phrase_map
			INNER JOIN xf_phrase AS phrase ON
				(phrase_map.phrase_id = phrase.phrase_id)
			WHERE phrase_map.language_id = ?
				AND ' . $whereClause . '
		', $languageId);
	}

	public function preparePhraseConditions(array $conditions, array &$fetchOptions)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['title']))
		{
			if (is_array($conditions['title']))
			{
				$sqlConditions[] = 'CONVERT(phrase.title USING utf8) LIKE ' . XenForo_Db::quoteLike($conditions['title'][0], $conditions['title'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'CONVERT(phrase.title USING utf8) LIKE ' . XenForo_Db::quoteLike($conditions['title'], 'lr', $db);
			}
		}

		if (!empty($conditions['phrase_text']))
		{
			$caseSensitive = (empty($conditions['phrase_case_sensitive']) ? '' : 'BINARY ');

			if (is_array($conditions['phrase_text']))
			{
				$sqlConditions[] = 'phrase.phrase_text LIKE ' . $caseSensitive . XenForo_Db::quoteLike($conditions['phrase_text'][0], $conditions['phrase_text'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'phrase.phrase_text LIKE ' . $caseSensitive . XenForo_Db::quoteLike($conditions['phrase_text'], 'lr', $db);
			}
		}

		if (!empty($conditions['contains']))
		{
			$sqlConditions[] = '(CONVERT(phrase.title USING utf8) LIKE ' . XenForo_Db::quoteLike($conditions['contains'], 'lr', $db)
				. ' OR phrase.phrase_text LIKE ' . XenForo_Db::quoteLike($conditions['contains'], 'lr', $db) . ')';
		}

		if (!empty($conditions['phrase_state']))
		{
			$stateIf = 'IF(phrase.language_id = 0, \'default\', IF(phrase.language_id = phrase_map.language_id, \'custom\', \'inherited\'))';
			if (is_array($conditions['phrase_state']))
			{
				$sqlConditions[] = $stateIf . ' IN (' . $db->quote($conditions['phrase_state']) . ')';
			}
			else
			{
				$sqlConditions[] = $stateIf . ' = ' . $db->quote($conditions['phrase_state']);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Gets all effective phrases in a language. "Effective" means a merged/flattened system
	 * where every valid phrase has a record.
	 *
	 * @param integer $languageId
	 *
	 * @return array Format: [] => (array) effective phrase info
	 */
	public function getAllEffectivePhrasesInLanguage($languageId)
	{
		return $this->_getDb()->fetchAll('
			SELECT phrase_map.phrase_map_id,
				phrase_map.language_id AS map_language_id,
				phrase.*
			FROM xf_phrase_map AS phrase_map
			INNER JOIN xf_phrase AS phrase ON
				(phrase_map.phrase_id = phrase.phrase_id)
			WHERE phrase_map.language_id = ?
			ORDER BY CONVERT(phrase_map.title USING utf8)
		', $languageId);
	}

	/**
	 * Gets the effective phrase value in all languages for the specified list of phrases.
	 *
	 * @param array $phraseList List of phrases to fetch
	 *
	 * @return array Format: [language id][title] => value
	 */
	public function getEffectivePhraseValuesInAllLanguages(array $phraseList)
	{
		if (!$phraseList)
		{
			return array();
		}

		$db = $this->_getDb();
		$output = array();

		$phraseResult = $db->query('
			SELECT language_id, title, phrase_text
			FROM xf_phrase_compiled
			WHERE title IN (' . $db->quote($phraseList) . ')
		');
		while ($phrase = $phraseResult->fetch())
		{
			$output[$phrase['language_id']][$phrase['title']] = $phrase['phrase_text'];
		}

		return $output;
	}

	/**
	 * Gets language ID/phrase ID pairs for all languages where the named phrase
	 * is modified.
	 *
	 * @param string $phraseTitle
	 *
	 * @return array Format: [language_id] => phrase_id
	 */
	public function getPhraseIdInLanguagesByTitle($phraseTitle)
	{
		return $this->_getDb()->fetchPairs('
			SELECT language_id, phrase_id
			FROM xf_phrase
			WHERE title = ?
		', $phraseTitle);
	}

	/**
	 * Gets the effective phrase in a language by its title. This includes all
	 * phrase information and the map ID.
	 *
	 * @param string $title
	 * @param integer $languageId
	 *
	 * @return array|false Effective phrase info.
	 */
	public function getEffectivePhraseByTitle($title, $languageId)
	{
		return $this->_getDb()->fetchRow('
			SELECT phrase_map.phrase_map_id,
				phrase_map.language_id AS map_language_id,
				phrase.*
			FROM xf_phrase_map AS phrase_map
			INNER JOIN xf_phrase AS phrase ON
				(phrase.phrase_id = phrase_map.phrase_id)
			WHERE phrase_map.title = ? AND phrase_map.language_id = ?
		', array($title, $languageId));
	}

	/**
	 * Gets the effective phrase based on a known map idea. Returns all phrase
	 * information and the map ID.
	 *
	 * @param integer $phraseMapId
	 *
	 * @return array|false Effective phrase info.
	 */
	public function getEffectivePhraseByMapId($phraseMapId)
	{
		return $this->_getDb()->fetchRow('
			SELECT phrase_map.phrase_map_id,
				phrase_map.language_id AS map_language_id,
				phrase.*
			FROM xf_phrase_map AS phrase_map
			INNER JOIN xf_phrase AS phrase ON
				(phrase.phrase_id = phrase_map.phrase_id)
			WHERE phrase_map.phrase_map_id = ?
		', $phraseMapId);
	}

	/**
	 * Gets multiple effective phrases based on 1 or more map IDs. Returns all phrase
	 * information and the map ID.
	 *
	 * @param integery|array $phraseMapIds Either one map ID as a scalar or any array of map IDs
	 *
	 * @return array Format: [] => (array) effective phrase info
	 */
	public function getEffectivePhrasesByMapIds($phraseMapIds)
	{
		if (!is_array($phraseMapIds))
		{
			$phraseMapIds = array($phraseMapIds);
		}

		if (!$phraseMapIds)
		{
			return array();
		}

		$db = $this->_getDb();

		return $db->fetchAll('
			SELECT phrase_map.phrase_map_id,
				phrase_map.language_id AS map_language_id,
				phrase.*
			FROM xf_phrase_map AS phrase_map
			INNER JOIN xf_phrase AS phrase ON
				(phrase.phrase_id = phrase_map.phrase_id)
			WHERE phrase_map.phrase_map_id IN (' . $db->quote($phraseMapIds) . ')
		');
	}

	/**
	 * Returns the phrase specified by phrase_id
	 *
	 * @param integer $phraseId phrase ID
	 *
	 * @return array|false phrase
	 */
	public function getPhraseById($phraseId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_phrase
			WHERE phrase_id = ?
		', $phraseId);
	}

	/**
	 * Fetches a phrase from a particular language based on its title.
	 * Note that if a version of the requested phrase does not exist
	 * in the specified language, nothing will be returned.
	 *
	 * @param string $title Title
	 * @param integer $languageId language ID (defaults to master language)
	 *
	 * @return array
	 */
	public function getPhraseInLanguageByTitle($title, $languageId = 0)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_phrase
			WHERE title = ?
				AND language_id = ?
		', array($title, $languageId));
	}

	/**
	 * Fetches a phrases from a particular language based on titles.
	 * Note that if a version of the requested phrase does not exist
	 * in the specified language, nothing will be returned for that phrase.
	 *
	 * @param array $titles List of titles
	 * @param integer $languageId language ID (defaults to master language)
	 *
	 * @return array Format: [title] => info
	 */
	public function getPhrasesInLanguageByTitles(array $titles, $languageId = 0)
	{
		if (!$titles)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_phrase
			WHERE title IN (' . $this->_getDb()->quote($titles) . ')
				AND language_id = ?
		', 'title', $languageId);
	}

	/**
	 * Gets all phrases that are outdated (master version edited more recently).
	 * Does not include contents of phrase.
	 *
	 * @return array [phrase id] => phrase info, including master_version_string
	 */
	public function getOutdatedPhrases()
	{
		return $this->fetchAllKeyed('
			SELECT phrase.phrase_id, phrase.title, phrase.language_id,
				phrase.addon_id, phrase.version_id, phrase.version_string,
				master.version_string AS master_version_string
			FROM xf_phrase AS phrase
			INNER JOIN xf_phrase AS master ON (master.title = phrase.title AND master.language_id = 0)
			INNER JOIN xf_language AS language ON (language.language_id = phrase.language_id)
			WHERE phrase.language_id > 0
				AND master.version_id > phrase.version_id
		', 'phrase_id');
	}

	/**
	 * Gets all the master (language 0) phrases in the specified add-on.
	 *
	 * @param string $addOnId
	 *
	 * @return array Format: [title] => info
	 */
	public function getMasterPhrasesInAddOn($addOnId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_phrase
			WHERE addon_id = ?
				AND language_id = 0
			ORDER BY CONVERT(title USING utf8)
		', 'title', $addOnId);
	}

	/**
	 * Gets the value for the named master phrase.
	 *
	 * @param string $title
	 *
	 * @return string Empty string if phrase is value
	 */
	public function getMasterPhraseValue($title)
	{
		$phrase = $this->getPhraseInLanguageByTitle($title, 0);
		return ($phrase ? $phrase['phrase_text'] : '');
	}

	/**
	 * Inserts or updates an array of master (language 0) phrases. Errors will be silently ignored.
	 *
	 * @param array $phrases Key-value pairs of phrases to insert/update
	 * @param string $addOnId Add-on all phrases belong to
	 * @param array $extra Extra fields to set
	 * @param array $options
	 *
	 * @param array $phrases Format: [title] => value
	 */
	public function insertOrUpdateMasterPhrases(array $phrases, $addOnId, array $extra = array(), array $options = array())
	{
		foreach ($phrases AS $title => $value)
		{
			$this->insertOrUpdateMasterPhrase($title, $value, $addOnId, $extra, $options);
		}
	}

	/**
	 * Inserts or updates a master (language 0) phrase. Errors will be silently ignored.
	 *
	 * @param string $title
	 * @param string $text
	 * @param string $addOnId
	 * @param array $extra Extra fields to set
	 * @param array $options
	 */
	public function insertOrUpdateMasterPhrase($title, $text, $addOnId = '', array $extra = array(), array $options = array())
	{
		$phrase = $this->getPhraseInLanguageByTitle($title, 0);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Phrase', XenForo_DataWriter::ERROR_SILENT);
		foreach ($options AS $key => $value)
		{
			$dw->setOption($key, $value);
		}
		if ($phrase)
		{
			$dw->setExistingData($phrase, true);
		}
		else
		{
			$dw->set('language_id', 0);
		}
		$dw->set('title', $title);
		$dw->set('phrase_text', $text);
		$dw->set('addon_id', $addOnId);
		$dw->bulkSet($extra);
		$dw->save();
	}

	/**
	 * Deletes the named master phrases if they exist.
	 *
	 * @param array $phraseTitles Phrase titles
	 * @param array $options
	 */
	public function deleteMasterPhrases(array $phraseTitles, array $options = array())
	{
		foreach ($phraseTitles AS $title)
		{
			$this->deleteMasterPhrase($title, $options);
		}
	}

	/**
	 * Deletes the named master phrase if it exists.
	 *
	 * @param string $title
	 * @param array $options
	 */
	public function deleteMasterPhrase($title, array $options = array())
	{
		$phrase = $this->getPhraseInLanguageByTitle($title, 0);
		if (!$phrase)
		{
			return;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Phrase', XenForo_DataWriter::ERROR_SILENT);
		foreach ($options AS $key => $value)
		{
			$dw->setOption($key, $value);
		}
		$dw->setExistingData($phrase, true);
		$dw->delete();
	}

	/**
	 * Renames a list of master phrases. If you get a conflict, it will
	 * be silently ignored.
	 *
	 * @param array $phraseMap Format: [old name] => [new name]
	 * @param array $options
	 */
	public function renameMasterPhrases(array $phraseMap, array $options = array())
	{
		foreach ($phraseMap AS $oldName => $newName)
		{
			$this->renameMasterPhrase($oldName, $newName);
		}
	}

	/**
	 * Renames a master phrase. If you get a conflict, it will
	 * be silently ignored.
	 *
	 * @param string $oldName
	 * @param string $newName
	 * @param array $options
	 */
	public function renameMasterPhrase($oldName, $newName, array $options = array())
	{
		$phrase = $this->getPhraseInLanguageByTitle($oldName, 0);
		if (!$phrase)
		{
			return;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Phrase', XenForo_DataWriter::ERROR_SILENT);
		foreach ($options AS $key => $value)
		{
			$dw->setOption($key, $value);
		}
		$dw->setExistingData($phrase, true);
		$dw->set('title', $newName);
		$dw->save();
	}

	/**
	 * Change the add-on for all phrases with a particular name.
	 *
	 * @param string $phraseName
	 * @param string $addOnId
	 */
	public function changePhraseAddOn($phraseName, $addOnId)
	{
		$phrases = $this->getPhraseIdInLanguagesByTitle($phraseName);
		foreach ($phrases AS $phrase)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Phrase', XenForo_DataWriter::ERROR_SILENT);
			$dw->setOption(XenForo_DataWriter_Phrase::OPTION_FULL_RECOMPILE, false);
			$dw->setOption(XenForo_DataWriter_Phrase::OPTION_REBUILD_LANGUAGE_CACHE, false);
			$dw->setExistingData($phrase, true);
			$dw->set('addon_id', $addOnId);
			$dw->save();
		}
	}

	/**
	 * Gets the phrase map information for all phrases that are mapped
	 * to the specified phrase ID.
	 *
	 * @param integer $phraseId
	 *
	 * @return array Format: [] => (array) phrase map info
	 */
	public function getMappedPhrasesByPhraseId($phraseId)
	{
		return $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_phrase_map
			WHERE phrase_id = ?
		', $phraseId);
	}

	/**
	 * Gets mapped phrase information from the parent language of the named
	 * phrase. If the named language is 0 (or invalid), returns false.
	 *
	 * @param string $title
	 * @param integer $languageId
	 *
	 * @return array|false
	 */
	public function getParentMappedPhraseByTitle($title, $languageId)
	{
		if ($languageId == 0)
		{
			return false;
		}

		return $this->_getDb()->fetchRow('
			SELECT parent_phrase_map.*
			FROM xf_phrase_map AS phrase_map
			INNER JOIN xf_language AS language ON
				(phrase_map.language_id = language.language_id)
			INNER JOIN xf_phrase_map AS parent_phrase_map ON
				(parent_phrase_map.language_id = language.parent_id AND parent_phrase_map.title = phrase_map.title)
			WHERE phrase_map.title = ? AND phrase_map.language_id = ?
		', array($title, $languageId));
	}

	public function compileAllPhrases($maxExecution = 0, $startLanguage = 0, $startPhrase = 0)
	{
		$db = $this->_getDb();

		$languages = $this->_getLanguageModel()->getAllLanguages();
		$languageIds = array_merge(array(0), array_keys($languages));
		sort($languageIds);

		$lastLanguage = 0;
		$startTime = microtime(true);
		$complete = true;

		XenForo_Db::beginTransaction($db);

		foreach ($languageIds AS $languageId)
		{
			if ($languageId < $startLanguage)
			{
				continue;
			}

			$lastLanguage = $languageId;
			$lastPhrase = 0;

			$phrases = $this->getAllPhrasesInLanguage($languageId);
			foreach ($phrases AS $phrase)
			{
				$lastPhrase++;
				if ($languageId == $startLanguage && $lastPhrase < $startPhrase)
				{
					continue;
				}

				$this->compileNamedPhraseInLanguageTree($phrase['title'], $phrase['language_id']);

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
				SELECT c.title, c.language_id
				FROM xf_phrase_compiled AS c
				LEFT JOIN xf_phrase_map AS m ON (c.title = m.title AND c.language_id = m.language_id)
				WHERE m.title IS NULL
			");
			foreach ($compiledRemove AS $remove)
			{
				$db->delete('xf_phrase_compiled',
					"language_id = " . $db->quote($remove['language_id']) . " AND title = " . $db->quote($remove['title'])
				);
			}

			$this->_getLanguageModel()->rebuildLanguageCaches();
		}

		XenForo_Db::commit($db);

		if ($complete)
		{
			return true;
		}
		else
		{
			return array($lastLanguage, $lastPhrase + 1);
		}
	}

	/**
	 * Compiles all phrases in the specified language.
	 */
	public function compileAllPhrasesInLanguage($languageId)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$phrases = $this->getAllPhrasesInLanguage($languageId);
		foreach ($phrases AS $phrase)
		{
			$this->compileNamedPhraseInLanguageTree($phrase['title'], $phrase['language_id']);
		}

		$this->_getLanguageModel()->rebuildLanguageCaches();

		XenForo_Db::commit($db);
	}

	/**
	 * Compiles the named phrase in the language tree. Any child phrases that
	 * use this phrase will be recompiled as well.
	 *
	 * @param string $title
	 * @param integer $languageId
	 *
	 * @return array A list of phrase map IDs that were compiled
	 */
	public function compileNamedPhraseInLanguageTree($title, $languageId)
	{
		$parsedRecord = $this->getEffectivePhraseByTitle($title, $languageId);
		if (!$parsedRecord)
		{
			return array();
		}
		return $this->compilePhraseInLanguageTree($parsedRecord);
	}

	/**
	 * Compiles the list of phrase map IDs and any child phrases that are using
	 * the same core phrase.
	 *
	 * @param integer|array $phraseMapIds One map ID as a scaler or many as an array
	 *
	 * @return array A list of phrase map IDs that were compiled
	 */
	public function compileMappedPhrasesInLanguageTree($phraseMapIds)
	{
		$phrases = $this->getEffectivePhrasesByMapIds($phraseMapIds);
		$mapIds = array();

		foreach ($phrases AS $phrase)
		{
			$mapIds = array_merge($mapIds, $this->compilePhraseInLanguageTree($phrase));
		}

		return $mapIds;
	}

	/**
	 * Compiles the specified phrase data in the language tree. This compiles this phrase
	 * in any language that is actually using this phrase.
	 *
	 * @param array $parsedRecord Full phrase information
	 *
	 * @return array List of phrase map IDs that were compiled
	 */
	public function compilePhraseInLanguageTree(array $parsedRecord)
	{
		$dependentPhrases = array();

		$languages = $this->getMappedPhrasesByPhraseId($parsedRecord['phrase_id']);
		foreach ($languages AS $compileLanguage)
		{
			$this->compileAndInsertParsedPhrase(
				$compileLanguage['phrase_map_id'],
				$parsedRecord['phrase_text'],
				$parsedRecord['title'],
				$compileLanguage['language_id']
			);
			$dependentPhrases[] = $compileLanguage['phrase_map_id'];
		}

		return $dependentPhrases;
	}

	/**
	 * Compiles and inserts the specified effective phrases.
	 *
	 * @param array $phrases Array of effective phrase info
	 */
	public function compileAndInsertEffectivePhrases(array $phrases)
	{
		foreach ($phrases AS $phrase)
		{
			$this->compileAndInsertParsedPhrase(
				$phrase['phrase_map_id'],
				$phrase['phrase_text'],
				$phrase['title'],
				isset($phrase['map_language_id']) ? $phrase['map_language_id'] : $phrase['language_id']
			);
		}
	}

	/**
	 * Compiles the specified parsed phrase and updates the compiled table
	 * and included phrases list.
	 *
	 * @param integer $phraseMapId The map ID of the phrase being compiled (for includes)
	 * @param string|array $parsedPhrase Parsed form of the phrase
	 * @param string $title Title of the phrase
	 * @param integer $compileLanguageId Language ID of the phrase
	 */
	public function compileAndInsertParsedPhrase($phraseMapId, $parsedPhrase, $title, $compileLanguageId)
	{
		$compiledPhrase = $parsedPhrase;

		$db = $this->_getDb();

		$db->query('
			INSERT INTO xf_phrase_compiled
				(language_id, title, phrase_text)
			VALUES
				(?, ?, ?)
			ON DUPLICATE KEY UPDATE
				title = VALUES(title),
				phrase_text = VALUES(phrase_text)
		', array($compileLanguageId, $title, $compiledPhrase));
	}

	/**
	 * Gets the titles of all phrases that should be cached globally.
	 *
	 * @return array List of titles
	 */
	public function getGlobalPhraseCacheTitles()
	{
		return $this->_getDb()->fetchCol('
			SELECT title
			FROM xf_phrase
			WHERE language_id = 0
				AND global_cache = 1
		');
	}

	/**
	 * Determines if the visiting user can modify a phrase in the specified language.
	 * If debug mode is not enabled, users can't modify phrases in the master language.
	 *
	 * @param integer $languageId
	 *
	 * @return boolean
	 */
	public function canModifyPhraseInLanguage($languageId)
	{
		return ($languageId != 0 || XenForo_Application::debugMode());
	}

	/**
	 * Builds (and inserts) the phrase map for a specified phrase, from
	 * a position within the language tree.
	 *
	 * @param string $title Title of the phrase being build
	 * @param array $data Injectable data. Supports languageTree and languagePhraseMap.
	 */
	public function buildPhraseMap($title, array $data = array())
	{
		if (!isset($data['languageTree']))
		{
			$languageModel = $this->getModelFromCache('XenForo_Model_Language');
			$data['languageTree'] = $languageModel->getLanguageTreeAssociations($languageModel->getAllLanguages());
		}

		if (!isset($data['languagePhraseMap']))
		{
			$data['languagePhraseMap'] = $this->getPhraseIdInLanguagesByTitle($title);
		}

		$mapUpdates = $this->findPhraseMapUpdates(0, $data['languageTree'], $data['languagePhraseMap']);
		if ($mapUpdates)
		{
			$db = $this->_getDb();
			$toDeleteInLanguageIds = array();

			foreach ($mapUpdates AS $languageId => $newPhraseId)
			{
				if ($newPhraseId == 0)
				{
					$toDeleteInLanguageIds[] = $languageId;
					continue;
				}

				$db->query('
					INSERT INTO xf_phrase_map
						(language_id, title, phrase_id)
					VALUES
						(?, ?, ?)
					ON DUPLICATE KEY UPDATE
						phrase_id = ?
				', array($languageId, $title, $newPhraseId, $newPhraseId));
			}

			if ($toDeleteInLanguageIds)
			{
				$db->delete('xf_phrase_map',
					'title = ' . $db->quote($title) . ' AND language_id IN (' . $db->quote($toDeleteInLanguageIds) . ')'
				);
			}
		}
	}

	/**
	 * Finds the necessary phrase map updates for the specified phrase within the
	 * sub-tree.
	 *
	 * If {$defaultPhraseId} is non-0, a return entry will be inserted for {$parentId}.
	 *
	 * @param integer $parentId Parent of the language sub-tree to search.
	 * @param array $languageTree Tree of languages
	 * @param array $languagePhraseMap List of languageId => phraseId pairs for the places where this phrase has been customized.
	 * @param integer $defaultPhraseId The default phrase ID that non-customized phrase in the sub-tree should get.
	 *
	 * @return array Format: [language id] => [effective phrase id]
	 */
	public function findPhraseMapUpdates($parentId, array $languageTree, array $languagePhraseMap, $defaultPhraseId = 0)
	{
		$output = array();

		if (isset($languagePhraseMap[$parentId]))
		{
			$defaultPhraseId = $languagePhraseMap[$parentId];
		}

		$output[$parentId] = $defaultPhraseId;

		if (!isset($languageTree[$parentId]))
		{
			return $output;
		}

		foreach ($languageTree[$parentId] AS $languageId)
		{
			$output += $this->findPhraseMapUpdates($languageId, $languageTree, $languagePhraseMap, $defaultPhraseId);
		}
		return $output;
	}

	/**
	 * Inserts the phrase map records for all elements of various languages.
	 *
	 * @param array $languageMapList Format: [language id][title] => phrase id
	 * @param boolean $truncate If true, all map data is truncated (quicker that way)
	 */
	public function insertPhraseMapForLanguages(array $languageMapList, $truncate = false)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		if ($truncate)
		{
			$db->query('TRUNCATE TABLE xf_phrase_map');
		}

		$rows = array();
		$rowLength = 0;

		foreach ($languageMapList AS $builtLanguageId => $map)
		{
			if (!$truncate)
			{
				$db->delete('xf_phrase_map', 'language_id = ' . $db->quote($builtLanguageId));
			}

			$builtLanguageIdQuoted = $db->quote($builtLanguageId);

			foreach ($map AS $title => $phraseId)
			{
				/*$db->insert('xf_phrase_map', array(
					'language_id' => $builtLanguageId,
					'title' => $title,
					'phrase_id' => $phraseId
				));*/

				$row = '(' . $builtLanguageIdQuoted . ', ' . $db->quote($title) . ',' . $db->quote($phraseId) . ')';

				$rows[] = $row;
				$rowLength += strlen($row);

				if ($rowLength > 500000)
				{
					$db->query('
						INSERT INTO xf_phrase_map
							(language_id, title, phrase_id)
						VALUES
							' . implode(',', $rows)
					);

					$rows = array();
					$rowLength = 0;
				}
			}
		}

		if ($rows)
		{
			$db->query('
				INSERT INTO xf_phrase_map
					(language_id, title, phrase_id)
				VALUES
					' . implode(', ', $rows)
			);
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Builds the full phrase map data for an entire language sub-tree.
	 *
	 * @param integer $languageId Starting language. This language and all children will be built.
	 *
	 * @return array Format: [language id][title] => phrase id
	 */
	public function buildPhraseMapForLanguageTree($languageId)
	{
		/* @var $languageModel XenForo_Model_Language */
		$languageModel = $this->getModelFromCache('XenForo_Model_Language');

		$languages = $languageModel->getAllLanguages();
		$languageTree = $languageModel->getLanguageTreeAssociations($languages);
		$languages[0] = true;

		if ($languageId && !isset($languages[$languageId]))
		{
			return array();
		}

		$map = array();
		if ($languageId)
		{
			$language = $languages[$languageId];

			$phrases = $this->getEffectivePhraseListForLanguage($language['parent_id']);
			foreach ($phrases AS $phrase)
			{
				$map[$phrase['title']] = $phrase['phrase_id'];
			}
		}

		return $this->_buildPhraseMapForLanguageTree($languageId, $map, $languages, $languageTree);
	}

	/**
	 * Internal handler to build the phrase map data for a language sub-tree.
	 * Calls itself recursively.
	 *
	 * @param integer $languageId Language to build (builds children automatically)
	 * @param array $map Base phrase map data. Format: [title] => phrase id
	 * @param array $languages List of languages
	 * @param array $languageTree Language tree
	 *
	 * @return array Format: [language id][title] => phrase id
	 */
	protected function _buildPhraseMapForLanguageTree($languageId, array $map, array $languages, array $languageTree)
	{
		if (!isset($languages[$languageId]))
		{
			return array();
		}

		$customPhrases = $this->getAllPhrasesInLanguage($languageId);
		foreach ($customPhrases AS $phrase)
		{
			$map[$phrase['title']] = $phrase['phrase_id'];
		}

		$output = array($languageId => $map);

		if (isset($languageTree[$languageId]))
		{
			foreach ($languageTree[$languageId] AS $childLanguageId)
			{
				$output += $this->_buildPhraseMapForLanguageTree($childLanguageId, $map, $languages, $languageTree);
			}
		}

		return $output;
	}

	/**
	 * Appends the language (phrase) XML for a given add-on to the specified
	 * DOM element.
	 *
	 * @param DOMElement $rootNode
	 * @param string $addOnId
	 */
	public function appendPhrasesAddOnXml(DOMElement $rootNode, $addOnId)
	{
		$document = $rootNode->ownerDocument;

		$phrases = $this->getMasterPhrasesInAddOn($addOnId);
		foreach ($phrases AS $phrase)
		{
			$phraseNode = $document->createElement('phrase');
			$phraseNode->setAttribute('title', $phrase['title']);
			if ($phrase['global_cache'])
			{
				$phraseNode->setAttribute('global_cache', $phrase['global_cache']);
			}
			$phraseNode->setAttribute('version_id', $phrase['version_id']);
			$phraseNode->setAttribute('version_string', $phrase['version_string']);
			$phraseNode->appendChild($document->createCDATASection($phrase['phrase_text']));
			$rootNode->appendChild($phraseNode);
		}
	}

	/**
	 * Gets the phrases development XML.
	 *
	 * @return DOMDocument
	 */
	public function getPhrasesDevelopmentXml()
	{
		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$rootNode = $document->createElement('phrases');
		$document->appendChild($rootNode);

		$this->appendPhrasesAddOnXml($rootNode, 'XenForo');

		return $document;
	}

	/**
	 * Internal function to import phrase XML. This does not remove any phrases
	 * that may conflict; that is the responsibility of the caller.
	 *
	 * @param SimpleXMLElement $xml Root XML node; must have "phrase" children
	 * @param integer $languageId Target language ID
	 * @param string|null $addOnId Add-on the phrases belong to; if null, read from xml
	 * @param array $existingPhrases Existing phrases; used to detect and resolve conflicts
	 * @param integer $maxExecution Maximum run time in seconds
	 * @param integer $offset Number of elements to skip
	 */
	public function importPhrasesXml(SimpleXMLElement $xml, $languageId, $addOnId = null,
		array $existingPhrases = array(), $maxExecution = 0, $offset = 0
	)
	{
		$startTime = microtime(true);

		$phrases = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->phrase);

		$current = 0;
		$restartOffset = false;
		foreach ($phrases AS $phrase)
		{
			$current++;
			if ($current <= $offset)
			{
				continue;
			}

			$title = (string)$phrase['title'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Phrase');
			if (isset($existingPhrases[$title]))
			{
				if ($languageId == 0
					&& $existingPhrases[$title]['addon_id'] == 'XenForo'
					&& $addOnId != 'XenForo'
				)
				{
					// trying to overwrite a built in phrase - don't let it
					continue;
				}

				$dw->setExistingData($existingPhrases[$title], true);
			}
			$dw->setOption(XenForo_DataWriter_Phrase::OPTION_DEV_OUTPUT_DIR, '');
			$dw->setOption(XenForo_DataWriter_Phrase::OPTION_REBUILD_LANGUAGE_CACHE, false);
			$dw->setOption(XenForo_DataWriter_Phrase::OPTION_FULL_RECOMPILE, false);
			$dw->setOption(XenForo_DataWriter_Phrase::OPTION_REBUILD_PHRASE_MAP, false);
			$dw->setOption(XenForo_DataWriter_Phrase::OPTION_CHECK_DUPLICATE, false);
			$dw->bulkSet(array(
				'title' => $title,
				'phrase_text' => (string)$phrase,
				'global_cache' => (int)$phrase['global_cache'],
				'version_id' => (int)$phrase['version_id'],
				'version_string' => (string)$phrase['version_string'],
				'language_id' => $languageId,
				'addon_id' => ($addOnId === null ? (string)$phrase['addon_id'] : $addOnId)
			));
			$dw->save();

			if ($maxExecution && (microtime(true) - $startTime) > $maxExecution)
			{
				$restartOffset = $current;
				break;
			}
		}

		XenForo_Template_Compiler::resetPhraseCache();

		if (!$restartOffset)
		{
			$this->_getLanguageModel()->rebuildLanguageCaches();
		}

		return ($restartOffset ? $restartOffset : true);
	}

	/**
	 * Deletes the phrases that belong to the specified add-on.
	 *
	 * @param string $addOnId
	 */
	public function deletePhrasesForAddOn($addOnId)
	{
		$db = $this->_getDb();

		$db->query('
			DELETE FROM xf_phrase_map
			WHERE phrase_id IN (
				SELECT phrase_id
				FROM xf_phrase
				WHERE language_id = 0
					AND addon_id = ?
			)
		', $addOnId);
		$db->query('
			DELETE FROM xf_phrase_compiled
			WHERE language_id = 0
				AND title IN (
					SELECT title
					FROM xf_phrase
					WHERE language_id = 0
						AND addon_id = ?
				)
		', $addOnId);
		$db->delete('xf_phrase', 'language_id = 0 AND addon_id = ' . $db->quote($addOnId));
	}

	/**
	 * Imports the master language (phrase) XML for the specified add-on.
	 *
	 * @param SimpleXMLElement $xml
	 * @param string $addOnId
	 * @param integer $maxExecution Maximum run time in seconds
	 * @param integer $offset Number of elements to skip
	 *
	 * @return boolean|integer True on completion; false if the XML isn't correct; integer otherwise with new offset value
	 */
	public function importPhrasesAddOnXml(SimpleXMLElement $xml, $addOnId, $maxExecution = 0, $offset = 0)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$startTime = microtime(true);

		if ($offset == 0)
		{
			$this->deletePhrasesForAddOn($addOnId);
		}

		$phrases = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->phrase);

		$titles = array();
		$current = 0;
		foreach ($phrases AS $phrase)
		{
			$current++;
			if ($current <= $offset)
			{
				continue;
			}
			$titles[] = (string)$phrase['title'];
		}

		$existingPhrases = $this->getPhrasesInLanguageByTitles($titles, 0);

		if ($maxExecution)
		{
			// take off whatever we've used
			$maxExecution -= microtime(true) - $startTime;
		}

		$return = $this->importPhrasesXml($xml, 0, $addOnId, $existingPhrases, $maxExecution, $offset);

		XenForo_Db::commit($db);

		return $return;
	}

	/**
	 * Returns the path to the phrase development directory, if it has been configured and exists
	 *
	 * @return string Path to development directory
	 */
	public function getPhraseDevelopmentDirectory()
	{
		$config = XenForo_Application::get('config');
		if (!$config->debug || !$config->development->directory)
		{
			return '';
		}

		return XenForo_Application::getInstance()->getRootDir()
			. '/' . $config->development->directory . '/file_output/phrases';
	}

	/**
	 * Checks that the development directory has been configured and exists
	 *
	 * @return boolean
	 */
	public function canImportPhrasesFromDevelopment()
	{
		$dir = $this->getPhraseDevelopmentDirectory();
		return ($dir && is_dir($dir));
	}

	/**
	 * Imports all phrases from the phrases directory into the database
	 */
	public function importPhrasesFromDevelopment()
	{
		$db = $this->_getDb();

		$phraseDir = $this->getPhraseDevelopmentDirectory();
		if (!$phraseDir && !is_dir($phraseDir))
		{
			throw new XenForo_Exception("Phrase development directory not enabled or doesn't exist");
		}

		$files = glob("$phraseDir/*.txt");
		if (!$files)
		{
			throw new XenForo_Exception("Phrase development directory does not have any phrases");
		}

		$metaData = XenForo_Helper_DevelopmentXml::readMetaDataFile($phraseDir . '/_metadata.xml');

		XenForo_Db::beginTransaction($db);
		$this->deletePhrasesForAddOn('XenForo');

		$titles = array();
		foreach ($files AS $templateFile)
		{
			$filename = basename($templateFile);
			if (preg_match('/^(.+)\.txt$/', $filename, $match))
			{
				$titles[] = $match[1];
			}
		}

		$existingPhrases = $this->getPhrasesInLanguageByTitles($titles, 0);

		foreach ($files AS $file)
		{
			if (!is_readable($file))
			{
				throw new XenForo_Exception("Phrase file '$file' not readable");
			}

			$filename = basename($file);
			if (preg_match('/^(.+)\.txt$/', $filename, $match))
			{
				$title = $match[1];

				$dw = XenForo_DataWriter::create('XenForo_DataWriter_Phrase');
				if (isset($existingPhrases[$title]))
				{
					$dw->setExistingData($existingPhrases[$title], true);
				}
				$dw->setOption(XenForo_DataWriter_Phrase::OPTION_DEV_OUTPUT_DIR, '');
				$dw->setOption(XenForo_DataWriter_Phrase::OPTION_REBUILD_LANGUAGE_CACHE, false);
				$dw->setOption(XenForo_DataWriter_Phrase::OPTION_FULL_RECOMPILE, false);
				$dw->setOption(XenForo_DataWriter_Phrase::OPTION_REBUILD_PHRASE_MAP, false);
				$dw->setOption(XenForo_DataWriter_Phrase::OPTION_CHECK_DUPLICATE, false);
				$dw->bulkSet(array(
					'title' => $title,
					'phrase_text' => file_get_contents($file),
					'language_id' => 0,
					'addon_id' => 'XenForo',
					'version_id' => 0,
					'version_string' => ''
				));
				if (isset($metaData[$title]))
				{
					$dw->bulkSet($metaData[$title]);
				}
				$dw->save();
				unset($dw);
			}
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Use this to map any phrases that have been inserted directly
	 * into the phrase table without the phrase DataWriter being involved.
	 */
	public function mapUnhandledPhrases()
	{
		$phrases = $this->_getDb()->fetchAll('
			SELECT phrase.*
			FROM xf_phrase AS phrase
			LEFT JOIN xf_phrase_map AS pm ON
				(pm.title = phrase.title AND pm.language_id = phrase.language_id AND pm.phrase_id = phrase.phrase_id)
			WHERE pm.title IS NULL
		');

		foreach ($phrases AS $phrase)
		{
			$this->buildPhraseMap($phrase['title']);
		}
	}

	/**
	 * Fetch phrases that contain $textSearch and have titles that match $titleConstraint
	 * from either the specified language, or the viewing user's chosen language.
	 *
	 * @param string Text to find in phrase
	 * @param string|array Either a string to be LIKE lr quoted, or an array containing 0 => search text including wild card, 1 => wild card character
	 * @param integer Max results to return
	 * @param integer|null Language in which to search
	 * @param array Viewing user array
	 *
	 * @return array [title => text]
	 */
	public function getPhrasesMatchingSearchTextWithConstrainedTitles($textSearch, $titleConstraint, $maxResults = 5, $languageId = null, array $viewingUser = null)
	{
		if (is_null($languageId))
		{
			$viewingUser = $this->standardizeViewingUserReference($viewingUser);

			$languageId = $viewingUser['language_id'];
			if (!$languageId)
			{
				$languageId = XenForo_Application::get('options')->defaultLanguageId;
			}
		}

		$db = $this->_getDb();

		if (is_array($titleConstraint))
		{
			$titleConstraint = XenForo_Db::quoteLike($titleConstraint[0], $titleConstraint[1], $db);
		}
		else
		{
			$titleConstraint = XenForo_Db::quoteLike($titleConstraint, 'lr', $db);
		}

		return $db->fetchPairs($this->limitQueryResults('
			SELECT title, phrase_text
			FROM xf_phrase_compiled
			WHERE language_id = ?
			AND phrase_text LIKE ' . XenForo_Db::quoteLike($textSearch, 'lr', $db) . '
			AND title LIKE ' . $titleConstraint . '
		', $maxResults), $languageId);
	}

	/**
	 * @return XenForo_Model_Language
	 */
	protected function _getLanguageModel()
	{
		return $this->getModelFromCache('XenForo_Model_Language');
	}
}