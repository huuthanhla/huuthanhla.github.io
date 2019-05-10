<?php

class XenForo_Deferred_Thread extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'batch' => 100,
			'position' => 0,
			'positionRebuild' => false
		), $data);

		/* @var $threadModel XenForo_Model_Thread */
		$threadModel = XenForo_Model::create('XenForo_Model_Thread');

		$threadIds = $threadModel->getThreadIdsInRange($data['position'], $data['batch']);
		if (sizeof($threadIds) == 0)
		{
			return false;
		}

		$forums = XenForo_Model::create('XenForo_Model_Forum')->getForumsByThreadIds($threadIds);

		foreach ($threadIds AS $threadId)
		{
			$data['position'] = $threadId;

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($threadId))
			{
				$dw->setOption(XenForo_DataWriter_Discussion::OPTION_UPDATE_CONTAINER, false);

				if (isset($forums[$dw->get('node_id')]))
				{
					$dw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forums[$dw->get('node_id')]);
				}

				if ($data['positionRebuild'])
				{
					$dw->rebuildDiscussion();
				}
				else
				{
					$dw->rebuildDiscussionCounters();
				}
				$dw->save();
			}
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('threads');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}