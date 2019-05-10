<?php

class XenForo_Deferred_ThreadDelete extends XenForo_Deferred_Abstract
{
	public function canTriggerManually()
	{
		return false;
	}

	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		if (!isset($data['conditions']))
		{
			$data = array('conditions' => $data);
		}

		$data = array_merge(array(
			'conditions' => false,
			'count' => 0,
			'total' => null
		), $data);

		if (!$data['conditions'])
		{
			return false;
		}

		$s = microtime(true);

		/* @var $threadModel XenForo_Model_Thread */
		$threadModel = XenForo_Model::create('XenForo_Model_Thread');

		if ($data['total'] === null)
		{
			$data['total'] = $threadModel->countThreads($data['conditions']);
			if (!$data['total'])
			{
				return false;
			}
		}

		$threadIds = $threadModel->getThreadIds($data['conditions'], array('limit' => 1000));
		if (!$threadIds)
		{
			return false;
		}

		$continue = count($threadIds) < 1000 ? false : true;

		foreach ($threadIds AS $threadId)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($threadId))
			{
				$dw->delete();
			}

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

		$actionPhrase = new XenForo_Phrase('deleting');
		$typePhrase = new XenForo_Phrase('threads');
		$status = sprintf('%s... %s (%s/%s)', $actionPhrase, $typePhrase,
			XenForo_Locale::numberFormat($data['count']), XenForo_Locale::numberFormat($data['total'])
		);

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}