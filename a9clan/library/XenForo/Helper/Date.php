<?php

class XenForo_Helper_Date
{
	static $presets = array
	(
		'1day' => array('-1 day', '1_day_ago'),
		'1week' => array('-1 week', '1_week_ago'),
		'2weeks' => array('-2 weeks', '2_weeks_ago'),
		'1month' => array('-1 month', '1_month_ago'),
		'3months' => array('-3 months', '3_months_ago'),
		'6months' => array('-6 months', '6_months_ago'),
		'9months' => array('-9 months', '9_months_ago'),
		'1year' => array('-1 year', '1_year_ago'),
		'2years' => array('-2 years', '2_years_ago'),
	);

	/**
	 * Gets an array of offsets from the given timestamp for convenient date preset values
	 *
	 * @param integer|null $timeStamp
	 *
	 * @return array [1week => 2011-08-30, 1year => 2010-09-06, ...]
	 */
	public static function getDatePresets($timeStamp = null)
	{
		if (is_null($timeStamp))
		{
			$timeStamp = XenForo_Application::$time;
		}

		$presets = array();

		foreach (self::$presets AS $period => $presetData)
		{
			$date = new DateTime('@' . $timeStamp);
			$date->modify($presetData[0]);
			$presets[$date->format('Y-m-d')] = new XenForo_Phrase($presetData[1]);
		}

		return $presets;
	}
}