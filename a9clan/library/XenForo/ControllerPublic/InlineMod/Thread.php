<?php

/**
 * Inline moderation actions for threads
 *
 * @package XenForo_Thread
 */
class XenForo_ControllerPublic_InlineMod_Thread extends XenForo_ControllerPublic_InlineMod_Abstract
{
	/**
	 * Key for inline mod data.
	 *
	 * @var string
	 */
	public $inlineModKey = 'threads';

	/**
	 * @return XenForo_Model_InlineMod_Thread
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Thread');
	}

	/**
	 * Thread deletion handler.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$threadIds = $this->getInlineModIds(false);

			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::STRING);
			$options = array(
				'deleteType' => ($hardDelete ? 'hard' : 'soft'),
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING),
				'starterAlert' => $this->_input->filterSingle('send_starter_alert', XenForo_Input::BOOLEAN),
				'starterAlertReason' => $this->_input->filterSingle('starter_alert_reason', XenForo_Input::STRING)
			);

			$deleted = $this->_getInlineModThreadModel()->deleteThreads(
				$threadIds, $options, $errorPhraseKey
			);
			if (!$deleted)
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$this->clearCookie();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(false, false)
			);
		}
		else // show confirmation dialog
		{
			$threadIds = $this->getInlineModIds();

			$handler = $this->_getInlineModThreadModel();
			if (!$handler->canDeleteThreads($threadIds, 'soft', $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$threadIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'threadIds' => $threadIds,
				'threadCount' => count($threadIds),
				'canHardDelete' => $handler->canDeleteThreads($threadIds, 'hard'),
				'redirect' => $redirect,
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Thread_Delete', 'inline_mod_thread_delete', $viewParams);
		}
	}

	/**
	 * Undeletes the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUndelete()
	{
		return $this->executeInlineModAction('undeleteThreads');
	}

	/**
	 * Approves the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionApprove()
	{
		return $this->executeInlineModAction('approveThreads');
	}

	/**
	 * Unapproves the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnapprove()
	{
		return $this->executeInlineModAction('unapproveThreads');
	}

	/**
	 * Lock the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLock()
	{
		return $this->executeInlineModAction('lockThreads');
	}

	/**
	 * Unlock the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnlock()
	{
		return $this->executeInlineModAction('unlockThreads');
	}

	/**
	 * Stick the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionStick()
	{
		return $this->executeInlineModAction('stickThreads');
	}

	/**
	 * Unstick the specified threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnstick()
	{
		return $this->executeInlineModAction('unstickThreads');
	}

	/**
	 * Thread move handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMove()
	{
		if ($this->isConfirmedPost())
		{
			$threadIds = $this->getInlineModIds(false);
			$input = $this->_input->filter(array(
				'node_id' => XenForo_Input::UINT,
				'apply_thread_prefix' => XenForo_Input::UINT,
				'prefix_id' => XenForo_Input::UINT,
				'create_redirect' => XenForo_Input::STRING,
				'redirect_ttl_value' => XenForo_Input::UINT,
				'redirect_ttl_unit' => XenForo_Input::STRING,
				'send_alert' => XenForo_Input::BOOLEAN,

			));

			$viewableNodes = $this->getModelFromCache('XenForo_Model_Node')->getViewableNodeList();
			if (isset($viewableNodes[$input['node_id']]))
			{
				$targetNode = $viewableNodes[$input['node_id']];
			}
			else
			{
				return $this->responseNoPermission();
			}

			if ($input['create_redirect'] == 'permanent')
			{
				$options = array('redirect' => true, 'redirectExpiry' => 0);
			}
			else if ($input['create_redirect'] == 'expiring')
			{
				$expiryDate = strtotime('+' . $input['redirect_ttl_value'] . ' ' . $input['redirect_ttl_unit']);
				$options = array('redirect' => true, 'redirectExpiry' => $expiryDate);
			}
			else
			{
				$options = array('redirect' => false);
			}

			$options['starterAlert'] = $this->_input->filterSingle('send_starter_alert', XenForo_Input::BOOLEAN);
			$options['starterAlertReason'] = $this->_input->filterSingle('starter_alert_reason', XenForo_Input::STRING);

			$options['notifyWatch'] = $input['send_alert'];

			if ($input['apply_thread_prefix'])
			{
				$options['prefix_id'] = $input['prefix_id'];
			}

			if (!$this->_getInlineModThreadModel()->moveThreads($threadIds, $input['node_id'], $options, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$this->clearCookie();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('forums', $targetNode)
			);
		}
		else // show confirmation dialog
		{
			$threadIds = $this->getInlineModIds();

			$handler = $this->_getInlineModThreadModel();
			if (!$handler->canMoveThreads($threadIds, 0, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$threadIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$firstThread = $this->_getThreadModel()->getThreadById(reset($threadIds));

			$canEditThreadPrefixes = $handler->canEditThreads($threadIds);

			$viewParams = array(
				'threadIds' => $threadIds,
				'threadCount' => count($threadIds),
				'firstThread' => $firstThread,
				'nodeOptions' => $this->getModelFromCache('XenForo_Model_Node')->getViewableNodeList(),
				'redirect' => $redirect,
				'canEditThreadPrefixes' => $canEditThreadPrefixes,
			);

			if ($canEditThreadPrefixes)
			{
				$viewParams = array_merge($viewParams, array(
					'prefixes' => $this->_getPrefixModel()->getUsablePrefixesInForums($firstThread['node_id']),
					'forcePrefixes' => (XenForo_Application::get('threadPrefixes') ? true : false),
				));
			}

			return $this->responseView('XenForo_ViewPublic_InlineMod_Thread_Move', 'inline_mod_thread_move', $viewParams);
		}
	}

	/**
	 * Thread merge handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMerge()
	{
		if ($this->isConfirmedPost())
		{
			$threadIds = $this->getInlineModIds(false);
			$input = $this->_input->filter(array(
				'target_thread_id' => XenForo_Input::UINT,
				'create_redirect' => XenForo_Input::STRING,
				'redirect_ttl_value' => XenForo_Input::UINT,
				'redirect_ttl_unit' => XenForo_Input::STRING
			));

			if ($input['create_redirect'] == 'permanent')
			{
				$options = array('redirect' => true, 'redirectExpiry' => 0);
			}
			else if ($input['create_redirect'] == 'expiring')
			{
				$expiryDate = strtotime('+' . $input['redirect_ttl_value'] . ' ' . $input['redirect_ttl_unit']);
				$options = array('redirect' => true, 'redirectExpiry' => $expiryDate);
			}
			else
			{
				$options = array('redirect' => false);
			}

			$options['starterAlert'] = $this->_input->filterSingle('send_starter_alert', XenForo_Input::BOOLEAN);
			$options['starterAlertReason'] = $this->_input->filterSingle('starter_alert_reason', XenForo_Input::STRING);

			$targetThread = $this->_getInlineModThreadModel()->mergeThreads($threadIds, $input['target_thread_id'], $options, $errorPhraseKey);
			if (!$targetThread)
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$this->clearCookie();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('threads', $targetThread)
			);
		}
		else // show confirmation dialog
		{
			$threadIds = $this->getInlineModIds();

			$handler = $this->_getInlineModThreadModel();
			if (!$handler->canMergeThreads($threadIds, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$threadIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$threads = $this->_getThreadModel()->getThreadsByIds($threadIds);

			$viewParams = array(
				'threadIds' => $threadIds,
				'threadCount' => count($threadIds),
				'threads' => $threads,
				'redirect' => $redirect,
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Thread_Merge', 'inline_mod_thread_merge', $viewParams);
		}
	}

	public function actionPrefix()
	{
		$threadIds = $this->getInlineModIds(!$this->isConfirmedPost());

		$redirect = $this->getDynamicRedirect();

		if (!$threadIds)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirect
			);
		}

		if ($this->isConfirmedPost())
		{
			$prefixId = $this->_input->filterSingle('prefix_id', XenForo_Input::UINT);

			if (!$this->_getInlineModThreadModel()->applyThreadPrefix($threadIds, $prefixId, $unchangedThreadIds, array(), $errorKey))
			{
				return $this->responseError(new XenForo_Phrase($errorKey));
			}

			if ($unchangedThreadIds)
			{
				XenForo_Helper_Cookie::setCookie('inlinemod_' . $this->inlineModKey, implode(',', $unchangedThreadIds));
			}
			else
			{
				$this->clearCookie();
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirect
			);
		}
		else // show confirmation dialog
		{
			$handler = $this->_getInlineModThreadModel();
			if (!$handler->canEditThreads($threadIds, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$threadModel = $this->_getThreadModel();
			$prefixModel = $this->_getPrefixModel();

			$threads = $threadModel->getThreadsByIds($threadIds);

			$nodeIds = $threadModel->getNodeIdsFromThreads($threads);

			$prefixes = $prefixModel->getUsablePrefixesInForums($nodeIds);

			if (empty($prefixes))
			{
				return $this->responseError(new XenForo_Phrase('no_thread_prefixes_available_for_selected_forums'));
			}

			$selectedPrefix = 0;
			$prefixCounts = array(0 => 0);
			foreach ($threads AS $thread)
			{
				$threadPrefixId = $thread['prefix_id'];

				if (!isset($prefixCounts[$threadPrefixId]))
				{
					$prefixCounts[$threadPrefixId] = 1;
				}
				else
				{
					$prefixCounts[$threadPrefixId]++;
				}

				if ($prefixCounts[$threadPrefixId] > $prefixCounts[$selectedPrefix])
				{
					$selectedPrefix = $threadPrefixId;
				}
			}

			$viewParams = array(
				'threadIds' => $threadIds,
				'threadCount' => count($threadIds),
				'threads' => $threads,
				'nodeIds' => $nodeIds,
				'forumCount' => count($nodeIds),
				'prefixes' => $prefixes,
				'selectedPrefix' => $selectedPrefix,
				'redirect' => $redirect,
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Thread_Prefix', 'inline_mod_thread_prefix', $viewParams);
		}
	}

	/**
	 * @return XenForo_Model_InlineMod_Thread
	 */
	protected function _getInlineModThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Thread');
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
}