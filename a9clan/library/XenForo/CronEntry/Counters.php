<?php

/**
 * Cron entry for timed counter updates.
 *
 * @package XenForo_Cron
 */
class XenForo_CronEntry_Counters
{
	/**
	 * Rebuilds the board totals counter.
	 */
	public static function rebuildBoardTotals()
	{
		XenForo_Model::create('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
	}

	/**
	 * Log daily statistics
	 */
	public static function recordDailyStats()
	{
		// get the the timestamp of 00:00 UTC for today
		$time = XenForo_Application::$time - XenForo_Application::$time % 86400;

		XenForo_Model::create('XenForo_Model_Stats')->buildStatsData($time - 86400, $time);
	}
}