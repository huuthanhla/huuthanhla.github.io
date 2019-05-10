<?php

/**
 * User group model.
 *
 * @package XenForo_UserGroups
 */
class XenForo_Model_UserGroup extends XenForo_Model
{
	/**
	 * Gets a user group by its ID.
	 *
	 * @param $id
	 *
	 * @return array|false User group info
	 */
	public function getUserGroupById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_user_group
			WHERE user_group_id = ?
		', $id);
	}

	/**
	 * Gets all user groups, ordered by title.
	 *
	 * @return array Format: [] => (array) user group info
	 */
	public function getAllUserGroups()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_user_group
			ORDER BY title
		', 'user_group_id');
	}

	/**
	 * Gets the title of all user groups in alphabetical order, with the
	 * user group ID as the key.
	 *
	 * @return array Format: [user group id] => title
	 */
	public function getAllUserGroupTitles()
	{
		return $this->_getDb()->fetchPairs('
			SELECT user_group_id, title
			FROM xf_user_group
			ORDER BY title
		');
	}

	/**
	 * Gets a list of user groups as options (for checkbox/multi-select usage).
	 *
	 * @param string|array $selectedGroupIds Array or comma delimited list
	 *
	 * @return array
	 */
	public function getUserGroupOptions($selectedGroupIds)
	{
		if (!is_array($selectedGroupIds))
		{
			$selectedGroupIds = ($selectedGroupIds ? explode(',', $selectedGroupIds) : array());
		}

		$userGroups = array();
		foreach ($this->getAllUserGroups() AS $userGroup)
		{
			$userGroups[] = array(
				'label' => $userGroup['title'],
				'value' => $userGroup['user_group_id'],
				'selected' => in_array($userGroup['user_group_id'], $selectedGroupIds)
			);
		}

		return $userGroups;
	}

	/**
	 * Gets the default user group information (for populating the add group page)
	 *
	 * @return array
	 */
	public function getDefaultUserGroup()
	{
		return array(
			'user_group_id' => 0,
			'title' => '',
			'user_display_style_id' => 0,
			'banner_css_class' => 'userBanner bannerPrimary',
			'banner_text' => ''
		);
	}

	/**
	 * Gets user ID-primary pairs for the users that belong to the specified group.
	 *
	 * @param integer $userGroupId
	 * @param integer $limit
	 * @param integer $offset
	 *
	 * @return array [user id] => 1 if this is the user's primary group, 0 otherwise
	 */
	public function getUserIdsInUserGroup($userGroupId, $limit = 0, $offset = 0)
	{
		return $this->_getDb()->fetchPairs($this->limitQueryResults(
			'
				SELECT user_id, is_primary
				FROM xf_user_group_relation
				WHERE user_group_id = ?
				ORDER BY user_id
			', $limit, $offset
		), $userGroupId);
	}

	/**
	 * Removes the specified user group from all users that have it.
	 *
	 * @param integer $userGroupId
	 * @param integer $primaryReplacementId Replacement primary user group ID
	 * @param integer $limit
	 * @param integer $offset
	 *
	 * @return array [primary user ids, secondary user ids]
	 */
	public function removeUserGroupFromUsers($userGroupId, $primaryReplacementId, $limit = 0, $offset = 0)
	{
		$primaryUsers = array();
		$secondaryUsers = array();
		foreach ($this->getUserIdsInUserGroup($userGroupId, $limit, $offset) AS $userId => $isPrimary)
		{
			if ($isPrimary)
			{
				$primaryUsers[] = $userId;
			}
			else
			{
				$secondaryUsers[] = $userId;
			}
		}

		$this->changePrimaryUserGroupForUsers($primaryUsers, $primaryReplacementId);
		$this->removeSecondaryGroupFromUsers($secondaryUsers, $userGroupId);

		return array($primaryUsers, $secondaryUsers);
	}

	/**
	 * Changes the primary user group for all the specified users.
	 *
	 * @param array $userIds
	 * @param integer $newPrimaryGroupId
	 */
	public function changePrimaryUserGroupForUsers(array $userIds, $newPrimaryGroupId)
	{
		if (!$userIds)
		{
			return;
		}

		$db = $this->_getDb();

		$userIdsQuoted = $db->quote($userIds);

		XenForo_Db::beginTransaction($db);

		$db->update('xf_user',
			array('user_group_id' => $newPrimaryGroupId),
			'user_id IN (' . $userIdsQuoted . ')'
		);
		$db->delete('xf_user_group_relation',
			'user_id IN (' . $userIdsQuoted . ') AND user_group_id = ' . $db->quote($newPrimaryGroupId)
				. ' AND is_primary = 0'
		);
		$db->update('xf_user_group_relation',
			array('user_group_id' => $newPrimaryGroupId),
			'user_id IN (' . $userIdsQuoted . ') AND is_primary = 1'
		);

		$this->_getPermissionModel()->updateUserPermissionCombinations($userIds);

		XenForo_Db::commit($db);
	}

	/**
	 * Removes the specified group as a secondary group for specified users.
	 *
	 * @param array $userIds
	 * @param integer $removeGroupId
	 */
	public function removeSecondaryGroupFromUsers(array $userIds, $removeGroupId)
	{
		if (!$userIds)
		{
			return;
		}

		$db = $this->_getDb();

		$userIdsQuoted = $db->quote($userIds);

		XenForo_Db::beginTransaction($db);

		$db->delete('xf_user_group_relation',
			'user_id IN (' . $userIdsQuoted . ') AND user_group_id = ' . $db->quote($removeGroupId)
				. ' AND is_primary = 0'
		);

		$users = $db->fetchPairs('
			SELECT user_id, secondary_group_ids
			FROM xf_user
			WHERE user_id IN (' . $userIdsQuoted . ')'
		);
		foreach ($users AS $userId => $groups)
		{
			if (!$groups)
			{
				continue;
			}

			$groupList = explode(',', $groups);
			$key = array_search($removeGroupId, $groupList);
			if ($key !== false)
			{
				unset($groupList[$key]);
				$db->update('xf_user',
					array('secondary_group_ids' => implode(',', $groupList)),
					'user_id = ' . $db->quote($userId)
				);
			}
		}

		$this->_getPermissionModel()->updateUserPermissionCombinations($userIds);

		XenForo_Db::commit($db);
	}

	public function deletePermissionCombinationsForUserGroup($userGroupId)
	{
		$permissionModel = $this->_getPermissionModel();

		$combinations = $this->_getPermissionModel()->getPermissionCombinationsByUserGroupId($userGroupId);

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		foreach (array_keys($combinations) AS $combinationId)
		{
			$permissionModel->deletePermissionCombination($combinationId);
		}

		XenForo_Db::commit($db);

		return array_keys($combinations);
	}

	/**
	 * Updates an existing user group or inserts a new one, then updates (or inserts)
	 * global permissions for that group.
	 *
	 * @param integer $userGroupId An existing user group ID, or 0 to create a new one
	 * @param array $userGroupInfo Array of info to update or insert: Key-value format
	 * @param array $permissions
	 *
	 * @return integer The user group that was updated/created
	 */
	public function updateUserGroupAndPermissions($userGroupId, array $userGroupInfo, array $permissions)
	{
		XenForo_Db::beginTransaction();

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserGroup');
		if ($userGroupId)
		{
			$dw->setExistingData($userGroupId);
		}

		$dw->bulkSet($userGroupInfo);
		$dw->save();
		$userGroupId = $dw->get('user_group_id');

		$this->_getPermissionModel()->updateGlobalPermissionsForUserCollection($permissions, $userGroupId);

		XenForo_Db::commit();

		return $userGroupId;
	}

	public function recalculateUserGroupDisplayStylePriority($userGroupId, $oldPriority, $newPriority)
	{
		if ($oldPriority == $newPriority)
		{
			return;
		}

		$userGroups = $this->getAllUserGroups();

		$betweenGroupIds = array();

		$lowerBound = min($oldPriority, $newPriority);
		$upperBound = max($oldPriority, $newPriority);

		foreach ($userGroups AS $userGroup)
		{
			if ($userGroup['display_style_priority'] >= $lowerBound
				&& $userGroup['display_style_priority'] < $upperBound
				&& $userGroup['user_group_id'] != $userGroupId
			)
			{
				$betweenGroupIds[] = $userGroup['user_group_id'];
			}
		}

		if (!$betweenGroupIds)
		{
			return;
		}

		if ($newPriority > $oldPriority)
		{
			// moving up: all who have this group as highest stay; switch to this if highest
			// was between old and new priorities and member of this group
			$updateUserIds = $this->_db->fetchCol('
				SELECT user.user_id
				FROM xf_user AS user
				INNER JOIN xf_user_group_relation AS user_group_relation ON
					(user_group_relation.user_id = user.user_id AND user_group_relation.user_group_id = ?)
				WHERE user.display_style_group_id IN (' . $this->_db->quote($betweenGroupIds) . ')
			', $userGroupId);
			if ($updateUserIds)
			{
				$this->_db->update('xf_user',
					array('display_style_group_id' => $userGroupId),
					'user_id IN (' . $this->_db->quote($updateUserIds) . ')'
				);
			}
		}
		else
		{
			// moving down: need to recalculate for users that have this group as highest
			// but only need to for users that have more than one group
			$updateRes = $this->_db->query('
				SELECT user.user_id, user_group.display_style_priority, user_group.user_group_id
				FROM xf_user AS user
				INNER JOIN xf_user_group_relation AS user_group_relation ON
					(user.user_id = user_group_relation.user_id
					AND user_group_relation.user_group_id <> user.display_style_group_id)
				INNER JOIN xf_user_group AS user_group ON
					(user_group.user_group_id = user_group_relation.user_group_id)
				WHERE user.display_style_group_id = ?
				GROUP BY user.user_id
			', $userGroupId);


			$updateGroups = array();
			$updatePriorities = array();
			while ($update = $updateRes->fetch())
			{
				// seed with current data if not set
				if (!isset($updateGroups[$update['user_id']]))
				{
					$updateGroups[$update['user_id']] = $userGroupId;
					$updatePriorities[$update['user_id']] = $newPriority;
				}

				// if higher, update
				if ($update['display_style_priority'] > $updatePriorities[$update['user_id']])
				{
					$updateGroups[$update['user_id']] = $update['user_group_id'];
					$updatePriorities[$update['user_id']] = $update['display_style_priority'];
				}
			}

			foreach ($updateGroups AS $userId => $displayGroupId)
			{
				// if ==, then no change from the current display group id
				if ($displayGroupId != $userGroupId)
				{
					$this->_db->update('xf_user',
						array('display_style_group_id' => $displayGroupId),
						'user_id = ' . $this->_db->quote($userId)
					);
				}
			}
		}
	}

	public function getDisplayStyleGroupIdForCombination(array $groupIds)
	{
		if (!$groupIds)
		{
			return 0;
		}

		$db = $this->_getDb();

		$displayGroupId = $db->fetchOne($this->limitQueryResults(
			'
				SELECT user_group_id
				FROM xf_user_group
				WHERE user_group_id IN (' . $db->quote($groupIds) . ')
				ORDER BY display_style_priority DESC
			', 1
		));

		if (!$displayGroupId)
		{
			$displayGroupId = end($groupIds);
		}

		return $displayGroupId;
	}

	public function rebuildDisplayStyleCache()
	{
		$userGroups = $this->getAllUserGroups();
		$cache = array();
		foreach ($userGroups AS $userGroup)
		{
			$cache[$userGroup['user_group_id']] = array(
				'username_css' => $userGroup['username_css'],
				'user_title' => $userGroup['user_title']
			);
		}

		$this->_getDataRegistryModel()->set('displayStyles', $cache);

		// need to force css updates
		$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();

		return $cache;
	}

	public function rebuildUserBannerCache()
	{
		$userGroups = $this->getAllUserGroups();
		$banners = array();
		$sort = array();
		foreach ($userGroups AS $userGroup)
		{
			if ($userGroup['banner_text'])
			{
				$banners[$userGroup['user_group_id']] = array(
					'class' => $userGroup['banner_css_class'],
					'text' => $userGroup['banner_text']
				);
				$sort[$userGroup['user_group_id']] = $userGroup['display_style_priority'];
			}
		}

		arsort($sort);
		$cache = array();
		foreach ($sort AS $groupId => $null)
		{
			$cache[$groupId] = $banners[$groupId];
		}

		$this->_getDataRegistryModel()->set('userBanners', $cache);

		return $cache;
	}

	/**
	 * Return results for admin quick search
	 *
	 * @param string Keywords for which to search
	 *
	 * @return array
	 */
	public function getUserGroupsForAdminQuickSearch($searchText)
	{
		$likeString = XenForo_Db::quoteLike($searchText, 'lr', $this->_getDb());

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_user_group
			WHERE title LIKE ' . $likeString . '
				OR user_title LIKE ' . $likeString . '
			ORDER BY title
		', 'user_group_id');
	}

	/**
	 * Get permission model.
	 *
	 * @return XenForo_Model_Permission
	 */
	protected function _getPermissionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Permission');
	}
}