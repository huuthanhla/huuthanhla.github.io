<?php

class XenForo_Deferred_User extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 70
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $userModel XenForo_Model_User */
		$userModel = XenForo_Model::create('XenForo_Model_User');

		/* @var $conversationModel XenForo_Model_Conversation */
		$conversationModel = XenForo_Model::create('XenForo_Model_Conversation');

		$userIds = $userModel->getUserIdsInRange($data['position'], $data['batch']);
		if (sizeof($userIds) == 0)
		{
			return true;
		}

		foreach ($userIds AS $userId)
		{
			$data['position'] = $userId;

			/* @var $userDw XenForo_DataWriter_User */
			$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
			if ($userDw->setExistingData($userId))
			{
				XenForo_Db::beginTransaction();

				$userDw->set('alerts_unread', $userModel->getUnreadAlertsCount($userId));
				$userDw->set('conversations_unread', $conversationModel->countUnreadConversationsForUser($userId));
				$userDw->save();
				$userDw->rebuildUserGroupRelations();
				$userDw->rebuildPermissionCombinationId();
				$userDw->rebuildDisplayStyleGroupId();
				$userDw->rebuildCustomFields();
				$userDw->rebuildIgnoreCache();

				XenForo_Db::commit();
			}
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('users');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}