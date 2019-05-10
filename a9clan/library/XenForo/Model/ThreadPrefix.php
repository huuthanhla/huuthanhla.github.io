<?php

/**
 * Model for thread prefixes.
 */
class XenForo_Model_ThreadPrefix extends XenForo_Model
{
	const FETCH_FORUM_PREFIX = 0x01;
	const FETCH_PREFIX_GROUP = 0x02;

	/**
	 * Fetches a single prefix, as defined by its unique prefix ID
	 *
	 * @param integer $prefixId
	 *
	 * @return array
	 */
	public function getPrefixById($prefixId)
	{
		if (!$prefixId)
		{
			return array();
		}

		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_thread_prefix
			WHERE prefix_id = ?
		', $prefixId);
	}

	/**
	 * Get prefixes as defined by conditions and fetch options
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array key: continuous or prefix id, value: array of prefix info
	 */
	public function getPrefixes(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->preparePrefixConditions($conditions, $fetchOptions);

		$orderClause = $this->preparePrefixOrderOptions($fetchOptions, 'prefix.materialized_order');
		$joinOptions = $this->preparePrefixFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$fetchAll = (!empty($fetchOptions['join']) && ($fetchOptions['join'] & self::FETCH_FORUM_PREFIX));

		$query = $this->limitQueryResults('
			SELECT prefix.*
				' . $joinOptions['selectFields'] . '
			FROM xf_thread_prefix AS prefix
				' . $joinOptions['joinTables'] . '
			WHERE ' . $whereConditions . '
			' . $orderClause . '
			', $limitOptions['limit'], $limitOptions['offset']
		);

		return ($fetchAll ? $this->_getDb()->fetchAll($query) : $this->fetchAllKeyed($query, 'prefix_id'));
	}

	/**
	 * Prepares a set of conditions against which to select prefixes.
	 *
	 * @param array $conditions List of conditions.
	 * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	 *
	 * @return string Criteria as SQL for where clause
	 */
	public function preparePrefixConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();

		$db = $this->_getDb();

		if (isset($conditions['prefix_ids']))
		{
			$sqlConditions[] = 'prefix.prefix_id IN(' . $db->quote($conditions['prefix_ids']) . ')';
		}

		if (isset($conditions['node_id']))
		{
			if (is_array($conditions['node_id']))
			{
				$sqlConditions[] = 'fp.node_id IN(' . $db->quote($conditions['node_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'fp.node_id = ' . $db->quote($conditions['node_id']);
			}
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_FORUM_PREFIX);
		}

		if (isset($conditions['node_ids']))
		{
			$sqlConditions[] = 'fp.node_id IN(' . $db->quote($conditions['node_ids']) . ')';
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_FORUM_PREFIX);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Prepares join-related fetch options.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys.
	 */
	public function preparePrefixFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_FORUM_PREFIX)
			{
				$selectFields .= ',
					fp.*';
				$joinTables .= '
					INNER JOIN xf_forum_prefix AS fp ON
						(fp.prefix_id = prefix.prefix_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_PREFIX_GROUP)
			{
				$selectFields .= ',
					prefix_group.display_order AS group_display_order';
				$joinTables .= '
					LEFT JOIN xf_thread_prefix_group AS prefix_group ON
						(prefix_group.prefix_group_id = prefix.prefix_group_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Construct 'ORDER BY' clause
	 *
	 * @param array $fetchOptions (uses 'order' key)
	 * @param string $defaultOrderSql Default order SQL
	 *
	 * @return string
	 */
	public function preparePrefixOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
	{
		$choices = array(
			'materialized_order' => 'prefix.materialized_order',
			'canonical_order' => 'prefix_group.display_order, prefix.display_order',
		);

		if (!empty($fetchOptions['order']) && $fetchOptions['order'] == 'canonical_order')
		{
			$this->addFetchOptionJoin($fetchOptions, self::FETCH_PREFIX_GROUP);
		}

		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}

	/**
	 * Fetches all prefixes, regardless of forum or user group associations
	 *
	 * @return array
	 */
	public function getAllPrefixes()
	{
		return $this->getPrefixes();
	}

	/**
	 * Fetches prefixes in prefix groups
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 * @param integer $prefixCount Reference: counts the total number of prefixes
	 *
	 * @return [group ID => [title, prefixes => prefix]]
	 */
	public function getPrefixesByGroups(array $conditions = array(), array $fetchOptions = array(), &$prefixCount = 0)
	{
		$prefixes = $this->getPrefixes($conditions, $fetchOptions);

		$prefixGroups = array();
		foreach ($prefixes AS $prefix)
		{
			$prefixGroups[$prefix['prefix_group_id']][$prefix['prefix_id']] = $this->preparePrefix($prefix);
		}

		$prefixCount = count($prefixes);

		return $prefixGroups;
	}

	/**
	 * Fetches all prefixes available in the specified forums
	 *
	 * @param integer|array $nodeIds
	 *
	 * @return array
	 */
	public function getPrefixesInForums($nodeId)
	{
		return $this->getPrefixes(is_array($nodeId)
			? array('node_ids' => $nodeId)
			: array('node_id' => $nodeId)
		);
	}

	/**
	 * Fetches all prefixes available in the specified forums
	 *
	 * @param integer|array $nodeIds
	 *
	 * @return array
	 */
	public function getPrefixesInForum($nodeId)
	{
		$output = array();
		foreach ($this->getPrefixes(array('node_id' => $nodeId)) AS $prefix)
		{
			$output[$prefix['prefix_id']] = $prefix;
		}

		return $output;
	}

	/**
	 * Fetches all prefixes usable by the visiting user in the specified forum(s)
	 *
	 * @param integer|array $nodeIds
	 * @param array|null $viewingUser
	 * @param boolean $verifyUsability
	 *
	 * @return array
	 */
	public function getUsablePrefixesInForums($nodeIds, array $viewingUser = null, $verifyUsability = true)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$prefixes = $this->getPrefixesInForums($nodeIds);

		$prefixGroups = array();
		foreach ($prefixes AS $prefix)
		{
			if (!$verifyUsability || $this->_verifyPrefixIsUsableInternal($prefix, $viewingUser))
			{
				$prefixId = $prefix['prefix_id'];
				$prefixGroupId = $prefix['prefix_group_id'];

				if (!isset($prefixGroups[$prefixGroupId]))
				{
					$prefixGroups[$prefixGroupId] = array();

					if ($prefixGroupId)
					{
						$prefixGroups[$prefixGroupId]['title'] = new XenForo_Phrase(
							$this->getPrefixGroupTitlePhraseName($prefixGroupId));
					}

				}

				$prefixGroups[$prefixGroupId]['prefixes'][$prefixId] = $prefix;
			}
		}

		return $prefixGroups;
	}

	public function getPrefixIfInForum($prefixId, $nodeId)
	{
		return $this->_getDb()->fetchRow('
			SELECT prefix.*
			FROM xf_thread_prefix AS prefix
			INNER JOIN xf_forum_prefix AS fp ON (fp.prefix_id = prefix.prefix_id AND fp.node_id = ?)
			WHERE prefix.prefix_id = ?
		', array($nodeId, $prefixId));
	}

	public function getForumAssociationsByPrefix($prefixId)
	{
		return $this->_getDb()->fetchCol('
			SELECT node_id
			FROM xf_forum_prefix
			WHERE prefix_id = ?
		', $prefixId);
	}

	public function getVisiblePrefixIds(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$prefixes = array();

		/** @var $forumModel XenForo_Model_Forum */
		$forumModel = $this->getModelFromCache('XenForo_Model_Forum');

		$results = $this->_getDb()->query("
			SELECT prefix.prefix_id, node.*, forum.*, cache.cache_value AS node_permission_cache
			FROM xf_thread_prefix AS prefix
			INNER JOIN xf_forum_prefix AS fp ON (fp.prefix_id = prefix.prefix_id)
			INNER JOIN xf_node AS node ON (fp.node_id = node.node_id)
			INNER JOIN xf_forum AS forum ON (node.node_id = forum.node_id)
			INNER JOIN xf_permission_cache_content AS cache ON
				(cache.content_type = 'node' AND cache.content_id = node.node_id AND cache.permission_combination_id = ?)
			ORDER BY prefix.materialized_order
		", $viewingUser['permission_combination_id']);
		while ($result = $results->fetch())
		{
			if (isset($prefixes[$result['prefix_id']]))
			{
				continue;
			}

			$permissions = $forumModel->getPermissionsForForum($result);
			if ($forumModel->canViewForum($result, $null, $permissions, $viewingUser))
			{
				$prefixes[$result['prefix_id']] = $result['prefix_id'];
			}
		}

		return $prefixes;
	}

	public function preparePrefix(array $prefix)
	{
		$prefix['title'] = new XenForo_Phrase($this->getPrefixTitlePhraseName($prefix['prefix_id']));

		return $prefix;
	}

	public function preparePrefixes(array $prefixes)
	{
		foreach ($prefixes AS &$prefix)
		{
			$prefix = $this->preparePrefix($prefix);
		}

		return $prefixes;
	}

	public function getPrefixTitlePhraseName($prefixId)
	{
		return 'thread_prefix_' . $prefixId;
	}

	public function updatePrefixForumAssociationByPrefix($prefixId, array $nodeIds)
	{
		$emptyNodeKey = array_search(0, $nodeIds);
		if ($emptyNodeKey !== false)
		{
			unset($nodeIds[$emptyNodeKey]);
		}

		$nodeIds = array_unique($nodeIds);

		$existingNodeIds = $this->getForumAssociationsByPrefix($prefixId);
		if (!$nodeIds && !$existingNodeIds)
		{
			return; // nothing to do
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$db->delete('xf_forum_prefix', 'prefix_id = ' . $db->quote($prefixId));

		foreach ($nodeIds AS $nodeId)
		{
			$db->insert('xf_forum_prefix', array(
				'node_id' => $nodeId,
				'prefix_id' => $prefixId
			));
		}

		$rebuildNodeIds = array_unique(array_merge($nodeIds, $existingNodeIds));
		$this->rebuildPrefixForumAssociationCache($rebuildNodeIds);

		XenForo_Db::commit($db);
	}

	public function updatePrefixForumAssociationByForum($nodeId, array $prefixIds)
	{
		$emptyPrefixKey = array_search(0, $prefixIds);
		if ($emptyPrefixKey !== false)
		{
			unset($prefixIds[$emptyPrefixKey]);
		}

		$prefixIds = array_unique($prefixIds);

		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$db->delete('xf_forum_prefix', 'node_id = ' . $db->quote($nodeId));

		foreach ($prefixIds AS $prefixId)
		{
			$db->insert('xf_forum_prefix', array(
				'node_id' => $nodeId,
				'prefix_id' => $prefixId
			));
		}

		$this->rebuildPrefixForumAssociationCache($nodeId);

		XenForo_Db::commit($db);
	}

	public function rebuildPrefixForumAssociationCache($nodeIds)
	{
		if (!is_array($nodeIds))
		{
			$nodeIds = array($nodeIds);
		}
		if (!$nodeIds)
		{
			return;
		}

		$db = $this->_getDb();

		$newCache = array();

		foreach ($this->getPrefixesInForums($nodeIds) AS $prefix)
		{
			$prefixGroupId = $prefix['prefix_group_id'];
			$newCache[$prefix['node_id']][$prefixGroupId][$prefix['prefix_id']] = $prefix['prefix_id'];
		}

		XenForo_Db::beginTransaction($db);

		foreach ($nodeIds AS $nodeId)
		{
			$update = (isset($newCache[$nodeId]) ? serialize($newCache[$nodeId]) : '');

			$db->update('xf_forum', array(
				'prefix_cache' => $update
			), 'node_id = ' . $db->quote($nodeId));
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Fetches an array of prefixes including prefix group info, for use in <xen:options source />
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getPrefixOptions(array $conditions = array(), array $fetchOptions = array())
	{
		$prefixGroups = $this->getPrefixesByGroups($conditions, $fetchOptions);

		$options = array();

		foreach ($prefixGroups AS $prefixGroupId => $prefixes)
		{
			if ($prefixes)
			{
				if ($prefixGroupId)
				{
					$groupTitle = new XenForo_Phrase($this->getPrefixGroupTitlePhraseName($prefixGroupId));
					$groupTitle = (string)$groupTitle;
				}
				else
				{
					$groupTitle = new XenForo_Phrase('ungrouped');
					$groupTitle = '(' . $groupTitle . ')';
				}

				foreach ($prefixes AS $prefixId => $prefix)
				{
					$options[$groupTitle][$prefixId] = array(
						'value' => $prefixId,
						'label' => (string)$prefix['title'],
						'_data' => array('css' => $prefix['css_class'])
					);
				}
			}
		}

		return $options;
	}

	/**
	 * Returns an array with default values for a new prefix
	 *
	 * @return array
	 */
	public function getDefaultPrefixValues()
	{
		return array(
			'prefix_group_id' => 0,
			'display_order' => 1,
			'css_class' => 'prefix prefixPrimary'
		);
	}

	/**
	 * Fetches the data for the prefix cache
	 *
	 * @return array
	 */
	public function getPrefixCache()
	{
		return $this->_getDb()->fetchPairs('
			SELECT prefix_id, css_class
			FROM xf_thread_prefix
			ORDER BY materialized_order
		');
	}

	/**
	 * Rebuilds the 'threadPrefixes' cache
	 *
	 * @return array
	 */
	public function rebuildPrefixCache()
	{
		$prefixes = $this->getPrefixCache();
		$this->_getDataRegistryModel()->set('threadPrefixes', $prefixes);

		return $prefixes;
	}

	/**
	 * Rebuilds the 'materialized_order' field in the prefix table,
	 * based on the canonical display_order data in the prefix and prefix_group tables.
	 */
	public function rebuildPrefixMaterializedOrder()
	{
		$prefixes = $this->getPrefixes(array(), array('order' => 'canonical_order'));

		$db = $this->_getDb();
		$ungroupedPrefixes = array();
		$updates = array();
		$i = 0;

		foreach ($prefixes AS $prefixId => $prefix)
		{
			if ($prefix['prefix_group_id'])
			{
				if (++$i != $prefix['materialized_order'])
				{
					$updates[$prefixId] = 'WHEN ' . $db->quote($prefixId) . ' THEN ' . $db->quote($i);
				}
			}
			else
			{
				$ungroupedPrefixes[$prefixId] = $prefix;
			}
		}

		foreach ($ungroupedPrefixes AS $prefixId => $prefix)
		{
			if (++$i != $prefix['materialized_order'])
			{
				$updates[$prefixId] = 'WHEN ' . $db->quote($prefixId) . ' THEN ' . $db->quote($i);
			}
		}

		if (!empty($updates))
		{
			$db->query('
				UPDATE xf_thread_prefix SET materialized_order = CASE prefix_id
				' . implode(' ', $updates) . '
				END
				WHERE prefix_id IN(' . $db->quote(array_keys($updates)) . ')
			');
		}
	}

	public function verifyPrefixIsUsable($prefixId, $nodeId, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$prefixId)
		{
			return true; // not picking one, always ok
		}

		$prefix = $this->getPrefixIfInForum($prefixId, $nodeId);
		if (!$prefix)
		{
			return false; // bad prefix or bad node
		}

		return $this->_verifyPrefixIsUsableInternal($prefix, $viewingUser);
	}

	protected function _verifyPrefixIsUsableInternal(array $prefix, array $viewingUser)
	{
		$userGroups = explode(',', $prefix['allowed_user_group_ids']);
		if (in_array(-1, $userGroups) || in_array($viewingUser['user_group_id'], $userGroups))
		{
			return true; // available to all groups or the primary group
		}

		if ($viewingUser['secondary_group_ids'])
		{
			foreach (explode(',', $viewingUser['secondary_group_ids']) AS $userGroupId)
			{
				if (in_array($userGroupId, $userGroups))
				{
					return true; // available to one secondary group
				}
			}
		}

		return false; // not available to any groups
	}

	// prefix groups ---------------------------------------------------------

	/**
	 * Fetches a single prefix group, as defined by its unique prefix group ID
	 *
	 * @param integer $prefixGroupId
	 *
	 * @return array
	 */
	public function getPrefixGroupById($prefixGroupId)
	{
		if (!$prefixGroupId)
		{
			return array();
		}

		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_thread_prefix_group
			WHERE prefix_group_id = ?
		', $prefixGroupId);
	}

	public function getAllPrefixGroups()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_thread_prefix_group
			ORDER BY display_order
		', 'prefix_group_id');
	}

	public function getPrefixGroupOptions($selectedGroupId)
	{
		$prefixGroups = $this->getAllPrefixGroups();
		$prefixGroups = $this->preparePrefixGroups($prefixGroups);

		$options = array();

		foreach ($prefixGroups AS $prefixGroupId => $prefixGroup)
		{
			$options[$prefixGroupId] = $prefixGroup['title'];
		}

		return $options;
	}

	public function mergePrefixesIntoGroups(array $prefixes, array $prefixGroups)
	{
		$merge = array();

		foreach ($prefixGroups AS $prefixGroupId => $prefixGroup)
		{
			if (isset($prefixes[$prefixGroupId]))
			{
				$merge[$prefixGroupId] = $prefixes[$prefixGroupId];
				unset($prefixes[$prefixGroupId]);
			}
			else
			{
				$merge[$prefixGroupId] = array();
			}
		}

		if (!empty($prefixes))
		{
			foreach ($prefixes AS $prefixGroupId => $_prefixes)
			{
				$merge[$prefixGroupId] = $_prefixes;
			}
		}

		return $merge;
	}

	public function getPrefixGroupTitlePhraseName($prefixGroupId)
	{
		return 'thread_prefix_group_' . $prefixGroupId;
	}

	public function preparePrefixGroups(array $prefixGroups)
	{
		return array_map(array($this, 'preparePrefixGroup'), $prefixGroups);
	}

	public function preparePrefixGroup(array $prefixGroup)
	{
		$prefixGroup['title'] = new XenForo_Phrase($this->getPrefixGroupTitlePhraseName($prefixGroup['prefix_group_id']));

		return $prefixGroup;
	}
}