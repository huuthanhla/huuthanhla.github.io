<?php

class XenForo_CronEntry_Sitemap
{
	public static function triggerSitemapRebuild()
	{
		if (XenForo_Application::getOptions()->sitemapAutoRebuild)
		{
			XenForo_Application::defer('Sitemap', array(), 'SitemapAuto');
		}
	}
}