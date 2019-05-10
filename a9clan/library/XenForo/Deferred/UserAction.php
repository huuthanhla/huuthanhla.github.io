<?php

class XenForo_Deferred_UserAction extends XenForo_Deferred_Abstract
{
	public function canTriggerManually()
	{
		return false;
	}

	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'criteria' => null,
			'start' => 0,
			'count' => 0,
			'total' => null,
			'userIds' => null,
			'actions' => array()
		), $data);

		$s = microtime(true);

		/* @var $userModel XenForo_Model_User */
		$userModel = XenForo_Model::create('XenForo_Model_User');

		if (is_array($data['criteria']))
		{
			$userIds = $userModel->getUserIds($data['criteria'], $data['start'], 1000);
		}
		else if (is_array($data['userIds']))
		{
			$userIds = $data['userIds'];
		}
		else
		{
			$userIds = array();
		}

		if (!$userIds)
		{
			return false;
		}

		$limitTime = ($targetRunTime > 0);

		XenForo_Db::beginTransaction();

		foreach ($userIds AS $key => $userId)
		{
			$data['count']++;
			$data['start'] = $userId;
			unset($userIds[$key]);

			/* @var $userDw XenForo_DataWriter_User */
			$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
			if ($userDw->setExistingData($userId))
			{
				if ($userModel->isUserSuperAdmin($userDw->getMergedData()))
				{
					// no updating of super admins
					continue;
				}

				if (!empty($data['actions']['delete']))
				{
					if (!$userDw->get('is_admin') && !$userDw->get('is_moderator'))
					{
						$userDw->delete();
					}
				}
				else
				{
					if (!empty($data['actions']['set_primary_group_id']))
					{
						$userDw->set('user_group_id', $data['actions']['set_primary_group_id']);
					}

					$groups = explode(',', $userDw->get('secondary_group_ids'));

					if (!empty($data['actions']['add_group_id']))
					{
						$groups[] = $data['actions']['add_group_id'];
						$userDw->setSecondaryGroups($groups);
					}

					if (!empty($data['actions']['remove_group_id']))
					{
						$searchKey = array_search(intval($data['actions']['remove_group_id']), $groups);
						if ($searchKey !== false)
						{
							unset($groups[$searchKey]);
						}
						$userDw->setSecondaryGroups($groups);
					}

					if (!empty($data['actions']['discourage']))
					{
						$userDw->set('is_discouraged', 1);
					}

					if (!empty($data['actions']['undiscourage']))
					{
						$userDw->set('is_discouraged', 0);
					}

					if (!empty($data['actions']['remove_avatar']))
					{
						XenForo_Model::create('XenForo_Model_Avatar')->deleteAvatar($userId);
					}

					if (!empty($data['actions']['remove_signature']))
					{
						$userDw->set('signature', '');
					}

					if (!empty($data['actions']['remove_homepage']))
					{
						$userDw->set('homepage', '');
					}

					if (!empty($data['actions']['custom_title']))
					{
						$userDw->set('custom_title', $data['actions']['custom_title']);
					}

					if (!empty($data['actions']['ban']) && !$userDw->get('is_admin') && !$userDw->get('is_moderator'))
					{
						if ($ban = XenForo_Model::create('XenForo_Model_Banning')->getBannedUserById($userId))
						{
							$existing = true;
						}
						else
						{
							$existing = false;
						}

						$userModel->ban($userId, 0, '', $existing);
					}

					if (!empty($data['actions']['unban']))
					{
						$userModel->liftBan($userId);
					}

					$userDw->save();
				}
			}

			if ($limitTime && microtime(true) - $s > $targetRunTime)
			{
				break;
			}
		}

		XenForo_Db::commit();

		if (is_array($data['userIds']) && !$userIds)
		{
			return false;
		}

		if (is_array($data['userIds']))
		{
			$data['userIds'] = $userIds;
		}

		$actionPhrase = new XenForo_Phrase('updating');
		$typePhrase = new XenForo_Phrase('users');
		if ($data['total'])
		{
			$status = sprintf('%s... %s (%d/%d)', $actionPhrase, $typePhrase, $data['count'], $data['total']);
		}
		else
		{
			$status = sprintf('%s... %s (%d)', $actionPhrase, $typePhrase, $data['count']);
		}

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}