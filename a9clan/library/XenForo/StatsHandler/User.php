<?php

class XenForo_StatsHandler_User extends XenForo_StatsHandler_Abstract
{
	public function getStatsTypes()
	{
		return array(
			'user_registration' => new XenForo_Phrase('user_registrations'),
			'user_activity' => new XenForo_Phrase('users_active'),
		);
	}

	public function getData($startDate, $endDate)
	{
		$userRegistrations = $this->_getDb()->fetchPairs(
			$this->_getBasicDataQuery('xf_user', 'register_date'),
			array($startDate, $endDate)
		);

		// this will only ever fetch the past 24 hours
		$usersActive = $this->_getDb()->fetchPairs('
			SELECT ' . (XenForo_Application::$time - XenForo_Application::$time % 86400) . ',
				COUNT(*)
			FROM xf_user
			WHERE last_activity > ?
		', XenForo_Application::$time - 86400); // 24 hours ago

		return array(
			'user_registration' => $userRegistrations,
			'user_activity' => $usersActive,
		);
	}
}