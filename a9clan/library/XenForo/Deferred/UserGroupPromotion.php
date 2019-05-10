<?php

class XenForo_Deferred_UserGroupPromotion extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 100
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $promotionModel XenForo_Model_UserGroupPromotion */
		$promotionModel = XenForo_Model::create('XenForo_Model_UserGroupPromotion');
		$promotions = $promotionModel->getPromotions(array(
			'active' => 1
		));
		if (!$promotions)
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

		$userPromotionStates = $promotionModel->getPromotionStatesByUserIds(array_keys($users));

		foreach ($users AS $userId => $user)
		{
			$promotionModel->updatePromotionsForUser(
				$user,
				isset($userPromotionStates[$userId]) ? $userPromotionStates[$userId] : array(),
				$promotions
			);
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('user_group_promotions');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}