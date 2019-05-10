<?php

class XenForo_Model_LinkProxy extends XenForo_Model
{
	public function logVisit($url)
	{
		if (!$url || !preg_match('#^https?://#i', $url))
		{
			throw new InvalidArgumentException('Invalid URL');
		}

		$time = XenForo_Application::$time;
		$hash = md5($url);

		$this->_getDb()->query('
			INSERT INTO xf_link_proxy
				(url, url_hash, first_request_date, last_request_date, hits)
			VALUES
				(?, ?, ?, ?, 1)
			ON DUPLICATE KEY UPDATE
				last_request_date = VALUES(last_request_date),
				hits = hits + 1
		', array($url, $hash, $time, $time));
	}

	/**
	 * Prepares a collection of link proxy fetching related conditions into an SQL clause
	 *
	 * @param array $conditions List of conditions
	 * @param array $fetchOptions Modifiable set of fetch options (may have joins pushed on to it)
	 *
	 * @return string SQL clause (at least 1=1)
	 */
	public function prepareLinkProxyConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['url']))
		{
			if (is_array($conditions['url']))
			{
				$sqlConditions[] = 'link_proxy.url LIKE ' . XenForo_Db::quoteLike($conditions['url'][0], $conditions['url'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'link_proxy.url LIKE ' . XenForo_Db::quoteLike($conditions['url'], 'lr', $db);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function getLinkProxyLogs(array $conditions = array(), array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$whereConditions = $this->prepareLinkProxyConditions($conditions, $fetchOptions);

		$orderBy = 'last_request_date';
		if (!empty($fetchOptions['order']))
		{
			switch ($fetchOptions['order'])
			{
				case 'last_request_date':
				case 'first_request_date':
				case 'hits':
					$orderBy = $fetchOptions['order'];
			}
		}

		return $this->fetchAllKeyed($this->limitQueryResults(
			"
				SELECT link_proxy.*
				FROM xf_link_proxy AS link_proxy
				WHERE $whereConditions
				ORDER BY link_proxy.$orderBy DESC
			", $limitOptions['limit'], $limitOptions['offset']
		), 'url');
	}

	public function countLinkProxyItems(array $conditions = array())
	{
		$fetchOptions = array();
		$whereConditions = $this->prepareLinkProxyConditions($conditions, $fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM xf_link_proxy AS link_proxy
			WHERE $whereConditions
		");
	}

	/**
	 * Prunes unused link proxy log entries.
	 *
	 * @param null|int $pruneDate
	 *
	 * @return int
	 */
	public function pruneLinkProxyLogs($pruneDate = null)
	{
		if ($pruneDate === null)
		{
			if (!XenForo_Application::getOptions()->imageLinkProxyLogLength)
			{
				return 0;
			}

			$pruneDate = XenForo_Application::$time - (86400 * XenForo_Application::getOptions()->imageLinkProxyLogLength);
		}

		return $this->_getDb()->delete('xf_link_proxy', 'last_request_date < ' . intval($pruneDate));
	}
}