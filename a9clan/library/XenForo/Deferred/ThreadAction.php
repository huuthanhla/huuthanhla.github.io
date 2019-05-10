<?php

class XenForo_Deferred_ThreadAction extends XenForo_Deferred_Abstract
{
	public function canTriggerManually()
	{
		return false;
	}

	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'criteria' => null,
			'start' => 0,
			'count' => 0,
			'total' => null,
			'threadIds' => null,
			'actions' => array()
		), $data);

		$s = microtime(true);

		/* @var $threadModel XenForo_Model_Thread */
		$threadModel = XenForo_Model::create('XenForo_Model_Thread');

		if (is_array($data['criteria']))
		{
			$criteria = $data['criteria'];
			$criteria['thread_id_gt'] = $data['start'];
			$threadIds = $threadModel->getThreadIds($criteria, array('limit' => 1000));
		}
		else if (is_array($data['threadIds']))
		{
			$threadIds = $data['threadIds'];
		}
		else
		{
			$threadIds = array();
		}

		if (!$threadIds)
		{
			return false;
		}

		$limitTime = ($targetRunTime > 0);

		XenForo_Db::beginTransaction();

		foreach ($threadIds AS $key => $threadId)
		{
			$data['count']++;
			$data['start'] = $threadId;
			unset($threadIds[$key]);

			/* @var $threadDw XenForo_DataWriter_Discussion_Thread */
			$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			if ($threadDw->setExistingData($threadId))
			{
				if (!empty($data['actions']['delete']))
				{
					$threadDw->delete();
				}
				else
				{
					if (!empty($data['actions']['node_id']))
					{
						$threadDw->set('node_id', $data['actions']['node_id']);
					}

					if (!empty($data['actions']['prefix_id']))
					{
						$threadDw->set('prefix_id', $data['actions']['prefix_id']);
					}

					if (!empty($data['actions']['stick']))
					{
						$threadDw->set('sticky', 1);
					}

					if (!empty($data['actions']['unstick']))
					{
						$threadDw->set('sticky', 0);
					}

					if (!empty($data['actions']['lock']))
					{
						$threadDw->set('discussion_open', 0);
					}

					if (!empty($data['actions']['unlock']))
					{
						$threadDw->set('discussion_open', 1);
					}

					if (!empty($data['actions']['approve']))
					{
						$threadDw->set('discussion_state', 'visible');
					}

					if (!empty($data['actions']['unapprove']))
					{
						$threadDw->set('discussion_state', 'moderated');
					}

					if (!empty($data['actions']['soft_delete']))
					{
						$threadDw->set('discussion_state', 'deleted');
					}

					$threadDw->save();
				}
			}

			if ($limitTime && microtime(true) - $s > $targetRunTime)
			{
				break;
			}
		}

		XenForo_Db::commit();

		if (is_array($data['threadIds']) && !$threadIds)
		{
			return false;
		}

		if (is_array($data['threadIds']))
		{
			$data['threadIds'] = $threadIds;
		}

		$actionPhrase = new XenForo_Phrase('updating');
		$typePhrase = new XenForo_Phrase('threads');
		if ($data['total'])
		{
			$status = sprintf('%s... %s (%d/%d)', $actionPhrase, $typePhrase, $data['count'], $data['total']);
		}
		else
		{
			$status = sprintf('%s... %s (%d)', $actionPhrase, $typePhrase, $data['count']);
		}

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}