<?php

class XenForo_CronEntry_EmailBounce
{
	/**
	 * Imports feeds.
	 */
	public static function process()
	{
		XenForo_Model::create('XenForo_Model_EmailBounce')->pruneEmailBounceLogs();
		XenForo_Model::create('XenForo_Model_EmailBounce')->pruneSoftBounceHistory();

		XenForo_Application::defer('EmailBounce', array(), 'EmailBounce');
	}
}