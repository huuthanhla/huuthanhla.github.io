<?php

/**
 * Controller for content watching actions.
 *
 * @package XenForo_Watch
 */
class XenForo_ControllerPublic_Watched extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Pre-dispatch code for all actions.
	 */
	protected function _preDispatch($action)
	{
		if (strtolower($action) !== 'viaemail')
		{
			$this->_assertRegistrationRequired();
		}
	}

	protected function _assertIpNotBanned() {}
	protected function _assertViewingPermissions($action) {}

	public function actionViaEmail()
	{
		$userId = $this->_input->filterSingle('u', XenForo_Input::UINT);
		if (!$userId)
		{
			return $this->responseError(new XenForo_Phrase('this_link_is_not_usable_by_you'), 403);
		}

		$confirmKey = $this->_input->filterSingle('c', XenForo_Input::STRING);

		/** @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');

		$user = $userModel->getUserById($userId);
		if (!$user || $confirmKey != $userModel->getUserEmailConfirmKey($user))
		{
			return $this->responseError(new XenForo_Phrase('this_link_could_not_be_verified'), 403);
		}

		$action = $this->_input->filterSingle('a', XenForo_Input::STRING);
		$type = $this->_input->filterSingle('t', XenForo_Input::STRING);
		$id = $type ? $this->_input->filterSingle('id', XenForo_Input::UINT) : 0;

		if ($this->isConfirmedPost())
		{
			switch ($action)
			{
				case 'stop':
					$action = '';
					break;

				case 'no_email':
				default:
					$action = 'watch_no_email';
			}

			$this->_takeEmailAction($user, $action, $type, $id);

			return $this->responseMessage(new XenForo_Phrase('your_email_notification_selections_have_been_updated'));
		}
		else
		{
			$viewParams = array(
				'user' => $user,
				'confirmKey' => $confirmKey,
				'action' => $action,
				'type' => $type,
				'id' => $id,
				'confirmPhrase' => $this->_getEmailActionConfirmPhrase($user, $action, $type, $id)
			);

			return $this->responseView('XenForo_ViewPublic_Watched_ViaEmail', 'watch_via_email', $viewParams);
		}
	}

	protected function _takeEmailAction(array $user, $action, $type, $id)
	{
		if ($type == '' || $type == 'thread')
		{
			if ($id)
			{
				$this->_getThreadWatchModel()->setThreadWatchState($user['user_id'], $id, $action);
			}
			else
			{
				$this->_getThreadWatchModel()->setThreadWatchStateForAll($user['user_id'], $action);
			}
		}

		if ($type == '' || $type == 'forum')
		{
			if ($id)
			{
				$this->_getForumWatchModel()->setForumWatchState(
					$user['user_id'], $id,
					$action == '' ? 'delete' : null,
					null,
					$action == 'watch_email' ? true : false
				);
			}
			else
			{
				$this->_getForumWatchModel()->setForumWatchStateForAll($user['user_id'], $action);
			}
		}
	}

	protected function _getEmailActionConfirmPhrase(array $user, $action, $type, $id)
	{
		if ($type == 'thread')
		{
			if ($id)
			{
				return new XenForo_Phrase('you_sure_you_want_to_update_notification_settings_for_one_thread');
			}
			else
			{
				return new XenForo_Phrase('you_sure_you_want_to_update_notification_settings_for_all_threads');
			}
		}

		if ($type == 'forum')
		{
			if ($id)
			{
				return new XenForo_Phrase('you_sure_you_want_to_update_notification_settings_for_one_forum');
			}
			else
			{
				return new XenForo_Phrase('you_sure_you_want_to_update_notification_settings_for_all_forums');
			}
		}

		return new XenForo_Phrase('you_sure_you_want_to_update_notification_settings_for_all_watched');
	}

	/**
	 * List of all new watched threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionThreads()
	{
		$threadWatchModel = $this->_getThreadWatchModel();
		$visitor = XenForo_Visitor::getInstance();

		$newThreads = $threadWatchModel->getThreadsWatchedByUser($visitor['user_id'], true, array(
			'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
			'readUserId' => $visitor['user_id'],
			'postCountUserId' => $visitor['user_id'],
			'permissionCombinationId' => $visitor['permission_combination_id'],
			'limit' => XenForo_Application::get('options')->discussionsPerPage
		));
		$newThreads = $threadWatchModel->unserializePermissionsInList($newThreads, 'node_permission_cache');
		$newThreads = $threadWatchModel->getViewableThreadsFromList($newThreads);

		$newThreads = $this->_prepareWatchedThreads($newThreads);

		$viewParams = array(
			'newThreads' => $newThreads
		);

		return $this->responseView('XenForo_ViewPublic_Watched_Threads', 'watch_threads', $viewParams);
	}

	/**
	 * List of all watched threads.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionThreadsAll()
	{
		$threadWatchModel = $this->_getThreadWatchModel();
		$visitor = XenForo_Visitor::getInstance();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$threadsPerPage = XenForo_Application::get('options')->discussionsPerPage;

		$threads = $threadWatchModel->getThreadsWatchedByUser($visitor['user_id'], false, array(
			'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
			'readUserId' => $visitor['user_id'],
			'postCountUserId' => $visitor['user_id'],
			'permissionCombinationId' => $visitor['permission_combination_id'],
			'perPage' => $threadsPerPage,
			'page' => $page,
		));
		$threads = $threadWatchModel->unserializePermissionsInList($threads, 'node_permission_cache');
		$threads = $threadWatchModel->getViewableThreadsFromList($threads);

		$threads = $this->_prepareWatchedThreads($threads);

		$totalThreads = $threadWatchModel->countThreadsWatchedByUser($visitor['user_id']);

		$this->canonicalizePageNumber($page, $threadsPerPage, $totalThreads, 'watched/threads/all');

		$viewParams = array(
			'threads' => $threads,
			'page' => $page,
			'threadsPerPage' => $threadsPerPage,
			'totalThreads' => $totalThreads
		);

		return $this->responseView('XenForo_ViewPublic_Watched_ThreadsAll', 'watch_threads_all', $viewParams);
	}

	protected function _prepareWatchedThreads(array $threads)
	{
		$visitor = XenForo_Visitor::getInstance();

		$threadModel = $this->_getThreadModel();
		foreach ($threads AS &$thread)
		{
			if (!$visitor->hasNodePermissionsCached($thread['node_id']))
			{
				$visitor->setNodePermissions($thread['node_id'], $thread['permissions']);
			}

			$thread = $threadModel->prepareThread($thread, $thread);

			// prevent these things from interfering
			$thread['canInlineMod'] = false;
			$thread['canEditThread'] = false;
			$thread['isIgnored'] = false;
		}

		return $threads;
	}

	/**
	 * Update selected watched threads (stop watching, change email notification settings).
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionThreadsUpdate()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'thread_ids' => array(XenForo_Input::UINT, 'array' => true),
			'do' => XenForo_Input::STRING
		));

		$watch = $this->_getThreadWatchModel()->getUserThreadWatchByThreadIds(XenForo_Visitor::getUserId(), $input['thread_ids']);

		foreach ($watch AS $threadWatch)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadWatch');
			$dw->setExistingData($threadWatch, true);

			switch ($input['do'])
			{
				case 'stop':
					$dw->delete();
					break;

				case 'email':
					$dw->set('email_subscribe', 1);
					$dw->save();
					break;

				case 'no_email':
					$dw->set('email_subscribe', 0);
					$dw->save();
					break;
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect(XenForo_Link::buildPublicLink('watched/threads/all'))
		);
	}

	public function actionThreadsAllManage()
	{
		$action = $this->_input->filterSingle('act', XenForo_Input::STRING);

		if ($this->isConfirmedPost())
		{
			$this->_getThreadWatchModel()->setThreadWatchStateForAll(XenForo_Visitor::getUserId(), $action);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect(XenForo_Link::buildPublicLink('watched/threads/all'))
			);
		}
		else
		{
			$viewParams = array(
				'action' => $action
			);

			return $this->responseView('XenForo_ViewPublic_Watched_ThreadsAllManage', 'watch_threads_all_manage', $viewParams);
		}
	}

	/**
	 * List of all new watched content.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionForums()
	{
		/** @var $nodeModel XenForo_Model_Node */
		$nodeModel = $this->getModelFromCache('XenForo_Model_Node');
		$forumWatchModel = $this->_getForumWatchModel();
		$visitor = XenForo_Visitor::getInstance();

		$forumsWatched = $forumWatchModel->getUserForumWatchByUser($visitor['user_id']);
		$nodes = $nodeModel->getAllNodes(false, false);
		foreach ($nodes AS $nodeId => $node)
		{
			if ($node['display_in_list'])
			{
				continue;
			}

			if (!isset($forumsWatched[$nodeId]))
			{
				unset($nodes[$nodeId]);
			}
		}

		$viewParams = array(
			'nodeList' => $nodeModel->getNodeListDisplayData($nodes, 0, 0),
			'forumsWatched' => $forumsWatched
		);

		return $this->responseView('XenForo_ViewPublic_Watched_Forums', 'watch_forums', $viewParams);
	}

	public function actionForumsUpdate()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'node_ids' => array(XenForo_Input::UINT, 'array' => true),
			'do' => XenForo_Input::STRING
		));

		$watch = $this->_getForumWatchModel()->getUserForumWatchByNodeIds(XenForo_Visitor::getUserId(), $input['node_ids']);

		foreach ($watch AS $forumWatch)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ForumWatch');
			$dw->setExistingData($forumWatch, true);

			switch ($input['do'])
			{
				case 'stop':
					$dw->delete();
					break;

				case 'email':
					$dw->set('send_email', 1);
					$dw->save();
					break;

				case 'no_email':
					$dw->set('send_email', 0);
					$dw->save();
					break;

				case 'alert':
					$dw->set('send_alert', 1);
					$dw->save();
					break;

				case 'no_alert':
					$dw->set('send_alert', 0);
					$dw->save();
					break;
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect(XenForo_Link::buildPublicLink('watched/forums'))
		);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('managing_account_details');
	}

	/**
	 * @return XenForo_Model_ThreadWatch
	 */
	protected function _getThreadWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadWatch');
	}

	/**
	 * @return XenForo_Model_ForumWatch
	 */
	protected function _getForumWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ForumWatch');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}
}