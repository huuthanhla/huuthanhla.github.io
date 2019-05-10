<?php

/**
 * Model to handle inline mod-style actions on threads. Generally, these are simply
 * bulk actions. They can be applied to other circumstances if desired.
 *
 * @package XenForo_Thread
 */
class XenForo_Model_InlineMod_Thread extends XenForo_Model
{
	public $enableLogging = true;

	/**
	 * Determines if the selected thread IDs can be generally edited (titles, prefixes etc.)
	 *
	 * @param array $threadIds List of thread IDs check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditThreads(array $threadIds, &$errorKey = '', $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);
		return $this->canEditThreadsData($threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected threads can be generally edited (titles, prefixes etc.)
	 *
	 * @param array $threads List of information about threads to be checked
	 * @param array $forums List of information about forums the threads are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditThreadsData(array $threads, array $forums, &$errorKey = '', $viewingUser = null)
	{
		return $this->_checkPermissionOnThreads('canEditThread', $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Apply a thread prefix to the specified threads if permissions are sufficient.
	 *
	 * @param array $threadIds List of thread IDs to change
	 * @param integer $prefixId ID of the thread prefix to apply
	 * @param array $unchangedThreadIds Array of thread IDs that were not updated by this action
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function applyThreadPrefix(array $threadIds, $prefixId, &$unchangedThreadIds = array(), array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$prefixModel = $this->_getPrefixModel();

		$prefixPerms = array();

		if (!$this->canEditThreads($threadIds, $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($threadIds AS $threadId)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			if (!$dw->setExistingData($threadId))
			{
				continue;
			}

			$thread = $dw->getMergedData();

			$nodeId = $dw->get('node_id');
			if (!isset($prefixPerms[$nodeId]))
			{
				$prefixPerms[$nodeId] = $prefixModel->verifyPrefixIsUsable($prefixId, $nodeId, $viewingUser);
			}

			if (!$prefixPerms[$nodeId])
			{
				$unchangedThreadIds[] = $threadId;
				continue;
			}

			$dw->set('prefix_id', $prefixId);
			$dw->save();

			if ($thread['prefix_id'] != $prefixId)
			{
				if ($thread['prefix_id'])
				{
					$phrase = new XenForo_Phrase('thread_prefix_' . $thread['prefix_id']);
					$oldValue = $phrase->render();
				}
				else
				{
					$oldValue = '-';
				}

				XenForo_Model_Log::logModeratorAction('thread', $thread, 'prefix', array('old' => $oldValue));
			}
		}

		return true;
	}

	/**
	 * Determines if the selected thread IDs can be deleted.
	 *
	 * @param array $threadIds List of thread IDs check
	 * @param string $deleteType The type of deletion being requested (soft or hard)
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canDeleteThreads(array $threadIds, $deleteType = 'soft', &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);
		return $this->canDeleteThreadsData($threads, $deleteType, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected threads can be deleted. This is a slightly more
	 * "internal" version of the canDeleteThreads() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $threads List of information about threads to be checked
	 * @param string $deleteType Type of deletion (soft or hard)
	 * @param array $forums List of information about forums the threads are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canDeleteThreadsData(array $threads, $deleteType, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		// note: this cannot use _checkPermissionOnThreads because of extra param

		if (!$threads)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);

		$threadModel = $this->_getThreadModel();

		foreach ($threads AS $thread)
		{
			$forum = $this->_getForumFromThread($thread, $forums);

			if (!$threadModel->canViewThreadAndContainer($thread, $forum, $null, $forum['nodePermissions'], $viewingUser))
			{
				return false;
			}

			if (!$threadModel->canDeleteThread($thread, $forum, $deleteType, $errorKey, $forum['nodePermissions'], $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Deletes the specified threads if permissions are sufficient.
	 *
	 * @param array $threadIds List of thread IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function deleteThreads(array $threadIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$options = array_merge(
			array(
				'deleteType' => '',
				'reason' => '',

				'starterAlert' => false,
				'starterAlertReason' => ''
			), $options
		);

		if (!$options['deleteType'])
		{
			throw new XenForo_Exception('No deletion type specified.');
		}

		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canDeleteThreadsData($threads, $options['deleteType'], $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		foreach ($threads AS $thread)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			if (!$dw->setExistingData($thread))
			{
				continue;
			}

			if (array_key_exists($dw->get('node_id'), $forums))
			{
				$dw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forums[$dw->get('node_id')]);
			}

			if ($options['deleteType'] == 'hard')
			{
				$dw->delete();

				if ($this->enableLogging)
				{
					XenForo_Model_Log::logModeratorAction('thread', $thread, 'delete_hard');
				}
			}
			else
			{
				$dw->setExtraData(XenForo_DataWriter_Discussion::DATA_DELETE_REASON, $options['reason']);
				$dw->set('discussion_state', 'deleted');
				$dw->save();

				if ($this->enableLogging)
				{
					XenForo_Model_Log::logModeratorAction('thread', $thread, 'delete_soft', array('reason' => $options['reason']));
				}
			}

			if ($thread['discussion_state'] == 'visible' && $options['starterAlert'])
			{
				$this->_getThreadModel()->sendModeratorActionAlert(
					'delete', $thread, $options['starterAlertReason']
				);
			}
		}

		return true;
	}

	/**
	 * Determines if the selected thread IDs can be undeleted.
	 *
	 * @param array $threadIds List of thread IDs check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canUndeleteThreads(array $threadIds, &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);
		return $this->canUndeleteThreadsData($threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected threads can be undeleted. This is a slightly more
	 * "internal" version of the canUndeleteThreads() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $threads List of information about threads to be checked
	 * @param array $forums List of information about forums the threads are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canUndeleteThreadsData(array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_checkPermissionOnThreads('canUndeleteThread', $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Undeletes the specified threads if permissions are sufficient.
	 *
	 * @param array $threadIds List of thread IDs to undelete
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function undeleteThreads(array $threadIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canUndeleteThreadsData($threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateThreadsDiscussionState($threads, $forums, 'visible', 'deleted');

		return true;
	}

	/**
	 * Determines if the selected thread IDs can be approved/unapproved.
	 *
	 * @param array $threadIds List of thread IDs check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canApproveUnapproveThreads(array $threadIds, &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);
		return $this->canApproveUnapproveThreadsData($threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected threads can be approved/unapproved. This is a slightly more
	 * "internal" version of the canApproveUnapproveThreads() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $threads List of information about threads to be checked
	 * @param array $forums List of information about forums the threads are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canApproveUnapproveThreadsData(array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_checkPermissionOnThreads('canApproveUnapproveThread', $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Approves the specified threads if permissions are sufficient.
	 *
	 * @param array $threadIds List of thread IDs to approve
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function approveThreads(array $threadIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canApproveUnapproveThreadsData($threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateThreadsDiscussionState($threads, $forums, 'visible', 'moderated');

		return true;
	}

	/**
	 * Unapproves the specified threads if permissions are sufficient.
	 *
	 * @param array $threadIds List of thread IDs to unapprove
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function unapproveThreads(array $threadIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canApproveUnapproveThreadsData($threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateThreadsDiscussionState($threads, $forums, 'moderated', 'visible');

		return true;
	}

	/**
	 * Determines if the selected thread IDs can be locked/unlocked.
	 *
	 * @param array $threadIds List of thread IDs check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canLockUnlockThreads(array $threadIds, &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);
		return $this->canLockUnlockThreadsData($threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected threads can be locked/unlocked. This is a slightly more
	 * "internal" version of the canLockUnlockThreads() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $threads List of information about threads to be checked
	 * @param array $forums List of information about forums the threads are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canLockUnlockThreadsData(array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_checkPermissionOnThreads('canLockUnlockThread', $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Locks the specified threads if permissions are sufficient.
	 *
	 * @param array $threadIds List of thread IDs to change
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function lockThreads(array $threadIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canLockUnlockThreadsData($threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateThreadsBulk($threads, $forums, array('discussion_open' => 0), 'lock');

		return true;
	}

	/**
	 * Unlocks the specified threads if permissions are sufficient.
	 *
	 * @param array $threadIds List of thread IDs to change
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function unlockThreads(array $threadIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canLockUnlockThreadsData($threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateThreadsBulk($threads, $forums, array('discussion_open' => 1), 'unlock');

		return true;
	}

	/**
	 * Determines if the selected thread IDs can be stickied/unstickied.
	 *
	 * @param array $threadIds List of thread IDs check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canStickUnstickThreads(array $threadIds, &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);
		return $this->canStickUnstickThreadsData($threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected threads can be stickied/unstickied. This is a slightly more
	 * "internal" version of the canStickUnstickThreads() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $threads List of information about threads to be checked
	 * @param array $forums List of information about forums the threads are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canStickUnstickThreadsData(array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		return $this->_checkPermissionOnThreads('canStickUnstickThread', $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Stickies the specified threads if permissions are sufficient.
	 *
	 * @param array $threadIds List of thread IDs to change
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function stickThreads(array $threadIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canStickUnstickThreadsData($threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateThreadsBulk($threads, $forums, array('sticky' => 1), 'stick');

		return true;
	}

	/**
	 * Stickies the specified threads if permissions are sufficient.
	 *
	 * @param array $threadIds List of thread IDs to change
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function unstickThreads(array $threadIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canStickUnstickThreadsData($threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$this->_updateThreadsBulk($threads, $forums, array('sticky' => 0), 'unstick');

		return true;
	}

	/**
	 * Determines if the selected thread IDs can be moved.
	 *
	 * @param array $threadIds List of thread IDs check
	 * @param integer $targetNodeId ID of node where threads are being moved to. Use 0 if unknown.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMoveThreads(array $threadIds, $targetNodeId, &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);
		return $this->canMoveThreadsData($threads, $targetNodeId, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected threads can be moved. This is a slightly more
	 * "internal" version of the canMoveThreads() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $threads List of information about threads to be checked
	 * @param integer $targetNodeId ID of node where threads are being moved to. Use 0 if unknown.
	 * @param array $forums List of information about forums the threads are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMoveThreadsData(array $threads, $targetNodeId, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		if ($targetNodeId > 0)
		{
			$forum = $this->_getForumModel()->getForumById($targetNodeId);
			if (!$forum)
			{
				$errorKey = 'please_select_valid_forum';
				return false;
			}
		}

		return $this->_checkPermissionOnThreads('canMoveThread', $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Moves the specified threads if permissions are sufficient.
	 *
	 * @param array $threadIds List of thread IDs to change
	 * @param integer $targetNodeId ID of node where threads are being moved to. Use 0 if unknown.
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function moveThreads(array $threadIds, $targetNodeId, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canMoveThreadsData($threads, $targetNodeId, $forums, $errorKey, $viewingUser))
		{
			return false;
		}
		if ($targetNodeId < 1)
		{
			return false;
		}

		$options = array_merge(
			array(
				'redirect' => false,
				'redirectExpiry' => 0,
				'checkSameForum' => true,
				'approveModerated' => false,
				'notifyWatch' => false,

				'starterAlert' => false,
				'starterAlertReason' => ''
			),
			$options
		);

		if ($options['checkSameForum'])
		{
			$allSameForum = true;
			foreach ($threads AS $thread)
			{
				if ($thread['node_id'] != $targetNodeId)
				{
					$allSameForum = false;
					break;
				}
			}

			if ($allSameForum)
			{
				$errorKey = 'all_threads_in_destination_forum';
				return false;
			}
		}

		$updateOptions = array('node_id' => $targetNodeId);
		if (isset($options['prefix_id']))
		{
			$updateOptions['prefix_id'] = $options['prefix_id'];
		}

		foreach ($threads AS $thread)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			if (!$dw->setExistingData($thread))
			{
				continue;
			}
			$dw->bulkSet($updateOptions);

			if (array_key_exists($dw->get('node_id'), $forums))
			{
				$dw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forums[$dw->get('node_id')]);
			}

			if ($options['approveModerated'] && $dw->get('discussion_state') == 'moderated')
			{
				$dw->set('discussion_state', 'visible');
			}

			$dw->save();

			if ($thread['discussion_state'] == 'visible'
				&& $thread['node_id'] != $targetNodeId
				&& $options['starterAlert']
			)
			{
				$alertThread = $thread;
				$alertThread['node_id'] = $targetNodeId;
				$this->_getThreadModel()->sendModeratorActionAlert(
					'move', $alertThread, $options['starterAlertReason']
				);
			}
		}

		if ($this->enableLogging)
		{
			foreach ($threads AS $thread)
			{
				if ($thread['node_id'] != $targetNodeId)
				{
					$forum = $this->_getForumFromThread($thread, $forums);
					$forumTitle = ($forum ? $forum['title'] : '');

					XenForo_Model_Log::logModeratorAction('thread', $thread, 'move', array('from' => $forumTitle));
				}

				if (isset($options['prefix_id']) && $thread['prefix_id'] != $options['prefix_id'])
				{
					if ($thread['prefix_id'])
					{
						$prefixTitle = new XenForo_Phrase($this->_getPrefixModel()->getPrefixTitlePhraseName($thread['prefix_id']));
						$prefixTitle = $prefixTitle->render();
					}
					else
					{
						$prefixTitle = '-';
					}

					XenForo_Model_Log::logModeratorAction('thread', $thread, 'prefix', array('old' => $prefixTitle));
				}
			}
		}

		if ($options['redirect'])
		{
			/** @var XenForo_Model_ThreadRedirect $threadRedirectModel */
			$threadRedirectModel = $this->getModelFromCache('XenForo_Model_ThreadRedirect');
			foreach ($threads AS $thread)
			{
				if ($targetNodeId == $thread['node_id'])
				{
					continue;
				}

				$threadRedirectModel->createRedirectThread(
					XenForo_Link::buildPublicLink('threads', $thread), $thread,
					"thread-$thread[thread_id]-$thread[node_id]-", $options['redirectExpiry']
				);
			}
		}

		if ($options['notifyWatch'])
		{
			/** @var XenForo_Model_ForumWatch $watchModel */
			$watchModel = $this->getModelFromCache('XenForo_Model_ForumWatch');

			/** @var XenForo_Model_Post $postModel */
			$postModel = $this->getModelFromCache('XenForo_Model_Post');

			foreach ($threads AS $thread)
			{
				if ($targetNodeId == $thread['node_id'])
				{
					continue;
				}

				$firstPost = $postModel->getPostById($thread['first_post_id']);
				if ($firstPost && $firstPost['thread_id'] == $thread['thread_id'])
				{
					$watchModel->sendNotificationToWatchUsersOnMove(
						$firstPost, $thread['node_id']
					);
				}
			}
		}

		return true;
	}

	/**
	 * Determines if the selected thread IDs can be merged.
	 *
	 * @param array $threadIds List of thread IDs check
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMergeThreads(array $threadIds, &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);
		return $this->canMergeThreadsData($threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Determines if the selected threads can be merged. This is a slightly more
	 * "internal" version of the canMergeThreads() function, as the required data
	 * must already be retrieved.
	 *
	 * @param array $threads List of information about threads to be checked
	 * @param array $forums List of information about forums the threads are in; must include unserialized permissions in 'nodePermissions' key
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMergeThreadsData(array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		if (count($threads) <= 1)
		{
			$errorKey = 'please_select_more_one_thread_merge';
			return false;
		}

		$threadModel = $this->_getThreadModel();

		foreach ($threads AS $thread)
		{
			if ($threadModel->isRedirect($thread))
			{
				$errorKey = 'cannot_merge_thread_redirection_notice';
				return false;
			}
		}

		return $this->_checkPermissionOnThreads('canMergeThread', $threads, $forums, $errorKey, $viewingUser);
	}

	/**
	 * Merge the specified threads if permissions are sufficient.
	 *
	 * @param array $threadIds List of thread IDs to change
	 * @param integer $targetThreadId ID of the thread (in the list) that the merging will happen into
	 * @param array $options Options that control the action. Nothing supported at this time.
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if permissions were ok
	 */
	public function mergeThreads(array $threadIds, $targetThreadId, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		list($threads, $forums) = $this->getThreadsAndParentData($threadIds, $viewingUser);

		if (empty($options['skipPermissions']) && !$this->canMergeThreadsData($threads, $forums, $errorKey, $viewingUser))
		{
			return false;
		}

		$options['log'] = $this->enableLogging;

		return $this->_getThreadModel()->mergeThreads($threads, $targetThreadId, $options);
	}

	/**
	 * Checks a standard thread permission against a collection of threads.
	 * True is returned only if the action is possible on all threads.
	 *
	 * @param string $permissionMethod Name of the permission method to call in the thread model
	 * @param array $threads List of threads to check
	 * @param array $forums List of forums the threads are in
	 * @param string $errorKey Returned by reference. Phrase key if an error occurs
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	protected function _checkPermissionOnThreads($permissionMethod, array $threads, array $forums, &$errorKey = '', array $viewingUser = null)
	{
		if (!$threads)
		{
			return true;
		}

		$this->standardizeViewingUserReference($viewingUser);

		$threadModel = $this->_getThreadModel();

		foreach ($threads AS $thread)
		{
			$forum = $this->_getForumFromThread($thread, $forums);

			if (!$threadModel->canViewThreadAndContainer($thread, $forum, $null, $forum['nodePermissions'], $viewingUser))
			{
				return false;
			}

			if ($permissionMethod && !$threadModel->$permissionMethod($thread, $forum, $errorKey, $forum['nodePermissions'], $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Internal helper to update the discussion_state of a collection of threads.
	 *
	 * @param array $threads Information about the threads to update
	 * @param array $forums Information about the forums that the threads are in
	 * @param string $newState New message state (visible, moderated, deleted)
	 * @param string|false $expectedOldState If specified, only updates if the old state matches
	 */
	protected function _updateThreadsDiscussionState(array $threads, array $forums, $newState, $expectedOldState = false)
	{
		switch ($newState)
		{
			case 'visible':
				switch (strval($expectedOldState))
				{
					case 'visible': return;
					case 'moderated': $logAction = 'approve'; break;
					case 'deleted': $logAction = 'undelete'; break;
					default: $logAction = 'undelete'; break;
				}
				break;

			case 'moderated':
				switch (strval($expectedOldState))
				{
					case 'visible': $logAction = 'unapprove'; break;
					case 'moderated': return;
					case 'deleted': $logAction = 'unapprove'; break;
					default: $logAction = 'unapprove'; break;
				}
				break;

			case 'deleted':
				switch (strval($expectedOldState))
				{
					case 'visible': $logAction = 'delete_soft'; break;
					case 'moderated': $logAction = 'delete_soft'; break;
					case 'deleted': return;
					default: $logAction = 'delete_soft'; break;
				}
				break;

			default: return;
		}

		foreach ($threads AS $thread)
		{
			if ($expectedOldState && $thread['discussion_state'] != $expectedOldState)
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			if (!$dw->setExistingData($thread))
			{
				continue;
			}
			$dw->set('discussion_state', $newState);

			if (array_key_exists($dw->get('node_id'), $forums))
			{
				$dw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forums[$dw->get('node_id')]);
			}

			$dw->save();

			if ($this->enableLogging)
			{
				XenForo_Model_Log::logModeratorAction('thread', $thread, $logAction);
			}
		}
	}

	/**
	 * Bulk update 1 or more fields in the given threads.
	 *
	 * @param array $threads List of threads to update
	 * @param array $forums List of forums threads are in
	 * @param array $updates Key-value pairs to update
	 * @param string $logAction If specified, this action will be logged with this action
	 */
	protected function _updateThreadsBulk(array $threads, array $forums, array $updates, $logAction = '')
	{
		foreach ($threads AS $thread)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
			if (!$dw->setExistingData($thread))
			{
				continue;
			}
			$dw->bulkSet($updates);

			if (array_key_exists($dw->get('node_id'), $forums))
			{
				$dw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forums[$dw->get('node_id')]);
			}

			$dw->save();

			if ($dw->hasChanges() && $this->enableLogging && $logAction)
			{
				XenForo_Model_Log::logModeratorAction('thread', $thread, $logAction);
			}
		}
	}

	/**
	 * Gets information about the forum a thread belogns to.
	 *
	 * @param array $thread Info about the thread
	 * @param array $forums List of forums that the thread could belong to
	 *
	 * @return array Forum info
	 */
	protected function _getForumFromThread(array $thread, array $forums)
	{
		return $forums[$thread['node_id']];
	}

	/**
	 * From a list of threads IDs, gets info about the threads and
	 * the forums the threads are in.
	 *
	 * @param array $threadIds List of thread IDs
	 * @param array|null $viewingUser
	 *
	 * @return array Format:  [0] => list of threads, [1] => list of forums
	 */
	public function getThreadsAndParentData(array $threadIds, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return $this->_getThreadModel()->getThreadsAndParentData($threadIds, $viewingUser['permission_combination_id']);
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_ThreadPrefix
	 */
	protected function _getPrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}
}