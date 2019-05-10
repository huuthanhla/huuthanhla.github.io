<?php

class XenForo_Importer_IPBoard32x extends XenForo_Importer_IPBoard
{
	public static function getName()
	{
		return 'IP.Board 3.2/3.3';
	}

	/**
	 * (non-PHPdoc)
	 * @see XenForo_Importer_IPBoard::getSteps()
	 */
	public function getSteps()
	{
		$steps = parent::getSteps();

		unset($steps['profileComments']);

		return $steps;
	}

	/**
	 * (non-PHPdoc)
	 * @see XenForo_Importer_IPBoard::_getUserGroupAvatarPerms($userGroup)
	 */
	protected function _getUserGroupAvatarPerms(array $userGroup)
	{
		// did IPB remove avatar permissions in 3.2?
		return array(
			'allowed' => true,
			'maxFileSize' => 51200
		);
	}

	/**
	 * (non-PHPdoc)
	 * @see XenForo_Importer_IPBoard::_getStatusUpdates($start, $limit)
	 */
	protected function _getStatusUpdates($start, $limit)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		return $sDb->fetchAll($sDb->limit(
			'
				SELECT msus.*,
					members.name AS status_author_name
				FROM ' . $prefix . 'member_status_updates AS msus
				INNER JOIN ' . $prefix . 'members AS members ON
					(msus.status_author_id = members.member_id)
				WHERE msus.status_id > ' . $sDb->quote($start) . '
				ORDER BY msus.status_id
			', $limit
		));
	}

	/**
	 * (non-PHPdoc)
	 * @see XenForo_Importer_IPBoard::_getStatusUpdateUserIdMap($model, $statusUpdates)
	 */
	protected function _getStatusUpdateUserIdMap(XenForo_Model_Import $model, array $statusUpdates)
	{
		return $model->getUserIdsMapFromArray($statusUpdates, array('status_member_id', 'status_author_id'));
	}

	/**
	 * (non-PHPdoc)
	 * @see XenForo_Importer_IPBoard::_getStatusUpdateUserInfo($statusUpdate, $userIdMap)
	 */
	protected function _getStatusUpdateUserInfo(array $statusUpdate, array $userIdMap)
	{
		$profileUserId = $this->_mapLookUp($userIdMap, $statusUpdate['status_member_id']);
		$userId = $this->_mapLookUp($userIdMap, $statusUpdate['status_author_id']);
		$username = $statusUpdate['status_author_name'];
		$ip = $statusUpdate['status_author_ip'];

		return array($profileUserId, $userId, $username, $ip);
	}

	/**
	 * (non-PHPdoc)
	 * @see XenForo_Importer_IPBoard::_importStatusUpdateExtra($statusUpdate, $profilePostId, $profilePost)
	 */
	protected function _importStatusUpdateExtra(array $statusUpdate, $profilePostId, array $profilePost)
	{
		$this->_importStatusUpdateLikes($statusUpdate, $profilePostId, $profilePost);

		return parent::_importStatusUpdateExtra($statusUpdate, $profilePostId, $profilePost);
	}

	protected function _importStatusUpdateLikes(array $statusUpdate, $profilePostId, array $profilePost)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		$likes = $sDb->fetchAll('
			SELECT like_rel_id, like_member_id, like_added
			FROM ' . $prefix . 'core_like
			WHERE like_rel_id = ' . $sDb->quote($statusUpdate['status_id']) . '
				AND like_app = \'members\'
				AND like_area = \'status\'
				AND like_is_anon = 0
				AND like_visible = 1
		');

		if ($likes)
		{
			$userIdMap = $model->getUserIdsMapFromArray($likes, 'like_member_id');

			foreach ($likes AS $like)
			{
				$model->importLike(
					'profile_post', $profilePostId,
					$profilePost['user_id'],
					$this->_mapLookUp($userIdMap, $like['like_member_id']),
					$like['like_added']
				);
			}
		}
	}

	/**
	 * Imports thread watch records for the given thread
	 *
	 * @param integer $threadId Imported XenForo thread ID
	 * @param array $sourceThread IPB source thread data
	 */
	protected function _importThreadWatch($threadId, array $sourceThread)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;
		$model = $this->_importModel;

		$subs = $sDb->fetchPairs('
			SELECT like_member_id, like_notify_freq
			FROM ' . $prefix . 'core_like
			WHERE like_rel_id = ' . $sDb->quote($sourceThread['tid']) . '
				AND like_app = \'forums\'
				AND like_area = \'topics\'
				AND like_notify_do = 1
		');
		if ($subs)
		{
			$userIdMap = $model->getImportContentMap('user', array_keys($subs));
			foreach ($subs AS $userId => $emailUpdate)
			{
				$newUserId = $this->_mapLookUp($userIdMap, $userId);
				if (!$newUserId)
				{
					continue;
				}

				$model->importThreadWatch($newUserId, $threadId, ($emailUpdate == 'none' || empty($emailUpdate) ? 0 : 1));
			}
		}
	}
}