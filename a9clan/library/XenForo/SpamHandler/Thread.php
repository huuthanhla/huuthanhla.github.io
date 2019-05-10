<?php

class XenForo_SpamHandler_Thread extends XenForo_SpamHandler_Abstract
{
	/**
	 * Checks that the options array contains a non-empty 'action_threads' key
	 *
	 * @param array $user
	 * @param array $options
	 *
	 * @return boolean
	 */
	public function cleanUpConditionCheck(array $user, array $options)
	{
		return !empty($options['action_threads']);
	}

	/**
	 * Moves or deletes threads by the specified user
	 *
	 * @see XenForo_SpamHandler_Abstract::cleanUp()
	 */
	public function cleanUp(array $user, array &$log, &$errorKey)
	{
		$threads = $this->getModelFromCache('XenForo_Model_Thread')->getThreads(array(
			'user_id' => $user['user_id'],
			'deleted' => true,
			'moderated' => true
		));

		if ($threads)
		{
			$threadsAction = XenForo_Application::get('options')->spamThreadAction;

			$postIds = array();
			foreach ($threads AS $thread)
			{
				$postIds[] = $thread['first_post_id'];
			}
			$this->getModelFromCache('XenForo_Model_SpamPrevention')->submitSpamCommentData('post', $postIds);
			$this->getModelFromCache('XenForo_Model_SpamPrevention')->submitSpamCommentData('thread', array_keys($threads));

			if ($threadsAction['action'] == 'move')
			{
				$log['thread'] = array(
					'action' => 'moved',
					'threadIds' => array()
				);

				foreach ($threads AS $threadId => $thread)
				{
					if (!isset($log['thread']['threadIds'][$thread['node_id']]))
					{
						$log['thread']['threadIds'][$thread['node_id']] = array();
					}

					$log['thread']['threadIds'][$thread['node_id']][] = $thread['thread_id'];
				}

				$inlineModModel = $this->getModelFromCache('XenForo_Model_InlineMod_Thread');
				$inlineModModel->enableLogging = false;

				return $inlineModModel->moveThreads(
					array_keys($threads), $threadsAction['node_id'], array(
						'skipPermissions' => true,
						'approveModerated' => true,
						'checkSameForum' => false
					), $errorKey
				);
			}
			else // delete
			{
				$deleteType = ($threadsAction['action'] == 'delete' ? 'hard' : 'soft');

				$threadIds = array_keys($threads);

				$log['thread'] = array(
					'action' => 'deleted',
					'deleteType' => $deleteType,
					'threadIds' => $threadIds
				);

				$inlineModModel = $this->getModelFromCache('XenForo_Model_InlineMod_Thread');
				$inlineModModel->enableLogging = false;

				return$inlineModModel->deleteThreads(
					$threadIds, array('deleteType' => $deleteType, 'skipPermissions' => true), $errorKey
				);
			}
		}

		return true;
	}

	/**
	 * @see XenForo_SpamHandler_Abstract::restore()
	 */
	public function restore(array $log, &$errorKey = '')
	{
		if ($log['action'] == 'moved')
		{
			$inlineModThreadModel = $this->getModelFromCache('XenForo_Model_InlineMod_Thread');
			$inlineModThreadModel->enableLogging = false;
			$options = array(
				'skipPermissions' => true,
				'checkSameForum' => false
			);

			foreach ($log['threadIds'] AS $nodeId => $threadIds)
			{
				if (!$inlineModThreadModel->moveThreads($threadIds, $nodeId, $options, $errorKey))
				{
					return false;
				}
			}
		}
		else // deleted
		{
			if ($log['deleteType'] == 'soft')
			{
				$inlineModModel = $this->getModelFromCache('XenForo_Model_InlineMod_Thread');
				$inlineModModel->enableLogging = false;

				return $inlineModModel->undeleteThreads(
					$log['threadIds'], array('skipPermissions' => true), $errorKey
				);
			}
		}

		return true;
	}
}