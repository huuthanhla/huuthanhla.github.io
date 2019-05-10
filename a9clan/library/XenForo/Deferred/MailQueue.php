<?php

class XenForo_Deferred_MailQueue extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		/* @var $queueModel XenForo_Model_MailQueue */
		$queueModel = XenForo_Model::create('XenForo_Model_MailQueue');

		$hasMore = $queueModel->runMailQueue($targetRunTime);
		if ($hasMore)
		{
			return $data;
		}
		else
		{
			return false;
		}
	}
}