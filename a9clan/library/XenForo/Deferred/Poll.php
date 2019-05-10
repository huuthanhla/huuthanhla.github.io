<?php

class XenForo_Deferred_Poll extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 100
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $pollModel XenForo_Model_Poll */
		$pollModel = XenForo_Model::create('XenForo_Model_Poll');

		$pollIds = $pollModel->getPollIdsInRange($data['position'], $data['batch']);
		if (count($pollIds) == 0)
		{
			return false;
		}

		foreach ($pollIds AS $pollId)
		{
			$data['position'] = $pollId;
			$pollModel->rebuildPollData($pollId);
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('polls');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}