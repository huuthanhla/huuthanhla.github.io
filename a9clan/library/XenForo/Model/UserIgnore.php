<?php

class XenForo_Model_UserIgnore extends XenForo_Model
{
	public function getIgnoredUsers($userId)
	{
		return $this->fetchAllKeyed('
			SELECT user.*
			FROM xf_user_ignored AS ignored
			INNER JOIN xf_user AS user ON (ignored.ignored_user_id = user.user_id)
			WHERE ignored.user_id = ?
			ORDER BY user.username
		', 'user_id', $userId);
	}

	public function getUserIgnoreCache($userId)
	{
		return $this->_getDb()->fetchPairs('
			SELECT user.user_id, user.username
			FROM xf_user_ignored AS ignored
			INNER JOIN xf_user AS user ON
				(ignored.ignored_user_id = user.user_id
				AND user.is_admin = 0 AND user.is_moderator = 0
				AND user.user_id <> ignored.user_id)
			WHERE ignored.user_id = ?
			ORDER BY user.username
		', $userId);
	}

	public function getUserIdsIgnoringUser($userId)
	{
		return $this->_getDb()->fetchCol('
			SELECT user_id
			FROM xf_user_ignored
			WHERE ignored_user_id = ?
		', $userId);
	}

	public function rebuildUserIgnoreCacheByIgnoring($ignoredUserId)
	{
		foreach ($this->getUserIdsIgnoringUser($ignoredUserId) AS $ignoringUserId)
		{
			$this->rebuildUserIgnoreCache($ignoringUserId);
		}
	}

	public function rebuildUserIgnoreCache($userId)
	{
		try
		{
			$users = $this->getUserIgnoreCache($userId);
			$this->_getUserModel()->update($userId, 'ignored', serialize($users));
		}
		catch (XenForo_Exception $e)
		{
			$users = array();
		}

		return $users;
	}

	public function ignoreUsers($userId, $ignoredUserIds)
	{
		if (!is_array($ignoredUserIds))
		{
			if (!$ignoredUserIds)
			{
				return false;
			}

			$ignoredUserIds = array($ignoredUserIds);
		}


		if (!$ignoredUserIds || !$userId)
		{
			return false;
		}

		foreach ($ignoredUserIds AS $ignoredUserId)
		{
			$this->_getDb()->query('
				INSERT IGNORE INTO xf_user_ignored
					(user_id, ignored_user_id)
				VALUES
					(?, ?)
			', array($userId, $ignoredUserId));
		}

		return $this->rebuildUserIgnoreCache($userId);
	}

	public function unignoreUser($userId, $ignoredUserId)
	{
		$db = $this->_getDb();

		$db->delete('xf_user_ignored',
			sprintf('user_id = %s AND ignored_user_id = %s', $db->quote($userId), $db->quote($ignoredUserId))
		);

		return $this->rebuildUserIgnoreCache($userId);
	}

	public function canIgnoreUser($userId, array $user, &$error = '')
	{
		$setError = (func_num_args() >= 3);

		if (!$userId)
		{
			return false;
		}

		if ($user['is_staff'])
		{
			if ($setError)
			{
				$error = new XenForo_Phrase('staff_members_may_not_be_ignored');
			}
			return false;
		}

		if ($userId == $user['user_id'])
		{
			if ($setError)
			{
				$error = new XenForo_Phrase('you_may_not_ignore_yourself');
			}
			return false;
		}

		return true;
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}