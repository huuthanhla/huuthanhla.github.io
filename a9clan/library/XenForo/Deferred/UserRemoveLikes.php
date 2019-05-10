<?php

class XenForo_Deferred_UserRemoveLikes extends XenForo_Deferred_Abstract
{
	public function canTriggerManually()
	{
		return false;
	}

	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'userId' => null,
			'cutOff' => null,
			'count' => 0,
			'total' => null
		), $data);

		if (!$data['userId'] || $data['cutOff'] === null)
		{
			return false;
		}

		$s = microtime(true);

		/* @var $likeModel XenForo_Model_Like */
		$likeModel = XenForo_Model::create('XenForo_Model_Like');

		$likes = $likeModel->getLikesByUserSinceDate($data['userId'], $data['cutOff'], 500);
		if (!$likes)
		{
			return false;
		}

		if ($data['total'] === null)
		{
			$data['total'] = $likeModel->countLikesByUserSinceDate($data['userId'], $data['cutOff']);
		}

		$continue = false;

		foreach ($likes AS $like)
		{
			try
			{
				$likeModel->unlikeContent($like);
			}
			catch (Exception $e)
			{
				// probably an orphaned piece of content - skip
			}

			$data['count']++;

			if ($targetRunTime && microtime(true) - $s > $targetRunTime)
			{
				$continue = true;
				break;
			}
		}

		if (!$continue)
		{
			return false;
		}

		$actionPhrase = new XenForo_Phrase('reverting_edits');
		$status = sprintf('%s... %s (%s/$s)', $actionPhrase,
			XenForo_Locale::numberFormat($data['count']), XenForo_Locale::numberFormat($data['total'])
		);

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}