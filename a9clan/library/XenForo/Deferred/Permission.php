<?php

class XenForo_Deferred_Permission extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'startCombinationId' => 0,
			'position' => 0
		), $data);

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('permissions');
		$status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

		/* @var $permissionModel XenForo_Model_Permission */
		$permissionModel = XenForo_Model::create('XenForo_Model_Permission');

		$result = $permissionModel->rebuildPermissionCache($targetRunTime, $data['startCombinationId']);
		if ($result === true)
		{
			return false;
		}
		else
		{
			$data['startCombinationId'] = $result;
			$data['position']++;

			return $data; // continue again
		}
	}
}