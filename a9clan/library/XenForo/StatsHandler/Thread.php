<?php

class XenForo_StatsHandler_Thread extends XenForo_StatsHandler_Abstract
{
	public function getStatsTypes()
	{
		return array(
			'thread' => new XenForo_Phrase('threads')
		);
	}

	public function getData($startDate, $endDate)
	{
		$threads = $this->_getDb()->fetchPairs(
			$this->_getBasicDataQuery('xf_thread', 'post_date', 'discussion_state = ?'),
			array($startDate, $endDate, 'visible')
		);

		return array(
			'thread' => $threads
		);
	}
}