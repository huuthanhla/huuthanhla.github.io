<?php

/**
 * Controller for conversation actions.
 *
 * @package XenForo_Conversation
 */
class XenForo_ControllerPublic_Conversation extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Pre-dispatch assurances.
	 */
	protected function _preDispatch($action)
	{
		$this->_assertRegistrationRequired();
	}

	/**
	 * Displays a list of visitor's conversations.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		if ($conversationId)
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		$viewParams = $this->_getConversationListData();

		$this->canonicalizePageNumber(
			$viewParams['page'], $viewParams['conversationsPerPage'], $viewParams['totalConversations'],
			'conversations'
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_List', 'conversation_list', $viewParams);
	}

	public function actionStarred()
	{
		$viewParams = $this->_getConversationListData(array('is_starred' => 1));

		$this->canonicalizePageNumber(
			$viewParams['page'], $viewParams['conversationsPerPage'], $viewParams['totalConversations'],
			'conversations/starred'
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Starred', 'conversation_list_starred', $viewParams);
	}

	public function actionYours()
	{
		$visitor = XenForo_Visitor::getInstance();
		$viewParams = $this->_getConversationListData(array(
			'search_type' => 'started_by',
			'search_user' => $visitor['username'],
			'search_user_id' => $visitor['user_id']
		));

		$this->canonicalizePageNumber(
			$viewParams['page'], $viewParams['conversationsPerPage'], $viewParams['totalConversations'],
			'conversations/yours'
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Yours', 'conversation_list_yours', $viewParams);
	}

	protected function _getConversationListData(array $extraConditions = array())
	{
		$visitor = XenForo_Visitor::getInstance();
		$conversationModel = $this->_getConversationModel();

		$conditions = $this->_getListConditions();
		$originalConditions = $conditions;
		$conditions = array_merge($conditions, $extraConditions);
		$fetchOptions = $this->_getListFetchOptions();

		$totalConversations = $conversationModel->countConversationsForUser($visitor['user_id'], $conditions);

		$conversations = $conversationModel->getConversationsForUser($visitor['user_id'], $conditions, $fetchOptions);
		$conversations = $conversationModel->prepareConversations($conversations);

		$page = max(1, intval($fetchOptions['page']));

		return array(
			'conversations' => $conversations,

			'page' => $fetchOptions['page'],
			'conversationsPerPage' => $fetchOptions['perPage'],
			'totalConversations' => $totalConversations,
			'startOffset' => ($page - 1) * $fetchOptions['perPage'] + 1,
			'endOffset' => ($page - 1) * $fetchOptions['perPage'] + count($conversations),

			'ignoredNames' => $this->_getIgnoredContentUserNames($conversations),

			'canStartConversation' => $conversationModel->canStartConversations(),

			'search_type' => $conditions['search_type'],
			'search_user' => $conditions['search_user'],

			'pageNavParams' => array(
				'search_type' => ($originalConditions['search_type'] ? $originalConditions['search_type'] : false),
				'search_user' => ($originalConditions['search_user'] ? $originalConditions['search_user'] : false),
			),
		);
	}

	protected function _getListConditions()
	{
		$searchType = $this->_input->filterSingle('search_type', XenForo_Input::STRING);
		$searchUser = $this->_input->filterSingle('search_user', XenForo_Input::STRING);

		if ($searchUser && $user = $this->getModelFromCache('XenForo_Model_User')->getUserByName($searchUser))
		{
			$conditions = array(
				'search_type' => $searchType,
				'search_user' => $user['username'],
				'search_user_id' => $user['user_id'],
			);
		}
		else
		{
			$conditions = array(
				'search_type' => '',
				'search_user' => '',
			);
		}

		return $conditions;
	}

	protected function _getListFetchOptions()
	{
		return array(
			'page' => $this->_input->filterSingle('page', XenForo_Input::UINT),
			'perPage' => XenForo_Application::get('options')->discussionsPerPage
		);
	}

	public function actionPopup()
	{
		$visitor = XenForo_Visitor::getInstance();
		$conversationModel = $this->_getConversationModel();

		$maxDisplay = 10;

		$conversationsUnread = $conversationModel->getConversationsForUser($visitor['user_id'],
			array('is_unread' => true),
			array(
				'join' => XenForo_Model_Conversation::FETCH_LAST_MESSAGE_AVATAR,
				'limit' => $maxDisplay
			)
		);

		$totalUnread = count($conversationsUnread);

		if ($totalUnread < $maxDisplay)
		{
			$cutOff = XenForo_Application::$time - 3600 * XenForo_Application::get('options')->conversationPopupExpiryHours;

			$conversationsRead = $conversationModel->getConversationsForUser($visitor['user_id'],
				array(
					'is_unread' => false,
					'last_message_date' => array('>', $cutOff)
				),
				array(
					'join' => XenForo_Model_Conversation::FETCH_LAST_MESSAGE_AVATAR,
					'limit' => $maxDisplay - $totalUnread
				)
			);

			if ($totalUnread != $visitor['conversations_unread'])
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
				$dw->setExistingData($visitor['user_id']);
				$dw->set('conversations_unread', $totalUnread);
				$dw->save();

				$visitor['conversations_unread'] = $totalUnread;
			}
		}
		else
		{
			$conversationsRead = array();
		}

		$viewParams = array(
			'conversationsUnread' => $conversationModel->prepareConversations($conversationsUnread),
			'conversationsRead' => $conversationModel->prepareConversations($conversationsRead)
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_ListPopup', 'conversation_list_popup', $viewParams);
	}

	/**
	 * Marks an unread conversation as read, or a read conversation as unread
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionToggleRead()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);

		if ($this->isConfirmedPost())
		{
			$visitor = XenForo_Visitor::getInstance();

			if ($conversation['isNew'])
			{
				$this->_getConversationModel()->markConversationAsRead(
					$conversationId, $visitor->user_id, XenForo_Application::$time);

				$redirectPhrase = 'conversation_marked_as_read';
			}
			else
			{
				$this->_getConversationModel()->markConversationAsUnread(
					$conversationId, $visitor->user_id);

				$redirectPhrase = 'conversation_marked_as_unread';
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('conversations'),
				new XenForo_Phrase($redirectPhrase),
				array(
					'unread' => $conversation['isNew'] ? false : true,
					'actionPhrase' => new XenForo_Phrase($conversation['isNew'] ? 'mark_as_unread' : 'mark_as_read'),
					'counter' => $visitor->conversations_unread,
					'counterFormatted' => XenForo_Locale::numberFormat($visitor->conversations_unread)
				)
			);
		}
		else
		{
			$viewParams = array('conversation' => $conversation);

			return $this->responseView('XenForo_ViewPublic_Conversation_ToggleRead', 'conversation_toggle_read', $viewParams);
		}
	}

	/**
	 * Toggles starred state for conversation
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionToggleStarred()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);

		if ($this->isConfirmedPost())
		{
			$visitor = XenForo_Visitor::getInstance();

			if ($conversation['is_starred'])
			{
				$this->_getConversationModel()->changeConversationStarState(
					$conversationId, $visitor->user_id, false
				);

				$redirectPhrase = 'conversation_unstarred';
				$actionPhrase = 'star_conversation';
				$targetLink = 'conversations';
			}
			else
			{
				$this->_getConversationModel()->changeConversationStarState(
					$conversationId, $visitor->user_id, true
				);

				$redirectPhrase = 'conversation_starred';
				$actionPhrase = 'unstar_conversation';
				$targetLink = 'conversations/starred';
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink($targetLink),
				new XenForo_Phrase($redirectPhrase),
				array('actionPhrase' => new XenForo_Phrase($actionPhrase))
			);
		}
		else
		{
			$viewParams = array('conversation' => $conversation);

			return $this->responseView('XenForo_ViewPublic_Conversation_ToggleStarred', 'conversation_toggle_starred', $viewParams);
		}
	}

	/**
	 * Displays a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionView()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$messagesPerPage = XenForo_Application::get('options')->messagesPerPage;

		$conversationModel = $this->_getConversationModel();

		$messageFetchOptions = array(
			'perPage' => $messagesPerPage,
			'page' => $page,
			'join' => 0
		);
		if (XenForo_Application::getOptions()->showMessageOnlineStatus)
		{
			$messageFetchOptions['join'] |= XenForo_Model_Conversation::FETCH_MESSAGE_SESSION_ACTIVITY;
		}

		$recipients = $conversationModel->getConversationRecipients($conversationId);
		$messages = $conversationModel->getConversationMessages($conversationId, $messageFetchOptions);

		$conversation['showMarkRead'] = $conversation['isNew'];

		$maxMessageDate = $conversationModel->getMaximumMessageDate($messages);
		if ($maxMessageDate > $conversation['last_read_date']
			|| ($maxMessageDate == $conversation['last_read_date'] && $conversation['is_unread'])
		)
		{
			$conversationModel->markConversationAsRead(
				$conversationId, XenForo_Visitor::getUserId(), $maxMessageDate, $conversation['last_message_date']
			);

			$conversation['showMarkRead'] = false;
		}

		$attachmentHash = null;
		if (!empty($conversation['draft_extra']))
		{
			$draftExtra = @unserialize($conversation['draft_extra']);
			if (!empty($draftExtra['attachment_hash']))
			{
				$attachmentHash = $draftExtra['attachment_hash'];
			}
		}

		$attachmentParams = $conversationModel->getAttachmentParams($conversation, array(
			'conversation_id' => $conversationId
		), null, $attachmentHash);

		$messages = $conversationModel->getAndMergeAttachmentsIntoConversationMessages($messages);

		$messages = $conversationModel->prepareMessages($messages, $conversation);

		$viewParams = array(
			'conversation' => $conversation,
			'recipients' => $recipients,

			'canEditConversation' => $conversationModel->canEditConversation($conversation),
			'canReplyConversation' => $conversationModel->canReplyToConversation($conversation),
			'canInviteUsers' => $conversationModel->canInviteUsersToConversation($conversation),

			'attachmentParams' => $attachmentParams,
			'attachmentConstraints' => $this->getModelFromCache('XenForo_Model_Attachment')->getAttachmentConstraints(),
			'canViewAttachments' => $conversationModel->canViewAttachmentOnConversation($conversation),

			'messages' => $messages,
			'lastMessage' => end($messages),
			'page' => $page,
			'messagesPerPage' => $messagesPerPage,
			'totalMessages' => $conversation['reply_count'] + 1
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_View', 'conversation_view', $viewParams);
	}

	/**
	 * Jumps to the first unread message in the conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnread()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);

		if (!$conversation['last_read_date'])
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('conversations', $conversation)
			);
		}

		$conversationModel = $this->_getConversationModel();

		if ($conversation['last_read_date'] >= $conversation['last_message_date'])
		{
			$firstUnread = false;
		}
		else
		{
			$firstUnread = $conversationModel->getNextMessageInConversation($conversationId, $conversation['last_read_date']);
		}

		if (!$firstUnread || $firstUnread['message_id'] == $conversation['last_message_id'])
		{
			$page = floor($conversation['reply_count'] / XenForo_Application::get('options')->messagesPerPage) + 1;
			$messageId = $conversation['last_message_id'];
		}
		else
		{
			$messagesBefore = $conversationModel->countMessagesBeforeDateInConversation($conversationId, $firstUnread['message_date']);

			$page = floor($messagesBefore / XenForo_Application::get('options')->messagesPerPage) + 1;
			$messageId = $firstUnread['message_id'];
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildPublicLink('conversations', $conversation, array('page' => $page)) . '#message-' . $messageId
		);
	}

	/**
	 * Redirects to the correct conversation, page and anchor for the given message.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMessage()
	{
		$conversationModel = $this->_getConversationModel();

		$messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);
		$message = $conversationModel->getConversationMessageById($messageId);

		if (!$message)
		{
			return $this->responseError(new XenForo_Phrase('requested_conversation_not_found'));
		}

		if ($conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT))
		{
			$conversation = $this->_getConversationOrError($conversationId);

			if (!$message || $message['conversation_id'] != $conversationId)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
					XenForo_Link::buildPublicLink('conversations', $conversation)
				);
			}
		}
		else
		{
			$conversation = $this->_getConversationOrError($message['conversation_id']);
		}

		$params = array();

		$messagesBefore = $conversationModel->countMessagesBeforeDateInConversation($message['conversation_id'], $message['message_date']);

		$messagesPerPage = XenForo_Application::get('options')->messagesPerPage;
		$page = floor($messagesBefore / $messagesPerPage) + 1;
		if ($page > 1)
		{
			$params['page'] = $page;
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
			XenForo_Link::buildPublicLink('conversations', $conversation, $params) . '#message-' . $message['message_id']
		);
	}

	/**
	 * Displays a form to create a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		if (!$this->_getConversationModel()->canStartConversations($errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$to = $this->_input->filterSingle('to', XenForo_Input::STRING);
		$title = $this->_input->filterSingle('title', XenForo_Input::STRING);

		if ($to !== '' && strpos($to, ',') === false)
		{
			$toUser = $this->getModelFromCache('XenForo_Model_User')->getUserByName($to, array(
				'join' => XenForo_Model_User::FETCH_USER_FULL
			));
			if (!$toUser)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
			}

			if (!$this->_getConversationModel()->canStartConversationWithUser($toUser, $errorPhraseKey))
			{
				if ($errorPhraseKey)
				{
					$error = new XenForo_Phrase($errorPhraseKey);
				}
				else
				{
					$error = new XenForo_Phrase('you_may_not_start_conversation_with_x_privacy_settings', array('name' => $toUser['username']));
				}
				return $this->responseError($error, 403);
			}

			$to = $toUser['username'];
		}

		$draft = $this->_getDraftModel()->getDraftByUserKey("conversation", XenForo_Visitor::getUserId());
		$attachmentHash = null;

		if ($draft)
		{
			$extra = @unserialize($draft['extra_data']);
			if ($to && !empty($extra['recipients']) && $to != $extra['recipients'])
			{
				// our draft is to someone else
				$draft = false;
			}
			else
			{
				if (!empty($extra['recipients']) && !$to)
				{
					$to = $extra['recipients'];
				}
				if (!empty($extra['title']) && !$title)
				{
					$title = $extra['title'];
				}
				if (!empty($extra['attachment_hash']))
				{
					$attachmentHash = $extra['attachment_hash'];
				}
			}
		}

		$attachmentParams = $this->_getConversationModel()->getAttachmentParams(array(), array(), null, $attachmentHash);

		$viewParams = array(
			'to' => $to,
			'title' => $title,
			'remaining' => $this->_getConversationModel()->allowedAdditionalConversationRecipients(array()),
			'draft' => $draft,

			'attachmentParams' => $attachmentParams,
			'attachmentConstraints' => $this->getModelFromCache('XenForo_Model_Attachment')->getAttachmentConstraints(),
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Add', 'conversation_add', $viewParams);
	}

	/**
	 * Inserts a new conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInsert()
	{
		$this->_assertPostOnly();

		if (!$this->_getConversationModel()->canStartConversations($errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$input = $this->_input->filter(array(
			'recipients' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'open_invite' => XenForo_Input::UINT,
			'conversation_locked' => XenForo_Input::UINT,
			'attachment_hash' => XenForo_Input::STRING
		));
		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		$visitor = XenForo_Visitor::getInstance();

		$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
		$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $visitor->toArray());
		$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_MESSAGE, $input['message']);
		$conversationDw->set('user_id', $visitor['user_id']);
		$conversationDw->set('username', $visitor['username']);
		$conversationDw->set('title', $input['title']);
		$conversationDw->set('open_invite', $input['open_invite']);
		$conversationDw->set('conversation_open', $input['conversation_locked'] ? 0 : 1);
		$conversationDw->addRecipientUserNames(explode(',', $input['recipients'])); // checks permissions

		$messageDw = $conversationDw->getFirstMessageDw();
		$messageDw->set('message', $input['message']);
		$messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_ATTACHMENT_HASH, $input['attachment_hash']);

		$conversationDw->preSave();

		$spamModel = $this->_getSpamPreventionModel();

		if (!$conversationDw->hasErrors()
			&& $spamModel->visitorRequiresSpamCheck()
		)
		{
			$spamResult = $spamModel->checkMessageSpam($input['title'] . "\n" . $input['message'], array(), $this->_request);
			switch ($spamResult)
			{
				case XenForo_Model_SpamPrevention::RESULT_MODERATED:
				case XenForo_Model_SpamPrevention::RESULT_DENIED;
					$spamModel->logSpamTrigger('conversation', null);
					$conversationDw->error(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'));
					break;
			}
		}

		if (!$conversationDw->hasErrors())
		{
			$this->assertNotFlooding('conversation');
		}

		$conversationDw->save();
		$conversation = $conversationDw->getMergedData();

		$this->_getDraftModel()->deleteDraft('conversation');

		$this->_getConversationModel()->markConversationAsRead(
			$conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time
		);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('conversations', $conversation),
			new XenForo_Phrase('your_conversation_has_been_created')
		);
	}

	public function actionSaveDraft()
	{
		$this->_assertPostOnly();

		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		if ($conversationId)
		{
			$conversation = $this->_getConversationOrError($conversationId);
			$this->_assertCanReplyToConversation($conversation);

			$key = 'conversation-' . $conversationId;
			$extra = $this->_input->filter(array(
				'attachment_hash' => XenForo_Input::STRING
			));
		}
		else
		{
			if (!$this->_getConversationModel()->canStartConversations($errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$conversation = false;
			$key = 'conversation';
			$extra = $this->_input->filter(array(
				'recipients' => XenForo_Input::STRING,
				'title' => XenForo_Input::STRING,
				'open_invite' => XenForo_Input::UINT,
				'conversation_locked' => XenForo_Input::UINT,
				'attachment_hash' => XenForo_Input::STRING
			));
		}

		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$forceDelete = $this->_input->filterSingle('delete_draft', XenForo_Input::BOOLEAN);

		if (!strlen($message) || $forceDelete)
		{
			$draftSaved = false;
			$draftDeleted = $this->_getDraftModel()->deleteDraft($key) || $forceDelete;
		}
		else
		{
			$this->_getDraftModel()->saveDraft($key, $message, $extra);
			$draftSaved = true;
			$draftDeleted = false;
		}

		$viewParams = array(
			'conversation' => $conversation,
			'draftSaved' => $draftSaved,
			'draftDeleted' => $draftDeleted
		);
		return $this->responseView('XenForo_ViewPublic_Conversation_SaveDraft', '', $viewParams);
	}

	/**
	 * Displays a form to edit a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);
		$this->_assertCanEditConversation($conversation);

		$viewParams = array(
			'conversation' => $conversation
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Edit', 'conversation_edit', $viewParams);
	}

	/**
	 * Shows a preview of the conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPreview()
	{
		$this->_assertPostOnly();

		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$message = XenForo_Helper_String::autoLinkBbCode($message);

		/** @var $taggingModel XenForo_Model_UserTagging */
		$taggingModel = $this->getModelFromCache('XenForo_Model_UserTagging');
		$taggingModel->getTaggedUsersInMessage($message, $message);

		$viewParams = array(
			'message' => $message
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Preview', 'conversation_preview', $viewParams);
	}

	/**
	 * Updates a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpdate()
	{
		$this->_assertPostOnly();

		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);
		$this->_assertCanEditConversation($conversation);

		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'open_invite' => XenForo_Input::UINT,
			'conversation_locked' => XenForo_Input::UINT
		));
		$update = array(
			'title' => $input['title'],
			'open_invite' => $input['open_invite'],
			'conversation_open' => ($input['conversation_locked'] ? 0 : 1)
		);

		$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
		$conversationDw->setExistingData($conversationId);
		$conversationDw->bulkSet($update);
		$conversationDw->save();

		$conversation = $conversationDw->getMergedData();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('conversations', $conversation)
		);
	}

	/**
	 * Leave a (user's) conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLeave()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);

		$deleteType = $this->_input->filterSingle('delete_type', XenForo_Input::STRING);

		if ($this->isConfirmedPost()) // delete the conversation
		{
			$this->_getConversationModel()->deleteConversationForUser(
				$conversationId, XenForo_Visitor::getUserId(), $deleteType
			);

			XenForo_Helper_Cookie::clearIdFromCookie($conversationId, 'inlinemod_conversations');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('conversations')
			);
		}
		else
		{
			$viewParams = array(
				'conversation' => $conversation
			);

			return $this->responseView(
				'XenForo_ViewPublic_Conversation_Leave',
				'conversation_leave',
				$viewParams
			);
		}
	}

	/**
	 * Displays a form to edit a message in a conversation
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditMessage()
	{
		$vp = $this->_getEditViewParams();
		$conversation = $vp['conversation'];
		$conversationMessage = $vp['conversationMessage'];

		$attachmentParams = $this->_getConversationModel()->getAttachmentParams($conversation, array(
			'conversation_id' => $conversation['conversation_id']
		));

		$attachmentModel = $this->_getAttachmentModel();

		$attachments = $attachmentModel->getAttachmentsByContentId('conversation_message', $conversationMessage['message_id']);

		$viewParams = array(
			'conversation' => $conversation,
			'conversationMessage' => $conversationMessage,
			'message' => XenForo_Helper_String::autoLinkBbCode($this->getHelper('Editor')->getMessageText('editMessage', $this->_input)),
			'attachmentParams' => $attachmentParams,
			'attachments' => $attachmentModel->prepareAttachments($attachments),
			'attachmentConstraints' => $attachmentModel->getAttachmentConstraints()
		);

		if ($this->_input->inRequest('more_options'))
		{
			$viewParams['conversationMessage']['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		}

		return $this->responseView(
			'XenForo_ViewPublic_Conversation_EditMessage',
			'conversation_message_edit',
			$viewParams
		);
	}

	/**
	 * Displays a simple overlay form to edit a message in a conversation
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditMessageInline()
	{
		return $this->responseView(
			'XenForo_ViewPublic_Conversation_EditMessageInline',
			'conversation_message_edit_inline',
			$this->_getEditViewParams()
		);
	}

	/**
	 * Previews the results of a message edit
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditMessagePreview()
	{
		$this->_assertPostOnly();

		$viewParams = $this->_getEditViewParams();

		$viewParams['message'] = XenForo_Helper_String::autoLinkBbCode(
			$this->getHelper('Editor')->getMessageText('message', $this->_input)
		);

		return $this->responseView(
			'XenForo_ViewPublic_Conversation_EditMessagePreview',
			'conversation_message_edit_preview',
			$viewParams
		);
	}

	/**
	 * Fetch $viewParams for the two identical edit actions
	 *
	 * @return array $viewParams
	 */
	protected function _getEditViewParams()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$messageId = $this->_input->filterSingle('m', XenForo_Input::UINT);

		list($conversation, $conversationMessage) = $this->_getConversationAndMessageOrError($messageId, $conversationId);

		$this->_assertCanEditMessageInConversation($conversationMessage, $conversation);

		return array(
			'conversation' => $conversation,
			'conversationMessage' => $conversationMessage
		);
	}

	/**
	 * Saves an edited message
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSaveMessage()
	{
		if ($this->_input->inRequest('more_options'))
		{
			return $this->responseReroute(__CLASS__, 'editMessage');
		}

		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$messageId = $this->_input->filterSingle('m', XenForo_Input::UINT);
		$attachmentHash = $this->_input->filterSingle('attachment_hash', XenForo_Input::STRING);

		list($conversation, $conversationMessage) = $this->_getConversationAndMessageOrError($messageId, $conversationId);

		$this->_assertCanEditMessageInConversation($conversationMessage, $conversation);

		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$message = XenForo_Helper_String::autoLinkBbCode($message);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
		$dw->setExistingData($messageId);
		$dw->set('message', $message);
		// this option prevents an error when editing the message of a deleted user
		$dw->setOption(XenForo_DataWriter_ConversationMessage::OPTION_CHECK_SENDER_RECIPIENT, false);
		$dw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_ATTACHMENT_HASH, $attachmentHash);

		$dw->preSave();

		$spamModel = $this->_getSpamPreventionModel();

		if (!$dw->hasErrors()
			&& $spamModel->visitorRequiresSpamCheck()
		)
		{
			switch ($spamModel->checkMessageSpam($message, array(), $this->_request))
			{
				case XenForo_Model_SpamPrevention::RESULT_MODERATED:
				case XenForo_Model_SpamPrevention::RESULT_DENIED;
					$spamModel->logSpamTrigger('conversation_message', $conversationMessage['message_id']);
					$dw->error(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'));
					break;
			}
		}

		$dw->save();

		if ($this->_noRedirect())
		{
			$conversationModel = $this->_getConversationModel();

			$message = array_merge($conversationMessage, $dw->getMergedData());
			$messages = $conversationModel->getAndMergeAttachmentsIntoConversationMessages(array($message['message_id'] => $message));
			$message = reset($messages);

			$viewParams = array(
				'conversation' => $conversation,
				'message' => $conversationModel->prepareMessage($message, $conversation),
				'canReplyConversation' => $conversationModel->canReplyToConversation($conversation),
				'canViewAttachments' => $conversationModel->canViewAttachmentOnConversation($conversation),
			);

			return $this->responseView(
				'XenForo_ViewPublic_Conversation_ViewMessage',
				'conversation_message',
				$viewParams
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('conversations/message', $conversation, array('message_id' => $conversationMessage['message_id']))
			);
		}
	}

	/**
	 * Displays a form to add a reply to a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionReply()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);
		$this->_assertCanReplyToConversation($conversation);

		if ($messageId = $this->_input->filterSingle('m', XenForo_Input::UINT)) // 'm' is a shortcut for 'message_id'
		{
			$conversationModel = $this->_getConversationModel();

			if ($message = $conversationModel->getConversationMessageById($messageId))
			{
				if ($message['conversation_id'] != $conversationId)
				{
					return $this->responseError(new XenForo_Phrase('not_possible_to_reply_to_messages_not_same_conversation'));
				}

				if ($this->_input->inRequest('quoteHtml'))
				{
					$defaultMessage = $conversationModel->getQuoteTextForMessageFromHtml($message,
						$this->_input->filterSingle('quoteHtml', XenForo_Input::STRING)
					);
				}
				else
				{
					$defaultMessage = $conversationModel->getQuoteForConversationMessage($message);
				}
			}
		}
		else if ($this->_input->inRequest('more_options'))
		{
			$message = array();
			$defaultMessage = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		}
		else
		{
			$message = array();
			$defaultMessage = '';
		}

		$attachmentParams = $this->_getConversationModel()->getAttachmentParams($conversation, array(
			'conversation_id' => $conversationId
		));

		if ($attachmentParams)
		{
			$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');

			$quickReplyAttachmentHash = $this->_input->filterSingle('attachment_hash', XenForo_Input::STRING);

			$attachments = $attachmentModel->prepareAttachments(
				$attachmentModel->getAttachmentsByTempHash($quickReplyAttachmentHash)
			);

			if ($attachments)
			{
				$attachmentParams['hash'] = $quickReplyAttachmentHash;
			}
		}
		else
		{
			$attachments = array();
		}

		$viewParams = array(
			'conversation' => $conversation,
			'message' => $message,
			'defaultMessage' => $defaultMessage,

			'attachmentParams' => $attachmentParams,
			'attachments' => $attachments,
			'attachmentConstraints' => $this->getModelFromCache('XenForo_Model_Attachment')->getAttachmentConstraints(),
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Reply', 'conversation_reply', $viewParams);
	}

	/**
	 * Inserts a reply into a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInsertReply()
	{
		$this->_assertPostOnly();

		if ($this->_input->inRequest('more_options'))
		{
			return $this->responseReroute(__CLASS__, 'reply');
		}

		$input = array();
		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);
		$this->_assertCanReplyToConversation($conversation);

		$visitor = XenForo_Visitor::getInstance();

		$attachmentHash = $this->_input->filterSingle('attachment_hash', XenForo_Input::STRING);

		$messageDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
		$messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_MESSAGE_SENDER, $visitor->toArray());
		$messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_ATTACHMENT_HASH, $attachmentHash);
		$messageDw->set('conversation_id', $conversation['conversation_id']);
		$messageDw->set('user_id', $visitor['user_id']);
		$messageDw->set('username', $visitor['username']);
		$messageDw->set('message', $input['message']);

		$messageDw->preSave();

		$spamModel = $this->_getSpamPreventionModel();

		if (!$messageDw->hasErrors()
			&& $spamModel->visitorRequiresSpamCheck()
		)
		{
			$spamResult = $spamModel->checkMessageSpam($input['message'], array(), $this->_request);
			switch ($spamResult)
			{
				case XenForo_Model_SpamPrevention::RESULT_MODERATED:
				case XenForo_Model_SpamPrevention::RESULT_DENIED;
					$spamModel->logSpamTrigger('conversation_message', null);
					$messageDw->error(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'));
					break;
			}
		}

		if (!$messageDw->hasErrors())
		{
			$this->assertNotFlooding('conversation');
		}

		$messageDw->save();
		$message = $messageDw->getMergedData();

		$this->_getDraftModel()->deleteDraft('conversation-' . $conversation['conversation_id']);

		$conversationModel = $this->_getConversationModel();

		if (!$this->_noRedirect() || !$this->_input->inRequest('last_date'))
		{
			$conversationModel->markConversationAsRead(
				$conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time, 0, false
			);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('conversations/message', $conversation, array('message_id' => $message['message_id'])),
				new XenForo_Phrase('your_message_has_been_posted')
			);
		}
		else
		{
			$lastDate = $this->_input->filterSingle('last_date', XenForo_Input::UINT);

			$limit = 3;
			$messageFetchOptions = array(
				'limit' => $limit + 1,
				'join' => 0
			);
			if (XenForo_Application::getOptions()->showMessageOnlineStatus)
			{
				$messageFetchOptions['join'] |= XenForo_Model_Conversation::FETCH_MESSAGE_SESSION_ACTIVITY;
			}

			$messages = $conversationModel->getNewestConversationMessagesAfterDate(
				$conversationId, $lastDate, $messageFetchOptions
			);

			// We fetched one more message than needed. If more than $limit message were returned,
			// we can show the 'there are more messages' notice
			if (count($messages) > $limit)
			{
				$firstUnshown = $conversationModel->getNextMessageInConversation($conversationId, $lastDate);

				// remove the extra post
				array_pop($messages);
			}
			else
			{
				$firstUnshown = false;
			}

			if (!$firstUnshown || $firstUnshown['message_date'] < $conversation['last_read_date'])
			{
				$conversationModel->markConversationAsRead(
					$conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time, 0, false
				);
			}

			$messages = array_reverse($messages, true);
			$messages = $conversationModel->prepareMessages($messages, $conversation);
			$messages = $conversationModel->getAndMergeAttachmentsIntoConversationMessages($messages);

			$viewParams = array(
				'conversation' => $conversation,

				'canReplyConversation' => $conversationModel->canReplyToConversation($conversation),
				'canViewAttachments' => $conversationModel->canViewAttachmentOnConversation($conversation),

				'firstUnshown' => $firstUnshown,
				'messages' => $messages,
				'lastMessage' => end($messages)
			);

			return $this->responseView(
				'XenForo_ViewPublic_Conversation_ViewNewMessages',
				'conversation_view_new_messages',
				$viewParams
			);
		}
	}

	/**
	 * Displays a form to invite users to a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInvite()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);
		$this->_assertCanInviteUsersToConversation($conversation);

		$viewParams = array(
			'conversation' => $conversation,
			'remaining' => $this->_getConversationModel()->allowedAdditionalConversationRecipients($conversation)
		);

		return $this->responseView('XenForo_ViewPublic_Conversation_Invite', 'conversation_invite', $viewParams);
	}

	/**
	 * Invites users to a conversation.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInviteInsert()
	{
		$this->_assertPostOnly();

		$emailConversationIncludeMessage = XenForo_Application::get('options')->emailConversationIncludeMessage;

		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId, null, $emailConversationIncludeMessage);
		$this->_assertCanInviteUsersToConversation($conversation);

		$recipients = $this->_input->filterSingle('recipients', XenForo_Input::STRING);

		/* @var $conversationDw XenForo_DataWriter_ConversationMaster */
		$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
		$conversationDw->setExistingData($conversationId);
		$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, XenForo_Visitor::getInstance()->toArray());
		$conversationDw->addRecipientUserNames(explode(',', $recipients));

		if ($emailConversationIncludeMessage)
		{
			$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_MESSAGE, $conversation['message']);
		}

		$conversationDw->save();

		if ($this->_noRedirect())
		{
			$viewParams = array(
				'conversation' => $conversation,
				'recipients' => $this->_getConversationModel()->getConversationRecipients($conversationId)
			);

			return $this->responseView(
				'XenForo_ViewPublic_Conversation_InviteInsert',
				'conversation_recipients',
				$viewParams
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('conversations', $conversation)
		);
	}

	public function actionReport()
	{
		$messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);

		list($conversation, $message) = $this->_getConversationAndMessageOrError($messageId, $conversationId);

		if (!$this->_getConversationModel()->canReportMessage($message, $conversation, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($this->isConfirmedPost())
		{
			$reportMessage = $this->_input->filterSingle('message', XenForo_Input::STRING);
			if (!$reportMessage)
			{
				return $this->responseError(new XenForo_Phrase('please_enter_reason_for_reporting_this_message'));
			}

			$this->assertNotFlooding('report');

			$message['conversation'] = $conversation;

			/* @var $reportModel XenForo_Model_Report */
			$reportModel = XenForo_Model::create('XenForo_Model_Report');
			$reportModel->reportContent('conversation_message', $message, $reportMessage);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('conversations/message', $conversation, array('message_id' => $message['message_id'])),
				new XenForo_Phrase('thank_you_for_reporting_this_message')
			);
		}
		else
		{
			$viewParams = array(
				'conversation' => $conversation,
				'message' => $message,
			);

			return $this->responseView('XenForo_ViewPublic_Conversation_Report', 'conversation_message_report', $viewParams);
		}
	}

	public function actionFindUser()
	{
		$this->_assertPostOnly();

		$conversationModel = $this->_getConversationModel();
		$userId = XenForo_Visitor::getUserId();
		$q = $this->_input->filterSingle('q', XenForo_Input::STRING);

		switch ($this->_input->filterSingle('search_type', XenForo_Input::STRING))
		{
			case 'started_by':
				$users = $conversationModel->findConversationStartersForUser($userId, $q);
				break;

			case 'received_by':
				$users = $conversationModel->findConversationRecipientsForUser($userId, $q);
				break;

			//case 'message_by':
			//	$users = $conversationModel->findConversationRespondersForUser($userId, $q);
			//	break;

			default:
				$users = array();
		}

		$viewParams = array(
			'users' => $users
		);

		return $this->responseView('XenForo_ViewPublic_Member_Find', 'member_autocomplete', $viewParams);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('engaged_in_conversation');
	}

	/**
	 * Gets the specified conversation for the specified user, or throws an error.
	 *
	 * @param integer $conversationId
	 * @param integer|null $userId If null, uses visitor
	 * @param boolean Fetch first message text with the conversation
	 *
	 * @return array
	 */
	protected function _getConversationOrError($conversationId, $userId = null, $fetchFirstMessage = false)
	{
		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		$conversationModel = $this->_getConversationModel();

		$fetchOptions = array();
		if ($fetchFirstMessage)
		{
			$fetchOptions['join'] = XenForo_Model_Conversation::FETCH_FIRST_MESSAGE;
		}
		$fetchOptions['draftUserId'] = XenForo_Visitor::getUserId();

		$conversation = $conversationModel->getConversationForUser($conversationId, $userId, $fetchOptions);
		if (!$conversation)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_conversation_not_found'), 404));
		}

		return $conversationModel->prepareConversation($conversation);
	}

	/**
	 * Gets the specified conversation and message, or throws an error
	 *
	 * @param integer $messageId
	 * @param integer $conversationId
	 * @param integer|null $userId
	 *
	 * @return array [$conversation, $message]
	 */
	protected function _getConversationAndMessageOrError($messageId, $conversationId, $userId = null)
	{
		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		$conversationModel = $this->_getConversationModel();

		$messageFetchOptions = array(
			'join' => 0
		);
		if (XenForo_Application::getOptions()->showMessageOnlineStatus)
		{
			$messageFetchOptions['join'] |= XenForo_Model_Conversation::FETCH_MESSAGE_SESSION_ACTIVITY;
		}

		$conversation = $this->_getConversationOrError($conversationId, $userId);
		$message = $conversationModel->getConversationMessageById($messageId, $messageFetchOptions);

		if (!$message || $message['conversation_id'] != $conversation['conversation_id'])
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_message_not_found'), 404));
		}

		return array(
			$conversation,
			$conversationModel->prepareMessage($message, $conversation)
		);
	}

	/**
	 * Asserts that the currently browsing user can reply to this conversation.
	 *
	 * @param array $conversation
	 */
	protected function _assertCanReplyToConversation(array $conversation)
	{
		if (!$this->_getConversationModel()->canReplyToConversation($conversation, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * Asserts that the currently browsing user can edit this conversation.
	 *
	 * @param array $conversation
	 */
	protected function _assertCanEditConversation(array $conversation)
	{
		if (!$this->_getConversationModel()->canEditConversation($conversation, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * Asserts that the currently browsing user can invite users this conversation.
	 *
	 * @param array $conversation
	 */
	protected function _assertCanInviteUsersToConversation(array $conversation)
	{
		if (!$this->_getConversationModel()->canInviteUsersToConversation($conversation, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * Asserts that the currently browsing user can edit the specified message
	 *
	 * @param array $message
	 * @param array $conversation
	 */
	protected function _assertCanEditMessageInConversation(array $message, array $conversation)
	{
		if (!$this->_getConversationModel()->canEditMessage($message, $conversation, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * @return XenForo_Model_Conversation
	 */
	protected function _getConversationModel()
	{
		return $this->getModelFromCache('XenForo_Model_Conversation');
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
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