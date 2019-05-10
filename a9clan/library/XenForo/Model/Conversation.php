<?php

/**
 * Model for conversations.
 *
 * @package XenForo_Conversation
 */
class XenForo_Model_Conversation extends XenForo_Model
{
	const FETCH_LAST_MESSAGE_AVATAR = 0x01;
	const FETCH_FIRST_MESSAGE = 0x02;
	const FETCH_RECEIVED_BY = 0x04;

	const FETCH_MESSAGE_SESSION_ACTIVITY = 0x01;

	/**
	 * Gets the specified conversation master record.
	 *
	 * @param integer $conversationId
	 *
	 * @return array|false
	 */
	public function getConversationMasterById($conversationId)
	{
		return $this->_getDb()->fetchRow('
			SELECT conversation_master.*
			FROM xf_conversation_master AS conversation_master
			WHERE conversation_master.conversation_id = ?
		', $conversationId);
	}

	/**
	 * Gets the specified conversation message record.
	 *
	 * @param integer $messageId
	 * @param array $fetchOptions Options for extra data to fetch
	 *
	 * @return array|false
	 */
	public function getConversationMessageById($messageId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareMessageFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT message.*,
				user.*, IF(user.username IS NULL, message.username, user.username) AS username,
				user_profile.*
				' . $joinOptions['selectFields'] . '
			FROM xf_conversation_message AS message
			LEFT JOIN xf_user AS user ON
				(user.user_id = message.user_id)
			LEFT JOIN xf_user_profile AS user_profile ON
				(user_profile.user_id = message.user_id)
			' . $joinOptions['joinTables'] . '
			WHERE message.message_id = ?
		', $messageId);
	}

	/**
	 * Gets the specified user conversation.
	 *
	 * @param integer $conversationId
	 * @param integer|array $viewingUser Can be a user array, or a user ID (for B.C. purposes)
	 * @param array $fetchOptions Options for extra data to fetch
	 *
	 * @return array|false
	 */
	public function getConversationForUser($conversationId, $viewingUser, array $fetchOptions = array())
	{
		if (is_array($viewingUser))
		{
			$this->standardizeViewingUserReference($viewingUser);
			$userId = $viewingUser['user_id'];
		}
		else
		{
			$userId = $viewingUser;
		}

		$joinOptions = $this->prepareConversationFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT conversation_master.*,
				conversation_user.*,
				conversation_recipient.recipient_state, conversation_recipient.last_read_date
				' . $joinOptions['selectFields'] . '
			FROM xf_conversation_user AS conversation_user
			INNER JOIN xf_conversation_master AS conversation_master ON
				(conversation_user.conversation_id = conversation_master.conversation_id)
			INNER JOIN xf_conversation_recipient AS conversation_recipient ON
					(conversation_user.conversation_id = conversation_recipient.conversation_id
					AND conversation_user.owner_user_id = conversation_recipient.user_id)
				' . $joinOptions['joinTables'] . '
			WHERE conversation_user.conversation_id = ?
				AND conversation_user.owner_user_id = ?
		', array($conversationId, $userId));
	}

	/**
	 * Gets information about all recipients of a conversation.
	 *
	 * @param integer $conversationId
	 * @param array $fetchOptions Options for extra data to fetch
	 *
	 * @return array Format: [user id] => info
	 */
	public function getConversationRecipients($conversationId, array $fetchOptions = array())
	{
		$sqlClauses = $this->prepareConversationFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT conversation_recipient.*,
				user.*, user_option.*
				' . $sqlClauses['selectFields'] . '
			FROM xf_conversation_recipient AS conversation_recipient
			LEFT JOIN xf_user AS user ON
				(user.user_id = conversation_recipient.user_id)
			LEFT JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			' . $sqlClauses['joinTables'] . '
			WHERE conversation_recipient.conversation_id = ?
			ORDER BY user.username
		', 'user_id', $conversationId, 'deleted');
	}

	/**
	 * Gets info about a single recipient of a conversation.
	 *
	 * @param integer $conversationId
	 * @param integer $userId
	 * @param array $fetchOptions Options for extra data to fetch
	 *
	 * @return array|false
	 */
	public function getConversationRecipient($conversationId, $userId, array $fetchOptions = array())
	{
		$sqlClauses = $this->prepareConversationFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT conversation_recipient.*,
				user.*, user_option.*
				' . $sqlClauses['selectFields'] . '
			FROM xf_conversation_recipient AS conversation_recipient
			LEFT JOIN xf_user AS user ON
				(user.user_id = conversation_recipient.user_id)
			LEFT JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			' . $sqlClauses['joinTables'] . '
			WHERE conversation_recipient.conversation_id = ?
				AND conversation_recipient.user_id = ?
		', array($conversationId, $userId));
	}

	/**
	 * Get conversations that a user can see, ordered by the latest message first.
	 *
	 * @param integer $userId
	 * @param array $conditions Conditions for the WHERE clause
	 * @param array $fetchOptions Options for extra data to fetch
	 *
	 * @return array Format: [conversation id] => info
	 */
	public function getConversationsForUser($userId, array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareConversationConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareConversationFetchOptions($fetchOptions);

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$sql = $this->limitQueryResults(
			'
				SELECT conversation_master.*,
					conversation_user.*,
					conversation_starter.*,
					conversation_master.username AS username,
					conversation_recipient.recipient_state, conversation_recipient.last_read_date
					' . $sqlClauses['selectFields'] . '
				FROM xf_conversation_user AS conversation_user
				INNER JOIN xf_conversation_master AS conversation_master ON
					(conversation_user.conversation_id = conversation_master.conversation_id)
				INNER JOIN xf_conversation_recipient AS conversation_recipient ON
					(conversation_user.conversation_id = conversation_recipient.conversation_id
					AND conversation_user.owner_user_id = conversation_recipient.user_id)
				LEFT JOIN xf_user AS conversation_starter ON
					(conversation_starter.user_id = conversation_master.user_id)
					' . $sqlClauses['joinTables'] . '
				WHERE conversation_user.owner_user_id = ?
					AND ' . $whereClause . '
				ORDER BY conversation_user.last_message_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		);

		return $this->fetchAllKeyed($sql, 'conversation_id', $userId);
	}

	/**
	 * Gets the total number of conversations that a user has.
	 *
	 * @param integer $userId
	 * @param array $conditions Conditions for the WHERE clause
	 * @param array $fetchConditions Only used in tandem with $conditions at this point
	 *
	 * @return integer
	 */
	public function countConversationsForUser($userId, array $conditions = array())
	{
		$fetchOptions = array();
		$whereClause = $this->prepareConversationConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareConversationFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_conversation_user AS conversation_user
			INNER JOIN xf_conversation_master AS conversation_master ON
				(conversation_user.conversation_id = conversation_master.conversation_id)
			INNER JOIN xf_conversation_recipient AS conversation_recipient ON
				(conversation_user.conversation_id = conversation_recipient.conversation_id
				AND conversation_user.owner_user_id = conversation_recipient.user_id)
				' . $sqlClauses['joinTables'] . '
			WHERE conversation_user.owner_user_id = ?
				AND ' . $whereClause
		, $userId);
	}

	/**
	 * Get the specified conversations that a user can see, ordered by last message first.
	 *
	 * @param integer $userId
	 * @param array $conversationIds
	 *
	 * @return array Format: [conversation id] => info
	 */
	public function getConversationsForUserByIds($userId, array $conversationIds)
	{
		if (!$conversationIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT conversation_master.*,
				conversation_user.*,
				conversation_starter.*,
				conversation_master.username AS username,
				conversation_recipient.recipient_state, conversation_recipient.last_read_date
			FROM xf_conversation_user AS conversation_user
			INNER JOIN xf_conversation_master AS conversation_master ON
				(conversation_user.conversation_id = conversation_master.conversation_id)
			INNER JOIN xf_conversation_recipient AS conversation_recipient ON
				(conversation_user.conversation_id = conversation_recipient.conversation_id
				AND conversation_user.owner_user_id = conversation_recipient.user_id)
			LEFT JOIN xf_user AS conversation_starter ON
				(conversation_starter.user_id = conversation_master.user_id)
			WHERE conversation_user.owner_user_id = ?
				AND conversation_user.conversation_id IN (' . $this->_getDb()->quote($conversationIds) . ')
			ORDER BY conversation_user.last_message_date DESC
		', 'conversation_id', $userId);
	}

	/**
	 * Return a list of users that have received conversations that the specified user has receved,
	 * where the recieving user matches the given username search string
	 *
	 * @param integer $userId
	 * @param string $searchString
	 *
	 * @return array
	 */
	public function findConversationRecipientsForUser($userId, $searchString)
	{
		$userIds = $this->_getDb()->fetchCol('
			SELECT DISTINCT recp1.user_id
			FROM xf_conversation_recipient AS recp1
			INNER JOIN xf_conversation_recipient AS recp2 ON
				(recp2.conversation_id = recp1.conversation_id
				AND recp2.user_id = ?
				AND recp2.recipient_state = \'active\')
		', $userId);

		return $this->_getUsersMatchingCriteria($userIds, $searchString);
	}

	/**
	 * Return a list of users that have started conversations that the specified user has receved,
	 * where the starting user matches the given username search string
	 *
	 * @param integer $userId
	 * @param string $searchString
	 *
	 * @return array
	 */
	public function findConversationStartersForUser($userId, $searchString)
	{
		$userIds = $this->_getDb()->fetchCol('
			SELECT DISTINCT conversation_master.user_id
			FROM xf_conversation_master AS conversation_master
			INNER JOIN xf_conversation_user AS conversation_user ON
				(conversation_user.conversation_id = conversation_master.conversation_id
				AND conversation_user.owner_user_id = ?)
		', $userId);

		return $this->_getUsersMatchingCriteria($userIds, $searchString);
	}

	/**
	 * Return a list of users that have posted in conversations that the specified user has receved,
	 * where the recieving user matches the given username search string
	 *
	 * @param integer $userId
	 * @param string $searchString
	 *
	 * @return array
	 */
	public function findConversationRespondersForUser($userId, $searchString)
	{
		$userIds = $this->_getDb()->fetchCol('
			SELECT DISTINCT conversation_message.user_id
			FROM xf_conversation_message AS conversation_message
			INNER JOIN xf_conversation_user AS conversation_user ON
				(conversation_user.conversation_id = conversation_message.conversation_id)
			WHERE conversation_user.owner_user_id = ?
		', $userId);

		return $this->_getUsersMatchingCriteria($userIds, $searchString);
	}

	/**
	 * Fetches a list of users matching the user ID and user name search criteria.
	 * Used in conjunction with this class's findConversation[x]ForUser() methods.
	 *
	 * @param array $userIds
	 * @param string $searchString
	 *
	 * @return array
	 */
	protected function _getUsersMatchingCriteria(array $userIds, $searchString)
	{
		if ($userIds)
		{
			return $this->_getUserModel()->getUsers(array(
				'user_id' => $userIds,
				'username' => array($searchString , 'r')
			));
		}

		return array();
	}

	/**
	 * Gets conversation IDs in the specified range. The IDs returned will be those immediately
	 * after the "start" value (not including the start), up to the specified limit.
	 *
	 * @param integer $start IDs greater than this will be returned
	 * @param integer $limit Number of posts to return
	 *
	 * @return array List of IDs
	 */
	public function getConversationIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT conversation_id
			FROM xf_conversation_master
			WHERE conversation_id > ?
			ORDER BY conversation_id
		', $limit), $start);
	}

	public function prepareConversationFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$db = $this->_getDb();

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_LAST_MESSAGE_AVATAR)
			{
				$selectFields .= ',
					last_message_user.avatar_date AS last_message_avatar_date,
					last_message_user.gender AS last_message_gender,
					last_message_user.gravatar AS last_message_gravatar';
				$joinTables .= '
					LEFT JOIN xf_user AS last_message_user ON
						(last_message_user.user_id = conversation_user.last_message_user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_FIRST_MESSAGE)
			{
				$selectFields .= ',
					conversation_message.message';
				$joinTables .= '
					INNER JOIN xf_conversation_message AS conversation_message ON
						(conversation_message.message_id = conversation_master.first_message_id)';
			}
		}

		if (!empty($fetchOptions['receivedUserId']))
		{
			$joinTables .= '
				INNER JOIN xf_conversation_recipient AS check_recipient ON
					(check_recipient.conversation_id = conversation_master.conversation_id
					AND check_recipient.user_id = ' . $db->quote($fetchOptions['receivedUserId']) . ')';
		}

		if (isset($fetchOptions['draftUserId']))
		{
			if (!empty($fetchOptions['draftUserId']))
			{
				$selectFields .= ',
					draft.message AS draft_message, draft.extra_data AS draft_extra';
				$joinTables .= '
					LEFT JOIN xf_draft AS draft
						ON (draft.draft_key = CONCAT(\'conversation-\', conversation_master.conversation_id)
						AND draft.user_id = ' . $db->quote($fetchOptions['draftUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					\'\' AS draft_message, NULL AS draft_extra';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Prepares a set of conditions against which to select conversations.
	 *
	 * @param array $conditions List of conditions.
	 * --popupMode (boolean) constrains results to unread, or sent within timeframe specified by options->conversationPopupExpiryHours
	 * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	 *
	 * @return string Criteria as SQL for where clause
	 */
	public function prepareConversationConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['popupMode']))
		{
			$cutOff = XenForo_Application::$time - 3600 * XenForo_Application::get('options')->conversationPopupExpiryHours;
			$sqlConditions[] = 'conversation_user.is_unread = 1 OR conversation_user.last_message_date > ' . $cutOff;
		}

		if (isset($conditions['is_unread']))
		{
			$sqlConditions[] = 'conversation_user.is_unread = ' . ($conditions['is_unread'] ? 1 : 0);
		}

		if (isset($conditions['is_starred']))
		{
			$sqlConditions[] = 'conversation_user.is_starred = ' . ($conditions['is_starred'] ? 1 : 0);
		}

		if (!empty($conditions['last_message_date']) && is_array($conditions['last_message_date']))
		{
			list($operator, $cutOff) = $conditions['last_message_date'];

			$this->assertValidCutOffOperator($operator);
			$sqlConditions[] = "conversation_user.last_message_date $operator " . $db->quote($cutOff);
		}

		if (!empty($conditions['search_type']) && !empty($conditions['search_user_id']))
		{
			switch ($conditions['search_type'])
			{
				case 'started_by':
					$sqlConditions[] = 'conversation_master.user_id = ' . $db->quote($conditions['search_user_id']);
					break;

				case 'received_by':
					$fetchOptions['receivedUserId'] = $conditions['search_user_id'];
					break;
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function prepareMessageFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$db = $this->_getDb();

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_MESSAGE_SESSION_ACTIVITY)
			{
				$selectFields .= ',
					session_activity.view_date AS last_view_date';
				$joinTables .= '
					LEFT JOIN xf_session_activity AS session_activity ON
						(message.user_id > 0 AND session_activity.user_id = message.user_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Get messages within a given conversation.
	 *
	 * @param integer $conversationId
	 * @param array $fetchOptions Options for extra data to fetch
	 *
	 * @return array Format [message id] => info
	 */
	public function getConversationMessages($conversationId, array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$joinOptions = $this->prepareMessageFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT message.*,
					user.*, IF(user.username IS NULL, message.username, user.username) AS username,
					user_profile.*,
					user_privacy.*
					' . $joinOptions['selectFields'] . '
				FROM xf_conversation_message AS message
				LEFT JOIN xf_user AS user ON
					(user.user_id = message.user_id)
				LEFT JOIN xf_user_profile AS user_profile ON
					(user_profile.user_id = message.user_id)
				LEFT JOIN xf_user_privacy AS user_privacy ON
					(user_privacy.user_id = message.user_id)
				' . $joinOptions['joinTables'] . '
				WHERE message.conversation_id = ?
				ORDER BY message.message_date
			', $limitOptions['limit'], $limitOptions['offset']
		), 'message_id', $conversationId);
	}

	/**
	 * Finds the newest conversation messages after the specified date.
	 *
	 * @param integer $conversationId
	 * @param integer $date
	 * @param array $fetchOptions
	 *
	 * @return array [message id] => info
	 */
	public function getNewestConversationMessagesAfterDate($conversationId, $date, array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$joinOptions = $this->prepareMessageFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT message.*,
					user.*, IF(user.username IS NULL, message.username, user.username) AS username,
					user_profile.*
					' . $joinOptions['selectFields'] . '
				FROM xf_conversation_message AS message
				LEFT JOIN xf_user AS user ON
					(user.user_id = message.user_id)
				LEFT JOIN xf_user_profile AS user_profile ON
					(user_profile.user_id = message.user_id)
				' . $joinOptions['joinTables'] . '
				WHERE message.conversation_id = ?
					AND message.message_date > ?
				ORDER BY message.message_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'message_id', array($conversationId, $date));
	}

	/**
	 * Gets the next message in a conversation, post after the specified date. This is useful
	 * for finding the first unread message, for example.
	 *
	 * @param integer $conversationId
	 * @param integer $messageDate Finds first message posted after this
	 *
	 * @return array|false
	 */
	public function getNextMessageInConversation($conversationId, $messageDate)
	{
		$db = $this->_getDb();

		return $db->fetchRow($db->limit('
			SELECT *
			FROM xf_conversation_message
			WHERE conversation_id = ?
				AND message_date > ?
			ORDER BY message_date
		', 1), array($conversationId, $messageDate));
	}

	/**
	 * Count the number of messages before a given date in a conversation.
	 *
	 * @param integer $conversationId
	 * @param integer $messageDate
	 *
	 * @return integer
	 */
	public function countMessagesBeforeDateInConversation($conversationId, $messageDate)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_conversation_message AS conversation_message
			WHERE conversation_message.conversation_id = ?
				AND conversation_message.message_date < ?
		', array($conversationId, $messageDate));
	}

	/**
	 * Prepare a conversation for display or further processing.
	 *
	 * @param array $conversation
	 *
	 * @return array
	 */
	public function prepareConversation(array $conversation)
	{
		$conversation['isNew'] = ($conversation['last_message_date'] > $conversation['last_read_date']);
		$conversation['title'] = XenForo_Helper_String::censorString($conversation['title']);

		$conversation['lastPageNumbers'] = $this->getLastPageNumbers($conversation['reply_count']);

		$conversation['last_message'] = array(
			'message_id' => $conversation['last_message_id'],
			'message_date' => $conversation['last_message_date'],
			'user_id' => $conversation['last_message_user_id'],
			'username' => $conversation['last_message_username']
		);

		if (isset($conversation['last_message_avatar_date']))
		{
			$conversation['last_message']['avatar_date'] = $conversation['last_message_avatar_date'];
		}

		if (isset($conversation['last_message_gender']))
		{
			$conversation['last_message']['gender'] = $conversation['last_message_gender'];
		}

		if (isset($conversation['last_message_gravatar']))
		{
			$conversation['last_message']['gravatar'] = $conversation['last_message_gravatar'];
		}

		if (array_key_exists('user_group_id', $conversation))
		{
			$conversation = $this->_getUserModel()->prepareUser($conversation);
			$conversation['isIgnored'] = false; // don't ignore conversations - the user can leave the conversation instead
		}

		$conversation['recipientNames'] = $conversation['recipients'] ? @unserialize($conversation['recipients']) : array();

		return $conversation;
	}

	/**
	 * Prepare a collection of conversations for display or further processing.
	 *
	 * @param array $conversations
	 *
	 * @return array
	 */
	public function prepareConversations(array $conversations)
	{
		foreach ($conversations AS &$conversation)
		{
			$conversation = $this->prepareConversation($conversation);
		}

		return $conversations;
	}

	/**
	 * Prepare a message for display or further processing.
	 *
	 * @param array $message
	 * @param array $conversation
	 *
	 * @return array Prepared message
	 */
	public function prepareMessage(array $message, array $conversation)
	{
		$message['isNew'] = ($message['message_date'] > $conversation['last_read_date']);

		$message['canEdit'] = $this->canEditMessage($message, $conversation);

		$message['canReport'] = $this->canReportMessage($message, $conversation);

		if (array_key_exists('user_group_id', $message))
		{
			$message = $this->_getUserModel()->prepareUser($message);
			$message['isIgnored'] = false; // don't ignore messages in conversations - the user can leave the conversation instead
		}

		$message['isOnline'] = null;
		if (array_key_exists('last_view_date', $message)
			&& $this->_getUserModel()->canViewUserOnlineStatus($message)
		)
		{
			$onlineCutOff = XenForo_Application::$time - XenForo_Application::getOptions()->onlineStatusTimeout * 60;
			$message['isOnline'] = (
				$message['user_id'] == XenForo_Visitor::getUserId()
				|| $message['last_view_date'] > $onlineCutOff
			);
		}

		return $message;
	}

	/**
	 * Prepare a collection of messages (in the same conversation) for display or
	 * further processing.
	 *
	 * @param array $messages
	 * @param array $conversation
	 *
	 * @return array Prepared messages
	 */
	public function prepareMessages(array $messages, array $conversation)
	{
		$pagePosition = 0;

		foreach ($messages AS &$message)
		{
			$message = $this->prepareMessage($message, $conversation);

			$message['position_on_page'] = ++$pagePosition;
		}

		return $messages;
	}

	/**
	 * Gets the maximum message date in a list of messages.
	 *
	 * @param array $messages
	 *
	 * @return integer Max message date timestamp; 0 if no messages
	 */
	public function getMaximumMessageDate(array $messages)
	{
		$max = 0;
		foreach ($messages AS $message)
		{
			if ($message['message_date'] > $max)
			{
				$max = $message['message_date'];
			}
		}

		return $max;
	}

	/**
	 * Add the details of a new conversation reply to conversation recipients.
	 *
	 * @param array $conversation Conversation info
	 * @param array|null $replyUser Information about the user who replied
	 * @param array|null $messageInfo Array containing 'message', which is the text the message being sent
	 *
	 * @return array $recipients
	 */
	public function addConversationReplyToRecipients(array $conversation, array $replyUser = null, array $messageInfo = null)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$extraData = array('message_id' => $conversation['last_message_id']);

		$recipients = $this->getConversationRecipients($conversation['conversation_id']);
		foreach ($recipients AS $recipient)
		{
			if (empty($recipient['user_id']))
			{
				continue; // deleted user
			}

			switch ($recipient['recipient_state'])
			{
				case 'active':
					$db->query('
						UPDATE xf_conversation_user SET
							is_unread = 1,
							reply_count = ' . $db->quote($conversation['reply_count']) . ',
							last_message_date = ' . $db->quote($conversation['last_message_date']) . ',
							last_message_id = ' . $db->quote($conversation['last_message_id']) . ',
							last_message_user_id = ' . $db->quote($conversation['last_message_user_id']) . ',
							last_message_username = ' . $db->quote($conversation['last_message_username']) . '
						WHERE conversation_id = ?
							AND owner_user_id = ?
					', array($conversation['conversation_id'], $recipient['user_id']));

					$this->rebuildUnreadConversationCountForUser($recipient['user_id']);

					$this->insertConversationAlert($conversation, $recipient, 'reply', $replyUser, $extraData, $messageInfo);
					break;

				case 'deleted':
					$this->insertConversationRecipient($conversation, $recipient['user_id'], $recipient);
					$this->insertConversationAlert($conversation, $recipient, 'reply', $replyUser, $extraData, $messageInfo);
					break;
			}
		}

		XenForo_Db::commit($db);

		return $recipients;
	}

	/**
	 * Insert a new conversation recipient record.
	 *
	 * @param array $conversation Conversation info
	 * @param integer $user User to insert for
	 * @param array $existingRecipient Information about the existing recipient record (if there is one)
	 * @param string $insertState State to insert the conversation for with this user
	 *
	 * @return boolean True if an insert was required (may be false if user is already an active recipient or is ignoring)
	 */
	public function insertConversationRecipient(array $conversation, $userId, array $existingRecipient = null, $insertState = 'active')
	{
		if ($existingRecipient === null)
		{
			$existingRecipient = $this->getConversationRecipient($conversation['conversation_id'], $userId);
		}

		if ($existingRecipient)
		{
			if (empty($existingRecipient['user_id'])
				|| $existingRecipient['recipient_state'] == 'deleted_ignored'
				|| $existingRecipient['recipient_state'] == $insertState)
			{
				return false;
			}
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$rowsAffected = $db->query('
			INSERT INTO xf_conversation_recipient
				(conversation_id, user_id, recipient_state, last_read_date)
			VALUES
				(?, ?, ?, 0)
			ON DUPLICATE KEY UPDATE recipient_state = VALUES(recipient_state)
		', array($conversation['conversation_id'], $userId, $insertState))->rowCount();

		$inserted = ($rowsAffected == 1);

		if ($insertState == 'active')
		{
			$db->query('
				INSERT IGNORE INTO xf_conversation_user
					(conversation_id, owner_user_id, is_unread, reply_count,
					last_message_date, last_message_id, last_message_user_id, last_message_username)
				VALUES
					(?, ?, 1, ?,
					?, ?, ?, ?)
			', array(
				$conversation['conversation_id'], $userId, $conversation['reply_count'],
				$conversation['last_message_date'], $conversation['last_message_id'],
				$conversation['last_message_user_id'], $conversation['last_message_username']
			));

			$this->rebuildUnreadConversationCountForUser($userId);

			if ($inserted)
			{
				$db->query('
					UPDATE xf_conversation_master SET
						recipient_count = recipient_count + 1
					WHERE conversation_id = ?
				', $conversation['conversation_id']);
			}
		}

		XenForo_Db::commit($db);

		return ($insertState == 'active');
	}

	/**
	 * Inserts an alert for this conversation.
	 *
	 * @param array $conversation
	 * @param array $alertUser User to notify
	 * @param string $action Action taken out (values: insert, reply, join)
	 * @param array|null $triggerUser User triggering the alert; defaults to last user to reply
	 * @param array|null $extraData
	 * @param array|null $messageInfo Array containing the text of the message being sent (if applicable) as 'message'
	 */
	public function insertConversationAlert(array $conversation, array $alertUser, $action,
		array $triggerUser = null, array $extraData = null, array &$messageInfo = null
	)
	{
		if (!$triggerUser)
		{
			$triggerUser = array(
				'user_id' => $conversation['last_message_user_id'],
				'username' => $conversation['last_message_username']
			);
		}

		if ($triggerUser['user_id'] == $alertUser['user_id'])
		{
			return;
		}

		if ($alertUser['email_on_conversation'] && $alertUser['user_state'] == 'valid' && !$alertUser['is_banned'])
		{
			if (!isset($conversation['titleCensored']))
			{
				$conversation['titleCensored'] = XenForo_Helper_String::censorString($conversation['title']);
			}

			if (isset($messageInfo['message']) && XenForo_Application::get('options')->emailConversationIncludeMessage)
			{
				if (!isset($messageInfo['messageText']))
				{
					$bbCodeParserText = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Text'));
					$messageInfo['messageText'] = new XenForo_BbCode_TextWrapper($messageInfo['message'], $bbCodeParserText);

					$bbCodeParserHtml = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('HtmlEmail'));
					$messageInfo['messageHtml'] = new XenForo_BbCode_TextWrapper($messageInfo['message'], $bbCodeParserHtml);
				}

				$emailTemplate = "conversation_{$action}_messagetext";
			}
			else
			{
				$emailTemplate = "conversation_{$action}";
			}

			$mail = XenForo_Mail::create($emailTemplate, array(
				'receiver' => $alertUser,
				'sender' => $triggerUser,
				'options' => XenForo_Application::get('options'),
				'conversation' => $conversation,
				'message' => $messageInfo,
			), $alertUser['language_id']);

			$mail->enableAllLanguagePreCache();
			$mail->queue($alertUser['email'], $alertUser['username']);
		}
	}

	/**
	 * Delets a conversation record for a specific user. If all users have deleted the conversation,
	 * it will be completely removed.
	 *
	 * @param integer $conversationId
	 * @param integer $userId
	 * @param string $deleteType Type of deletion (either delete, or delete_ignore)
	 */
	public function deleteConversationForUser($conversationId, $userId, $deleteType)
	{
		$recipientState = ($deleteType == 'delete_ignore' ? 'deleted_ignored' : 'deleted');

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$db->update('xf_conversation_recipient',
			array('recipient_state' => $recipientState),
			'conversation_id = ' . $db->quote($conversationId) . ' AND user_id = ' . $db->quote($userId)
		);
		$db->delete('xf_conversation_user',
			'conversation_id = ' . $db->quote($conversationId) . ' AND owner_user_id = ' . $db->quote($userId)
		);
		$db->delete('xf_user_alert',
			'content_type = \'conversation\' AND content_id = ' . $db->quote($conversationId)
				. ' AND alerted_user_id = ' . $db->quote($userId)
		);

		$this->rebuildUnreadConversationCountForUser($userId);

		$haveActive = false;
		foreach ($this->getConversationRecipients($conversationId) AS $recipient)
		{
			if (empty($recipient['user_id']))
			{
				continue; // deleted user
			}

			if ($recipient['recipient_state'] == 'active')
			{
				$haveActive = true;
				break;
			}
		}

		if (!$haveActive)
		{
			// no one has the conversation any more, so delete it
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
			$dw->setExistingData($conversationId);
			$dw->delete();
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Marks the conversation as read to a certain point for a user.
	 *
	 * @param integer $conversationId
	 * @param integer $userId
	 * @param integer $newReadDate Timestamp to mark as read until
	 * @param integer $lastMessageDate Date of last message; only marks whole conversation read if more than this date
	 * @param boolean $updateVisitor If true, reduces the conversations_unread counter for the visitor; should be false for replies
	 */
	public function markConversationAsRead($conversationId, $userId, $newReadDate, $lastMessageDate = 0, $updateVisitor = true)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$this->_updateConversationReadDate($conversationId, $userId, $newReadDate, $db);

		if ($newReadDate >= $lastMessageDate)
		{
			$rowsChanged = $db->update('xf_conversation_user',
				array('is_unread' => 0),
				'conversation_id = ' . $db->quote($conversationId) . ' AND owner_user_id = ' . $db->quote($userId)
			);
			if ($rowsChanged)
			{
				$db->query('
					UPDATE xf_user SET
						conversations_unread = IF(conversations_unread > 1, conversations_unread - 1, 0)
					WHERE user_id = ?
				', $userId);

				$visitor = XenForo_Visitor::getInstance();
				if ($updateVisitor && $userId == $visitor['user_id'] && $visitor['conversations_unread'] >= 1)
				{
					$visitor['conversations_unread'] -= 1;
				}
			}
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Marks the conversation as (completely) unread for a user.
	 *
	 * @param integer $conversationId
	 * @param integer $userId
	 */
	public function markConversationAsUnread($conversationId, $userId)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$this->_updateConversationReadDate($conversationId, $userId, 0, $db);

		$rowsChanged = $db->update('xf_conversation_user',
			array('is_unread' => 1),
			'conversation_id = ' . $db->quote($conversationId) . ' AND owner_user_id = ' . $db->quote($userId)
		);
		if ($rowsChanged)
		{
			$db->query('
				UPDATE xf_user SET
					conversations_unread = conversations_unread + 1
				WHERE user_id = ?
			', $userId);

			$visitor = XenForo_Visitor::getInstance();
			if ($userId == $visitor['user_id'])
			{
				$visitor['conversations_unread'] += 1;
			}
		}

		XenForo_Db::commit($db);
	}

	public function changeConversationStarState($conversationId, $userId, $starred)
	{
		$db = $this->_getDb();
		return (bool)$db->update('xf_conversation_user',
			array('is_starred' => $starred ? 1 : 0),
			'conversation_id = ' . $db->quote($conversationId) . ' AND owner_user_id = ' . $db->quote($userId)
		);
	}

	/**
	 * Update the read date record for a conversation
	 *
	 * @param integer $conversationId
	 * @param integer $userId
	 * @param integer $newReadDate
	 * @param Zend_Db_Adapter_Abstract $db
	 *
	 * @return integer $newReadDate
	 */
	protected function _updateConversationReadDate($conversationId, $userId, $newReadDate, Zend_Db_Adapter_Abstract $db = null)
	{
		if (empty($db))
		{
			$db = $this->_getDb();
		}

		$db->update('xf_conversation_recipient', array('last_read_date' => $newReadDate),
			'conversation_id = ' . $db->quote($conversationId) . ' AND user_id = ' . $db->quote($userId)
		);

		return $newReadDate;
	}

	/**
	 * Gets the count of unread conversations for the specified user.
	 *
	 * @param integer $userId
	 *
	 * @return integer
	 */
	public function countUnreadConversationsForUser($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_conversation_user AS conversation_user
			INNER JOIN xf_conversation_master AS conversation_master ON
				(conversation_user.conversation_id = conversation_master.conversation_id)
			INNER JOIN xf_conversation_recipient AS conversation_recipient ON
					(conversation_user.conversation_id = conversation_recipient.conversation_id
					AND conversation_user.owner_user_id = conversation_recipient.user_id)
			WHERE conversation_user.owner_user_id = ?
				AND conversation_user.is_unread = 1
		', $userId);
	}

	/**
	 * Recalculates the unread conversation count for the specified user.
	 *
	 * @param integer $userId
	 */
	public function rebuildUnreadConversationCountForUser($userId)
	{
		$db = $this->_getDb();
		$db->update('xf_user', array(
			'conversations_unread' => $this->countUnreadConversationsForUser($userId)
		), 'user_id = ' . $db->quote($userId));
	}

	/**
	 * Determines if the viewing user can start conversations in general.
	 *
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canStartConversations(&$errorPhraseKey = '', array $viewingUser = null)
	{
		// moved for easier access on all pages
		return $this->_getUserModel()->canStartConversations($errorPhraseKey, $viewingUser);
	}

	/**
	 * Determines if the viewing user can start a conversation with the given user.
	 * Does not check standard conversation permissions.
	 *
	 * @param array $user
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canStartConversationWithUser(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($user['user_id'] == $viewingUser['user_id'])
		{
			$errorPhraseKey = 'you_may_not_start_conversation_with_yourself';
			return false;
		}

		if (empty($user['permissions']))
		{
			if (empty($user['global_permission_cache']))
			{
				$user['global_permission_cache'] = $this->_getDb()->fetchOne('
					SELECT cache_value
					FROM xf_permission_combination
					WHERE permission_combination_id = ?
				', $user['permission_combination_id']);
			}

			$user['permissions'] = XenForo_Permission::unserializePermissions($user['global_permission_cache']);
		}

		$userModel = $this->_getUserModel();

		if (!$userModel->canBypassUserPrivacy($null, $viewingUser)
			&& !XenForo_Permission::hasPermission($user['permissions'], 'conversation', 'receive')
		)
		{
			return false;
		}

		return (
			$this->canStartConversations($errorPhraseKey, $viewingUser)
			&& !$user['is_banned']
			&& $userModel->passesPrivacyCheck(
				$user['allow_send_personal_conversation'], $user, $viewingUser
			)
		);
	}

	/**
	 * Determines if the specified user can reply to the conversation.
	 * Does not check conversation viewing permissions.
	 *
	 * @param array $conversation
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canReplyToConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return ($conversation['user_id'] == $viewingUser['user_id'] || $conversation['conversation_open']);
	}

	/**
	 * Determines if the specified user can edit the conversation.
	 * Does not check conversation viewing permissions.
	 *
	 * @param array $conversation
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return ($conversation['user_id'] == $viewingUser['user_id']);
	}

	/**
	 * Determines if the specified user can invite users the conversation.
	 * Does not check conversation viewing permissions.
	 *
	 * @param array $conversation
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canInviteUsersToConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'alwaysInvite'))
		{
			return true;
		}

		if (!$conversation['conversation_open'])
		{
			return false;
		}

		if ($conversation['user_id'] == $viewingUser['user_id'] || $conversation['open_invite'])
		{
			if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'start'))
			{
				$remaining = $this->allowedAdditionalConversationRecipients($conversation, $viewingUser);
				return ($remaining == -1 || $remaining >= 1);
			}
		}

		return false;
	}

	/**
	 * Determines if the specified user can edit the specified message within a conversation
	 *
	 * @param array $message
	 * @param array $conversation
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditMessage(array $message, array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		// moderator permission, so ignore conversation open/closed and time limit
		if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'editAnyPost'))
		{
			return true;
		}

		// no editing of messages in a closed conversation by normal users
		if (!$conversation['conversation_open'])
		{
			$errorPhraseKey = 'conversation_is_closed';
			return false;
		}

		// own message
		if ($message['user_id'] == $viewingUser['user_id'])
		{
			if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'editOwnPost'))
			{
				$editLimit = XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'editOwnPostTimeLimit');

				if ($editLimit != -1 && (!$editLimit || $message['message_date'] < XenForo_Application::$time - 60 * $editLimit))
				{
					$errorPhraseKey = array('message_edit_time_limit_expired', 'minutes' => $editLimit);
					return false;
				}

				return true;
			}
		}

		$errorPhraseKey = 'you_may_not_edit_this_message';
		return false;
	}

	/**
	 * Checks that the viewing user may report the specified conversation message
	 *
	 * @param array $message
	 * @param array $conversation
	 * @param string
	 * @param boolean $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canReportMessage(array $message, array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		return $this->_getUserModel()->canReportContent($errorPhraseKey, $viewingUser);
	}

	/**
	 * Check permission to view a reported conversation
	 *
	 * @param array $message
	 * @param array $conversation
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canManageReportedMessage(array $message, array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($viewingUser['is_moderator'] && XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'warn'))
		{
			return true;
		}

		$errorPhraseKey = 'you_may_not_manage_this_reported_content';
		return false;
	}

	/**
	 * Determines if a new attachment can be posted in the specified conversation,
	 * with the given permissions. If no permissions are specified, permissions
	 * are retrieved from the currently visiting user. This does not check viewing permissions.
	 *
	 * @param array $conversation Info about the conversation posting in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canUploadAndManageAttachment(array $conversation = array(), &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($conversation)
		{
			// must be able to reply to the conversation in order to upload an attachment
			if (!$this->canReplyToConversation($conversation, $errorPhraseKey, $viewingUser))
			{
				return false;
			}
		}
		else if (!$this->canStartConversations($errorPhraseKey, $viewingUser))
		{
			return false;
		}

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'uploadAttachment');
	}

	/**
	 * Determines if the specified user can view attachments in the specified conversation
	 *
	 * @param array $conversation
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewAttachmentOnConversation(array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return ($conversation['owner_user_id'] == $viewingUser['user_id']);
	}

	/**
	 * Determines if the specified user can view an attachment to the specified message
	 *
	 * @param array $message
	 * @param array $conversation
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewAttachmentOnConversationMessage(array $message, array $conversation, &$errorPhraseKey = '', array $viewingUser = null)
	{
		return $this->canViewAttachmentOnConversation($conversation, $errorPhraseKey, $viewingUser);
	}

	/**
	 * Calculates the allowed number of additional conversation receiptions the
	 * viewing user can add to the given conversation.
	 * @param array $conversation Conversation; if empty array, assumes new conversation
	 * @param array|null $viewingUser
	 *
	 * @return integer -1 means unlimited; 0 is no more invites; other is remaining count
	 */
	public function allowedAdditionalConversationRecipients(array $conversation, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$maxRecipients = XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'maxRecipients');
		if ($maxRecipients == -1)
		{
			return -1;
		}

		if ($conversation)
		{
			$remaining = ($maxRecipients - $conversation['recipient_count'] + 1); // +1 represents self; self doesn't count
			return max(0, $remaining);
		}
		else
		{
			return $maxRecipients;
		}
	}

	/**
	 * Gets the quote text for the specified conversation message.
	 *
	 * @param array $message
	 * @param integer $maxQuoteDepth Max depth of quotes (-1 for unlimited)
	 *
	 * @return string
	 */
	public function getQuoteForConversationMessage(array $message, $maxQuoteDepth = 0)
	{
		return $this->_getQuoteWrapperBbCode(
			$message,
			trim(XenForo_Helper_String::stripQuotes($message['message'], $maxQuoteDepth))
		);
	}

	/**
	 * Converts some HTML into quotable BB code
	 *
	 * @param array $message
	 * @param string $messageHtml
	 * @param integer $maxQuoteDepth Max depth of quotes (-1 for unlimited)
	 *
	 * @return string
	 */
	public function getQuoteTextForMessageFromHtml(array $message, $messageHtml, $maxQuoteDepth = 0)
	{
		$bbCode = $this->getModelFromCache('XenForo_Model_BbCode')->getBbCodeFromSelectionHtml($messageHtml);
		$bbCode = trim(XenForo_Helper_String::stripQuotes($bbCode, $maxQuoteDepth));
		return $this->_getQuoteWrapperBbCode($message, $bbCode);
	}

	/*
	 * Returns a [QUOTE...][/QUOTE] bb code block
	 *
	 * @param array $message
	 * @param string $test
	 *
	 * @return string
	 */
	protected function _getQuoteWrapperBbCode(array $message, $text)
	{
		return '[QUOTE="' . $message['username']
			. ', convMessage: ' . $message['message_id']
			. (!empty($message['user_id']) ? ', member: ' . $message['user_id'] : '')
			. '"]'
			. $text
			. "[/QUOTE]\n";
	}

	/**
	 * Returns the last few page numbers of a conversation
	 *
	 * @param integer $replyCount
	 *
	 * @return array|boolean
	 */
	public function getLastPageNumbers($replyCount)
	{
		$perPage = XenForo_Application::get('options')->messagesPerPage;

		if (($replyCount +1) > $perPage)
		{
			return XenForo_Helper_Discussion::getLastPageNumbers($replyCount, $perPage);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Gets the set of attachment params required to allow uploading.
	 *
	 * @param array $conversation
	 * @param array $contentData Information about the content, for URL building
	 * @param array|null $viewingUser
	 * @param string|null $tempHash
	 *
	 * @return array|bool
	 */
	public function getAttachmentParams(array $conversation = array(), array $contentData = array(), array $viewingUser = null, $tempHash = null)
	{
		if ($this->canUploadAndManageAttachment($conversation, $null, $viewingUser))
		{
			$existing = is_string($tempHash) && strlen($tempHash) == 32;
			$output = array(
				'hash' => $existing ? $tempHash : md5(uniqid('', true)),
				'content_type' => 'conversation_message',
				'content_data' => $contentData
			);
			if ($existing)
			{
				$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
				$output['attachments'] = $attachmentModel->prepareAttachments(
					$attachmentModel->getAttachmentsByTempHash($tempHash)
				);
			}

			return $output;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Gets the attachments that belong to the given messages, and merges them in with
	 * their parent message (in the attachments key). The attachments key will not be
	 * set if no attachments are found for the message.
	 *
	 * @param array $messages
	 *
	 * @return array Messages, with attachments added where necessary
	 */
	public function getAndMergeAttachmentsIntoConversationMessages(array $messages)
	{
		$messageIds = array();

		foreach ($messages AS $messageId => $message)
		{
			if ($message['attach_count'])
			{
				$messageIds[] = $messageId;
			}
		}

		if ($messageIds)
		{
			$attachmentModel = $this->_getAttachmentModel();

			$attachments = $attachmentModel->getAttachmentsByContentIds('conversation_message', $messageIds);

			foreach ($attachments AS $attachment)
			{
				$messages[$attachment['content_id']]['attachments'][$attachment['attachment_id']] = $attachmentModel->prepareAttachment($attachment);
			}
		}

		return $messages;
	}

	/**
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getConversationsStartedByUser($userId)
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM xf_conversation_master
			WHERE user_id = ?
			ORDER BY start_date DESC
		", 'conversation_id', $userId);
	}

	/**
	 * @param integer $userId
	 *
	 * @return int Total conversations deleted
	 */
	public function deleteConversationsStartedByUser($userId)
	{
		$conversations = $this->getConversationsStartedByUser($userId);
		$i = 0;
		foreach ($conversations AS $conversation)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
			$dw->setExistingData($conversation, true);
			$dw->delete();
			$i++;
		}

		return $i;
	}

	/**
	 * @return XenForo_Model_Alert
	 */
	protected function _getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}

	/**
	 * @return XenForo_Model_Attachment.
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}