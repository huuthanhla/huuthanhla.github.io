<?php

/**
 * Thread controller.
 */
class XenForo_ControllerAdmin_Thread extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('thread');
	}

	protected function _filterThreadSearchCriteria(array $criteria)
	{
		foreach ($criteria AS $key => $value)
		{
			if ($value === '')
			{
				unset($criteria[$key]);
			}
			else
			{
				switch ($key)
				{
					case 'node_id':
					case 'prefix_id':
					case 'reply_count_start':
					case 'view_count_start':
					case 'first_post_likes_start':
						if ($value === '0' || $value === 0 || (is_array($value) && in_array(0, $value)))
						{
							unset($criteria[$key]);
						}
						break;

					case 'reply_count_end':
					case 'view_count_end':
					case 'first_post_likes_end':
						if ($value === '-1' || $value === -1)
						{
							unset($criteria[$key]);
						}
						break;

					case 'last_post_date_start':
					case 'last_post_date_end':
					case 'post_date_start':
					case 'post_date_end':
						if (!preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', strval($value)))
						{
							unset($criteria[$key]);
						}
						break;
				}
			}
		}

		if (isset($criteria['discussion_state']) && is_array($criteria['discussion_state']) && count($criteria['discussion_state']) == 3)
		{
			// all types selected, no filtering
			unset($criteria['discussion_state']);
		}
		if (isset($criteria['discussion_open']) && is_array($criteria['discussion_open']) && count($criteria['discussion_open']) == 2)
		{
			// both options selected, no filtering
			unset($criteria['discussion_open']);
		}
		if (isset($criteria['sticky']) && is_array($criteria['sticky']) && count($criteria['sticky']) == 2)
		{
			// both options selected, no filtering
			unset($criteria['sticky']);
		}

		return $criteria;
	}

	protected function _prepareThreadSearchCriteria(array $criteria)
	{
		if (isset($criteria['discussion_open']) && is_array($criteria['discussion_open']))
		{
			$criteria['discussion_open'] = reset($criteria['discussion_open']);
		}
		if (isset($criteria['sticky']) && is_array($criteria['sticky']))
		{
			$criteria['sticky'] = reset($criteria['sticky']);
		}

		if (!empty($criteria['username']))
		{
			/* @var $userModel XenForo_Model_User */
			$userModel = $this->getModelFromCache('XenForo_Model_User');
			$usernames = explode(',', $criteria['username']);
			$users = $userModel->getUsersByNames($usernames, array(), $notFound);

			$userIds = array();
			foreach ($users AS $user)
			{
				$userIds[] = $user['user_id'];
			}

			if ($notFound && !$userIds)
			{
				$userIds[] = -1;
			}

			unset($criteria['username']);
			$criteria['user_id'] = $userIds;
		}

		foreach (array('last_post_date', 'post_date') AS $field)
		{
			if (!empty($criteria["{$field}_start"]))
			{
				$date = new DateTime($criteria["{$field}_start"], XenForo_Locale::getDefaultTimeZone());
				$criteria["{$field}_start"]= $date->format('U');
			}

			if (!empty($criteria["{$field}_end"]))
			{
				$date = new DateTime($criteria["{$field}_end"], XenForo_Locale::getDefaultTimeZone());
				$date->setTime(23, 59, 59);
				$criteria["{$field}_end"] = $date->format('U');
			}
		}

		foreach (array(
			'reply_count' => 0,
			'view_count' => 0,
			'first_post_likes' => 0,
			'last_post_date' => 1,
			'post_date' => 1
		) AS $field => $upperMin)
		{
			$lower = null;
			$upper = null;

			if (!empty($criteria["{$field}_start"]) && intval($criteria["{$field}_start"]))
			{
				$lower = intval($criteria["{$field}_start"]);
			}

			if (isset($criteria["{$field}_end"]) && intval($criteria["{$field}_end"]) >= $upperMin)
			{
				$upper = intval($criteria["{$field}_end"]);
			}

			unset($criteria["{$field}_start"], $criteria["{$field}_end"]);

			if ($lower !== null && $upper !== null)
			{
				$criteria[$field] = array('>=<', $lower, $upper);
			}
			else if ($lower !== null)
			{
				$criteria[$field] = array('>=', $lower);
			}
			else if ($upper !== null)
			{
				$criteria[$field] = array('<=', $upper);
			}
		}

		foreach (array('title') AS $field)
		{
			if (isset($criteria[$field]) && is_string($criteria[$field]))
			{
				$criteria[$field] = trim($criteria[$field]);
			}
		}

		return $criteria;
	}

	public function actionBatchUpdate()
	{
		if ($this->isConfirmedPost())
		{
			$criteria = $this->_input->filterSingle('criteria', XenForo_Input::JSON_ARRAY);
			$criteria = $this->_filterThreadSearchCriteria($criteria);
			$criteriaPrepared = $this->_prepareThreadSearchCriteria($criteria);

			$threadIds = $this->_input->filterSingle('thread_ids', XenForo_Input::JSON_ARRAY);

			$threadModel = $this->_getThreadModel();

			$totalThreads = $threadIds ? count($threadIds) : $threadModel->countThreads($criteriaPrepared);
			if (!$totalThreads)
			{
				return $this->responseError(new XenForo_Phrase('no_items_matched_your_filter'));
			}

			$actions = $this->_input->filterSingle('actions', XenForo_Input::ARRAY_SIMPLE);

			if ($this->_input->filterSingle('confirmUpdate', XenForo_Input::UINT) && $actions)
			{
				$defer = array(
					'actions' => $actions,
					'total' => $totalThreads
				);

				if ($threadIds)
				{
					$defer['threadIds'] = $threadIds;
				}
				else if ($totalThreads > 10000)
				{
					$defer['criteria'] = $criteriaPrepared;
				}
				else
				{
					$defer['threadIds'] = $threadModel->getThreadIds($criteriaPrepared);
				}

				XenForo_Application::defer('ThreadAction', $defer, null, true);

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('tools/run-deferred', null, array(
						'redirect' => XenForo_Link::buildAdminLink('threads/batch-update', null, array('success' => 1))
					))
				);
			}
			else
			{
				return $this->responseView('XenForo_ViewAdmin_Thread_BatchUpdateConfirm', 'thread_batch_update_confirm', array(
					'criteria' => $criteria,
					'threadIds' => $threadIds,
					'totalThreads' => $totalThreads,
					'linkParams' => array('criteria' => $criteria, 'order' => 'last_post_date', 'direction' => 'desc'),
					'nodes' => $this->_getNodeModel()->getAllNodes(),
					'prefixes' => $this->getModelFromCache('XenForo_Model_ThreadPrefix')->getPrefixOptions(),
				));
			}
		}
		else
		{
			$viewParams = array(
				'nodes' => $this->_getNodeModel()->getAllNodes(),
				'prefixes' => $this->getModelFromCache('XenForo_Model_ThreadPrefix')->getPrefixOptions(),
				'criteria' => array(
					'discussion_state' => array('visible' => true, 'deleted' => true, 'moderated' => true),
					'discussion_open' => array(0 => true, 1 => true),
					'sticky' => array(0 => true, 1 => true),
					'reply_count_end' => -1,
					'view_count_end' => -1,
					'first_post_likes_end' => -1,
					'node_id' => array(0),
					'prefix_id' => array(0),
				),
				'success' => $this->_input->filterSingle('success', XenForo_Input::UINT)
			);

			return $this->responseView('XenForo_ViewAdmin_Thread_BatchUpdateSearch', 'thread_batch_update_search', $viewParams);
		}
	}

	public function actionList()
	{
		$criteria = $this->_input->filterSingle('criteria', XenForo_Input::JSON_ARRAY);
		$criteria = $this->_filterThreadSearchCriteria($criteria);

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING);
		$direction = $this->_input->filterSingle('direction', XenForo_Input::STRING);

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 50;

		$showingAll = $this->_input->filterSingle('all', XenForo_Input::UINT);
		if ($showingAll)
		{
			$page = 1;
			$perPage = 5000;
		}

		$fetchOptions = array(
			'perPage' => $perPage,
			'page' => $page,
			'join' => XenForo_Model_Thread::FETCH_FORUM,

			'order' => $order,
			'direction' => $direction
		);

		$threadModel = $this->_getThreadModel();

		$criteriaPrepared = $this->_prepareThreadSearchCriteria($criteria);

		$totalThreads = $threadModel->countThreads($criteriaPrepared);
		if (!$totalThreads)
		{
			return $this->responseError(new XenForo_Phrase('no_items_matched_your_filter'));
		}

		$threads = $threadModel->getThreads($criteriaPrepared, $fetchOptions);

		$viewParams = array(
			'threads' => $threads,
			'totalThreads' => $totalThreads,
			'showingAll' => $showingAll,
			'showAll' => (!$showingAll && $totalThreads <= 5000),

			'linkParams' => array('criteria' => $criteria, 'order' => $order, 'direction' => $direction),
			'page' => $page,
			'perPage' => $perPage,
		);

		return $this->responseView('XenForo_ViewAdmin_Thread_List', 'thread_list', $viewParams);
	}

	public function actionReplyBans()
	{
		/*
		 * per forum
		 * per user
		 */

		$order = 'ban_date';
		$direction = 'desc';

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 25;

		$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
		if ($username)
		{
			$user = $this->getModelFromCache('XenForo_Model_User')->getUserByName($username);
		}
		else
		{
			$user = null;
		}

		$linkParams = array();
		$conditions = array();
		$fetchOptions = array(
			'perPage' => $perPage,
			'page' => $page,
			'join' => XenForo_Model_Thread::FETCH_REPLY_BAN_FORUM,

			'order' => $order,
			'direction' => $direction
		);

		if ($user)
		{
			$conditions['user_id'] = $user['user_id'];
			$linkParams['username'] = $user['username'];
		}

		$threadModel = $this->_getThreadModel();

		// count reply bans
		$totalBans = $threadModel->countThreadReplyBans($conditions);

		// display reply bans
		$bans = $threadModel->getThreadReplyBans($conditions, $fetchOptions);

		$viewParams = array(
			'bans' => $bans,
			'totalBans' => $totalBans,

			'user' => $user,

			'linkParams' => $linkParams,
			'page' => $page,
			'perPage' => $perPage,
		);

		return $this->responseView('XenForo_ViewAdmin_Thread_ReplyBans', 'thread_reply_ban_list', $viewParams);
	}

	public function actionReplyBansDelete()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		$thread = $this->_getThreadModel()->getThreadById($threadId);
		$user = $this->getModelFromCache('XenForo_Model_User')->getUserById($userId);

		if (!$thread || !$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_page_not_found'));
		}

		if ($this->isConfirmedPost())
		{
			$this->_getThreadModel()->deleteThreadReplyBan($thread, $user);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('threads/reply-bans')
			);
		}
		else
		{
			$viewParams = array(
				'thread' => $thread,
				'user' => $user
			);
			return $this->responseView('XenForo_ViewAdmin_Thread_ReplyBansDelete', 'thread_reply_ban_delete', $viewParams);
		}
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}

	/**
	 * @return XenForo_Model_UserGroup
	 */
	protected function _getUserGroupModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroup');
	}
}