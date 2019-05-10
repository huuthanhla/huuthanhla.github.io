<?php

class XenForo_StatsHandler_Attachment extends XenForo_StatsHandler_Abstract
{
	public function getStatsTypes()
	{
		return array(
			'attachment' => new XenForo_Phrase('attachments'),
			'attachment_disk_usage' => new XenForo_Phrase('attachment_disk_usage')
		);
	}

	public function getData($startDate, $endDate)
	{
		$db = $this->_getDb();

		$attachments = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_attachment_data', 'upload_date', 'attach_count > ?'),
			array($startDate, $endDate, 0)
		);

		$attachmentDiskUsage = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_attachment_data', 'upload_date', 'attach_count > ?', 'SUM(file_size)'),
			array($startDate, $endDate, 0)
		);

		return array(
			'attachment' => $attachments,
			'attachment_disk_usage' => $attachmentDiskUsage
		);
	}

	/**
	 * Catches the attachment_disk_usage stats type and format the bytes integer into a megabytes float
	 *
	 * @see XenForo_StatsHandler_Abstract::getCounterForDisplay()
	 */
	public function getCounterForDisplay($statsType, $counter)
	{
		if ($statsType == 'attachment_disk_usage')
		{
			return round($counter / 1048576, 3); // megabytes
		}

		return parent::getCounterForDisplay($statsType, $counter);
	}
}