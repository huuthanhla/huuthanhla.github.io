<?php

/**
* Data writer for threads.
*
* @package XenForo_Discussion
*/
class XenForo_DataWriter_Discussion_Thread extends XenForo_DataWriter_Discussion
{
	public static $forumCache = array();

	const DATA_FORUM = 'forumInfo';

	/**
	 * Gets the object that represents the definition of this type of discussion.
	 *
	 * @return XenForo_Discussion_Definition_Abstract
	 */
	public function getDiscussionDefinition()
	{
		return new XenForo_Discussion_Definition_Thread();
	}

	/**
	 * Gets the object that represents the definition of the message within this discussion.
	 *
	 * @return XenForo_DiscussionMessage_Definition_Abstract
	 */
	public function getDiscussionMessageDefinition()
	{
		return new XenForo_DiscussionMessage_Definition_Post();
	}

	/**
	 * Gets information about the last message in this discussion.
	 *
	 * @return array|false
	 */
	protected function _getLastMessageInDiscussion()
	{
		return $this->_getPostModel()->getLastPostInThread($this->get('thread_id'));
	}

	/**
	 * Gets simple information about all messages in this discussion.
	 *
	 * @param boolean $includeMessage If true, includes the message contents
	 *
	 * @return array Format: [post id] => info
	 */
	protected function _getMessagesInDiscussionSimple($includeMessage = false)
	{
		return $this->_getPostModel()->getPostsInThreadSimple($this->get('thread_id'), $includeMessage);
	}

	/**
	 * Rebuilds the discussion info.
	 *
	 * @return boolean True if still valid
	 */
	public function rebuildDiscussion()
	{
		$threadId = $this->get('thread_id');

		$newCounters = $this->_getPostModel()->recalculatePostPositionsInThread($threadId);
		if (!$newCounters['firstPostId'])
		{
			return false;
		}

		$this->rebuildDiscussionCounters($newCounters['visibleCount'] - 1, $newCounters['firstPostId'], $newCounters['lastPostId']);
		$this->_getThreadModel()->replaceThreadUserPostCounters($threadId, $newCounters['userPosts']);

		return true;
	}

	/**
	 * Rebuilds the counters of the discussion.
	 *
	 * @param integer|false $replyCount Total reply count, if known
	 * @param integer|false $firstPostId First post ID, if known already
	 * @param integer|false $lastPostId Last post ID, if known already
	 */
	public function rebuildDiscussionCounters($replyCount = false, $firstPostId = false, $lastPostId = false)
	{
		$postModel = $this->_getPostModel();
		$threadId = $this->get('thread_id');

		if ($firstPostId && $lastPostId)
		{
			$posts = $postModel->getPostsByIds(
				array($firstPostId, $lastPostId),
				array('join' => XenForo_Model_Post::FETCH_USER)
			);
			$firstPost = $posts[$firstPostId];
			$lastPost = $posts[$lastPostId];
		}
		else
		{
			$postsTemp = $postModel->getPostsInThread(
				$threadId,
				array('join' => XenForo_Model_Post::FETCH_USER, 'limit' => 1)
			);
			$firstPost = reset($postsTemp);

			$lastPost = $postModel->getLastPostInThread(
				$threadId,
				array('join' => XenForo_Model_Post::FETCH_USER)
			);
		}

		if (!$firstPost || !$lastPost)
		{
			return;
		}

		if ($replyCount === false)
		{
			$replyCount = $postModel->countVisiblePostsInThread($threadId) - 1;
		}

		$this->set('first_post_id', $firstPost['post_id']);
		$this->set('post_date', $firstPost['post_date']);
		$this->set('user_id', $firstPost['user_id']);
		$this->set('username', $firstPost['username'] !== '' ? $firstPost['username'] : '-');
		$this->set('first_post_likes', $firstPost['likes']);
		$this->set('reply_count', $replyCount);

		$this->set('last_post_id', $lastPost['post_id']);
		$this->set('last_post_date', $lastPost['post_date']);
		$this->set('last_post_user_id', $lastPost['user_id']);
		$this->set('last_post_username', $lastPost['username']);
	}

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		$fields = $this->_getCommonFields();
		$fields['xf_thread']['first_post_likes'] = array('type' => self::TYPE_UINT_FORCED, 'default' => 0);
		$fields['xf_thread']['prefix_id'] = array('type' => self::TYPE_UINT, 'default' => 0);

		return $fields;
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$threadId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array($this->getDiscussionTableName() => $this->_getThreadModel()->getThreadById($threadId));
	}

	/**
	 * Specific discussion pre-save behaviors.
	 */
	protected function _discussionPreSave()
	{
		if ($this->get('prefix_id') && ($this->isChanged('prefix_id') || $this->isChanged('node_id')))
		{
			if (!$this->_getPrefixModel()->getPrefixIfInForum($this->get('prefix_id'), $this->get('node_id')))
			{
				$this->set('prefix_id', 0); // invalid prefix
			}
		}
	}

	/**
	 * Specific discussion post-save behaviors.
	 */
	protected function _discussionPostSave()
	{
		$threadId = $this->get('thread_id');

		if ($this->isUpdate() && $this->isChanged('discussion_state') && $this->get('discussion_state') != 'visible')
		{
			$this->_deleteRedirects('thread-' . $threadId . '-%', true);
		}
		else if ($this->isUpdate() && $this->isChanged('node_id'))
		{
			if ($this->get('discussion_type') == 'redirect')
			{
				$threadRedirectModel = $this->getModelFromCache('XenForo_Model_ThreadRedirect');
				$redirect = $threadRedirectModel->getThreadRedirectById($threadId);
				if ($redirect && $redirect['redirect_key'])
				{
					$redirectKey = preg_replace('/^(thread-\d+)-(\d+)$/', '$1-' . $this->get('node_id'), $redirect['redirect_key']);
					if ($redirectKey != $redirect['redirect_key'])
					{
						$threadRedirectModel->updateThreadRedirect($this->get('thread_id'), array('redirect_key' => $redirectKey));
					}
				}
			}
			else
			{
				// delete redirects if moving back to forum that already had it
				$this->_deleteRedirects('thread-' . $threadId . '-' . $this->get('node_id') . '-');
			}
		}

		if ($this->isUpdate() && $this->get('discussion_state') == 'visible' && $this->isChanged('node_id'))
		{
			$messageHandler = $this->_messageDefinition->getSearchDataHandler();
			if ($messageHandler)
			{
				XenForo_Application::defer('SearchIndexPartial', array(
					'contentType' => 'post',
					'contentIds' => $this->_getDiscussionMessageIds()
				));
			}

			$this->_handleForumMove($this->getExisting('node_id'), $this->get('node_id'));
		}
	}

	/**
	 * Post-save handling, after the transaction is committed.
	 */
	protected function _postSaveAfterTransaction()
	{
		if (
			$this->isUpdate()
			&& $this->get('discussion_state') == 'visible'
			&& $this->getExisting('discussion_state') == 'moderated'
		)
		{
			$post = $this->_getPostModel()->getPostById($this->get('first_post_id'));
			if ($post)
			{
				$this->getModelFromCache('XenForo_Model_ForumWatch')->sendNotificationToWatchUsersOnMessage($post);
			}
		}
	}

	/**
	 * Returns true if the changes made require the search index to be updated.
	 *
	 * @return boolean
	 */
	protected function _needsSearchIndexUpdate()
	{
		return (parent::_needsSearchIndexUpdate() || $this->isChanged('prefix_id'));
	}

	/**
	 * Specific discussion post-delete behaviors.
	 */
	protected function _discussionPostDelete()
	{
		$threadId = $this->get('thread_id');
		$threadIdQuoted = $this->_db->quote($threadId);

		$this->_db->delete('xf_thread_watch', "thread_id = $threadIdQuoted");
		$this->_db->delete('xf_thread_user_post', "thread_id = $threadIdQuoted");
		$this->_db->delete('xf_thread_reply_ban', "thread_id = $threadIdQuoted");

		if ($this->get('discussion_type') == 'redirect')
		{
			$this->getModelFromCache('XenForo_Model_ThreadRedirect')->deleteThreadRedirects(array($threadId));
		}
		else
		{
			$this->_deleteRedirects('thread-' . $this->get('thread_id') . '-%', true);
		}

		if ($this->get('discussion_type') == 'poll')
		{
			$poll = $this->getModelFromCache('XenForo_Model_Poll')->getPollByContent('thread', $threadId);
			if ($poll)
			{
				$pollDw = XenForo_DataWriter::create('XenForo_DataWriter_Poll', XenForo_DataWriter::ERROR_SILENT);
				$pollDw->setExistingData($poll, true);
				$pollDw->delete();
			}
		}
	}

	/**
	 * Checks that the messages posted in the containing forum count towards the
	 * user message count before running the standard message count update.
	 *
	 * @see XenForo_DataWriter_DiscussionMessage::_updateUserMessageCount()
	 */
	protected function _updateUserMessageCount($isDelete = false, $forceUpdateType = null)
	{
		if ($this->_forumCountsMessages())
		{
			return parent::_updateUserMessageCount($isDelete, $forceUpdateType);
		}
	}

	/**
	 * Determine if messages posted in the containing forum count towards the
	 * user message count.
	 *
	 * @param array $forum If empty, will attempt to fetch DATA_FORUM, or query for forum based on thread node_id
	 *
	 * @return boolean
	 */
	protected function _forumCountsMessages(array $forum = null)
	{
		if (empty($forum))
		{
			$forum = $this->_getForumData();
		}

		if ($forum && isset($forum['count_messages']))
		{
			return !empty($forum['count_messages']);
		}
		else
		{
			// default fallback
			return true;
		}
	}

	/**
	 * Handle moving from a non-counting to a counting forum, or vice versa
	 *
	 * @param integer $sourceForumId
	 * @param integer $destinationForumId
	 */
	protected function _handleForumMove($sourceForumId, $destinationForumId)
	{
		// fetch the source and destination forum info
		if (!self::isForumCacheItem($sourceForumId) || !self::isForumCacheItem($destinationForumId))
		{
			$forums = $this->getModelFromCache('XenForo_Model_Forum')->getForumsByIds(
				array($sourceForumId, $destinationForumId));

			foreach ($forums AS $forum)
			{
				self::setForumCacheItem($forum);
			}
		}

		$sourceForum = self::getForumCacheItem($sourceForumId);
		$destinationForum = self::getForumCacheItem($destinationForumId);

		// do the 'count_messages' settings differ?
		if ($sourceForum && $destinationForum && $sourceForum['count_messages'] != $destinationForum['count_messages'])
		{
			$oldState = $this->getExisting('discussion_state');
			$newState = $this->get('discussion_state');

			if ($newState == 'visible' && ($oldState != 'visible' || $destinationForum['count_messages']))
			{
				// moving to a counting forum from a non-counting forum
				parent::_updateUserMessageCount(false, 'add');
			}
			else if ($oldState == 'visible' && ($newState != 'visible' || !$destinationForum['count_messages']))
			{
				// moving from a counting forum from a non-counting forum
				parent::_updateUserMessageCount(false, 'subtract');
			}
		}
	}

	/**
	* @see XenForo_DataWriter::setExtraData
	*/
	public function setExtraData($name, $value)
	{
		if ($name == self::DATA_FORUM && is_array($value) && !empty($value['node_id']))
		{
			self::setForumCacheItem($value);
		}

		return parent::setExtraData($name, $value);
	}

	/**
	 * Get the data for the forum the thread is in
	 *
	 * @return array
	 */
	protected function _getForumData()
	{
		if (!$forum = $this->getExtraData(self::DATA_FORUM))
		{
			$forum = self::getForumCacheItem($this->get('node_id'));
		}

		return $forum;
	}

	public static function setForumCacheItem(array $forum)
	{
		self::$forumCache[$forum['node_id']] = $forum;
	}

	public static function getForumCacheItem($forumId)
	{
		if (!self::isForumCacheItem($forumId))
		{
			$forum = XenForo_Model::create('XenForo_Model_Forum')->getForumById($forumId);
			if (!$forum)
			{
				self::$forumCache[$forumId] = false;
			}
			else
			{
				self::setForumCacheItem($forum);
			}
		}

		return self::$forumCache[$forumId];
	}

	public static function isForumCacheItem($forumId)
	{
		return array_key_exists($forumId, self::$forumCache);
	}

	/**
	 * Deletes thread redirects with the specified key(s).
	 *
	 * @param string $redirectKey
	 * @param boolean $likeMatch
	 */
	protected function _deleteRedirects($redirectKey, $likeMatch = false)
	{
		$threadRedirectModel = $this->getModelFromCache('XenForo_Model_ThreadRedirect');
		$redirects = $threadRedirectModel->getThreadRedirectsByKey($redirectKey, $likeMatch);
		$threadRedirectModel->deleteThreadRedirects(array_keys($redirects));
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
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}
}