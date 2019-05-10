<?php

class XenForo_Model_Stats extends XenForo_Model
{
	protected $_statsHandlerCache = array();

	protected $_statsTypes = array();

	protected $_statsTypeHandlerLookupMap = array();

	public function getStatsTypePhrases(array $statsTypes)
	{
		$phrases = array();

		foreach ($this->_getStatsContentTypeHandlerNames() AS $statsType => $statsHandlerName)
		{
			$statsHandler = $this->_getStatsHandler($statsHandlerName);

			$phrases = array_merge($phrases, $statsHandler->getStatsTypes());
		}

		return $phrases;
	}

	public function getStatsData($start, $end, array $statsTypes, $grouping = 'daily')
	{
		$db = $this->_getDb();

		if ($grouping == 'monthly')
		{
			$data = $db->fetchAll('
				SELECT AVG(stats_date) AS grouping,
					stats_type, SUM(counter) AS counter
				FROM xf_stats_daily
				WHERE stats_date BETWEEN ? AND ?
					AND stats_type IN(' . $db->quote($statsTypes) . ')
				GROUP BY YEAR(FROM_UNIXTIME(stats_date)), MONTH(FROM_UNIXTIME(stats_date)), stats_type
			', array($start, $end));
		}
		else if ($grouping == 'weekly')
		{
			$data = $db->fetchAll('
				SELECT AVG(stats_date) AS grouping,
					stats_type, SUM(counter) AS counter
				FROM xf_stats_daily
				WHERE stats_date BETWEEN ? AND ?
					AND stats_type IN(' . $db->quote($statsTypes) . ')
				GROUP BY YEAR(FROM_UNIXTIME(stats_date)), WEEKOFYEAR(FROM_UNIXTIME(stats_date)), stats_type
			', array($start, $end));
		}
		else
		{
			$data = $db->fetchAll('
				SELECT stats_date AS grouping, stats_type, counter
				FROM xf_stats_daily
				WHERE stats_date BETWEEN ? AND ?
					AND stats_type IN(' . $db->quote($statsTypes) . ')
				ORDER BY stats_date
			', array($start, $end));
		}

		$dataGrouped = array();

		$handlerNames = $this->getStatsTypeHandlerLookupMap();

		foreach ($data AS $stat)
		{
			$handler = $this->_getStatsHandler($handlerNames[$stat['stats_type']]);

			$dataGrouped[$stat['stats_type']][intval($stat['grouping'])] = $handler->getCounterForDisplay($stat['stats_type'], $stat['counter']);
		}

		return $dataGrouped;
	}

	public function prepareGraphData(array $data, $grouping = 'daily')
	{
		$plot = array();
		$dateMap = array();

		$keys = array_keys($data);

		$date = reset($keys);
		$maxDate = end($keys);

		// stats are generated based on UTC
		$utcTz = new DateTimeZone('UTC');

		if ($grouping == 'monthly')
		{
			list($year, $month) = explode('/', gmdate("Y/m", $date));
			list($endYear, $endMonth) = explode('/', gmdate("Y/m", $maxDate));

			$year = intval($year);
			$month = intval($month);
			$endYear = intval($endYear);
			$endMonth = intval($endMonth);

			while ($year < $endYear || ($year == $endYear && $month <= $endMonth))
			{
				$k = intval($year . sprintf("%02d", $month));
				$plot[$k] = array($k, 0);
				$dateMap[$k] = new XenForo_Phrase("month_{$month}_short") . " $year";

				$month++;
				if ($month > 12)
				{
					$month = 1;
					$year++;
				}
			}

			foreach ($data AS $k => $v)
			{
				$newK = intval(gmdate("Ym", $k));
				$plot[$newK] = array($newK, floatval($v));
				$dateMap[$newK] = XenForo_Locale::date($k, 'M Y', null, $utcTz);
			}
		}
		else if ($grouping == 'weekly')
		{
			list($year, $week) = explode('/', gmdate("Y/W", $date));
			list($endYear, $endWeek) = explode('/', gmdate("Y/W", $maxDate));

			$year = intval($year);
			$week = intval($week);
			$endYear = intval($endYear);
			$endWeek = intval($endWeek);
			$maxWeekNum = gmdate('W', gmmktime(12, 0, 0, 12, 31, $year));

			while ($year < $endYear || ($year == $endYear && $week <= $endWeek))
			{
				$weekPrint = sprintf("%02d", $week);
				$k = intval($year . $weekPrint);
				$plot[$k] = array($k, 0);
				$dateMap[$k] = "W$weekPrint $year";

				$week++;
				if ($week > $maxWeekNum)
				{
					$week = 1;
					$year++;
					$maxWeekNum = gmdate('W', gmmktime(12, 0, 0, 12, 31, $year));
				}
			}

			foreach ($data AS $k => $v)
			{
				$newK = intval(gmdate("YW", $k));
				$plot[$newK] = array($newK, floatval($v));
				$dateMap[$newK] = XenForo_Locale::date($k, '\WW Y', null, $utcTz);
			}
		}
		else
		{
			while ($date <= $maxDate)
			{
				$dateMap[$date] = XenForo_Locale::date($date, 'absolute', null, $utcTz);

				$value = (isset($data[$date]) ? $data[$date] : 0);
				$plot[$date] = array($date * 1000, floatval($value));

				$date += 86400;
			}
		}

		ksort($plot);
		return array(
			'plot' => array_values($plot),
			'dateMap' => $dateMap
		);
	}

	public function filterGraphDataDates(array $plots, array $dateMap)
	{
		$dates = array();
		foreach ($dateMap AS $map)
		{
			$dates += $map;
		}
		ksort($dates);

		$dateIds = array_keys($dates);
		$dateIdMap = array_flip($dateIds);

		foreach ($plots AS &$plot)
		{
			foreach ($plot AS &$data)
			{
				$data[0] = $dateIdMap[$data[0]];
			}
		}

		foreach ($dateMap AS $type => $dates)
		{
			$new = array();
			foreach ($dates AS $k => $v)
			{
				$new[$dateIdMap[$k]] = $v;
			}
			$dateMap[$type] = $new;
		}

		return array(
			'plots' => $plots,
			'dateMap' => $dateMap
		);
	}

	/**
	 * Fetch all stats handler content types
	 *
	 * @return array
	 */
	protected function _getStatsContentTypeHandlerNames()
	{
		$classes = array();
		foreach ($this->getContentTypesWithField('stats_handler_class') AS $class)
		{
			if (class_exists($class))
			{
				$classes[] = $class;
			}
		}

		return $classes;
	}

	/**
	 * Fetch all stats types
	 *
	 * @return array
	 */
	public function getStatsTypes()
	{
		if (empty($this->_statsTypes))
		{
			$this->_statsTypes = array();

			foreach ($this->_getStatsContentTypeHandlerNames() AS $contentType => $statsHandlerName)
			{
				$this->_statsTypes[$contentType] = $this->_getStatsHandler($statsHandlerName)->getStatsTypes();
			}
		}

		return $this->_statsTypes;
	}

	/**
	 * Fetch an array allowing a stats type to be mapped back to its stats handler
	 *
	 * @return array
	 */
	public function getStatsTypeHandlerLookupMap()
	{
		if (empty($this->_statsTypeHandlerLookupMap))
		{
			$this->_statsTypeHandlerLookupMap = array();

			foreach ($this->_getStatsContentTypeHandlerNames() AS $contentType => $statsHandlerName)
			{
				foreach ($this->_getStatsHandler($statsHandlerName)->getStatsTypes() AS $statsType => $_null)
				{
					$this->_statsTypeHandlerLookupMap[$statsType] = $statsHandlerName;
				}
			}
		}

		return $this->_statsTypeHandlerLookupMap;
	}

	/**
	 * Fetch options for a list of stats types to be used with <xen:options source="{this}" />
	 *
	 * @param array $selected Selected options
	 *
	 * @return array
	 */
	public function getStatsTypeOptions(array $selected = array())
	{
		$statsTypeOptions = array();

		foreach ($this->getStatsTypes() AS $contentType => $statsTypes)
		{
			foreach ($statsTypes AS $statsType => $statsTypePhrase)
			{
				$statsTypeOptions[$contentType][] = array(
					'name' => "statsTypes[]",
					'value' => $statsType,
					'label' => $statsTypePhrase,
					'selected' => in_array($statsType, $selected)
				);
			}
		}

		return $statsTypeOptions;
	}

	/**
	 * Fetch a stats handler
	 *
	 * @param string $statsHandlerName
	 *
	 * @return XenForo_StatsHandler_Abstract
	 */
	protected function _getStatsHandler($statsHandlerName)
	{
		$statsHandlerName = XenForo_Application::resolveDynamicClass($statsHandlerName);
		if (!isset($this->_statsHandlerCache[$statsHandlerName]))
		{
			$this->_statsHandlerCache[$statsHandlerName] = new $statsHandlerName;
		}

		return $this->_statsHandlerCache[$statsHandlerName];
	}

	/**
	 * Deletes ALL data from the xf_stats_daily table. Use with care!
	 */
	public function deleteStats()
	{
		$this->_getDb()->delete('xf_stats_daily');
	}

	public function buildStatsData($start, $end)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		foreach ($this->_getStatsContentTypeHandlerNames() AS $contentType => $handlerClassName)
		{
			$handlerClass = $this->_getStatsHandler($handlerClassName);

			$data = $handlerClass->getData($start, $end);

			foreach ($data AS $statsType => $records)
			{
				$statsType = $db->quote($statsType);

				foreach ($records AS $date => $counter)
				{
					$date = $db->quote($date);
					$counter = $db->quote($counter);

					$db->query("
						INSERT INTO xf_stats_daily
							(stats_date, stats_type, counter)
						VALUES
							($date, $statsType, $counter)
						ON DUPLICATE KEY UPDATE
							counter = $counter
					");
				}
			}
		}

		XenForo_Db::commit($db);
	}
}