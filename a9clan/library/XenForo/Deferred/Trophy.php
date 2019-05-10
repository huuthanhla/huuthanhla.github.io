<?php

class XenForo_Deferred_Trophy extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 100
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $trophyModel XenForo_Model_Trophy */
		$trophyModel = XenForo_Model::create('XenForo_Model_Trophy');
		$trophies = $trophyModel->getAllTrophies();
		if (!$trophies)
		{
			return true;
		}

		/* @var $userModel XenForo_Model_User */
		$userModel = XenForo_Model::create('XenForo_Model_User');

		$userIds = $userModel->getUserIdsInRange($data['position'], $data['batch']);
		if (sizeof($userIds) == 0)
		{
			return true;
		}

		$data['position'] = end($userIds);

		$users = $userModel->getUsers(array(
			'user_state' => 'valid',
			'is_banned' => 0,
			'user_id' => $userIds
		), array(
			'join' => XenForo_Model_User::FETCH_USER_FULL
		));

		$userTrophies = $trophyModel->getUserTrophiesByUserIds(array_keys($users));

		foreach ($users AS $user)
		{
			$trophyModel->updateTrophiesForUser(
				$user,
				isset($userTrophies[$user['user_id']]) ? $userTrophies[$user['user_id']] : array(),
				$trophies
			);
			$trophyModel->updateTrophyPointsForUser($user['user_id']);
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('trophies');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}