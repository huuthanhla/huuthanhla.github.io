<?php

/**
 * Class to help display news feed items.
 *
 * @package XenForo_NewsFeed
 */
class XenForo_ViewPublic_Helper_NewsFeed
{
	/**
	 * Private constructor. Use statically.
	 */
	private function __construct()
	{
	}

	/**
	 * Attaches a template object to each record in the incoming news feed array
	 *
	 * @param XenForo_View $view
	 * @param array $newsFeed
	 * @param array $handlers
	 *
	 * @return array $newsFeed
	 */
	public static function getTemplates(XenForo_View $view, array $newsFeed, array $handlers)
	{
		foreach ($newsFeed AS $id => $item)
		{
			if (!isset($handlers[$item['content_type']]))
			{
				unset($newsFeed[$id]);
				continue;
			}

			$handler = $handlers[$item['content_type']];

			$newsFeed[$id]['template'] = $handler->renderHtml($item, $view);
		}

		return $newsFeed;
	}
}