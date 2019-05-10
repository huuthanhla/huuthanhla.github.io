<?php

class XenForo_Deferred_UserRevertMessageEdit extends XenForo_Deferred_Abstract
{
	public function canTriggerManually()
	{
		return false;
	}

	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'userId' => null,
			'cutOff' => null,
			'count' => 0,
			'total' => null
		), $data);

		if (!$data['userId'] || $data['cutOff'] === null)
		{
			return false;
		}

		$s = microtime(true);

		/* @var $editHistoryModel XenForo_Model_EditHistory */
		$editHistoryModel = XenForo_Model::create('XenForo_Model_EditHistory');

		$edits = $editHistoryModel->getEditHistoryByUserSinceDate($data['userId'], $data['cutOff']);
		if (!$edits)
		{
			return false;
		}

		if ($data['total'] === null)
		{
			$data['total'] = count($edits);
		}

		$continue = false;

		foreach ($edits AS $edit)
		{
			$editHistoryModel->revertToHistoryId($edit['edit_history_id']);

			$data['count']++;

			if ($targetRunTime && microtime(true) - $s > $targetRunTime)
			{
				$continue = true;
				break;
			}
		}

		if (!$continue)
		{
			return false;
		}

		$actionPhrase = new XenForo_Phrase('reverting_edits');
		$status = sprintf('%s... %s (%s/$s)', $actionPhrase,
			XenForo_Locale::numberFormat($data['count']), XenForo_Locale::numberFormat($data['total'])
		);

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}