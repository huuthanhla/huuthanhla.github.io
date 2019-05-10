<?php

/**
 * Model for smilies.
 *
 * @package XenForo_Smilie
 */
class XenForo_Model_Smilie extends XenForo_Model
{
	/**
	 * Gets the named smilie by ID.
	 *
	 * @param integer $smilieId
	 *
	 * @return array|false
	 */
	public function getSmilieById($smilieId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_smilie
			WHERE smilie_id = ?
		', $smilieId);
	}

	/**
	 * Gets all smilies that match the given smilie text. This text may
	 * be an array of text, or a string with each match on separate lines.
	 *
	 * @param string|array $matchText
	 *
	 * @return array [text] => smilie that matched
	 */
	public function getSmiliesByText($matchText)
	{
		if (!is_array($matchText))
		{
			$matchText = preg_split('/\r?\n/', $matchText, -1, PREG_SPLIT_NO_EMPTY);
		}

		if (!$matchText)
		{
			return array();
		}

		$matches = array();
		foreach ($this->getAllSmilies() AS $smilie)
		{
			$smilieText = preg_split('/\r?\n/', $smilie['smilie_text'], -1, PREG_SPLIT_NO_EMPTY);

			$textMatch = array_intersect($matchText, $smilieText);
			foreach ($textMatch AS $text)
			{
				$matches[$text] = $smilie;
			}
		}

		return $matches;
	}

	/**
	 * Gets all smilies ordered by their title.
	 *
	 * @return array Format: [smilie id] => info
	 */
	public function getAllSmilies()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_smilie
			ORDER BY display_order, title
		', 'smilie_id');
	}

	/**
	 * Get the smilie data needed for the smilie cache.
	 *
	 * @return array Format: [smilie id] => info
	 */
	public function getAllSmiliesForCache()
	{
		$smilies = $this->fetchAllKeyed('
			SELECT smilie_id, title, smilie_text, image_url,
				sprite_mode, sprite_params
			FROM xf_smilie
			ORDER BY display_order, title
		', 'smilie_id');

		$smilies = $this->prepareSmilies($smilies);

		foreach ($smilies AS &$smilie)
		{
			$smilie['smilieText'] = preg_split('/\r?\n/', $smilie['smilie_text'], -1, PREG_SPLIT_NO_EMPTY);

			if (!$smilie['sprite_mode'] || !$smilie['sprite_params'])
			{
				unset($smilie['sprite_params']);
			}

			unset($smilie['sprite_mode'], $smilie['smilie_text']);
		}

		return $smilies;
	}

	/**
	 * Rebuilds the smilie cache.
	 *
	 * @return array Smilie cache
	 */
	public function rebuildSmilieCache()
	{
		$smilies = $this->getAllSmiliesForCache();
		$this->_getDataRegistryModel()->set('smilies', $smilies);

		$this->rebuildSpriteCss();

		return $smilies;
	}

	public function rebuildSpriteCss()
	{
		$spriteCss = array();

		foreach ($this->getAllSmilies() AS $smilieId => $smilie)
		{
			$smilie = $this->prepareSmilie($smilie);

			if ($smilie['sprite_mode'] && !empty($smilie['sprite_params']))
			{
				$spriteCss[$smilieId] = array('sprite_css' => sprintf('width: %1$dpx; height: %2$dpx; background: url(\'%3$s\') no-repeat %4$dpx %5$dpx;',
					(int)$smilie['sprite_params']['w'],
					(int)$smilie['sprite_params']['h'],
					htmlspecialchars($smilie['image_url']),
					(int)$smilie['sprite_params']['x'],
					(int)$smilie['sprite_params']['y']
				));
			}
		}

		$this->_getDataRegistryModel()->set('smilieSprites', $spriteCss);

		// need to force css updates
		$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();

		return $spriteCss;
	}

	/**
	 * Adds a 'smilieTextArray' array to each smilie in the provided array,
	 * where each array item contains one possible smilie_text search string
	 * as its key. Value is false (no rotation), 90 or 270
	 *
	 * @param array $smilies
	 *
	 * @return array
	 */
	public function prepareSmiliesForList(array $smilies)
	{
		$smilies = $this->prepareSmilies($smilies);

		foreach ($smilies AS &$smilie)
		{
			 $smilieTextArray = preg_split('/\r?\n/', $smilie['smilie_text'], -1, PREG_SPLIT_NO_EMPTY);

			 $out = array();

			 foreach ($smilieTextArray AS $smilieText)
			 {
			 	$out[$smilieText] = false;

			 	if (strlen($smilieText) > 4 || preg_match('#^:.*:$#', $smilieText))
			 	{
			 		continue;
			 	}

			 	if (!preg_match('#[:;8]#', $smilieText))
			 	{
			 		continue;
			 	}

			 	if (preg_match('#(:|;)$#', $smilieText))
			 	{
			 		$out[$smilieText] = 270;
			 	}
			 	else
			 	{
			 		$out[$smilieText] = 90;
			 	}
			 }

			 $smilie['smilieTextArray'] = $out;
		}

		return $smilies;
	}

	/**
	 * Prepares a number of smilies for use
	 *
	 * @param array $smilies
	 *
	 * @return array
	 */
	public function prepareSmilies(array $smilies)
	{
		return array_map(array($this, 'prepareSmilie'), $smilies);
	}

	/**
	 * Prepares a single smilie for use
	 *
	 * @param array $smilie
	 * @param boolean $getSmilieText Prepare a 'smilieText' key that contains the first item in smilie_text
	 *
	 * @return array
	 */
	public function prepareSmilie(array $smilie, $getSmilieText = false)
	{
		if (is_string($smilie['sprite_params']))
		{
			$smilie['sprite_params'] = unserialize($smilie['sprite_params']);
		}

		if ($getSmilieText)
		{
			$smilieText = preg_split('/\r?\n/', $smilie['smilie_text'], -1, PREG_SPLIT_NO_EMPTY);
			$smilie['smilieText'] = reset($smilieText);
		}

		return $smilie;
	}

	/**
	 * Gets the default values for smilie sprite params
	 *
	 * @return array
	 */
	public function getDefaultSmilieSpriteParams()
	{
		return array(
			'w' => 18,
			'h' => 18,
			'x' => 0,
			'y' => 0
		);
	}

	/**
	 * Fetches smilies for admin quick search results
	 *
	 * @param string $searchText
	 *
	 * @return array
	 */
	public function getSmiliesForAdminQuickSearch($searchText)
	{
		$quotedText = XenForo_Db::quoteLike($searchText, 'lr', $this->_getDb());

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_smilie
			WHERE title LIKE ' . $quotedText . '
				OR smilie_text LIKE ' . $quotedText
		, 'smilie_id');
	}

	/**
	 * Fetches a single smilie category record
	 *
	 * @param integer $smilieCategoryId
	 *
	 * @return array|false
	 */
	public function getSmilieCategoryById($smilieCategoryId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_smilie_category
			WHERE smilie_category_id = ?
		', $smilieCategoryId);
	}

	/**
	 * Fetches all smilie categories
	 *
	 * @return array
	 */
	public function getAllSmilieCategories()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_smilie_category
			ORDER BY display_order
		', 'smilie_category_id');
	}

	/**
	 * Prepares a smilie category for display.
	 *
	 * @param array $smilieCategory
	 *
	 * @return array
	 */
	public function prepareSmilieCategory(array $smilieCategory)
	{
		if (!empty($smilieCategory['smilie_category_id']))
		{
			$smilieCategory['title'] = new XenForo_Phrase($this->getSmilieCategoryTitlePhraseName($smilieCategory['smilie_category_id']));
		}

		return $smilieCategory;
	}

	/**
	 * Adds a title record to smilie category arrays
	 *
	 * @param array $smilieCategories
	 *
	 * @return array
	 */
	public function prepareSmilieCategories(array $smilieCategories)
	{
		return array_map(array($this, 'prepareSmilieCategory'), $smilieCategories);
	}

	/**
	 * Fetches all smilie categories as id => title options
	 *
	 * @param integer $selectedCategoryId
	 * @return array
	 */
	public function getSmilieCategoryOptions($selectedCategoryId = 0)
	{
		$categories = $this->getAllSmilieCategories();
		$categories = $this->prepareSmilieCategories($categories);

		$output = array();

		foreach ($categories AS $id => $category)
		{
			$output[$id] = $category['title'];
		}

		return $output;
	}

	/**
	 * Gets the phrase name for the title of a smilie category
	 *
	 * @param integer $smilieCategoryId
	 *
	 * @return string
	 */
	public function getSmilieCategoryTitlePhraseName($smilieCategoryId)
	{
		return 'smilie_category_' . $smilieCategoryId . '_title';
	}

	/**
	 * Gets a trophy's master title phrase text.
	 *
	 * @param integer $trophyId
	 *
	 * @return string
	 */
	public function getSmilieCategoryMasterTitlePhraseValue($smilieCategoryId)
	{
		$phraseName = $this->getSmilieCategoryTitlePhraseName($smilieCategoryId);
		return $this->getModelFromCache('XenForo_Model_Phrase')->getMasterPhraseValue($phraseName);
	}

	/**
	 * Fetches all smilies, grouped within smilie categories
	 *
	 * @param boolean Include hidden categories and smilies
	 *
	 * @return array
	 */
	public function getAllSmiliesCategorized($includeHidden = true)
	{
		$smilies = $this->fetchAllKeyed('
			SELECT smilie.*, category.*
			FROM xf_smilie AS smilie
			LEFT JOIN xf_smilie_category AS category ON
				(category.smilie_category_id = smilie.smilie_category_id)
			' . ($includeHidden ? '' : 'WHERE smilie.display_in_editor = 1') . '
			ORDER BY category.display_order, smilie.display_order, smilie.title
		', 'smilie_id');

		$smilieCategories = $this->_getDefaultSmilieCategory();

		foreach ($smilies AS $smilieId => $smilie)
		{
			$smilieCategories[$smilie['smilie_category_id']]['smilie_category_id'] = $smilie['smilie_category_id'];

			$smilieCategories[$smilie['smilie_category_id']]['smilies'][$smilieId] = $smilie;
		}

		if (!$includeHidden && empty($smilieCategories[0]['smilies']))
		{
			unset($smilieCategories[0]);
		}

		return $smilieCategories;
	}

	/**
	 * Fetches all smilie categories (including the default category)
	 * with a sub-array of all smilies within each category
	 *
	 * @return array
	 */
	public function getAllSmilieCategoriesWithSmilies()
	{
		$smilieCategories = $this->_getDefaultSmilieCategory();
		foreach ($this->getAllSmilieCategories() AS $id => $category)
		{
			$smilieCategories[$id] = $category;
		}

		$smilies = $this->getAllSmilies();

		foreach ($smilies AS $smilieId => $smilie)
		{
			$smilieCategories[$smilie['smilie_category_id']]['smilies'][$smilieId] = $smilie;
		}

		return $smilieCategories;
	}

	/**
	 * Creates a record for the default, uncategorized smilie category
	 *
	 * @return array
	 */
	protected function _getDefaultSmilieCategory()
	{
		return array(
			0 => array(
				'smilie_category_id' => 0,
				'smilies' => array()
			)
		);
	}

	/**
	 * Prepares smilie categories and subordinate smilies for display in a categorized list
	 *
	 * @param array $smilieCategories
	 * @param integer $totalSmilies
	 *
	 * @return array
	 */
	public function prepareCategorizedSmiliesForList(array $smilieCategories, &$totalSmilies = 0)
	{
		foreach ($smilieCategories AS $smilieCategoryId => &$smilieCategory)
		{
			$smilieCategory = $this->prepareSmilieCategory($smilieCategory);

			if (!empty($smilieCategory['smilies']))
			{
				$smilieCategory['smilies'] = $this->prepareSmiliesForList($smilieCategory['smilies']);

				$totalSmilies += count($smilieCategory['smilies']);
			}
		}

		return $smilieCategories;
	}

	/**
	 * Prepares smilie categories and subordinate smilies for display in the text editor
	 *
	 * @param array $smilieCategories
	 *
	 * @return array
	 */
	public function prepareCategorizedSmiliesForEditor(array $smilieCategories)
	{
		foreach ($smilieCategories AS $smilieCategoryId => &$smilieCategory)
		{
			$smilieCategory = $this->prepareSmilieCategory($smilieCategory);

			if (!empty($smilieCategory['smilies']))
			{
				foreach ($smilieCategory['smilies'] AS &$smilie)
				{
					$smilie['smilieText'] = preg_split('/\r?\n/', $smilie['smilie_text'], -1, PREG_SPLIT_NO_EMPTY);
				}
			}
		}

		return $smilieCategories;
	}

	/**
	 * Takes an array of display_order => [smilie_id, smilie_category_id]
	 * and updates all smilies (used for the drag/drop UI)
	 *
	 * @param array $order
	 */
	public function massUpdateDisplayOrder(array $order)
	{
		$sqlOrder = '';
		$sqlParent = '';

		$db = $this->_getDb();

		foreach ($order AS $displayOrder => $data)
		{
			$smilieId = $db->quote((int)$data[0]);

			$sqlParent .= "WHEN $smilieId THEN " . $db->quote((int)$data[1]) . "\n";

			$sqlOrder .= "WHEN $smilieId THEN " . $db->quote((int)$displayOrder * 10) . "\n";
		}

		$db->query('
			UPDATE xf_smilie SET
			display_order = CASE smilie_id
			' . $sqlOrder . '
				ELSE 0 END,
			smilie_category_id = CASE smilie_id
			' . $sqlParent . '
				ELSE 0 END
		');

		$this->rebuildSmilieCache();
	}

	/**
	 * Prepares XML to export the specified smilies and their containing categories
	 *
	 * @param array $smilieIds
	 *
	 * @return DOMDocument
	 */
	public function getSmiliesXml(array $smilieIds)
	{
		if ($smilieIds)
		{
			$smilies = $this->fetchAllKeyed('
				SELECT xf_smilie.*,
					xf_smilie_category.display_order AS smilie_category_order
				FROM xf_smilie
				LEFT JOIN xf_smilie_category ON
					(xf_smilie_category.smilie_category_id = xf_smilie.smilie_category_id)
				WHERE xf_smilie.smilie_id IN (' . $this->_getDb()->quote($smilieIds) . ')
				ORDER BY xf_smilie_category.display_order, xf_smilie.display_order, xf_smilie.title
			', 'smilie_id');
		}
		else
		{
			$smilies = array();
		}

		$document = new DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;

		$rootNode = $document->createElement('smilies_export');
		$document->appendChild($rootNode);

		$smiliesNode = $document->createElement('smilies');
		$smilieCategories = array();
		foreach ($smilies AS $smilie)
		{
			$smilieNode = $document->createElement('smilie');

			if ($smilie['smilie_category_id'])
			{
				$smilieCategories[$smilie['smilie_category_id']] = $smilie['smilie_category_order'];
				$smilieNode->setAttribute('smilie_category_id', $smilie['smilie_category_id']);
			}

			$smilieNode->setAttribute('title', $smilie['title']);

			$smilieNode->appendChild($document->createElement('image_url', $smilie['image_url']));

			if ($smilie['sprite_mode'])
			{
				$spriteParamsNode = $document->createElement('sprite_params');

				foreach (unserialize($smilie['sprite_params']) AS $param => $value)
				{
					$spriteParamsNode->setAttribute($param, $value);
				}

				$smilieNode->appendChild($spriteParamsNode);
			}

			foreach (preg_split('/\r?\n/', $smilie['smilie_text'], -1, PREG_SPLIT_NO_EMPTY) AS $smilieText)
			{
				$smilieNode->appendChild($document->createElement('smilie_text', $smilieText));
			}

			$smilieNode->setAttribute('display_order', $smilie['display_order']);
			$smilieNode->setAttribute('display_in_editor', $smilie['display_in_editor']);

			$smiliesNode->appendChild($smilieNode);
		}

		$categoriesNode = $document->createElement('smilie_categories');
		foreach ($smilieCategories AS $smilieCategoryId => $displayOrder)
		{
			if ($smilieCategoryId)
			{
				$categoryNode = $document->createElement('smilie_category');
				$categoryNode->setAttribute('id', $smilieCategoryId);
				$categoryNode->setAttribute('title', $this->getSmilieCategoryMasterTitlePhraseValue($smilieCategoryId));
				$categoryNode->setAttribute('display_order', $displayOrder);

				$categoriesNode->appendChild($categoryNode);
			}
		}

		$rootNode->appendChild($categoriesNode);
		$rootNode->appendChild($smiliesNode);

		return $document;
	}

	public function getSmilieDataFromXml(SimpleXMLElement $document, array $existingSmilieCategoryOptions, &$errors = array())
	{
		if ($document->getName() != 'smilies_export')
		{
			throw new XenForo_Exception(new XenForo_Phrase('provided_file_is_not_valid_smilies_xml'), true);
		}

		$categoryMap = array();
		$smilieCategories = array();
		$smilieCategoryOptions = array();

		foreach ($document->smilie_categories->smilie_category AS $smilieCategory)
		{
			$existingId = array_search((string)$smilieCategory['title'], $existingSmilieCategoryOptions);
			if ($existingId !== false)
			{
				$categoryMap[(int)$smilieCategory['id']] = $existingId;
			}
			else
			{
				$id = (int)$smilieCategory['id'] * -1;
				$categoryMap[(int)$smilieCategory['id']] = $id;

				$smilieCategories[$id] = array(
					'smilie_category_id' => $id,
					'display_order' => (int)$smilieCategory['display_order'],
					'title' => (string)$smilieCategory['title']
				);

				$smilieCategoryOptions[$id] = (string)$smilieCategory['title'];
			}
		}

		$smilies = array();
		$i = 0;

		foreach ($document->smilies->smilie AS $smilie)
		{
			$smilieText = '';
			foreach ($smilie->smilie_text AS $text)
			{
				$smilieText .= (string)$text . "\n";
			}

			if ($smilie->sprite_params)
			{
				$spriteParams = array(
					'w' => (int)$smilie->sprite_params['w'],
					'h' => (int)$smilie->sprite_params['h'],
					'x' => (int)$smilie->sprite_params['x'],
					'y' => (int)$smilie->sprite_params['y'],
				);
			}
			else
			{
				$spriteParams = $this->getDefaultSmilieSpriteParams();
			}

			if ((int)$smilie['smilie_category_id'])
			{
				$smilieCategoryId = $categoryMap[(int)$smilie['smilie_category_id']];
			}
			else
			{
				$smilieCategoryId = 0;
			}

			$smilies[$i] = array(
				'title' => (string)$smilie['title'],
				'display_order' => (int)$smilie['display_order'],
				'display_in_editor' => (int)$smilie['display_in_editor'],
				'image_url' => (string)$smilie->image_url,
				'sprite_mode' => ($smilie->sprite_params ? 1 : 0),
				'sprite_params' => $spriteParams,
				'smilie_text' => trim($smilieText),
				'smilie_category_id' => $smilieCategoryId
			);

			$i++;
		}

		return array(
			'smilies' => $smilies,
			'newSmilieCategories' => $smilieCategories,
			'newSmilieCategoryOptions' => $smilieCategoryOptions
		);
	}

	public function getSmilieDataFromDirectory($directory)
	{
		if (!file_exists($directory) || !is_readable($directory))
		{
			throw new XenForo_Exception(new XenForo_Phrase('invalid_or_unreadable_directory'), true);
		}

		if ($smilieFiles = scandir($directory))
		{
			$smilies = array();
			$i = 0;

			// ensure we have a trailing slash
			if (substr($directory, -1) != '/')
			{
				$directory .= '/';
			}

			$imageTypes = array('jpg', 'jpe', 'jpeg', 'gif', 'png');
			$charReplace = array('-', '_');

			foreach ($smilieFiles AS $smilieFile)
			{
				$filePath = $directory . $smilieFile;
				$suffix = pathinfo($filePath, PATHINFO_EXTENSION);

				if (in_array(strtolower($suffix), $imageTypes))
				{
					$smilies[$i] = array(
						'title' => ucwords(str_replace($charReplace, ' ', basename($filePath, ".$suffix"))),
						'image_url' => $filePath,
						'display_order' => ++$i * 10,
						'display_in_editor' => 1
					);
				}
			}
		}

		return array(
			'smilies' => $smilies,
			'newSmilieCategories' => array(),
			'newSmilieCategoryOptions' => array()
		);
	}

	/**
	 * Returns an array of [phrase_text] => smilie_category_id for all existing smilie categories
	 *
	 * @return array
	 */
	protected function _getExistingSmilieCategoryIdsByTitle()
	{
		return $this->_getDb()->fetchPairs("
			SELECT p.phrase_text, sc.smilie_category_id
			FROM xf_smilie_category AS sc
			INNER JOIN xf_phrase AS p ON
				(p.language_id = 0 AND p.title = CONCAT('smilie_category_', sc.smilie_category_id, '_title'))
			ORDER BY sc.display_order
		");
	}

	public function massImportSmilies(array $smilies, array $smilieCategories, &$errors = array())
	{
		$db = $this->_getDb();

		$categoryMap = array();

		// get existing smilie categories to avoid duplication - just in case
		$existingCategories = $this->_getExistingSmilieCategoryIdsByTitle();

		XenForo_Db::beginTransaction();

		foreach ($smilieCategories AS $smilieCategoryId => $smilieCategory)
		{
			$import = false;

			foreach ($smilies AS $smilie)
			{
				if ($smilie['smilie_category_id'] == $smilieCategoryId)
				{
					// only import categories that contain imported smilies
					$import = true;
					break;
				}
			}

			if ($import)
			{
				if (isset($existingCategories[$smilieCategory['title']]))
				{
					// an existing category has the title of the incoming category, so use it

					$categoryMap[$smilieCategoryId] = $existingCategories[$smilieCategory['title']];
				}
				else
				{
					$dw = XenForo_DataWriter::create('XenForo_DataWriter_SmilieCategory');
					$dw->set('display_order', $smilieCategory['display_order']);
					$dw->setExtraData(XenForo_DataWriter_SmilieCategory::DATA_TITLE, $smilieCategory['title']);

					if ($dwErrors = $dw->getErrors())
					{
						foreach ($dwErrors AS $field => $error)
						{
							$errors[$field . '__' . $smilieCategoryId] = $error;
						}

						XenForo_Db::rollback();
						return;

					}
					else
					{
						$dw->save();
						$categoryMap[$smilieCategoryId] = $dw->get('smilie_category_id');
					}
				}
			}
		}

		$dataWriters = array();

		foreach ($smilies AS $smilieId => $smilie)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Smilie');

			$dw->set('title', $smilie['title']);
			$dw->set('smilie_text', $smilie['smilie_text']);
			$dw->set('image_url', $smilie['image_url']);
			$dw->set('display_order', $smilie['display_order']);
			$dw->set('display_in_editor', $smilie['display_in_editor']);
			$dw->set('sprite_mode', $smilie['sprite_mode']);
			$dw->set('sprite_params', $smilie['sprite_params']);

			if ($smilie['smilie_category_id'])
			{
				if ($smilie['smilie_category_id'] < 0)
				{
					$smilie['smilie_category_id'] = $categoryMap[$smilie['smilie_category_id']];
				}
			}

			$dw->set('smilie_category_id', $smilie['smilie_category_id']);

			if ($dwErrors = $dw->getErrors())
			{
				foreach ($dwErrors AS $field => $error)
				{
					$errors[$field . '__' . $smilieId] = $error;
				}
			}
			else
			{
				$dataWriters[] = $dw;
			}
		}

		if (empty($errors))
		{
			foreach ($dataWriters AS $dw)
			{
				$dw->save();
			}

			XenForo_Db::commit();
		}
		else
		{
			XenForo_Db::rollback();
		}
	}
}