<?php

class XenForo_Deferred_Cron extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$s = microtime(true);

		/* @var $cronModel XenForo_Model_Cron */
		$cronModel = XenForo_Model::create('XenForo_Model_Cron');

		XenForo_Application::defer('Cron', array(), 'cron', false, XenForo_Application::$time + 300);

		$entries = $cronModel->getCronEntriesToRun();
		foreach ($entries AS $entry)
		{
			if (!$cronModel->updateCronRunTimeAtomic($entry))
			{
				continue;
			}

			try
			{
				$cronModel->runEntry($entry);
			}
			catch (Exception $e)
			{
				// suppress so we don't get stuck
				XenForo_Error::logException($e);
			}

			$runTime = microtime(true) - $s;
			if ($targetRunTime && $runTime > $targetRunTime)
			{
				break;
			}
		}

		$cronModel->updateMinimumNextRunTime();

		return false;
	}
}