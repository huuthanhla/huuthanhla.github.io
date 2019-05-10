<?php

class XenForo_Model_AdminSearch extends XenForo_Model
{
	/**
	 * Cache for all search type definition data
	 *
	 * @var array
	 */
	protected $_typesCache = array();

	/**
	 * Cache for all search type handlers
	 *
	 * @var array
	 */
	protected $_typeHandlerCache = array();

	/**
	 * Search all registered search types for the given search text.
	 *
	 * @param string $searchText
	 *
	 * @return array
	 */
	public function search($searchText)
	{
		$searchHandlers = $this->getAllSearchTypeHandlers(
			XenForo_Application::get('options')->adminSearchExclusions,
			XenForo_Visitor::getInstance()
		);

		$phraseConditions = array();
		foreach ($searchHandlers AS $searchType => $handler)
		{
			$phraseConditions[$searchType] = $handler->getPhraseConditions();
		}
		$phrases = $this->_getPhraseMatches($searchText, $phraseConditions);

		$searchResults = array();
		foreach ($searchHandlers AS $searchType => $handler)
		{
			$searchResults[$searchType] = $handler->search($searchText, $phrases[$searchType]);
		}

		return $searchResults;
	}

	/**
	 * Searches phrases for the given search text, when the phrase title
	 * matches constraints set by $phraseConditions, then returns those
	 * results in groups according to the title constraint they matched.
	 *
	 * @param string $searchText
	 * @param array $phraseConditions [type => [like => SQL Like string, regex => regex match]]
	 * @param array $viewingUser
	 *
	 * @return array
	 */
	protected function _getPhraseMatches($searchText, array $phraseConditions)
	{
		$db = $this->_getDb();

		// build the title constraints
		$titleConditions = array();
		foreach ($phraseConditions AS $searchType => $phraseCondition)
		{
			if ($phraseCondition)
			{
				$titleConditions[$searchType] = 'title LIKE ' . $phraseCondition['like'];
			}
		}

		// there were no title constraints, so bypass all the heavy lifting
		if (!$titleConditions)
		{
			// build an array that looks like we did all the stuff below but found nothing
			return array_fill_keys(array_keys($phraseConditions), array());
		}

		// get the ID of the language within which to search
		$languageId = XenForo_Visitor::getInstance()->language_id;
		if (!$languageId)
		{
			$languageId = XenForo_Application::get('options')->defaultLanguageId;
		}

		$phraseIds = $db->fetchCol('
			SELECT title
			FROM xf_phrase_compiled
			WHERE language_id = ?
			AND phrase_text LIKE ' . XenForo_Db::quoteLike($searchText, 'lr', $db) . '
			AND (
				' . implode(' OR
				', $titleConditions) . '
			)
		', $languageId);

		// Divide the found phrases into groups using the phrase condition regexes
		$phrases = array();
		foreach ($phraseConditions AS $searchType => $phraseCondition)
		{
			$phrases[$searchType] = array();

			if ($phraseCondition)
			{
				foreach ($phraseIds AS $i => $phraseId)
				{
					if (preg_match($phraseCondition['regex'], $phraseId, $match))
					{
						$phrases[$searchType][] = $match[1];
						unset($phraseIds[$i]);
					}
				}
			}
		}

		return $phrases;
	}

	/**
	 * Fetches all XenForo_AdminSearch_Abstract handlers.
	 *
	 * @param array Exclusions
	 *
	 * @return array
	 */
	public function getAllSearchTypeHandlers(array $exclusions = array(), XenForo_Visitor $visitor = null)
	{
		$searchTypes = $this->getAllSearchTypes();
		$searchTypeHandlers = array();

		foreach ($searchTypes AS $searchType => $handler)
		{
			if (empty($exclusions[$searchType]))
			{
				if ($handler = $this->getHandler($searchType))
				{
					if (is_null($visitor) || ($visitor && $visitor->hasAdminPermission($handler->getAdminPermission())))
					{
						$searchTypeHandlers[$searchType] = $handler;
					}
				}
			}
		}

		return $searchTypeHandlers;
	}

	/**
	 * Fetches all admin search types
	 *
	 * @param boolean If true, fetch the data from the DB instead of the data registry
	 *
	 * @return array
	 */
	public function getAllSearchTypes($fromDb = false)
	{
		if (empty($this->_typesCache) || $fromDb)
		{
			$this->_typesCache = $this->_getDb()->fetchPairs('
				SELECT search_type, handler_class
				FROM xf_admin_search_type
				ORDER BY display_order
			');
		}
		else
		{
			$this->_typesCache = XenForo_Application::get('adminSearchTypes');
		}

		return $this->_typesCache;
	}

	/**
	 * Get the name of the search type handler for a single search type
	 *
	 * @param string $searchType
	 *
	 * @return string
	 */
	public function getSearchTypeHandlerName($searchType)
	{
		if (empty($this->_typesCache))
		{
			$this->_typesCache = $this->getAllSearchTypes();
		}

		if (array_key_exists($searchType, $this->_typesCache))
		{
			return $this->_typesCache[$searchType];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get the admin search handler for the specified type.
	 *
	 * @param string $searchType
	 *
	 * @return XenForo_AdminSearchHandler_Abstract|false
	 */
	public function getHandler($searchType)
	{
		if (!isset($this->_typeHandlerCache[$searchType]))
		{
			$handlerName = $this->getSearchTypeHandlerName($searchType);
			$handlerName = XenForo_Application::resolveDynamicClass($handlerName);

			if ($handlerName && class_exists($handlerName))
			{
				$this->_typeHandlerCache[$searchType] = new $handlerName;
			}
			else
			{
				return false;
			}
		}

		return $this->_typeHandlerCache[$searchType];
	}

	/**
	 * Rebuilds the admin search type cache.
	 *
	 * @return array Smilie cache
	 */
	public function rebuildSearchTypesCache()
	{
		$searchTypes = $this->getAllSearchTypes(true);
		$this->_getDataRegistryModel()->set('adminSearchTypes', $searchTypes);

		return $searchTypes;
	}

	// perhaps for future use...

	/**
	 * Index content into the admin search index
	 *
	 * @param string $searchType
	 * @param array $item
	 * @param XenForo_DataWriter $dw
	 *
	 * @return integer Insert ID
	 */
	/*public function index($searchType, array $item, XenForo_DataWriter $dw = null)
	{
		if ($handler = $this->getHandler($searchType))
		{
			if ($indexData = $handler->index($item, $dw))
			{
				list($searchType, $contentId, $contentContainerId, $primaryData, $secondaryData) = $indexData;

				return $this->_index($searchType, $contentId, $contentContainerId, $primaryData, $secondaryData);
			}
		}

		return false;
	}*/

	/**
	 * Write index data into the admin search index
	 *
	 * @param string $searchType
	 * @param string $contentId
	 * @param string $contentContainerId
	 * @param string $primaryData
	 * @param string $secondaryData
	 *
	 * @return integer
	 */
	/*protected function _index($searchType, $contentId, $contentContainerId, $primaryData, $secondaryData = '')
	{
		$db = $this->_getDb();

		$db->query('
			INSERT INTO xf_admin_search_index
				(search_type, content_id, content_container_id, primary_data, secondary_data)
			VALUES
				(?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				content_container_id = VALUES(content_container_id),
				primary_data = VALUES(primary_data),
				secondary_data = VALUES(secondary_data)
		', array($searchType, $contentId, $contentContainerId, $primaryData, $secondaryData));

		return $db->lastInsertId();
	}*/

	/*CREATE TABLE IF NOT EXISTS `xf_admin_search_index` (
	  `search_type` varchar(25) NOT NULL,
	  `content_id` varchar(250) NOT NULL,
	  `content_container_id` varchar(250) NOT NULL,
	  `primary_data` mediumtext NOT NULL,
	  `secondary_data` mediumtext NOT NULL,
	  PRIMARY KEY (`search_type`,`content_id`),
	  FULLTEXT KEY `search_fields` (`primary_data`,`secondary_data`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;*/
}