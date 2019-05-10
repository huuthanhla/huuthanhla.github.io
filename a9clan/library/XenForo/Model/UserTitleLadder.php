<?php

class XenForo_Model_UserTitleLadder extends XenForo_Model
{
	/**
	 * Gets the user title ladder
	 *
	 * @return array [minimum level] => info
	 */
	public function getUserTitleLadder()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_user_title_ladder
			ORDER BY minimum_level
		', 'minimum_level');
	}

	/**
	 * Updates the given set of user titles. The set is assumed to be all user titles,
	 * as the existing ones are removed before updating.
	 *
	 * @param array $titles [] => [title, minimum_level]
	 * @param boolean $rebuildCache If true, rebuilds the user title cache
	 */
	public function updateUserTitleLadder(array $titles, $rebuildCache = true)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$db->delete('xf_user_title_ladder');

		foreach ($titles AS $titleInfo)
		{
			if (isset($titleInfo['title'], $titleInfo['minimum_level']))
			{
				$this->insertUserTitleLadderEntry($titleInfo['title'], $titleInfo['minimum_level'], false);
			}
		}

		if ($rebuildCache)
		{
			$this->rebuildUserTitleLadderCache();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Inserts a new user title ladder entry. Throws an exception if error occurs.
	 *
	 * @param string $title
	 * @param integer $minimumLevel
	 * @param boolean $rebuildCache
	 */
	public function insertUserTitleLadderEntry($title, $minimumLevel, $rebuildCache = true)
	{
		$minimumLevel = intval($minimumLevel);
		if ($minimumLevel < 0)
		{
			$minimumLevel = 0;
		}

		$existing = $this->_getDb()->fetchRow('
			SELECT minimum_level
			FROM xf_user_title_ladder
			WHERE minimum_level = ?
		', $minimumLevel);
		if ($existing)
		{
			throw new XenForo_Exception(new XenForo_Phrase('trophy_already_exists_for_x_points', array('count' => $minimumLevel)), true);
		}

		$this->_getDb()->insert('xf_user_title_ladder', array(
			'minimum_level' => $minimumLevel,
			'title' => utf8_substr($title, 0, 50)
		));

		if ($rebuildCache)
		{
			$this->rebuildUserTitleLadderCache();
		}
	}

	/**
	 * Deletes the specified user titles.
	 *
	 * @param array $levels List of minimum point values to delete
	 * @param boolean $rebuildCache
	 */
	public function deleteUserTitleLadderEntries(array $levels, $rebuildCache = true)
	{
		if (!$levels)
		{
			return;
		}

		$db = $this->_getDb();
		$db->delete('xf_user_title_ladder', 'minimum_level IN (' . $db->quote($levels) . ')');

		if ($rebuildCache)
		{
			$this->rebuildUserTitleLadderCache();
		}
	}

	/**
	 * Rebuilds the user title ladder cache.
	 *
	 * @return array [minimum_level] => title
	 */
	public function rebuildUserTitleLadderCache()
	{
		$titles = $this->getUserTitleLadder();
		$cache = array();
		foreach ($titles AS $title)
		{
			$cache[$title['minimum_level']] = $title['title'];
		}

		krsort($cache, SORT_NUMERIC);

		$this->_getDataRegistryModel()->set('userTitleLadder', $cache);
		return $cache;
	}
}