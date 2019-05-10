<?php

class XenForo_Deferred_UserGroupDelete extends XenForo_Deferred_Abstract
{
	public function canTriggerManually()
	{
		return false;
	}

	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'userGroupId' => 0,
			'displayPriority' => 0,
			'count' => 0
		), $data);

		if (!$data['userGroupId'])
		{
			return false;
		}

		$s = microtime(true);

		/* @var $groupModel XenForo_Model_UserGroup */
		$groupModel = XenForo_Model::create('XenForo_Model_UserGroup');

		$limit = 100;

		do
		{
			$results = $groupModel->removeUserGroupFromUsers($data['userGroupId'], XenForo_Model_User::$defaultRegisteredGroupId, $limit);
			if (!$results[0] && !$results[1])
			{
				$groupModel->recalculateUserGroupDisplayStylePriority($data['userGroupId'], $data['displayPriority'], -1);
				$groupModel->rebuildDisplayStyleCache();
				$groupModel->rebuildUserBannerCache();

				return false;
			}

			$data['count'] += $limit;
		}
		while ($targetRunTime && microtime(true) - $s < $targetRunTime);

		$actionPhrase = new XenForo_Phrase('deleting');
		$typePhrase = new XenForo_Phrase('user_group');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['count']));

		return $data;
	}
}