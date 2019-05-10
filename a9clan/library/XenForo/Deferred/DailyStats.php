<?php

class XenForo_Deferred_DailyStats extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 100,
			'delete' => false
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $statsModel XenForo_Model_Stats */
		$statsModel = XenForo_Model::create('XenForo_Model_Stats');

		if ($data['position'] == 0)
		{
			// delete old stats cache if required
			if (!empty($data['delete']))
			{
				$statsModel->deleteStats();
			}

			// an appropriate date from which to start... first thread, or earliest user reg?
			$data['position'] = min(
				XenForo_Model::create('XenForo_Model_Thread')->getEarliestThreadDate(),
				XenForo_Model::create('XenForo_Model_User')->getEarliestRegistrationDate()
			);

			// start on a 24 hour increment point
			$data['position'] = $data['position'] - $data['position'] % 86400;
		}
		else if ($data['position'] > XenForo_Application::$time)
		{
			return true;
		}

		$endPosition = $data['position'] + $data['batch'] * 86400;

		$statsModel->buildStatsData($data['position'], $endPosition);

		$data['position'] = $endPosition;

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('daily_statistics');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::date($data['position'], 'absolute'));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}