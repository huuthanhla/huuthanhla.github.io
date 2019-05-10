<?php

/**
 * Controller for post-related actions.
 *
 * @package XenForo_Post
 */
class XenForo_ControllerPublic_Post extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		return $this->getPostSpecificRedirect(
			$post, $thread, XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT
		);
	}

	/**
	 * Gets the redirect to a particular post in the specified thread.
	 *
	 * @param array $post
	 * @param array $thread
	 * @param constant $redirectType
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function getPostSpecificRedirect(array $post, array $thread,
		$redirectType = XenForo_ControllerResponse_Redirect::SUCCESS
	)
	{
		$page = floor($post['position'] / XenForo_Application::get('options')->messagesPerPage) + 1;

		return $this->responseRedirect(
			$redirectType,
			XenForo_Link::buildPublicLink('threads', $thread, array('page' => $page)) . '#post-' . $post['post_id']
		);
	}

	/**
	 * Displays a form to edit an existing post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);
		$this->_assertCanEditPost($post, $thread, $forum);

		$postModel = $this->_getPostModel();
		$attachmentModel = $this->_getAttachmentModel();

		$attachmentParams = $this->getModelFromCache('XenForo_Model_Forum')->getAttachmentParams($forum, array(
			'post_id' => $post['post_id']
		));

		$attachments = $attachmentModel->getAttachmentsByContentId('post', $postId);

		if ($this->_input->inRequest('more_options'))
		{
			$post['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		}

		$viewParams = array(
			'post' => $post,
			'thread' => $thread,
			'forum' => $forum,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

			'attachmentParams' => $attachmentParams,
			'attachments' => $attachmentModel->prepareAttachments($attachments),
			'attachmentConstraints' => $attachmentModel->getAttachmentConstraints(),

			'watchState' => $this->_getThreadWatchModel()->getThreadWatchStateForVisitor($thread['thread_id'], false),

			'canDeletePost' => $postModel->canDeletePost($post, $thread, $forum, 'soft'),
			'canHardDeletePost' => $postModel->canDeletePost($post, $thread, $forum, 'hard'),
			'canSilentEdit' => $postModel->canControlSilentEdit($post, $thread, $forum)
		);

		if ($this->_input->inRequest('more_options'))
		{
			$viewParams['silentEdit'] = $this->_input->filterSingle('silent', XenForo_Input::UINT);
			$viewParams['clearEdit'] = $this->_input->filterSingle('clear_edit', XenForo_Input::UINT);
		}

		return $this->responseView('XenForo_ViewPublic_Post_Edit', 'post_edit', $viewParams);
	}

	/**
	 * Updates an existing post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		$this->_assertCanEditPost($post, $thread, $forum);

		$input = $this->_input->filter(array(
			'attachment_hash' => XenForo_Input::STRING,

			'watch_thread_state' => XenForo_Input::UINT,
			'watch_thread' => XenForo_Input::UINT,
			'watch_thread_email' => XenForo_Input::UINT
		));
		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
		$dw->setExistingData($postId);
		$dw->set('message', $input['message']);
		$dw->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $input['attachment_hash']);
		$dw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
		$this->_setSilentEditOptions($post, $thread, $forum, $dw);

		$spamModel = $this->_getSpamPreventionModel();

		if (!$dw->hasErrors()
			&& $dw->get('message_state') == 'visible'
			&& $spamModel->visitorRequiresSpamCheck()
		)
		{
			$spamExtraParams = array(
				'permalink' => XenForo_Link::buildPublicLink('canonical:threads', $thread)
			);
			switch ($spamModel->checkMessageSpam($input['message'], $spamExtraParams, $this->_request))
			{
				case XenForo_Model_SpamPrevention::RESULT_MODERATED:
				case XenForo_Model_SpamPrevention::RESULT_DENIED;
					$spamModel->logSpamTrigger('post', $post['post_id']);
					$dw->error(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'));
					break;
			}
		}

		$dw->save();

		$this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], $input);

		XenForo_Model_Log::logModeratorAction('post', $post, 'edit', array(), $thread);

		return $this->getPostSpecificRedirect($post, $thread);
	}

	/**
	 * Shows a preview of the edit.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditPreview()
	{
		$this->_assertPostOnly();

		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		$this->_assertCanEditPost($post, $thread, $forum);

		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$message = XenForo_Helper_String::autoLinkBbCode($message);

		/** @var $taggingModel XenForo_Model_UserTagging */
		$taggingModel = $this->getModelFromCache('XenForo_Model_UserTagging');
		$taggingModel->getTaggedUsersInMessage($message, $message);

		$viewParams = array(
			'post' => $post,
			'thread' => $thread,
			'forum' => $forum,
			'message' => $message
		);

		return $this->responseView('XenForo_ViewPublic_Post_EditPreview', 'post_edit_preview', $viewParams);
	}

	/**
	 * Displays a simple form to edit an existing post inline
	 *
	 * @return XenForo_ControllerPublic_Abstract
	 */
	public function actionEditInline()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);
		$this->_assertCanEditPost($post, $thread, $forum);

		$postModel = $this->_getPostModel();

		$viewParams = array(
			'post' => $post,
			'thread' => $thread,
			'forum' => $forum,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

			'canSilentEdit' => $postModel->canControlSilentEdit($post, $thread, $forum)
		);

		return $this->responseView('XenForo_ViewPublic_Post_EditInline', 'post_edit_inline', $viewParams);
	}

	public function actionSaveInline()
	{
		$this->_assertPostOnly();

		if ($this->_input->inRequest('more_options'))
		{
			return $this->responseReroute(__CLASS__, 'edit');
		}

		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		$this->_assertCanEditPost($post, $thread, $forum);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
		$dw->setExistingData($postId);
		$dw->set('message',
			XenForo_Helper_String::autoLinkBbCode(
				$this->getHelper('Editor')->getMessageText('message', $this->_input)
			)
		);
		$dw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
		$this->_setSilentEditOptions($post, $thread, $forum, $dw);
		$dw->save();

		XenForo_Model_Log::logModeratorAction('post', $post, 'edit', array(), $thread);

		if ($this->_noRedirect())
		{
			$this->_request->setParam('thread_id', $thread['thread_id']);

			return $this->responseReroute('XenForo_ControllerPublic_Thread', 'show-posts');
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('posts', $post)
			);
		}
	}

	protected function _setSilentEditOptions(array $post, array $thread, array $forum,
		XenForo_DataWriter_DiscussionMessage_Post $dw
	)
	{
		if ($this->_getPostModel()->canControlSilentEdit($post, $thread, $forum))
		{
			$logEdit = !$this->_input->filterSingle('silent', XenForo_Input::UINT);
			$dw->setOption(XenForo_DataWriter_DiscussionMessage_Post::OPTION_UPDATE_EDIT_DATE, $logEdit);
			if (!$logEdit && $this->_input->filterSingle('clear_edit', XenForo_Input::UINT))
			{
				$dw->set('last_edit_date', 0);
			}
		}
	}

	/**
	 * Deletes an existing post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::UINT);
		$deleteType = ($hardDelete ? 'hard' : 'soft');

		$this->_assertCanDeletePost($post, $thread, $forum, $deleteType);

		$postModel = $this->_getPostModel();

		if ($this->isConfirmedPost()) // delete the post
		{
			$options = array(
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING),
				'authorAlert' => $this->_input->filterSingle('send_author_alert', XenForo_Input::BOOLEAN),
				'authorAlertReason' => $this->_input->filterSingle('author_alert_reason', XenForo_Input::STRING)
			);
			if ($thread['discussion_state'] != 'visible' || $post['message_state'] != 'visible' || $post['user_id'] == XenForo_Visitor::getUserId())
			{
				$options['authorAlert'] = false;
			}

			$dw = $postModel->deletePost($postId, $deleteType, $options, $forum);

			if ($post['post_id'] == $thread['first_post_id'])
			{
				XenForo_Model_Log::logModeratorAction(
					'thread', $thread, 'delete_' . $deleteType, array('reason' => $options['reason'])
				);
			}
			else
			{
				XenForo_Model_Log::logModeratorAction(
					'post', $post, 'delete_' . $deleteType, array('reason' => $options['reason']), $thread
				);
			}

			XenForo_Helper_Cookie::clearIdFromCookie($postId, 'inlinemod_posts');

			if ($dw->discussionDeleted() || !$post['position'])
			{
				XenForo_Helper_Cookie::clearIdFromCookie($thread['thread_id'], 'inlinemod_threads');

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('forums', $forum)
				);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$this->getDynamicRedirect(XenForo_Link::buildPublicLink('threads', $thread))
				);
			}
		}
		else // show a deletion confirmation dialog
		{
			$viewParams = array(
				'post' => $post,
				'thread' => $thread,
				'forum' => $forum,
				'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

				'canHardDelete' => $postModel->canDeletePost($post, $thread, $forum, 'hard'),

				'redirect' => $this->getDynamicRedirect(XenForo_Link::buildPublicLink('threads', $thread))
			);

			return $this->responseView(
				'XenForo_ViewPublic_Post_Delete',
				'post_delete',
				$viewParams
			);
		}
	}

	public function actionHistory()
	{
		$this->_request->setParam('content_type', 'post');
		$this->_request->setParam('content_id', $this->_input->filterSingle('post_id', XenForo_Input::UINT));
		return $this->responseReroute('XenForo_ControllerPublic_EditHistory', 'index');
	}

	/**
	 * Displays a form to like a post or likes a post (via, uhh, POST).
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLike()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		if (!$this->_getPostModel()->canLikePost($post, $thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$likeModel = $this->_getLikeModel();

		$existingLike = $likeModel->getContentLikeByLikeUser('post', $postId, XenForo_Visitor::getUserId());

		if ($this->_request->isPost())
		{
			if ($existingLike)
			{
				$latestUsers = $likeModel->unlikeContent($existingLike);
			}
			else
			{
				$latestUsers = $likeModel->likeContent('post', $postId, $post['user_id']);
			}

			$liked = ($existingLike ? false : true);

			if ($this->_noRedirect() && $latestUsers !== false)
			{
				$post['likeUsers'] = $latestUsers;
				$post['likes'] += ($liked ? 1 : -1);
				$post['like_date'] = ($liked ? XenForo_Application::$time : 0);

				$viewParams = array(
					'post' => $post,
					'thread' => $thread,
					'forum' => $forum,
					'liked' => $liked,
				);

				return $this->responseView('XenForo_ViewPublic_Post_LikeConfirmed', '', $viewParams);
			}
			else
			{
				return $this->getPostSpecificRedirect($post, $thread);
			}
		}
		else
		{
			$viewParams = array(
				'post' => $post,
				'thread' => $thread,
				'forum' => $forum,
				'like' => $existingLike,
				'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum)
			);

			return $this->responseView('XenForo_ViewPublic_Post_Like', 'post_like', $viewParams);
		}
	}

	/**
	 * List of everyone that liked this post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLikes()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		$likes = $this->_getLikeModel()->getContentLikes('post', $postId);
		if (!$likes)
		{
			return $this->responseError(new XenForo_Phrase('no_one_has_liked_this_post_yet'));
		}

		$viewParams = array(
			'post' => $post,
			'thread' => $thread,
			'forum' => $forum,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

			'likes' => $likes
		);

		return $this->responseView('XenForo_ViewPublic_Post_Likes', 'post_likes', $viewParams);
	}

	/**
	 * Reports this post.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionReport()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		if (!$this->_getPostModel()->canReportPost($post, $thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($this->_request->isPost())
		{
			$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
			if (!$message)
			{
				return $this->responseError(new XenForo_Phrase('please_enter_reason_for_reporting_this_message'));
			}

			$this->assertNotFlooding('report');

			/* @var $reportModel XenForo_Model_Report */
			$reportModel = XenForo_Model::create('XenForo_Model_Report');
			$reportModel->reportContent('post', $post, $message);

			$controllerResponse = $this->getPostSpecificRedirect($post, $thread);
			$controllerResponse->redirectMessage = new XenForo_Phrase('thank_you_for_reporting_this_message');
			return $controllerResponse;
		}
		else
		{
			$viewParams = array(
				'post' => $post,
				'thread' => $thread,
				'forum' => $forum,
				'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
			);

			return $this->responseView('XenForo_ViewPublic_Post_Report', 'post_report', $viewParams);
		}
	}

	/**
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionQuote()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId,  array(
			'join' => XenForo_Model_Post::FETCH_USER
		));

		$postModel = $this->_getPostModel();

		if ($this->_input->inRequest('quoteHtml'))
		{
			$quote = $postModel->getQuoteTextForPostFromHtml($post,
				$this->_input->filterSingle('quoteHtml', XenForo_Input::STRING));
		}
		else
		{
			$quote = $postModel->getQuoteTextForPost($post);
		}

		$viewParams = array(
			'thread' => $thread,
			'forum' => $forum,
			'post' => $post,
			'quote' => $quote
		);

		return $this->responseView('XenForo_ViewPublic_Post_Quote', 'post_quote', $viewParams);
	}

	/**
	 * Displays the IP associated with a post
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIp()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId,  array(
			'join' => XenForo_Model_Post::FETCH_USER
		));

		if (!$this->_getPostModel()->canViewIps($post, $thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$ipInfo = $this->getModelFromCache('XenForo_Model_Ip')->getContentIpInfo($post);

		if (empty($ipInfo['contentIp']))
		{
			return $this->responseError(new XenForo_Phrase('no_ip_information_available'));
		}

		$viewParams = array(
			'forum' => $forum,
			'thread' => $thread,
			'post' => $post,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
			'ipInfo' => $ipInfo
		);

		return $this->responseView('XenForo_ViewPublic_Post_Ip', 'post_ip', $viewParams);
	}

	/**
	 * Shows a dialog containing the permalink to a post
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermalink()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		$viewParams = array(
			'post' => $post,
			'thread' => $thread,
			'forum' => $forum,
		);

		return $this->responseView('XenForo_ViewPublic_Post_Permalink', 'post_permalink', $viewParams);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('viewing_thread'); // no need to be more specific - this is a fairly infrequent event
	}

	/**
	 * Asserts that the currently browsing user can edit this post.
	 *
	 * @param array $post
	 * @param array $thread
	 * @param array $forum
	 */
	protected function _assertCanEditPost(array $post, array $thread, array $forum)
	{
		if (!$this->_getPostModel()->canEditPost($post, $thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * Asserts that the currently browsing user can delete this post.
	 *
	 * @param array $post
	 * @param array $thread
	 * @param array $forum
	 * @param string $deleteType Type of deletion (soft or hard)
	 */
	protected function _assertCanDeletePost(array $post, array $thread, array $forum, $deleteType)
	{
		if (!$this->_getPostModel()->canDeletePost($post, $thread, $forum, $deleteType, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}

	/**
	 * @return XenForo_Model_Like
	 */
	protected function _getLikeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Like');
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}

	/**
	 * @return XenForo_Model_ThreadWatch
	 */
	protected function _getThreadWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadWatch');
	}

	/**
	 * @return XenForo_Model_SpamPrevention
	 */
	protected function _getSpamPreventionModel()
	{
		return $this->getModelFromCache('XenForo_Model_SpamPrevention');
	}
}