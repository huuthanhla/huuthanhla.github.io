<?php

class XenForo_CronEntry_Warnings
{
	public static function expireWarnings()
	{
		XenForo_Model::create('XenForo_Model_Warning')->processExpiredWarnings();
	}
}