<?php

class XenForo_Model_ForumWatch extends XenForo_Model
{
	/**
	 * Gets a user's thread watch record for the specified forum ID.
	 *
	 * @param integer $userId
	 * @param integer $nodeId
	 *
	 * @return array|bool
	 */
	public function getUserForumWatchByForumId($userId, $nodeId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_forum_watch
			WHERE user_id = ?
				AND node_id = ?
		', array($userId, $nodeId));
	}

	/**
	 * Get the thread watch records for a user, across many node IDs.
	 *
	 * @param integer $userId
	 * @param array $nodeIds
	 *
	 * @return array Format: [node_id] => watch info
	 */
	public function getUserForumWatchByNodeIds($userId, array $nodeIds)
	{
		if (!$nodeIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_forum_watch
			WHERE user_id = ?
				AND node_id IN (' . $this->_getDb()->quote($nodeIds) . ')
		', 'node_id', $userId);
	}

	/**
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getUserForumWatchByUser($userId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_forum_watch
			WHERE user_id = ?
		', 'node_id', $userId);
	}

	/**
	 * Get a list of all users watching a forum. Includes permissions for the forum.
	 *
	 * Note that inactive users may be filtered out
	 *
	 * @param integer $nodeId
	 * @param integer $threadId
	 * @param boolean $isReply
	 *
	 * @return array Format: [user_id] => info
	 */
	public function getUsersWatchingForum($nodeId, $threadId, $isReply = false)
	{
		$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);

		if ($isReply)
		{
			$notificationLimit = "AND forum_watch.notify_on = 'message'";
		}
		else
		{
			$notificationLimit = "AND forum_watch.notify_on IN ('thread', 'message')";
		}

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
				forum_watch.notify_on,
				forum_watch.send_alert,
				forum_watch.send_email,
				permission.cache_value AS node_permission_cache,
				GREATEST(COALESCE(thread_read.thread_read_date, 0), COALESCE(forum_read.forum_read_date, 0), ' . $autoReadDate . ') AS read_date
			FROM xf_forum_watch AS forum_watch
			INNER JOIN xf_user AS user ON
				(user.user_id = forum_watch.user_id AND user.user_state = \'valid\' AND user.is_banned = 0' . $activeLimit . ')
			INNER JOIN xf_user_option AS user_option ON
				(user_option.user_id = user.user_id)
			INNER JOIN xf_user_profile AS user_profile ON
				(user_profile.user_id = user.user_id)
			LEFT JOIN xf_permission_cache_content AS permission
				ON (permission.permission_combination_id = user.permission_combination_id
					AND permission.content_type = \'node\'
					AND permission.content_id = forum_watch.node_id)
			LEFT JOIN xf_forum_read AS forum_read
				ON (forum_read.node_id = forum_watch.node_id AND forum_read.user_id = user.user_id)
			LEFT JOIN xf_thread_read AS thread_read
				ON (thread_read.thread_id = ? AND thread_read.user_id = user.user_id)
			WHERE forum_watch.node_id = ?
				' . $notificationLimit . '
				AND (forum_watch.send_alert <> 0 OR forum_watch.send_email <> 0)
		', 'user_id', array($threadId, $nodeId));
	}

	protected static $_preventDoubleNotify = array();

	/**
	 * Send a notification to the users watching the thread.
	 *
	 * @param array $post The post being made
	 * @param array|null $thread Info about the thread the reply is in; fetched if null
	 * @param array $noAlerts List of user ids to NOT alert (but still send email)
	 * @param array $noEmail List of user ids to not send an email
	 *
	 * @return array Empty or keys: alerted: user ids alerted, emailed: user ids emailed
	 */
	public function sendNotificationToWatchUsersOnMessage(array $post, array $thread = null, array $noAlerts = array(), array $noEmail = array())
	{
		if ($post['message_state'] != 'visible')
		{
			return array();
		}

		$threadModel = $this->_getThreadModel();

		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');

		if (!$thread)
		{
			$thread = $threadModel->getThreadById($post['thread_id'], array(
				'join' => XenForo_Model_Thread::FETCH_FORUM
			));
		}
		if (!$thread || $thread['discussion_state'] != 'visible')
		{
			return array();
		}

		$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);

		if ($post['position'] == 0)
		{
			$actionType = 'thread';
			$latestPosts = array();
			$defaultPreviousPost = false;
		}
		else
		{
			$actionType = 'message';

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
		}

		if (XenForo_Application::get('options')->emailWatchedThreadIncludeMessage)
		{
			$parseBbCode = true;
			$emailTemplate = 'watched_forum_' . $actionType . '_messagetext';
		}
		else
		{
			$parseBbCode = false;
			$emailTemplate = 'watched_forum_' . $actionType;
		}

		// fetch a full user record if we don't have one already
		if (!isset($post['avatar_width']) || !isset($post['custom_title']))
		{
			$postUser = $this->getModelFromCache('XenForo_Model_User')->getUserById($post['user_id']);
			if ($postUser)
			{
				$post = array_merge($postUser, $post);
			}
			else
			{
				$post['avatar_width'] = 0;
				$post['custom_title'] = '';
			}
		}

		$alerted = array();
		$emailed = array();

		$users = $this->getUsersWatchingForum($thread['node_id'], $thread['thread_id'], $actionType == 'message');
		foreach ($users AS $user)
		{
			if ($user['user_id'] == $post['user_id'])
			{
				continue;
			}

			if ($userModel->isUserIgnored($user, $post['user_id']))
			{
				continue;
			}

			if ($user['read_date'] >= $thread['last_post_date'])
			{
				// user has already read the entire thread
				continue;
			}

			if ($actionType == 'message')
			{
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
				else if ($previousPost['post_date'] > $user['read_date'])
				{
					// user hasn't read the thread since the last alert, don't send another one
					continue;
				}
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

			if ($user['send_email'] && !in_array($user['user_id'], $noEmail)
				&& $user['email'] && $user['user_state'] == 'valid')
			{
				if (!isset($post['messageText']) && $parseBbCode)
				{
					$bbCodeParserText = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Text'));
					$post['messageText'] = new XenForo_BbCode_TextWrapper($post['message'], $bbCodeParserText);

					$bbCodeParserHtml = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('HtmlEmail'));
					$post['messageHtml'] = new XenForo_BbCode_TextWrapper($post['message'], $bbCodeParserHtml);
				}

				if (!isset($thread['titleCensored']))
				{
					$thread['titleCensored'] = XenForo_Helper_String::censorString($thread['title']);
				}

				$user['email_confirm_key'] = $userModel->getUserEmailConfirmKey($user);

				$mail = XenForo_Mail::create($emailTemplate, array(
					'reply' => $post,
					'thread' => $thread,
					'forum' => $thread,
					'receiver' => $user
				), $user['language_id']);
				$mail->enableAllLanguagePreCache();
				$mail->queue($user['email'], $user['username']);

				$emailed[] = $user['user_id'];
			}

			if ($user['send_alert'] && !in_array($user['user_id'], $noAlerts))
			{
				$alertType = ($post['attach_count'] ? 'insert_attachment' : 'insert');

				XenForo_Model_Alert::alert(
					$user['user_id'],
					$post['user_id'],
					$post['username'],
					'post',
					$post['post_id'],
					$alertType
				);

				$alerted[] = $user['user_id'];
			}
		}

		return array(
			'emailed' => $emailed,
			'alerted' => $alerted
		);
	}

	/**
	 * Notify users of a thread being moved to a new forum. Attempt
	 * to prevent double notifications by not notifying people watching
	 * the source forum.
	 *
	 * @param array $firstPost
	 * @param integer $sourceForumId
	 *
	 * @return array
	 */
	public function sendNotificationToWatchUsersOnMove(array $firstPost, $sourceForumId)
	{
		$watchers = $this->_getDb()->fetchAll("
			SELECT user_id, send_alert, send_email
			FROM xf_forum_watch
			WHERE node_id = ?
				AND (send_alert = 1 OR send_email = 1)
		", $sourceForumId);

		$noAlert = array();
		$noEmail = array();
		foreach ($watchers AS $watcher)
		{
			if ($watcher['send_alert'])
			{
				$noAlert[] = $watcher['user_id'];
			}
			if ($watcher['send_email'])
			{
				$noEmail[] = $watcher['user_id'];
			}
		}

		return $this->sendNotificationToWatchUsersOnMessage($firstPost, null, $noAlert, $noEmail);
	}

	/**
	 * Sets the forum watch state as requested. An empty state will delete any watch record.
	 *
	 * @param integer $userId
	 * @param integer $forumId
	 * @param string|null $notifyOn If "delete", watch record is removed
	 * @param boolean|null $sendAlert
	 * @param boolean|null $sendEmail
	 *
	 * @return boolean
	 */
	public function setForumWatchState($userId, $forumId, $notifyOn = null, $sendAlert = null, $sendEmail = null)
	{
		if (!$userId)
		{
			return false;
		}

		$forumWatch = $this->getUserForumWatchByForumId($userId, $forumId);

		if ($notifyOn === 'delete')
		{
			if ($forumWatch)
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_ForumWatch');
				$dw->setExistingData($forumWatch, true);
				$dw->delete();
			}
			return true;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ForumWatch');
		if ($forumWatch)
		{
			$dw->setExistingData($forumWatch, true);
		}
		else
		{
			$dw->set('user_id', $userId);
			$dw->set('node_id', $forumId);
		}
		if ($notifyOn !== null)
		{
			$dw->set('notify_on', $notifyOn);
		}
		if ($sendAlert !== null)
		{
			$dw->set('send_alert', $sendAlert ? 1 : 0);
		}
		if ($sendEmail !== null)
		{
			$dw->set('send_email', $sendEmail ? 1 : 0);
		}
		$dw->save();
		return true;
	}

	public function setForumWatchStateForAll($userId, $state)
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
				return $db->update('xf_forum_watch',
					array('send_email' => 1),
					"user_id = " . $db->quote($userId)
				);

			case 'watch_no_email':
				return $db->update('xf_forum_watch',
					array('send_email' => 0),
					"user_id = " . $db->quote($userId)
				);

			case 'watch_alert':
				return $db->update('xf_forum_watch',
					array('send_alert' => 1),
					"user_id = " . $db->quote($userId)
				);

			case 'watch_no_alert':
				return $db->update('xf_forum_watch',
					array('send_alert' => 0),
					"user_id = " . $db->quote($userId)
				);

			case '':
				return $db->delete('xf_forum_watch', "user_id = " . $db->quote($userId));

			default:
				return false;
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