<?php

class XenForo_Deferred_SearchIndexPartial extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'contentType' => '',
			'contentIds' => false
		), $data);

		$status = sprintf('Search Index (Partial)');

		if (!$data['contentType'] || !$data['contentIds'] || !is_array($data['contentIds']))
		{
			return false;
		}

		$limitTime = ($targetRunTime > 0);

		do
		{
			$s = microtime(true);

			$i = 0;
			$contentIds = array();
			foreach ($data['contentIds'] AS $key => $id)
			{
				$contentIds[] = $id;
				unset($data['contentIds'][$key]);
				if (++$i >= 10)
				{
					break;
				}
			}

			$indexer = new XenForo_Search_Indexer();
			$indexer->quickIndex($data['contentType'], $contentIds);

			$targetRunTime -= microtime(true) - $s;
		}
		while ((!$limitTime || $targetRunTime > 0) && $data['contentIds']);

		if (!$data['contentIds'])
		{
			return false;
		}

		return $data;
	}
}