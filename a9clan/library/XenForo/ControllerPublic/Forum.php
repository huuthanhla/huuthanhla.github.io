<?php

/**
 * Controller for handling actions on forums.
 *
 * @package XenForo_Forum
 */
class XenForo_ControllerPublic_Forum extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Adds 'forum' to the list of $containerParams if it exists in $params
	 */
	protected function _postDispatch($controllerResponse, $controllerName, $action)
	{
		if (isset($controllerResponse->params['forum']))
		{
			$controllerResponse->containerParams['forum'] = $controllerResponse->params['forum'];
		}
	}

	public function actionIndex()
	{
		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);
		if ($forumId || $forumName)
		{
			return $this->responseReroute(__CLASS__, 'forum');
		}

		if ($this->_routeMatch->getResponseType() == 'rss')
		{
			return $this->getGlobalForumRss();
		}

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('forums')
		);

		$visitor = XenForo_Visitor::getInstance();
		$profilePostLimit = XenForo_Application::getOptions()->forumListNewProfilePosts;

		if ($profilePostLimit && $visitor->canViewProfilePosts())
		{
			/** @var XenForo_Model_ProfilePost $profilePostModel */
			$profilePostModel = $this->getModelFromCache('XenForo_Model_ProfilePost');
			$profilePosts = $profilePostModel->getLatestProfilePosts(
				array(
					'deleted' => false,
					'moderated' => false
				), array(
					'limit' => max($profilePostLimit * 2, 10),
					'join' =>
						XenForo_Model_ProfilePost::FETCH_USER_POSTER |
						XenForo_Model_ProfilePost::FETCH_USER_RECEIVER |
						XenForo_Model_ProfilePost::FETCH_USER_RECEIVER_PRIVACY,
					'permissionCombinationId' => $visitor->permission_combination_id
				)
			);
			foreach ($profilePosts AS $id => &$profilePost)
			{
				$receivingUser = $profilePostModel->getProfileUserFromProfilePost($profilePost);
				if (!$profilePostModel->canViewProfilePostAndContainer($profilePost, $receivingUser))
				{
					unset($profilePosts[$id]);
				}

				$profilePost = $profilePostModel->prepareProfilePost($profilePost, $receivingUser);
				if (!empty($profilePost['isIgnored']))
				{
					unset($profilePosts[$id]);
				}
			}
			$profilePosts = array_slice($profilePosts, 0, $profilePostLimit, true);
		}
		else
		{
			$profilePosts = array();
		}

		$viewParams = array(
			'nodeList' => $this->_getNodeModel()->getNodeDataForListDisplay(false, 0),
			'onlineUsers' => $this->_getSessionActivityList(),
			'boardTotals' => $this->_getBoardTotals(),
			'canViewMemberList' => $this->getModelFromCache('XenForo_Model_User')->canViewMemberList(),

			'profilePosts' => $profilePosts,
			'canUpdateStatus' => XenForo_Visitor::getInstance()->canUpdateStatus()
		);

		return $this->responseView('XenForo_ViewPublic_Forum_List', 'forum_list', $viewParams);
	}

	protected function _getSessionActivityList()
	{
		$visitor = XenForo_Visitor::getInstance();

		/** @var $sessionModel XenForo_Model_Session */
		$sessionModel = $this->getModelFromCache('XenForo_Model_Session');

		return $sessionModel->getSessionActivityQuickList(
			$visitor->toArray(),
			array('cutOff' => array('>', $sessionModel->getOnlineStatusTimeout())),
			($visitor['user_id'] ? $visitor->toArray() : null)
		);

	}

	protected function _getBoardTotals()
	{
		$boardTotals = $this->getModelFromCache('XenForo_Model_DataRegistry')->get('boardTotals');
		if (!$boardTotals)
		{
			$boardTotals = $this->getModelFromCache('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
		}

		return $boardTotals;
	}

	/**
	 * Displays the contents of a forum.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionForum()
	{
		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $this->getHelper('ForumThreadPost')->assertForumValidAndViewable(
			$forumId ? $forumId : $forumName,
			$this->_getForumFetchOptions()
		);
		$forumId = $forum['node_id'];

		$visitor = XenForo_Visitor::getInstance();
		$threadModel = $this->_getThreadModel();
		$forumModel = $this->_getForumModel();

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$threadsPerPage = XenForo_Application::get('options')->discussionsPerPage;

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('forums', $forum, array('page' => $page))
		);

		list($defaultOrder, $defaultOrderDirection) = $this->_getDefaultThreadSort($forum);

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => $defaultOrder));
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING, array('default' => $defaultOrderDirection));

		$displayConditions = $this->_getDisplayConditions($forum);

		$noDateLimit = $this->_input->filterSingle('no_date_limit', XenForo_Input::BOOLEAN);
		$isDateLimited = ($forum['list_date_limit_days'] && $order == 'last_post_date' && !$noDateLimit);
		if ($isDateLimited)
		{
			$displayConditions['last_post_date'] = array('>=', XenForo_Application::$time - 86400 * $forum['list_date_limit_days']);
		}

		$fetchElements = $this->_getThreadFetchElements($forum, $displayConditions);
		$threadFetchConditions = $fetchElements['conditions'];
		$threadFetchOptions = $fetchElements['options'] + array(
			'perPage' => $threadsPerPage,
			'page' => $page,
			'order' => $order,
			'orderDirection' => $orderDirection
		);
		unset($fetchElements);

		$totalThreads = $threadModel->countThreadsInForum($forumId, $threadFetchConditions);

		$this->canonicalizePageNumber($page, $threadsPerPage, $totalThreads, 'forums', $forum);

		$threads = $threadModel->getThreadsInForum($forumId, $threadFetchConditions, $threadFetchOptions);

		if ($page == 1)
		{
			$stickyThreadFetchOptions = $threadFetchOptions;
			unset($stickyThreadFetchOptions['perPage'], $stickyThreadFetchOptions['page']);

			$stickyThreadConditions = $threadFetchConditions;
			unset($stickyThreadConditions['last_post_date']);

			$stickyThreads = $threadModel->getStickyThreadsInForum($forumId, $stickyThreadConditions, $stickyThreadFetchOptions);
		}
		else
		{
			$stickyThreads = array();
		}

		// prepare all threads for the thread list
		$inlineModOptions = array();
		$permissions = $visitor->getNodePermissions($forumId);

		foreach ($threads AS &$thread)
		{
			$threadModOptions = $threadModel->addInlineModOptionToThread($thread, $forum, $permissions);
			$inlineModOptions += $threadModOptions;

			$thread = $threadModel->prepareThread($thread, $forum, $permissions);
		}
		foreach ($stickyThreads AS &$thread)
		{
			$threadModOptions = $threadModel->addInlineModOptionToThread($thread, $forum, $permissions);
			$inlineModOptions += $threadModOptions;

			$thread = $threadModel->prepareThread($thread, $forum, $permissions);
		}
		unset($thread);

		// if we've read everything on the first page of a normal sort order, probably need to mark as read
		if ($visitor['user_id'] && $page == 1 && !$displayConditions
			&& $order == 'last_post_date' && $orderDirection == 'desc'
			&& $forum['forum_read_date'] < $forum['last_post_date']
		)
		{
			$hasNew = false;
			foreach ($threads AS $thread)
			{
				if ($thread['isNew'] && !$thread['isIgnored'])
				{
					$hasNew = true;
					break;
				}
			}

			if (!$hasNew)
			{
				// everything read, but forum not marked as read. Let's check.
				$this->_getForumModel()->markForumReadIfNeeded($forum);
			}
		}

		// get the ordering params set for the header links
		$orderParams = array();
		foreach ($this->_getThreadSortFields($forum) AS $field)
		{
			$orderParams[$field] = $displayConditions;
			$orderParams[$field]['order'] = ($field != $defaultOrder ? $field : false);
			if ($order == $field)
			{
				$orderParams[$field]['direction'] = ($orderDirection == 'desc' ? 'asc' : 'desc');
			}
		}

		$pageNavParams = $displayConditions;
		$pageNavParams['order'] = ($order != $defaultOrder ? $order : false);
		$pageNavParams['direction'] = ($orderDirection != $defaultOrderDirection ? $orderDirection : false);
		if ($noDateLimit)
		{
			$pageNavParams['no_date_limit'] = 1;
		}
		unset($pageNavParams['last_post_date']);

		$threadEndOffset = ($page - 1) * $threadsPerPage + count($threads);
		$showDateLimitDisabler = ($isDateLimited && $threadEndOffset >= $totalThreads);

		$viewParams = array(
			'nodeList' => $this->_getNodeModel()->getNodeDataForListDisplay($forum, 0),
			'forum' => $forum,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum, false),

			'canPostThread' => $forumModel->canPostThreadInForum($forum),
			'canSearch' => $visitor->canSearch(),
			'canWatchForum' => $forumModel->canWatchForum($forum),

			'inlineModOptions' => $inlineModOptions,
			'threads' => $threads,
			'stickyThreads' => $stickyThreads,

			'ignoredNames' => $this->_getIgnoredContentUserNames($threads) + $this->_getIgnoredContentUserNames($stickyThreads),

			'order' => $order,
			'orderDirection' => $orderDirection,
			'orderParams' => $orderParams,
			'displayConditions' => $displayConditions,

			'pageNavParams' => $pageNavParams,
			'page' => $page,
			'threadStartOffset' => ($page - 1) * $threadsPerPage + 1,
			'threadEndOffset' => $threadEndOffset,
			'threadsPerPage' => $threadsPerPage,
			'totalThreads' => $totalThreads,

			'showPostedNotice' => $this->_input->filterSingle('posted', XenForo_Input::UINT),
			'showDateLimitDisabler' => $showDateLimitDisabler
		);

		return $this->responseView('XenForo_ViewPublic_Forum_View', 'forum_view', $viewParams);
	}

	protected function _getForumFetchOptions()
	{
		$userId = XenForo_Visitor::getUserId();

		return array(
			'readUserId' => $userId,
			'watchUserId' => $userId
		);
	}

	protected function _getDisplayConditions(array $forum)
	{
		$displayConditions = array();

		$prefixId = $this->_input->filterSingle('prefix_id', XenForo_Input::UINT);
		if ($prefixId)
		{
			$displayConditions['prefix_id'] = $prefixId;
		}

		return $displayConditions;
	}

	protected function _getThreadFetchElements(array $forum, array $displayConditions)
	{
		$threadModel = $this->_getThreadModel();
		$visitor = XenForo_Visitor::getInstance();

		$threadFetchConditions = $displayConditions + $threadModel->getPermissionBasedThreadFetchConditions($forum);

		if ($this->_routeMatch->getResponseType() != 'rss')
		{
			$threadFetchConditions += array('sticky' => 0);
		}

		$threadFetchOptions = array(
			'join' => XenForo_Model_Thread::FETCH_USER,
			'readUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'postCountUserId' => $visitor['user_id'],
		);
		if (!empty($threadFetchConditions['deleted']))
		{
			$threadFetchOptions['join'] |= XenForo_Model_Thread::FETCH_DELETION_LOG;
		}

		if ($this->getResponseType() == 'rss')
		{
			$threadFetchOptions['join'] |= XenForo_Model_Thread::FETCH_FIRSTPOST;
		}

		return array(
			'conditions' => $threadFetchConditions,
			'options' => $threadFetchOptions
		);
	}

	protected function _getDefaultThreadSort(array $forum)
	{
		return array($forum['default_sort_order'], $forum['default_sort_direction']);
	}

	protected function _getThreadSortFields(array $forum)
	{
		return array('title', 'post_date', 'reply_count', 'view_count', 'last_post_date');
	}

	/**
	 * Gets the data for the global forum RSS feed.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function getGlobalForumRss()
	{
		$threadModel = $this->_getThreadModel();
		$visitor = XenForo_Visitor::getInstance();

		$threadsPerPage = max(1, XenForo_Application::get('options')->discussionsPerPage);
		$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);

		$threads = $threadModel->getThreads(
			array('find_new' => true, 'last_post_date' => array('>', $autoReadDate)),
			array(
				'limit' => $threadsPerPage * 3, // to filter
				'order' => 'last_post_date',
				'join' =>
					XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_FORUM_OPTIONS |
					XenForo_Model_Thread::FETCH_USER | XenForo_Model_Thread::FETCH_FIRSTPOST,
				'permissionCombinationId' => $visitor['permission_combination_id']
			)
		);
		foreach ($threads AS $key => &$thread)
		{
			$thread['permissions'] = XenForo_Permission::unserializePermissions($thread['node_permission_cache']);

			if (!$threadModel->canViewThreadAndContainer($thread, $thread, $null, $thread['permissions']))
			{
				unset($threads[$key]);
			}
		}
		$threads = array_slice($threads, 0, $threadsPerPage, true);

		foreach ($threads AS &$thread)
		{
			$thread = $threadModel->prepareThread($thread, $thread, $thread['permissions']);
		}

		$viewParams = array(
			'threads' => $threads,
		);
		return $this->responseView('XenForo_ViewPublic_Forum_GlobalRss', '', $viewParams);
	}

	/**
	 * Displays a confirmation of watching (or stopping the watch of) a forum.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionWatch()
	{
		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $this->getHelper('ForumThreadPost')->assertForumValidAndViewable(
			$forumId ? $forumId : $forumName,
			$this->_getForumFetchOptions()
		);
		$forumId = $forum['node_id'];

		if (!$this->_getForumModel()->canWatchForum($forum))
		{
			return $this->responseNoPermission();
		}

		/** @var $forumWatchModel XenForo_Model_ForumWatch */
		$forumWatchModel = $this->getModelFromCache('XenForo_Model_ForumWatch');

		if ($this->isConfirmedPost())
		{
			if ($this->_input->filterSingle('stop', XenForo_Input::STRING))
			{
				$notifyOn = 'delete';
			}
			else
			{
				$notifyOn = $this->_input->filterSingle('notify_on', XenForo_Input::STRING);
				if ($notifyOn)
				{
					if ($forum['allowed_watch_notifications'] == 'none')
					{
						$notifyOn = '';
					}
					else if ($forum['allowed_watch_notifications'] == 'thread' && $notifyOn == 'message')
					{
						$notifyOn = 'thread';
					}
				}
			}

			$sendAlert = $this->_input->filterSingle('send_alert', XenForo_Input::BOOLEAN);
			$sendEmail = $this->_input->filterSingle('send_email', XenForo_Input::BOOLEAN);

			$forumWatchModel->setForumWatchState(
				XenForo_Visitor::getUserId(), $forumId,
				$notifyOn, $sendAlert, $sendEmail
			);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('forums', $forum),
				null,
				array('linkPhrase' => ($notifyOn != 'delete' ? new XenForo_Phrase('unwatch_forum') : new XenForo_Phrase('watch_forum')))
			);
		}
		else
		{
			$forumWatch = $forumWatchModel->getUserForumWatchByForumId(
				XenForo_Visitor::getUserId(), $forumId
			);

			$viewParams = array(
				'forum' => $forum,
				'forumWatch' => $forumWatch,
				'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
			);

			return $this->responseView('XenForo_ViewPublic_Forum_Watch', 'forum_watch', $viewParams);
		}
	}

	/**
	 * Displays a form to create a new thread in this forum.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCreateThread()
	{
		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $ftpHelper->assertForumValidAndViewable($forumId ? $forumId : $forumName);

		$forumId = $forum['node_id'];

		$this->_assertCanPostThreadInForum($forum);

		$maxResponses = XenForo_Application::get('options')->pollMaximumResponses;
		if ($maxResponses == 0)
		{
			$maxResponses = 10; // number to create for non-JS users
		}
		if ($maxResponses > 2)
		{
			$pollExtraArray = array_fill(0, $maxResponses - 2, true);
		}
		else
		{
			$pollExtraArray = array();
		}

		$title = $this->_input->filterSingle('title', XenForo_Input::STRING);
		$prefixId = $this->_input->filterSingle('prefix_id', XenForo_Input::STRING);
		$draft = $this->_getDraftModel()->getDraftByUserKey("forum-$forumId", XenForo_Visitor::getUserId());
		$attachmentHash = null;

		if (!$prefixId)
		{
			$prefixId = $forum['default_prefix_id'];
		}

		$poll = array();
		if ($draft)
		{
			$extra = @unserialize($draft['extra_data']);
			if (!empty($extra['prefix_id']) && !$prefixId)
			{
				$prefixId = $extra['prefix_id'];
			}
			if (!empty($extra['title']) && !$title)
			{
				$title = $extra['title'];
			}

			if (!empty($extra['attachment_hash']))
			{
				$attachmentHash = $extra['attachment_hash'];
			}

			if (!empty($extra['poll']))
			{
				$poll = $extra['poll'];
				if (!empty($poll['responses']))
				{
					$poll['extraResponses'] = array();

					$poll['responses'] = array_filter($poll['responses']);
					if (sizeof($poll['responses']) <= 2)
					{
						$poll['extraResponses'] = array_fill(0,  2 - count($poll['responses']), true);
					}
				}
			}
		}

		$attachmentParams = $this->_getForumModel()->getAttachmentParams($forum, array(
			'node_id' => $forum['node_id']
		), null, null, $attachmentHash);

		$viewParams = array(
			'thread' => array(
				'discussion_open' => 1,
				'prefix_id' => $forum['default_prefix_id'],
			),
			'forum' => $forum,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

			'title' => $title,
			'prefixId' => $prefixId,
			'draft' => $draft,

			'prefixes' => $this->_getPrefixModel()->getUsablePrefixesInForums($forumId),

			'attachmentParams' => $attachmentParams,

			'watchState' => $this->_getThreadWatchModel()->getThreadWatchStateForVisitor(false),

			'captcha' => XenForo_Captcha_Abstract::createDefault(),

			'poll' => $poll,
			'canPostPoll' => $this->_getForumModel()->canPostPollInForum($forum),
			'pollExtraArray' => $pollExtraArray,

			'canLockUnlockThread' => $this->_getForumModel()->canLockUnlockThreadInForum($forum),
			'canStickUnstickThread' => $this->_getForumModel()->canStickUnstickThreadInForum($forum),

			'attachmentConstraints' => $this->getModelFromCache('XenForo_Model_Attachment')->getAttachmentConstraints(),
		);
		return $this->responseView('XenForo_ViewPublic_Thread_Create', 'thread_create', $viewParams);
	}

	/**
	 * Inserts a new thread into this forum.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAddThread()
	{
		$this->_assertPostOnly();

		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $ftpHelper->assertForumValidAndViewable($forumId ? $forumId : $forumName);

		$forumId = $forum['node_id'];

		$this->_assertCanPostThreadInForum($forum);

		if (!XenForo_Captcha_Abstract::validateDefault($this->_input))
		{
			return $this->responseCaptchaFailed();
		}

		$visitor = XenForo_Visitor::getInstance();

		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'prefix_id' => XenForo_Input::UINT,
			'attachment_hash' => XenForo_Input::STRING,

			'watch_thread_state' => XenForo_Input::UINT,
			'watch_thread' => XenForo_Input::UINT,
			'watch_thread_email' => XenForo_Input::UINT,

			'_set' => array(XenForo_Input::UINT, 'array' => true),
			'discussion_open' => XenForo_Input::UINT,
			'sticky' => XenForo_Input::UINT,

			'poll' => XenForo_Input::ARRAY_SIMPLE, // filtered below
		));
		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		if (!$this->_getPrefixModel()->verifyPrefixIsUsable($input['prefix_id'], $forumId))
		{
			$input['prefix_id'] = 0; // not usable, just blank it out
		}

		// note: assumes that the message dw will pick up the username issues
		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
		$writer->bulkSet(array(
			'user_id' => $visitor['user_id'],
			'username' => $visitor['username'],
			'title' => $input['title'],
			'prefix_id' => $input['prefix_id'],
			'node_id' => $forumId
		));

		// discussion state changes instead of first message state
		$writer->set('discussion_state', $this->getModelFromCache('XenForo_Model_Post')->getPostInsertMessageState(array(), $forum));

		// discussion open state - moderator permission required
		if (!empty($input['_set']['discussion_open']) && $this->_getForumModel()->canLockUnlockThreadInForum($forum))
		{
			$writer->set('discussion_open', $input['discussion_open']);
		}

		// discussion sticky state - moderator permission required
		if (!empty($input['_set']['sticky']) && $this->_getForumModel()->canStickUnstickThreadInForum($forum))
		{
			$writer->set('sticky', $input['sticky']);
		}

		$postWriter = $writer->getFirstMessageDw();
		$postWriter->set('message', $input['message']);
		$postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $input['attachment_hash']);
		$postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
		$postWriter->setOption(XenForo_DataWriter_DiscussionMessage_Post::OPTION_MAX_TAGGED_USERS, $visitor->hasPermission('general', 'maxTaggedUsers'));

		$writer->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);

		$pollWriter = false;

		if ($this->_getForumModel()->canPostPollInForum($forum))
		{
			$pollInputHandler = new XenForo_Input($input['poll']);
			$pollInput = $pollInputHandler->filter(array(
				'question' => XenForo_Input::STRING,
				'responses' => array(XenForo_Input::STRING, 'array' => true),
			));
			if ($pollInput['question'] !== '')
			{
				/** @var XenForo_Model_Poll $pollModel */
				$pollModel = $this->getModelFromCache('XenForo_Model_Poll');
				$pollWriter = $pollModel->setupNewPollFromForm($pollInputHandler);

				$pollWriter->set('content_type', 'thread');
				$pollWriter->set('content_id', 0); // changed before saving
				$pollWriter->preSave();

				$writer->mergeErrors($pollWriter->getErrors());

				$writer->set('discussion_type', 'poll', '', array('setAfterPreSave' => true));
			}
			else
			{
				foreach ($pollInput['responses'] AS $response)
				{
					if ($response !== '')
					{
						$writer->error(new XenForo_Phrase('you_entered_poll_response_but_no_question'));
						break;
					}
				}
			}
		}

		$spamModel = $this->_getSpamPreventionModel();

		if (!$writer->hasErrors()
			&& $writer->get('discussion_state') == 'visible'
			&& $spamModel->visitorRequiresSpamCheck()
		)
		{
			switch ($spamModel->checkMessageSpam($input['title'] . "\n" . $input['message'], array(), $this->_request))
			{
				case XenForo_Model_SpamPrevention::RESULT_MODERATED:
					$writer->set('discussion_state', 'moderated');
					break;

				case XenForo_Model_SpamPrevention::RESULT_DENIED;
					$spamModel->logSpamTrigger('thread', null);
					$writer->error(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'));
					break;
			}
		}

		$writer->preSave();

		if ($forum['require_prefix'] && !$writer->get('prefix_id'))
		{
			$writer->error(new XenForo_Phrase('please_select_a_prefix'), 'prefix_id');
		}

		if (!$writer->hasErrors())
		{
			$this->assertNotFlooding('post');
		}

		$writer->save();

		$thread = $writer->getMergedData();

		if ($pollWriter)
		{
			$pollWriter->set('content_id', $thread['thread_id'], '', array('setAfterPreSave' => true));
			$pollWriter->save();
		}

		$spamModel->logContentSpamCheck('thread', $thread['thread_id']);
		$spamModel->logSpamTrigger('thread', $thread['thread_id']);
		$this->_getDraftModel()->deleteDraft('forum-' . $forum['node_id']);

		$this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], $input);

		$this->_getThreadModel()->markThreadRead($thread, $forum, XenForo_Application::$time);

		if (!$this->_getThreadModel()->canViewThread($thread, $forum))
		{
			$return = XenForo_Link::buildPublicLink('forums', $forum, array('posted' => 1));
		}
		else
		{
			$return = XenForo_Link::buildPublicLink('threads', $thread);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$return,
			new XenForo_Phrase('your_thread_has_been_posted')
		);
	}

	/**
	 * Shows a preview of the thread creation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCreateThreadPreview()
	{
		$this->_assertPostOnly();

		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $ftpHelper->assertForumValidAndViewable($forumId ? $forumId : $forumName);

		$forumId = $forum['node_id'];

		$this->_assertCanPostThreadInForum($forum);

		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$message = XenForo_Helper_String::autoLinkBbCode($message);

		/** @var $taggingModel XenForo_Model_UserTagging */
		$taggingModel = $this->getModelFromCache('XenForo_Model_UserTagging');
		$taggingModel->getTaggedUsersInMessage($message, $message);

		$viewParams = array(
			'forum' => $forum,
			'message' => $message
		);

		return $this->responseView('XenForo_ViewPublic_Thread_CreatePreview', 'thread_create_preview', $viewParams);
	}

	public function actionSaveDraft()
	{
		$this->_assertPostOnly();

		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $ftpHelper->assertForumValidAndViewable($forumId ? $forumId : $forumName);

		$forumId = $forum['node_id'];

		$this->_assertCanPostThreadInForum($forum);

		$extra = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'prefix_id' => XenForo_Input::UINT,
			'attachment_hash' => XenForo_Input::STRING,

			'watch_thread_state' => XenForo_Input::UINT,
			'watch_thread' => XenForo_Input::UINT,
			'watch_thread_email' => XenForo_Input::UINT,

			'_set' => array(XenForo_Input::UINT, 'array' => true),
			'discussion_open' => XenForo_Input::UINT,
			'sticky' => XenForo_Input::UINT,

			'poll' => XenForo_Input::ARRAY_SIMPLE, // filtered below
		));
		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$forceDelete = $this->_input->filterSingle('delete_draft', XenForo_Input::BOOLEAN);

		if (!strlen($message) || $forceDelete)
		{
			$draftSaved = false;
			$draftDeleted = $this->_getDraftModel()->deleteDraft("forum-$forumId") || $forceDelete;
		}
		else
		{
			$this->_getDraftModel()->saveDraft("forum-$forumId", $message, $extra);
			$draftSaved = true;
			$draftDeleted = false;
		}

		$viewParams = array(
			'forum' => $forum,
			'draftSaved' => $draftSaved,
			'draftDeleted' => $draftDeleted
		);
		return $this->responseView('XenForo_ViewPublic_Forum_SaveDraft', '', $viewParams);
	}

	public function actionMarkRead()
	{
		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forumName = $this->_input->filterSingle('node_name', XenForo_Input::STRING);

		$visitor = XenForo_Visitor::getInstance();

		$markDate = $this->_input->filterSingle('date', XenForo_Input::UINT);
		if (!$markDate)
		{
			$markDate = XenForo_Application::$time;
		}

		$forumModel = $this->_getForumModel();

		if ($forumId || $forumName)
		{
			// mark individual forum read
			$ftpHelper = $this->getHelper('ForumThreadPost');
			$forum = $ftpHelper->assertForumValidAndViewable(
				$forumId ? $forumId : $forumName, array('readUserId' => $visitor['user_id'])
			);

			$forumId = $forum['node_id'];

			if ($this->isConfirmedPost())
			{
				$forumModel->markForumTreeRead($forum, $markDate);

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('forums', $forum),
					new XenForo_Phrase('forum_x_marked_as_read', array('forum' => $forum['title']))
				);
			}
			else
			{
				$viewParams = array(
					'forum' => $forum,
					'markDate' => $markDate
				);

				return $this->responseView('XenForo_ViewPublic_Forum_MarkRead', 'forum_mark_read', $viewParams);
			}
		}
		else
		{
			// mark all forums read
			if ($this->isConfirmedPost())
			{
				$forumModel->markForumTreeRead(null, $markDate);

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('forums'),
					new XenForo_Phrase('all_forums_marked_as_read')
				);
			}
			else
			{
				$viewParams = array(
					'forum' => false,
					'markDate' => $markDate
				);

				return $this->responseView('XenForo_ViewPublic_Forum_MarkRead', 'forum_mark_read', $viewParams);
			}
		}
	}

	/**
	 * Fetches a grouped list of all prefixes available to the specified forum
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPrefixes()
	{
		$this->_assertPostOnly();

		$forumId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forum = $this->getHelper('ForumThreadPost')->assertForumValidAndViewable($forumId);

		$viewParams = array(
			'forum' => $forum,
			'prefixGroups' => $this->_getPrefixModel()->getUsablePrefixesInForums($forum['node_id']),
		);

		return $this->responseView('XenForo_ViewPublic_Forum_Prefixes', '', $viewParams);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		$forumIds = array();
		$nodeNames = array();
		foreach ($activities AS $activity)
		{
			if (!empty($activity['params']['node_id']))
			{
				$forumIds[$activity['params']['node_id']] = intval($activity['params']['node_id']);
			}
			else if (!empty($activity['params']['node_name']))
			{
				$nodeNames[$activity['params']['node_name']] = $activity['params']['node_name'];
			}
		}

		if ($nodeNames)
		{
			$nodeNames = XenForo_Model::create('XenForo_Model_Node')->getNodeIdsFromNames($nodeNames);

			foreach ($nodeNames AS $nodeName => $nodeId)
			{
				$forumIds[$nodeName] = $nodeId;
			}
		}

		$forumData = array();

		if ($forumIds)
		{
			/* @var $forumModel XenForo_Model_Forum */
			$forumModel = XenForo_Model::create('XenForo_Model_Forum');

			$visitor = XenForo_Visitor::getInstance();
			$permissionCombinationId = $visitor['permission_combination_id'];

			$forums = $forumModel->getForumsByIds($forumIds, array(
				'permissionCombinationId' => $permissionCombinationId
			));
			foreach ($forums AS $forum)
			{
				$visitor->setNodePermissions($forum['node_id'], $forum['node_permission_cache']);
				if ($forumModel->canViewForum($forum))
				{
					$forumData[$forum['node_id']] = array(
						'title' => $forum['title'],
						'url' => XenForo_Link::buildPublicLink('forums', $forum)
					);
				}
			}
		}

		$output = array();
		foreach ($activities AS $key => $activity)
		{
			$forum = false;
			$list = false;
			if (!empty($activity['params']['node_id']))
			{
				$nodeId = $activity['params']['node_id'];
				if (isset($forumData[$nodeId]))
				{
					$forum = $forumData[$nodeId];
				}
			}
			else if (!empty($activity['params']['node_name']))
			{
				$nodeName = $activity['params']['node_name'];
				if (isset($nodeNames[$nodeName]))
				{
					$nodeId = $nodeNames[$nodeName];
					if (isset($forumData[$nodeId]))
					{
						$forum = $forumData[$nodeId];
					}
				}
			}
			else
			{
				$list = true;
			}

			if ($forum)
			{
				$output[$key] = array(
					new XenForo_Phrase('viewing_forum'),
					$forum['title'],
					$forum['url'],
					false
				);
			}
			else
			{
				$output[$key] = $list ? new XenForo_Phrase('viewing_forum_list') : new XenForo_Phrase('viewing_forum');
			}
		}

		return $output;
	}

	/**
	 * Asserts that the currently browsing user can post a thread in
	 * the specified forum.
	 *
	 * @param array $forum
	 */
	protected function _assertCanPostThreadInForum(array $forum)
	{
		if (!$this->_getForumModel()->canPostThreadInForum($forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_ThreadWatch
	 */
	protected function _getThreadWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadWatch');
	}

	/**
	 * @return XenForo_Model_ThreadPrefix
	 */
	protected function _getPrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}

	/**
	 * @return XenForo_Model_SpamPrevention
	 */
	protected function _getSpamPreventionModel()
	{
		return $this->getModelFromCache('XenForo_Model_SpamPrevention');
	}

	/**
	 * @return XenForo_Model_Draft
	 */
	protected function _getDraftModel()
	{
		return $this->getModelFromCache('XenForo_Model_Draft');
	}
}