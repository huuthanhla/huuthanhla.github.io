<?php

/**
 * Model for thread watch records.
 *
 * @package XenForo_Thread
 */
class XenForo_Model_ThreadWatch extends XenForo_Model
{
	/**
	 * Gets a user's thread watch record for the specified thread ID.
	 *
	 * @param integer $userId
	 * @param integer $threadId
	 *
	 * @return array|false
	 */
	public function getUserThreadWatchByThreadId($userId, $threadId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_thread_watch
			WHERE user_id = ?
				AND thread_id = ?
		', array($userId, $threadId));
	}

	/**
	 * Get the thread watch records for a user, across many thread IDs.
	 *
	 * @param integer $userId
	 * @param array $threadIds
	 *
	 * @return array Format: [thread_id] => thread watch info
	 */
	public function getUserThreadWatchByThreadIds($userId, array $threadIds)
	{
		if (!$threadIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_thread_watch
			WHERE user_id = ?
				AND thread_id IN (' . $this->_getDb()->quote($threadIds) . ')
		', 'thread_id', $userId);
	}

	/**
	 * Get a list of all users watching a thread. Includes permissions for the forum the thread is in.
	 *
	 * Note that inactive users may be filtered out.
	 *
	 * @param integer $threadId
	 * @param integer $nodeId Forum the thread is in.
	 *
	 * @return array Format: [user_id] => info
	 */
	public function getUsersWatchingThread($threadId, $nodeId)
	{
		$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);

		$activeLimitOption = XenForo_Application::getOptions()->watchAlertActiveOnly;
		if (!empty($activeLimitOption['enabled']))
		{
			$activeLimit = ' AND user.last_activity >= ' . (XenForo_Application::$time - 86400 * $activeLimitOption['days']);
		}
		else
		{
			$activeLimit = '';
		}

		return $this->fetchAllKeyed('
			SELECT user.*,
				user_option.*,
				user_profile.*,
				thread_watch.email_subscribe,
				permission.cache_value AS node_permission_cache,
				GREATEST(COALESCE(thread_read.thread_read_date, 0), COALESCE(forum_read.forum_read_date, 0), ' . $autoReadDate . ') AS thread_read_date
			FROM xf_thread_watch AS thread_watch
			INNER JOIN xf_user AS user ON
				(user.user_id = thread_watch.user_id AND user.user_state = \'valid\' AND user.is_banned = 0' . $activeLimit . ')
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			INNER JOIN xf_user_profile AS user_profile ON
				(user_profile.user_id = user.user_id)
			LEFT JOIN xf_permission_cache_content AS permission
				ON (permission.permission_combination_id = user.permission_combination_id
					AND permission.content_type = \'node\'
					AND permission.content_id = ?)
			LEFT JOIN xf_thread_read AS thread_read
				ON (thread_read.thread_id = thread_watch.thread_id AND thread_read.user_id = user.user_id)
			LEFT JOIN xf_forum_read AS forum_read
				ON (forum_read.node_id = ? AND forum_read.user_id = user.user_id)
			WHERE thread_watch.thread_id = ?
		', 'user_id', array($nodeId, $nodeId, $threadId));
	}

	protected static $_preventDoubleNotify = array();

	/**
	 * Send a notification to the users watching the thread.
	 *
	 * @param array $reply The reply that has been added
	 * @param array|null $thread Info about the thread the reply is in; fetched if null
	 * @param array $noAlerts List of user ids to NOT alert (but still send email)
	 *
	 * @return array Empty or keys: alerted: user ids alerted, emailed: user ids emailed
	 */
	public function sendNotificationToWatchUsersOnReply(array $reply, array $thread = null, array $noAlerts = array())
	{
		if ($reply['message_state'] != 'visible')
		{
			return array();
		}

		$threadModel = $this->_getThreadModel();

		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');

		if (!$thread)
		{
			$thread = $threadModel->getThreadById($reply['thread_id'], array(
				'join' => XenForo_Model_Thread::FETCH_FORUM
			));
		}
		if (!$thread || $thread['discussion_state'] != 'visible')
		{
			return array();
		}

		$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);

		// get last 15 posts that could be relevant - need to go back in time for ignored reply handling
		$latestPosts = $this->getModelFromCache('XenForo_Model_Post')->getNewestPostsInThreadAfterDate(
			$thread['thread_id'], $autoReadDate,
			array('limit' => 15)
		);
		if (!$latestPosts)
		{
			return array();
		}

		// the reply is likely the last post, so get the one before that and only
		// alert again if read since; note these posts are in newest first order
		list($key) = each($latestPosts);
		unset($latestPosts[$key]);
		$defaultPreviousPost = reset($latestPosts);

		if (XenForo_Application::get('options')->emailWatchedThreadIncludeMessage)
		{
			$parseBbCode = true;
			$emailTemplate = 'watched_thread_reply_messagetext';
		}
		else
		{
			$parseBbCode = false;
			$emailTemplate = 'watched_thread_reply';
		}

		// fetch a full user record if we don't have one already
		if (!isset($reply['avatar_width']) || !isset($reply['custom_title']))
		{
			$replyUser = $this->getModelFromCache('XenForo_Model_User')->getUserById($reply['user_id']);
			if ($replyUser)
			{
				$reply = array_merge($replyUser, $reply);
			}
			else
			{
				$reply['avatar_width'] = 0;
				$reply['custom_title'] = '';
			}
		}

		$alerted = array();
		$emailed = array();

		$users = $this->getUsersWatchingThread($thread['thread_id'], $thread['node_id']);
		foreach ($users AS $user)
		{
			if ($user['user_id'] == $reply['user_id'])
			{
				continue;
			}

			if ($userModel->isUserIgnored($user, $reply['user_id']))
			{
				continue;
			}

			if (!$defaultPreviousPost || !$userModel->isUserIgnored($user, $defaultPreviousPost['user_id']))
			{
				$previousPost = $defaultPreviousPost;
			}
			else
			{
				// need to recalculate the last post that they would've seen
				$previousPost = false;
				foreach ($latestPosts AS $latestPost)
				{
					if (!$userModel->isUserIgnored($user, $latestPost['user_id']))
					{
						// this is the most recent post they didn't ignore
						$previousPost = $latestPost;
						break;
					}
				}
			}

			if (!$previousPost || $previousPost['post_date'] < $autoReadDate)
			{
				// always alert
			}
			else if ($previousPost['post_date'] > $user['thread_read_date'])
			{
				// user hasn't read the thread since the last alert, don't send another one
				continue;
			}

			$permissions = XenForo_Permission::unserializePermissions($user['node_permission_cache']);
			if (!$threadModel->canViewThreadAndContainer($thread, $thread, $null, $permissions, $user))
			{
				continue;
			}

			if (isset(self::$_preventDoubleNotify[$thread['thread_id']][$user['user_id']]))
			{
				continue;
			}
			self::$_preventDoubleNotify[$thread['thread_id']][$user['user_id']] = true;

			if ($user['email_subscribe'] && $user['email'] && $user['user_state'] == 'valid')
			{
				if (!isset($reply['messageText']) && $parseBbCode)
				{
					$bbCodeParserText = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Text'));
					$reply['messageText'] = new XenForo_BbCode_TextWrapper($reply['message'], $bbCodeParserText);

					$bbCodeParserHtml = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('HtmlEmail'));
					$reply['messageHtml'] = new XenForo_BbCode_TextWrapper($reply['message'], $bbCodeParserHtml);
				}

				if (!isset($thread['titleCensored']))
				{
					$thread['titleCensored'] = XenForo_Helper_String::censorString($thread['title']);
				}

				$user['email_confirm_key'] = $userModel->getUserEmailConfirmKey($user);

				$mail = XenForo_Mail::create($emailTemplate, array(
					'reply' => $reply,
					'thread' => $thread,
					'forum' => $thread,
					'receiver' => $user
				), $user['language_id']);
				$mail->enableAllLanguagePreCache();
				$mail->queue($user['email'], $user['username']);

				$emailed[] = $user['user_id'];
			}

			if (!in_array($user['user_id'], $noAlerts))
			{
				$alertType = ($reply['attach_count'] ? 'insert_attachment' : 'insert');

				if (XenForo_Model_Alert::userReceivesAlert($user, 'post', $alertType))
				{
					XenForo_Model_Alert::alert(
						$user['user_id'],
						$reply['user_id'],
						$reply['username'],
						'post',
						$reply['post_id'],
						$alertType
					);

					$alerted[] = $user['user_id'];
				}
			}
		}

		return array(
			'emailed' => $emailed,
			'alerted' => $alerted
		);
	}

	/**
	 * Get the threads watched by a specific user.
	 *
	 * @param integer $userId
	 * @param boolean $newOnly If true, only gets unread threads.
	 * @param array $fetchOptions Thread fetch options (uses all valid for XenForo_Model_Thread).
	 *
	 * @return array Format: [thread_id] => info
	 */
	public function getThreadsWatchedByUser($userId, $newOnly, array $fetchOptions = array())
	{
		$fetchOptions['readUserId'] = $userId;
		$fetchOptions['includeForumReadDate'] = true;

		$joinOptions = $this->_getThreadModel()->prepareThreadFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		if ($newOnly)
		{
			$cutoff = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);
			$newOnlyClause = '
				AND thread.last_post_date > ' . $cutoff . '
				AND thread.last_post_date > COALESCE(thread_read.thread_read_date, 0)
				AND thread.last_post_date > COALESCE(forum_read.forum_read_date, 0)
			';
		}
		else
		{
			$newOnlyClause = '';
		}

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT thread.*,
					thread_watch.email_subscribe
					' . $joinOptions['selectFields'] . '
				FROM xf_thread_watch AS thread_watch
				INNER JOIN xf_thread AS thread ON
					(thread.thread_id = thread_watch.thread_id)
				' . $joinOptions['joinTables'] . '
				WHERE thread_watch.user_id = ?
					AND thread.discussion_state = \'visible\'
					' . $newOnlyClause . '
				ORDER BY thread.last_post_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'thread_id', $userId);
	}

	/**
	 * Gets the total number of threads a user is watching.
	 *
	 * @param integer $userId
	 *
	 * @return integer
	 */
	public function countThreadsWatchedByUser($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_thread_watch AS thread_watch
			INNER JOIN xf_thread AS thread ON
				(thread.thread_id = thread_watch.thread_id)
			WHERE thread_watch.user_id = ?
				AND thread.discussion_state = \'visible\'
		', $userId);
	}

	/**
	 * Take a list of threads (with the forum and permission info included in the thread)
	 * and filters them to those that are viewable.
	 *
	 * @param array $threads List of threads, with forum info and permissions included
	 * @param array|null $viewingUser
	 *
	 * @return array
	 */
	public function getViewableThreadsFromList(array $threads, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$threadModel = $this->_getThreadModel();

		foreach ($threads AS $key => $thread)
		{
			if (isset($thread['permissions']))
			{
				$permissions = $thread['permissions'];
			}
			else
			{
				$permissions = XenForo_Permission::unserializePermissions($thread['node_permission_cache']);
			}

			if (!$threadModel->canViewThreadAndContainer($thread, $thread, $null, $permissions, $viewingUser))
			{
				unset($threads[$key]);
			}
		}

		return $threads;
	}

	/**
	 * Sets the thread watch state as requested. An empty state will delete any watch record.
	 *
	 * @param integer $userId
	 * @param integer $threadId
	 * @param string $state Values: watch_email, watch_no_email, (empty string)
	 *
	 * @return boolean
	 */
	public function setThreadWatchState($userId, $threadId, $state)
	{
		if (!$userId)
		{
			return false;
		}

		$threadWatch = $this->getUserThreadWatchByThreadId($userId, $threadId);

		switch ($state)
		{
			case 'watch_email':
			case 'watch_no_email':
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadWatch');
				if ($threadWatch)
				{
					$dw->setExistingData($threadWatch, true);
				}
				else
				{
					$dw->set('user_id', $userId);
					$dw->set('thread_id', $threadId);
				}
				$dw->set('email_subscribe', ($state == 'watch_email' ? 1 : 0));
				$dw->save();
				return true;

			case '':
				if ($threadWatch)
				{
					$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadWatch');
					$dw->setExistingData($threadWatch, true);
					$dw->delete();
				}
				return true;

			default:
				return false;
		}
	}

	public function setThreadWatchStateForAll($userId, $state)
	{
		$userId = intval($userId);
		if (!$userId)
		{
			return false;
		}

		$db = $this->_getDb();

		switch ($state)
		{
			case 'watch_email':
				return $db->update('xf_thread_watch',
					array('email_subscribe' => 1),
					"user_id = " . $db->quote($userId)
				);

			case 'watch_no_email':
				return $db->update('xf_thread_watch',
					array('email_subscribe' => 0),
					"user_id = " . $db->quote($userId)
				);

			case '':
				return $db->delete('xf_thread_watch', "user_id = " . $db->quote($userId));

			default:
				return false;
		}
	}

	/**
	 * Sets the thread watch state based on the user's default. This will never unwatch a thread.
	 *
	 * @param integer $userId
	 * @param integer $threadId
	 * @param string $state Values: watch_email, watch_no_email, (empty string)
	 *
	 * @return boolean
	 */
	public function setThreadWatchStateWithUserDefault($userId, $threadId, $state)
	{
		if (!$userId)
		{
			return false;
		}

		$threadWatch = $this->getUserThreadWatchByThreadId($userId, $threadId);
		if ($threadWatch)
		{
			return true;
		}

		switch ($state)
		{
			case 'watch_email':
			case 'watch_no_email':
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadWatch');
				$dw->set('user_id', $userId);
				$dw->set('thread_id', $threadId);
				$dw->set('email_subscribe', ($state == 'watch_email' ? 1 : 0));
				$dw->save();
				return true;

			default:
				return false;
		}
	}

	/**
	 * Sets the thread watch state for the visitor from an array of input. Keys in input:
	 * 	* watch_thread_state: if true, uses watch_thread and watch_thread_email to set state as requested
	 *  * watch_thread: if true, watches thread
	 *  * watch_thread_email: if true (and watch_thread is true), watches thread with email; otherwise, watches thread without email
	 *
	 * @param integer $threadId
	 * @param array $input
	 *
	 * @return boolean
	 */
	public function setVisitorThreadWatchStateFromInput($threadId, array $input)
	{
		$visitor = XenForo_Visitor::getInstance();

		if (!$visitor['user_id'])
		{
			return false;
		}

		if ($input['watch_thread_state'])
		{
			if ($input['watch_thread'])
			{
				$watchState = ($input['watch_thread_email'] ? 'watch_email' : 'watch_no_email');
			}
			else
			{
				$watchState = '';
			}

			return $this->setThreadWatchState($visitor['user_id'], $threadId, $watchState);
		}
		else
		{
			return $this->setThreadWatchStateWithUserDefault($visitor['user_id'], $threadId, $visitor['default_watch_state']);
		}
	}

	/**
	 * Gets the thread watch state for the specified thread for the visiting user.
	 *
	 * @param integer|false $threadId Thread ID, or false if unknown
	 * @param boolean $useDefaultIfNotWatching If true, uses visitor default if thread isn't watched
	 *
	 * @return string Values: watch_email, watch_no_email, (empty string)
	 */
	public function getThreadWatchStateForVisitor($threadId = false, $useDefaultIfNotWatching = true)
	{
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor['user_id'])
		{
			return '';
		}

		if ($threadId)
		{
			$threadWatch = $this->getUserThreadWatchByThreadId($visitor['user_id'], $threadId);
		}
		else
		{
			$threadWatch = false;
		}

		if ($threadWatch)
		{
			return ($threadWatch['email_subscribe'] ? 'watch_email' : 'watch_no_email');
		}
		else if ($useDefaultIfNotWatching)
		{
			return $visitor['default_watch_state'];
		}
		else
		{
			return '';
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
	 * @return XenForo_Model_Alert
	 */
	protected function _getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}
}