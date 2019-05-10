<?php

class XenForo_ControllerAdmin_Stats extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('viewStatistics');
	}

	public function actionIndex()
	{
		return $this->responseReroute(__CLASS__, 'daily');
	}

	public function getStatsData($grouping, $defaultStart)
	{
		$tz = new DateTimeZone('GMT');

		if (!$start = $this->_input->filterSingle('start', XenForo_Input::DATE_TIME, array('timeZone' => $tz)))
		{
			$start = $defaultStart;
		}

		if (!$end = $this->_input->filterSingle('end', XenForo_Input::DATE_TIME, array('dayEnd' => true, 'timeZone' => $tz)))
		{
			$end = XenForo_Application::$time;
		}

		if (!$statsTypes = $this->_input->filterSingle('statsTypes', XenForo_Input::ARRAY_SIMPLE))
		{
			$statsTypes = array('post', 'post_like');
		}

		$statsModel = $this->_getStatsModel();

		$groupings = array(
			'daily' => array(
				'printDateFormat' => 'absolute',
				'xAxisTime' => true
			),
			'monthly' => array(
				'printDateFormat' => 'M Y',
				'groupDateFormat' => 'Ym'
			),
			'weekly' => array(
				'printDateFormat' => '\WW Y',
				'groupDateFormat' => 'YW'
			)
		);

		if (!isset($groupings[$grouping]))
		{
			$grouping = 'daily';
		}

		$groupingConfig = $groupings[$grouping];

		$plots = $statsModel->getStatsData($start, $end, $statsTypes, $grouping);
		$dateMap = array();

		foreach ($plots AS $type => $plot)
		{
			$output = $statsModel->prepareGraphData($plot, $grouping);

			$plots[$type] = $output['plot'];
			$dateMap[$type] = $output['dateMap'];
		}

		if (empty($groupingConfig['xAxisTime']))
		{
			$output = $statsModel->filterGraphDataDates($plots, $dateMap);
			$plots = $output['plots'];
			$dateMap = $output['dateMap'];
		}

		$viewParams = array(
			'plots' => $plots,
			'dateMap' => $dateMap,
			'start' => $start,
			'end' => $end,
			'endDisplay' => ($end >= XenForo_Application::$time ? 0 : $end),
			'statsTypeOptions' => $statsModel->getStatsTypeOptions($statsTypes),
			'statsTypePhrases' => $statsModel->getStatsTypePhrases($statsTypes),
			'datePresets' => XenForo_Helper_Date::getDatePresets(),
			'grouping' => $grouping,
			'groupingConfig' => $groupingConfig
		);

		return $viewParams;
	}

	public function actionMonthly()
	{
		$viewParams = $this->getStatsData('monthly', strtotime('-1 year'));

		return $this->responseView('XenForo_ViewAdmin_Stats_Monthly', 'stats_monthly', $viewParams);
	}

	public function actionWeekly()
	{
		$viewParams = $this->getStatsData('weekly', strtotime('-1 year'));

		return $this->responseView('XenForo_ViewAdmin_Stats_Weekly', 'stats_weekly', $viewParams);
	}

	public function actionDaily()
	{
		$viewParams = $this->getStatsData('daily', strtotime('-1 month'));

		return $this->responseView('XenForo_ViewAdmin_Stats_Daily', 'stats_daily', $viewParams);
	}

	/**
	 * @return XenForo_Model_Stats
	 */
	protected function _getStatsModel()
	{
		return $this->getModelFromCache('XenForo_Model_Stats');
	}
}