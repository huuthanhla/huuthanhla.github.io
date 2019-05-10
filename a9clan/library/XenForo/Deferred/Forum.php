<?php

class XenForo_Deferred_Forum extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 100
		), $data);
		$data['batch'] = max(1, $data['batch']);

		if ($data['position'] == 0)
		{
			XenForo_Model::create('XenForo_Model_Node')->updateNestedSetInfo();
		}

		/* @var $forumModel XenForo_Model_Forum */
		$forumModel = XenForo_Model::create('XenForo_Model_Forum');

		$forums = $forumModel->getForums(array(), array('limit' => $data['batch'], 'offset' => $data['position']));
		if (!$forums)
		{
			return false;
		}

		foreach ($forums AS $forum)
		{
			$data['position']++;

			$forumDw = XenForo_DataWriter::create('XenForo_DataWriter_Forum', XenForo_DataWriter::ERROR_SILENT);
			if ($forumDw->setExistingData($forum, true))
			{
				$forumDw->rebuildCounters();
				$forumDw->save();
			}
		}

		$rbPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('forums');
		$status = sprintf('%s... %s (%s)', $rbPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}