<?php

/**
 * Model for posts.
 *
 * @package XenForo_Post
 */
class XenForo_Model_Post extends XenForo_Model
{
	const FETCH_USER = 0x01;
	const FETCH_USER_PROFILE = 0x02;
	const FETCH_USER_OPTIONS = 0x04;
	const FETCH_THREAD = 0x08;
	const FETCH_FORUM = 0x10;
	const FETCH_DELETION_LOG = 0x20;
	const FETCH_BBCODE_CACHE = 0x40;
	const FETCH_NODE_PERMS = 0x80;
	const FETCH_SESSION_ACTIVITY = 0x100;

	/**
	 * Gets the named post.
	 *
	 * @param integer $postId
	 *
	 * @return array|false
	 */
	public function getPostById($postId, array $fetchOptions = array())
	{
		$joinOptions = $this->preparePostJoinOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT post.*
				' . $joinOptions['selectFields'] . '
			FROM xf_post AS post
			' . $joinOptions['joinTables'] . '
			WHERE post.post_id = ?
		', $postId);
	}

	/**
	 * Gets the specified posts.
	 *
	 * @param array $postIds
	 * @param array $fetchOptions Collection of options that relate to fetching
	 *
	 * @return array Format: [post id] => info
	 */
	public function getPostsByIds(array $postIds, array $fetchOptions = array())
	{
		if (!$postIds)
		{
			return array();
		}

		$joinOptions = $this->preparePostJoinOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT
				post.*
				' . $joinOptions['selectFields'] . '
			FROM xf_post AS post' . $joinOptions['joinTables'] . '
			WHERE post.post_id IN (' . $this->_getDb()->quote($postIds) . ')
		', 'post_id');
	}

	/**
	 * Checks the 'join' key of the incoming array for the presence of the FETCH_x bitfields in this class
	 * and returns SQL snippets to join the specified tables if required
	 *
	 * @param array $fetchOptions containing a 'join' integer key build from this class's FETCH_x bitfields
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys. Example: selectFields = ', user.*, foo.title'; joinTables = ' INNER JOIN foo ON (foo.id = other.id) '
	 */
	public function preparePostJoinOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		$db = $this->_getDb();

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_THREAD || $fetchOptions['join'] & self::FETCH_FORUM || $fetchOptions['join'] & self::FETCH_NODE_PERMS)
			{
				$selectFields .= ',
					thread.*, thread.user_id AS thread_user_id, thread.username AS thread_username,
					thread.post_date AS thread_post_date,
					post.user_id, post.username, post.post_date'; // overwrite thread.post_date with post.post_date
				$joinTables .= '
					INNER JOIN xf_thread AS thread ON
						(thread.thread_id = post.thread_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_FORUM)
			{
				$selectFields .= ',
					node.title AS node_title, node.node_name';
				$joinTables .= '
					INNER JOIN xf_node AS node ON
						(node.node_id = thread.node_id)';
			}


			if (XenForo_Application::getOptions()->cacheBbCodeTree && $fetchOptions['join'] & self::FETCH_BBCODE_CACHE)
			{
				$selectFields .= ',
					bb_code_parse_cache.parse_tree AS message_parsed, bb_code_parse_cache.cache_version AS message_cache_version';
				$joinTables .= '
					LEFT JOIN xf_bb_code_parse_cache AS bb_code_parse_cache ON
						(bb_code_parse_cache.content_type = \'post\' AND bb_code_parse_cache.content_id = post.post_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_USER || $fetchOptions['join'] & self::FETCH_NODE_PERMS)
			{
				$selectFields .= ',
					user.*, IF(user.username IS NULL, post.username, user.username) AS username';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = post.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_USER_PROFILE)
			{
				$selectFields .= ',
					user_profile.*,
					user_privacy.*';
				$joinTables .= '
					LEFT JOIN xf_user_profile AS user_profile ON
						(user_profile.user_id = post.user_id)
					LEFT JOIN xf_user_privacy AS user_privacy ON
						(user_privacy.user_id = post.user_id)';
			}

			if (XenForo_Application::getOptions()->cacheBbCodeTree && $fetchOptions['join'] & self::FETCH_USER_PROFILE && $fetchOptions['join'] & self::FETCH_BBCODE_CACHE)
			{
				$selectFields .= ',
					signature_parse_cache.parse_tree AS signature_parsed, bb_code_parse_cache.cache_version AS signature_cache_version';
				$joinTables .= '
					LEFT JOIN xf_bb_code_parse_cache AS signature_parse_cache ON
						(signature_parse_cache.content_type = \'signature\' AND signature_parse_cache.content_id = post.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_USER_OPTIONS)
			{
				$selectFields .= ',
					user_option.*';
				$joinTables .= '
					LEFT JOIN xf_user_option AS user_option ON
						(user_option.user_id = post.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_NODE_PERMS)
			{
				$selectFields .= ',
					permission.cache_value AS node_permission_cache';
				$joinTables .= '
					LEFT JOIN xf_permission_cache_content AS permission
						ON (permission.permission_combination_id = user.permission_combination_id
							AND permission.content_type = \'node\'
							AND permission.content_id = thread.node_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_DELETION_LOG)
			{
				$selectFields .= ',
					deletion_log.delete_date, deletion_log.delete_reason,
					deletion_log.delete_user_id, deletion_log.delete_username';
				$joinTables .= '
					LEFT JOIN xf_deletion_log AS deletion_log ON
						(deletion_log.content_type = \'post\' AND deletion_log.content_id = post.post_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_SESSION_ACTIVITY)
			{
				$selectFields .= ',
					session_activity.view_date AS last_view_date';
				$joinTables .= '
					LEFT JOIN xf_session_activity AS session_activity ON
						(post.user_id > 0 AND session_activity.user_id = post.user_id)';
			}
		}

		if (!empty($fetchOptions['permissionCombinationId']))
		{
			$selectFields .= ',
				permission.cache_value AS node_permission_cache';
			$joinTables .= '
				LEFT JOIN xf_permission_cache_content AS permission
					ON (permission.permission_combination_id = ' . $db->quote($fetchOptions['permissionCombinationId']) . '
						AND permission.content_type = \'node\'
						AND permission.content_id = thread.node_id)';
		}

		if (isset($fetchOptions['likeUserId']))
		{
			if (empty($fetchOptions['likeUserId']))
			{
				$selectFields .= ',
					0 AS like_date';
			}
			else
			{
				$selectFields .= ',
					liked_content.like_date';
				$joinTables .= '
					LEFT JOIN xf_liked_content AS liked_content
						ON (liked_content.content_type = \'post\'
							AND liked_content.content_id = post.post_id
							AND liked_content.like_user_id = ' .$db->quote($fetchOptions['likeUserId']) . ')';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Returns all posts for a specified thread. Fetch options may limit
	 * posts returned.
	 *
	 * @param integer $threadId
	 * @param array $fetchOptions Collection of options that relate to fetching
	 *
	 * @return array
	 */
	public function getPostsInThread($threadId, array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$stateLimit = $this->prepareStateLimitFromConditions($fetchOptions, 'post');
		$joinOptions = $this->preparePostJoinOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT post.*
				' . $joinOptions['selectFields'] . '
			FROM xf_post AS post
			' . $joinOptions['joinTables'] . '
			WHERE post.thread_id = ?
				' . $this->addPositionLimit('post', $limitOptions['limit'], $limitOptions['offset']) . '
				AND (' . $stateLimit . ')
			ORDER BY post.position ASC, post.post_date ASC
		', 'post_id', $threadId);
	}

	/**
	 * Gets simple information about all posts in a thread. This does not
	 * include the actual contents of a message unless specifically requested.
	 *
	 * @param integer $threadId
	 * @param boolean $includeMessage If true, includes message contents
	 *
	 * @return array Format: [post id] => info
	 */
	public function getPostsInThreadSimple($threadId, $includeMessage = false)
	{
		return $this->fetchAllKeyed('
			SELECT post_id, thread_id, user_id, message_state, likes, post_date
				' . ($includeMessage ? ', message' : '') . '
			FROM xf_post
			WHERE thread_id = ?
			ORDER BY position ASC, post_date ASC
		', 'post_id', $threadId);
	}

	public function getPostIdsInThread($threadId, $ordered = true)
	{
		return $this->_getDb()->fetchCol('
			SELECT post_id
			FROM xf_post
			WHERE thread_id = ?
			' . ($ordered ? 'ORDER BY position ASC, post_date ASC' : '') . '
		', $threadId);
	}

	/**
	 * Fetches basic information about all posts made by $userId,
	 * except those in threads started by $userId
	 *
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getPostsByUserInOthersThreads($userId)
	{
		return $this->fetchAllKeyed('
			SELECT post.*
			FROM xf_post AS post
			INNER JOIN xf_thread AS thread ON
				(thread.thread_id = post.thread_id)
			WHERE post.user_id = ?
				AND thread.user_id <> ?
		', 'post_id', array($userId, $userId));
	}

	/**
	 * Gets the latest post in the specified thread.
	 *
	 * @param integer $threadId
	 * @param array $fetchOptions Collection of options that relate to fetching
	 *
	 * @return array|false
	 */
	public function getLastPostInThread($threadId, array $fetchOptions = array())
	{
		$stateLimit = $this->prepareStateLimitFromConditions($fetchOptions, 'post');
		$joinOptions = $this->preparePostJoinOptions($fetchOptions);

		$db = $this->_getDb();
		return $db->fetchRow($db->limit('
			SELECT post.*
				' . $joinOptions['selectFields'] . '
			FROM xf_post AS post
			' . $joinOptions['joinTables'] . '
			WHERE post.thread_id = ?
				AND (' . $stateLimit . ')
			ORDER BY post.post_date DESC
		', 1), $threadId);
	}

	/**
	 * Gets the next post in a thread, post after the specified date. This is useful
	 * for finding the first unread post in a thread, for example.
	 *
	 * @param integer $threadId
	 * @param integer $postDate Finds first post posted after this
	 * @param array $fetchOptions Collection of options that relate to fetching
	 *
	 * @return array|false
	 */
	public function getNextPostInThread($threadId, $postDate, array $fetchOptions = array(), array $ignoredUserIds = array())
	{
		$stateLimit = $this->prepareStateLimitFromConditions($fetchOptions);

		$db = $this->_getDb();

		return $db->fetchRow($db->limit('
			SELECT *
			FROM xf_post
			WHERE thread_id = ?
				AND post_date > ?
				AND (' . $stateLimit . ')
				' . ($ignoredUserIds ? 'AND user_id NOT IN(' . $db->quote($ignoredUserIds) . ')' : '') . '
			ORDER BY post_date
		', 1), array($threadId, $postDate));
	}

	/**
	 * Returns the ID of the next post in the given thread after the specified position
	 *
	 * @param integer $threadId
	 * @param integer $position
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getNextPostInThreadByPosition($threadId, $position, array $fetchOptions = array())
	{
		$stateLimit = $this->prepareStateLimitFromConditions($fetchOptions);

		return $this->_getDb()->fetchRow($this->limitQueryResults('
			SELECT *
			FROM xf_post
			WHERE thread_id = ?
				AND position = ?
				AND (' . $stateLimit . ')
			ORDER BY post_date
		', 1), array($threadId, $position + 1));
	}

	/**
	 * Returns the newest posts for a specified thread, after the specified date.
	 * Posts are returned newest first.
	 *
	 * @param integer $threadId
	 * @param integer $postDate
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getNewestPostsInThreadAfterDate($threadId, $postDate, array $fetchOptions = array())
	{
		$stateLimit = $this->prepareStateLimitFromConditions($fetchOptions, 'post');
		$joinOptions = $this->preparePostJoinOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT post.*
					' . $joinOptions['selectFields'] . '
				FROM xf_post AS post
				' . $joinOptions['joinTables'] . '
				WHERE post.thread_id = ?
					AND post.post_date > ?
					AND (' . $stateLimit . ')
				ORDER BY post.post_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'post_id', array($threadId, $postDate));
	}

	/**
	 * Counts the number of visible posts in the specified thread. This is the reply
	 * count + 1.
	 *
	 * @param integer $threadId
	 *
	 * @return integer
	 */
	public function countVisiblePostsInThread($threadId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_post
			WHERE thread_id = ?
				AND message_state = \'visible\'
		', $threadId);
	}

	/**
	 * Gets post IDs in the specified range. The IDs returned will be those immediately
	 * after the "start" value (not including the start), up to the specified limit.
	 *
	 * @param integer $start IDs greater than this will be returned
	 * @param integer $limit Number of posts to return
	 *
	 * @return array List of IDs
	 */
	public function getPostIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT post_id
			FROM xf_post
			WHERE post_id > ?
			ORDER BY post_id
		', $limit), $start);
	}

	/**
	 * Gets the attachments that belong to the given posts, and merges them in with
	 * their parent post (in the attachments key). The attachments key will not be
	 * set if no attachments are found for the post.
	 *
	 * @param array $posts
	 *
	 * @return array Posts, with attachments added where necessary
	 */
	public function getAndMergeAttachmentsIntoPosts(array $posts)
	{
		$postIds = array();

		foreach ($posts AS $postId => $post)
		{
			if ($post['attach_count'])
			{
				$postIds[] = $postId;
			}
		}

		if ($postIds)
		{
			$attachmentModel = $this->_getAttachmentModel();

			$attachments = $attachmentModel->getAttachmentsByContentIds('post', $postIds);

			foreach ($attachments AS $attachment)
			{
				$posts[$attachment['content_id']]['attachments'][$attachment['attachment_id']] = $attachmentModel->prepareAttachment($attachment);
			}
		}

		return $posts;
	}

	/**
	 * Determines if the post can be viewed with the given permissions.
	 * This does not check thread/forum viewing permissions fully.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewPost(array $post, array $thread, array $forum, &$errorPhraseKey = '',
		array $nodePermissions = null, array $viewingUser = null
	)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!XenForo_Permission::hasContentPermission($nodePermissions, 'view'))
		{
			return false;
		}

		if ($this->isModerated($post))
		{
			if (!$this->_getThreadModel()->canViewModeratedPosts($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser))
			{
				if (!$viewingUser['user_id'] || $viewingUser['user_id'] != $post['user_id'])
				{
					return false;
				}
			}
		}
		else if ($this->isDeleted($post))
		{
			if (!$this->_getThreadModel()->canViewDeletedPosts($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines if the post can be viewed with the given permissions.
	 * TThis will check that any parent container can be viewed as well.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewPostAndContainer(array $post, array $thread, array $forum, &$errorPhraseKey = '',
		array $nodePermissions = null, array $viewingUser = null
	)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$this->_getThreadModel()->canViewThreadAndContainer($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser))
		{
			return false;
		}

		return $this->canViewPost($post, $thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser);
	}

	/**
	 * Determines if an attachment on this post can be viewed.
	 * This does not check post viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewAttachmentOnPost(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		return $this->_getThreadModel()->canViewAttachmentsInThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser);
	}

	/**
	 * Determines if the post can be edited with the given permissions.
	 * This does not check post viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditPost(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (!$thread['discussion_open']
			&& !$this->_getThreadModel()->canLockUnlockThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser))
		{
			$errorPhraseKey = 'you_may_not_perform_this_action_because_discussion_is_closed';
			return false;
		}

		if (XenForo_Permission::hasContentPermission($nodePermissions, 'editAnyPost'))
		{
			return true;
		}

		if ($post['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'editOwnPost'))
		{
			$editLimit = XenForo_Permission::hasContentPermission($nodePermissions, 'editOwnPostTimeLimit');

			if ($editLimit != -1 && (!$editLimit || $post['post_date'] < XenForo_Application::$time - 60 * $editLimit))
			{
				$errorPhraseKey = array('message_edit_time_limit_expired', 'minutes' => $editLimit);
				return false;
			}

			if (empty($forum['allow_posting']))
			{
				$errorPhraseKey = 'you_may_not_perform_this_action_because_forum_does_not_allow_posting';
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Determines if the post's edit history can be viewed with the given permissions.
	 * This does not check post viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewPostHistory(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (!XenForo_Application::getOptions()->editHistory['enabled'])
		{
			return false;
		}

		if (XenForo_Permission::hasContentPermission($nodePermissions, 'editAnyPost'))
		{
			return true;
		}

		return false;
	}

	/**
	 * Determines if the silent edit-related options can be set with the given permissions.
	 * This does not check post editing/viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canControlSilentEdit(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		return (
			$viewingUser['user_id']
			&& XenForo_Permission::hasContentPermission($nodePermissions, 'editAnyPost')
		);
	}

	/**
	 * Determines if the post can be deleted with the given permissions.
	 * This does not check post viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $deleteType The type of deletion requested (soft or hard)
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canDeletePost(array $post, array $thread, array $forum, $deleteType = 'soft', &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($deleteType != 'soft' && !XenForo_Permission::hasContentPermission($nodePermissions, 'hardDeleteAnyPost'))
		{
			// fail immediately on hard delete without permission
			return false;
		}

		if (!$thread['discussion_open']
			&& !$this->_getThreadModel()->canLockUnlockThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser))
		{
			$errorPhraseKey = 'you_may_not_perform_this_action_because_discussion_is_closed';
			return false;
		}

		if ($post['post_id'] == $thread['first_post_id'])
		{
			// would delete thread, so use that permission
			return $this->_getThreadModel()->canDeleteThread(
				$thread, $forum, $deleteType, $errorPhraseKey, $nodePermissions, $viewingUser
			);
		}
		else if (XenForo_Permission::hasContentPermission($nodePermissions, 'deleteAnyPost'))
		{
			return true;
		}
		else if ($post['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'deleteOwnPost'))
		{
			$editLimit = XenForo_Permission::hasContentPermission($nodePermissions, 'editOwnPostTimeLimit');

			if ($editLimit != -1 && (!$editLimit || $post['post_date'] < XenForo_Application::$time - 60 * $editLimit))
			{
				$errorPhraseKey = array('message_edit_time_limit_expired', 'minutes' => $editLimit);
				return false;
			}

			if (empty($forum['allow_posting']))
			{
				$errorPhraseKey = 'you_may_not_perform_this_action_because_forum_does_not_allow_posting';
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Determines if the post can be undeleted with the given permissions.
	 * This does not check post viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canUndeletePost(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'undelete'));
	}

	/**
	 * Determines if the post can be approved or unapproved with the given permissions.
	 * This does not check post viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canApproveUnapprovePost(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'approveUnapprove'));
	}

	/**
	 * Determines if the post can be liked with the given permissions.
	 * This does not check post viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canLikePost(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($post['message_state'] != 'visible')
		{
			return false;
		}

		if ($post['user_id'] == $viewingUser['user_id'])
		{
			$errorPhraseKey = 'liking_own_content_cheating';
			return false;
		}

		return XenForo_Permission::hasContentPermission($nodePermissions, 'like');
	}

	/**
	 * Determines if the post can be moved to a new thread with the given permissions.
	 * This does not check post viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMovePost(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread'));
	}

	/**
	 * Determines if the post can be copied to a new thread with the given permissions.
	 * This does not check post viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canCopyPost(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread'));
	}

	/**
	 * Determines if the post can be merged with another with the given permissions.
	 * This does not check post viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMergePost(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread'));
	}

	/**
	 * Determines if the post can be warning with the given permissions.
	 * This does not check post viewing permissions.
	 *
	 * @param array $post Info about the post
	 * @param array $thread Info about the thread this post is in
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canWarnPost(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		if ($post['warning_id'] || empty($post['user_id']))
		{
			return false;
		}

		if (!empty($post['is_admin']) || !empty($post['is_moderator']))
		{
			return false;
		}

		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'warn'));
	}

	/**
	 * Determines if the specified user can view IP addresses
	 *
	 * @param array $post
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewIps(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		return $this->_getUserModel()->canViewIps($errorPhraseKey, $viewingUser);
	}

	/**
	 * Checks that the viewing user may report the specified post
	 *
	 * @param array $post
	 * @param array $thread
	 * @param array $forum
	 * @param string
	 * @param boolean $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canReportPost(array $post, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null ,array $viewingUser = null)
	{
		return $this->_getUserModel()->canReportContent($errorPhraseKey, $viewingUser);
	}

	/**
	 * Adds the canInlineMod value to the provided post and returns the
	 * specific list of inline mod actions that are allowed on this post.
	 *
	 * @param array $post Post info
	 * @param array $thread Thread the post is in
	 * @param array $forum Forum the thread/post is in
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return array List of allowed inline mod actions, format: [action] => true
	 */
	public function addInlineModOptionToPost(array &$post, array $thread, array $forum, array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		$postModOptions = array();
		$canInlineMod = ($viewingUser['user_id'] && (
			XenForo_Permission::hasContentPermission($nodePermissions, 'deleteAnyPost')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'undelete')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'approveUnapprove')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread')
		));

		if ($canInlineMod)
		{
			if ($this->canDeletePost($post, $thread, $forum, 'soft', $null, $nodePermissions, $viewingUser))
			{
				$postModOptions['delete'] = true;
			}
			if ($this->canUndeletePost($post, $thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$postModOptions['undelete'] = true;
			}
			if ($this->canApproveUnapprovePost($post, $thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$postModOptions['approve'] = true;
				$postModOptions['unapprove'] = true;
			}
			if ($this->canMovePost($post, $thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$postModOptions['move'] = true;
			}
			if ($this->canCopyPost($post, $thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$postModOptions['copy'] = true;
			}
			if ($this->canMergePost($post, $thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$postModOptions['merge'] = true;
			}
		}

		$post['canInlineMod'] = (count($postModOptions) > 0);

		return $postModOptions;
	}

	/**
	 * Gets the message state for a newly inserted post by the viewing user.
	 *
	 * @param array $thread Thread info, may be empty for a new thread
	 * @param array $forum
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return string Message state (visible, moderated, deleted)
	 */
	public function getPostInsertMessageState(array $thread, array $forum, array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($forum['node_id'], $viewingUser, $nodePermissions);

		if ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'approveUnapprove'))
		{
			return 'visible';
		}
		else if (XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'followModerationRules'))
		{
			if (empty($thread['thread_id']))
			{
				// new thread
				return (empty($forum['moderate_threads']) ? 'visible' : 'moderated');
			}
			else
			{
				// reply
				return (empty($forum['moderate_replies']) ? 'visible' : 'moderated');
			}
		}
		else
		{
			return 'moderated';
		}
	}

	/**
	 * Prepares a post for display, generally within the context of a thread.
	 *
	 * @param array $post Post to prepare
	 * @param array $thread Thread post is in
	 * @param array $forum Forum thread/post is in
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return array Prepared version of post
	 */
	public function preparePost(array $post, array $thread, array $forum, array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!isset($post['canInlineMod']))
		{
			$this->addInlineModOptionToPost($post, $thread, $forum, $nodePermissions, $viewingUser);
		}

		$post['canEdit'] = $this->canEditPost($post, $thread, $forum, $null, $nodePermissions, $viewingUser);
		$post['canViewHistory'] = $this->canViewPostHistory($post, $thread, $forum, $null, $nodePermissions, $viewingUser);
		$post['canDelete'] = $this->canDeletePost($post, $thread, $forum, 'soft', $null, $nodePermissions, $viewingUser);
		$post['canLike'] = $this->canLikePost($post, $thread, $forum, $null, $nodePermissions, $viewingUser);
		$post['canReport'] = $this->canReportPost($post, $thread, $forum, $null, $nodePermissions, $viewingUser);
		$post['canWarn'] = $this->canWarnPost($post, $thread, $forum, $null, $nodePermissions, $viewingUser);
		$post['isFirst'] = ($post['post_id'] == $thread['first_post_id']);
		$post['isDeleted'] = $this->isDeleted($post);
		$post['isModerated'] = $this->isModerated($post);

		if (isset($thread['thread_read_date']) || isset($forum['forum_read_date']))
		{
			$readOptions = array(0);
			if (isset($thread['thread_read_date'])) { $readOptions[] = $thread['thread_read_date']; }
			if (isset($forum['forum_read_date'])) { $readOptions[] = $forum['forum_read_date']; }

			$post['isNew'] = (max($readOptions) < $post['post_date']);
		}
		else
		{
			$post['isNew'] = false;
		}

		$post['isOnline'] = null;
		if (array_key_exists('last_view_date', $post)
			&& $this->_getUserModel()->canViewUserOnlineStatus($post, $null, $viewingUser)
		)
		{
			$onlineCutOff = XenForo_Application::$time - XenForo_Application::getOptions()->onlineStatusTimeout * 60;
			$post['isOnline'] = (
				$post['user_id'] == $viewingUser['user_id']
				|| $post['last_view_date'] > $onlineCutOff
			);
		}

		if (array_key_exists('user_group_id', $post))
		{
			$userModel = $this->_getUserModel();

			$post = $userModel->prepareUser($post);
			$post['canCleanSpam'] = (
				!empty($post['user_group_id'])
				&& XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'cleanSpam')
				&& $userModel->couldBeSpammer($post)
			);
		}

		if (!empty($post['delete_date']))
		{
			$post['deleteInfo'] = array(
				'user_id' => $post['delete_user_id'],
				'username' => $post['delete_username'],
				'date' => $post['delete_date'],
				'reason' => $post['delete_reason'],
			);
		}

		if ($post['likes'])
		{
			$post['likeUsers'] = unserialize($post['like_users']);
		}

		return $post;
	}

	/**
	 * Gets permission-based options that apply to post fetching functions.
	 *
	 * @param array $thread Thread the posts will belong to
	 * @param array $forum Forum the thread belongs to
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return array Keys: deleted/moderated (both booleans)
	 */
	public function getPermissionBasedPostFetchOptions(array $thread, array $forum, array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (XenForo_Permission::hasContentPermission($nodePermissions, 'viewModerated'))
		{
			$viewModerated = true;
		}
		else if ($viewingUser['user_id'])
		{
			$viewModerated = $viewingUser['user_id'];
		}
		else
		{
			$viewModerated = false;
		}

		return array(
			'deleted' => XenForo_Permission::hasContentPermission($nodePermissions, 'viewDeleted'),
			'moderated' => $viewModerated
		);
	}

	/**
	 * Helper to delete the specified post, via a soft or hard delete.
	 *
	 * @param integer $postId ID of the post to delete
	 * @param string $deleteType Type of deletion (soft or hard)
	 * @param array $options Deletion options.
	 * @param array $forum The forum containing this post
	 *
	 * @return XenForo_DataWriter_DiscussionMessage_Post The DW used to delete the post
	 */
	public function deletePost($postId, $deleteType, array $options = array(), array $forum = null)
	{
		$options = array_merge(array(
			'reason' => '',
			'authorAlert' => false,
			'authorAlertReason' => ''
		), $options);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
		$dw->setExistingData($postId);

		if (empty($forum))
		{
			$forum = $this->getModelFromCache('XenForo_Model_Forum')->getForumByThreadId($dw->get('thread_id'));
		}

		$dw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);

		if ($deleteType == 'hard')
		{
			$dw->delete();
		}
		else
		{
			$dw->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_DELETE_REASON, $options['reason']);
			$dw->set('message_state', 'deleted');
			$dw->save();
		}

		if ($options['authorAlert'])
		{
			$thread = $this->_getThreadModel()->getThreadById($dw->get('thread_id'));
			if ($thread)
			{
				$this->sendModeratorActionAlert(
					'delete', $dw->getMergedData(), $thread, $options['authorAlertReason']
				);
			}
		}

		return $dw;
	}

	/**
	 * From a list of post IDs, gets info about the posts, their threads, and
	 * the forums the threads are in.
	 *
	 * If a permission combination ID is passed, the forums will retrieve permission info.
	 *
	 * @param array $postIds List of post IDs
	 * @param integer $permissionCombinationId Permission combination ID that will be retrieved with the forums.
	 *
	 * @return array Format: [0] => list of posts, [1] => list of threads, [2] => list of forums
	 */
	public function getPostsAndParentData(array $postIds, $permissionCombinationId = null)
	{
		if ($permissionCombinationId === null)
		{
			$visitor = XenForo_Visitor::getInstance();
			$permissionCombinationId = $visitor['permission_combination_id'];
		}

		$threads = array();
		$forums = array();
		$posts = $this->getPostsByIds($postIds);

		if ($posts)
		{
			$threadIds = array();
			foreach ($posts AS $post)
			{
				$threadIds[$post['post_id']] = $post['thread_id'];
			}

			list($threads, $forums) = $this->_getThreadModel()->getThreadsAndParentData($threadIds, $permissionCombinationId);

			foreach ($posts AS $postId => $post)
			{
				if (!isset($threads[$post['thread_id']]))
				{
					unset($posts[$postId]);
				}
			}
		}

		return array($posts, $threads, $forums);
	}

	/**
	 * Recalculates the position of all posts in the thread, and returns info relating
	 * to the thread: firstPostId, firstPostDate, firstPostState, lastPostId, visibleCount.
	 *
	 * @param integer $threadId
	 *
	 * @return array
	 */
	public function recalculatePostPositionsInThread($threadId)
	{
		$db = $this->_getDb();

		$postResults = $this->_getDb()->query('
			SELECT post_id, user_id, username, post_date, message_state, position
			FROM xf_post
			WHERE thread_id = ?
			ORDER BY post_date, post_id
		', $threadId);

		$position = 0;
		$firstPost = array();
		$lastPost = array();
		$userPosts = array();
		$updatePositions = array();

		while ($post = $postResults->fetch())
		{
			if ($post['position'] != $position)
			{
				$updatePositions[$post['post_id']] = $position;
			}

			if (!$firstPost)
			{
				$firstPost = $post;
			}

			if ($post['message_state'] == 'visible')
			{
				$lastPost = $post;
				$position++;

				if ($post['user_id'])
				{
					if (isset($userPosts[$post['user_id']]))
					{
						$userPosts[$post['user_id']]++;
					}
					else
					{
						$userPosts[$post['user_id']] = 1;
					}
				}
			}
		}


		if (!$firstPost)
		{
			return array(
				'firstPostId' => 0,
				'lastPostId' => 0,
				'visibleCount' => 0,
				'userPosts' => array()
			);
		}

		if ($updatePositions)
		{
			XenForo_Db::beginTransaction($db);

			foreach ($updatePositions AS $postId => $updatePosition)
			{
				$db->update('xf_post', array('position' => $updatePosition), 'post_id = ' . $db->quote($postId));
			}

			XenForo_Db::commit($db);
		}

		if (!$lastPost)
		{
			$lastPost = $firstPost;
		}

		return array(
			'firstPostId' => $firstPost['post_id'],
			'firstPostDate' => $firstPost['post_date'],
			'firstPostState' => $firstPost['message_state'],
			'firstPost' => $firstPost,

			'lastPostId' => $lastPost['post_id'],
			'lastPost' => $lastPost,

			'visibleCount' => $position,
			'userPosts' => $userPosts
		);
	}

	/**
	 * Moves the specified posts (in the given threads) to a new thread. The
	 * new thread will be created in this function if necessary.
	 *
	 * @param array $posts
	 * @param array $sourceThreads
	 * @param array $newThread Information about the new thread or target thread
	 * @param array $options Options to control behavior
	 *
	 * @return array|false New thread ID or false
	 */
	public function movePosts(array $posts, array $sourceThreads, array $newThread, array $options = array())
	{
		return $this->_moveOrCopyPosts('move', $posts, $sourceThreads, $newThread, $options);
	}

	/**
	 * Moves the specified posts (in the given threads) to a new thread. The
	 * new thread will be created in this function if necessary.
	 *
	 * @param array $posts
	 * @param array $sourceThreads
	 * @param array $targetThread Information about the new thread or target thread
	 * @param array $options Options to control behavior
	 *
	 * @return array|false New thread ID or false
	 */
	public function copyPosts(array $posts, array $sourceThreads, array $targetThread, array $options = array())
	{
		return $this->_moveOrCopyPosts('copy', $posts, $sourceThreads, $targetThread, $options);
	}

	/**
	 * Implements post move/copy logic. If the target thread doesn't have a thread ID,
	 * the thread will be created automatically.
	 *
	 * @param string $action "move" or "copy"
	 * @param array $posts
	 * @param array $sourceThreads
	 * @param array $targetThread
	 * @param array $options
	 *
	 * @return array|bool Target thread info on success, false otherwise
	 *
	 * @throws XenForo_Exception
	 */
	protected function _moveOrCopyPosts($action, array $posts, array $sourceThreads, array $targetThread, array $options = array())
	{
		switch ($action)
		{
			case 'move':
			case 'copy':
				break;

			default:
				throw new XenForo_Exception("Unknown post move/copy action $action");
		}

		if (!$posts)
		{
			return false;
		}

		$forum = $this->getModelFromCache('XenForo_Model_Forum')->getForumById($targetThread['node_id']);
		if (!$forum)
		{
			return false;
		}

		$firstPostId = 0;
		$firstPostDate = PHP_INT_MAX;
		$sourceThreadIds = array();

		$options = array_merge(array(
			'log' => true,
			'authorAlert' => false,
			'authorAlertReason' => ''
		), $options);

		foreach ($posts AS $postId => $post)
		{
			$sourceThreadIds[$post['thread_id']] = true;

			if ($post['post_date'] < $firstPostDate
				|| ($post['post_date'] == $firstPostDate && $post['post_id'] < $firstPostId)
			)
			{
				$firstPostId = $postId;
				$firstPostDate = $post['post_date'];
			}
		}

		if (!isset($posts[$firstPostId]))
		{
			return false;
		}

		$firstPost = $posts[$firstPostId];

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$targetExisting = !empty($targetThread['thread_id']);

		if (!$targetExisting)
		{
			$targetThread['post_date'] = $firstPost['post_date'];
			$targetThread['user_id'] = $firstPost['user_id'];
			$targetThread['username'] = $firstPost['username'];
			$targetThread['discussion_state'] = $firstPost['message_state'];

			$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
			$threadDw->setOption(XenForo_DataWriter_Discussion::OPTION_REQUIRE_INSERT_FIRST_MESSAGE, false);
			$threadDw->bulkSet($targetThread);
			$threadDw->save();
			$targetThread = $threadDw->getMergedData();
		}

		if ($action == 'copy')
		{
			$newPostIds = array();
			foreach ($posts AS $post)
			{
				$newPost = $this->_copyPost($post, $targetThread, $forum);
				$newPostIds[] = $newPost['post_id'];

				if ($post['post_id'] == $firstPostId)
				{
					$firstPost = $newPost;
					$firstPostId = $newPost['post_id'];
				}
			}
		}
		else
		{
			$postIds = array_keys($posts);
			$quotedIds = $db->quote($postIds);

			$db->update('xf_post',
				array('thread_id' => $targetThread['thread_id']),
				'post_id IN (' . $quotedIds . ')'
			);

			// when moving posts, remove alerts for these posts because
			// they'll reflect the new thread instead which is just confusing
			$db->query("
				DELETE FROM xf_user_alert
				WHERE content_type = 'post'
					AND content_id IN ($quotedIds)
					AND action IN ('insert', 'insert_attachment')
			");

			if (!$targetExisting)
			{
				// moving to a new thread, first post shouldn't be in news feed
				$db->query("
					DELETE FROM xf_news_feed
					WHERE content_type = 'post' AND content_id = ?
				", $firstPostId);

				$dw = XenForo_DataWriter::create('XenForo_DataWriter_NewsFeed');
				$dw->set('user_id', $firstPost['user_id']);
				$dw->set('username', $firstPost['username']);
				$dw->set('content_type', 'thread');
				$dw->set('content_id', $targetThread['thread_id']);
				$dw->set('action', 'insert');
				$dw->set('extra_data', null);
				$dw->set('event_date', $firstPost['post_date']);
				$dw->save();
			}

			$newPostIds = $postIds;
		}

		if ($firstPost['post_date'] <= $targetThread['post_date'])
		{
			$firstPostDw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
			$firstPostDw->setExistingData($firstPostId);
			$firstPostDw->set('message_state', 'visible');
			$firstPostDw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
			$firstPostDw->save();
		}

		$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
		$threadDw->setExistingData($targetThread, true);
		$threadDw->rebuildDiscussion();
		$threadDw->save();

		$sourceForums = $this->getModelFromCache('XenForo_Model_Forum')->getForumsByThreadIds($sourceThreadIds);
		$newThreadUrl = XenForo_Link::buildPublicLink('threads', $targetThread);

		foreach ($sourceThreads AS $sourceThread)
		{
			$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
			$threadDw->setExistingData($sourceThread, true);
			if (isset($sourceForums[$sourceThread['node_id']]))
			{
				$threadDw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $sourceForums[$sourceThread['node_id']]);
			}

			$threadValid = $threadDw->rebuildDiscussion();
			if ($threadValid)
			{
				$threadDw->save();

				if ($options['log'])
				{
					XenForo_Model_Log::logModeratorAction(
						'thread', $sourceThread, 'post_' . $action . '_source', array('url' => $newThreadUrl, 'title' => $targetThread['title'])
					);
				}
			}
			else
			{
				// all posts removed -> delete
				$threadDw->delete();
			}
		}

		$alerted = array();
		foreach ($posts AS $post)
		{
			if (
				$post['message_state'] == 'visible'
				&& $options['authorAlert']
				&& !isset($alerted[$post['user_id']][$post['thread_id']])
				&& isset($sourceThreads[$post['thread_id']])
			)
			{
				$alerted[$post['user_id']][$post['thread_id']] = true;
				$this->sendModeratorActionAlert(
					$action, $post, $sourceThreads[$post['thread_id']], $options['authorAlertReason'],
					array(
						'targetTitle' => $targetThread['title'],
						'targetLink' => XenForo_Link::buildPublicLink('threads', $targetThread)
					)
				);
			}
		}

		if ($options['log'])
		{
			$modKey = 'post_' . $action . '_target' . ($targetExisting ? '_existing' : '');

			XenForo_Model_Log::logModeratorAction(
				'thread', $targetThread, $modKey, array('ids' => implode(', ', $newPostIds))
			);
		}

		if ($newPostIds)
		{
			XenForo_Application::defer('SearchIndexPartial', array('contentType' => 'post', 'contentIds' => $newPostIds));
		}

		XenForo_Db::commit($db);

		return $targetThread;
	}

	/**
	 * Handles the actual copying of a post and all related information
	 *
	 * @param array $post
	 * @param array $targetThread
	 * @param array $forum
	 *
	 * @return array New post content
	 */
	protected function _copyPost(array $post, array $targetThread, array $forum)
	{
		$db = $this->_getDb();

		$oldPostId = $post['post_id'];

		$newPost = $post;
		unset($newPost['post_id']);
		$newPost['thread_id'] = $targetThread['thread_id'];
		$newPost['likes'] = 0;
		$newPost['like_users'] = 'a:0:{}';
		$newPost['warning_id'] = 0;
		$newPost['warning_message'] = '';
		$newPost['last_edit_date'] = 0;
		$newPost['last_edit_user_id'] = 0;
		$newPost['edit_count'] = 0;

		$db->insert('xf_post', $newPost);
		$postId = $db->lastInsertId();
		$newPost['post_id'] = $postId;

		if ($newPost['attach_count'])
		{
			$message = $newPost['message'];

			$attachments = $db->fetchAll("
				SELECT *
				FROM xf_attachment
				WHERE content_type = 'post'
					AND content_id = ?
			", $oldPostId);
			foreach ($attachments AS $attachment)
			{
				$oldAttachmentId = $attachment['attachment_id'];

				unset($attachment['attachment_id']);
				$attachment['content_id'] = $postId;
				$attachment['view_count'] = 0;

				$db->insert('xf_attachment', $attachment);
				$newAttachmentId = $db->lastInsertId();
				$db->query("
					UPDATE xf_attachment_data
					SET attach_count = attach_count + 1
					WHERE data_id = ?
				", $attachment['data_id']);

				$message = preg_replace(
					'#(\[attach(=[^]]+)?\])' . $oldAttachmentId . '(\[/attach\])#i',
					'${1}' . $newAttachmentId . '${3}',
					$message
				);
			}

			$db->query("
				UPDATE xf_post
				SET message = ?
				WHERE post_id = ?
			", array($message, $newPost['post_id']));
		}

		if ($newPost['message_state'] == 'deleted')
		{
			$delete = $db->fetchRow("
				SELECT *
				FROM xf_deletion_log
				WHERE content_type = 'post'
					AND content_id = ?
			", $oldPostId);
			if ($delete)
			{
				$delete['content_id'] = $postId;
				$db->insert('xf_deletion_log', $delete);
			}
		}
		else if ($newPost['message_state'] == 'moderated')
		{
			$moderate = $db->fetchRow("
				SELECT *
				FROM xf_moderation_queue
				WHERE content_type = 'post'
					AND content_id = ?
			", $oldPostId);
			if ($moderate)
			{
				$moderate['content_id'] = $postId;
				$db->insert('xf_moderation_queue', $moderate);
			}
		}

		if ($targetThread['discussion_state'] == 'visible'
			&& $newPost['message_state'] == 'visible'
			&& $newPost['user_id']
			&& !empty($forum['count_messages'])
		)
		{
			$db->query("
				UPDATE xf_user
				SET message_count = message_count + 1
				WHERE user_id = ?
			", $newPost['user_id']);
		}

		return $newPost;
	}

	/**
	 * Merges specified posts (from given threads) into a target post and updates the text.
	 * The target post must be in the list of given posts.
	 *
	 * @param array $posts
	 * @param array $threads
	 * @param integer $targetPostId
	 * @param string $newMessage
	 * @param array $options
	 *
	 * @return boolean
	 */
	public function mergePosts(array $posts, array $threads, $targetPostId, $newMessage, $options = array())
	{
		if (!isset($posts[$targetPostId]))
		{
			return false;
		}

		$options = array_merge(array(
			'log' => true
		), $options);

		$targetPost = $posts[$targetPostId];
		unset($posts[$targetPostId]);

		if (!$posts)
		{
			return false;
		}

		$attachPosts = array();
		$likePosts = array();
		foreach ($posts AS $postId => $post)
		{
			if ($post['attach_count'])
			{
				$attachPosts[$postId] = $post['attach_count'];
			}
			if ($post['likes'])
			{
				$likePosts[$postId] = $post['likes'];
			}
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$forums = $this->getModelFromCache('XenForo_Model_Forum')->getForumsByThreadIds(array_keys($threads));

		$targetPostDw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
		$targetPostDw->setExistingData($targetPost, true);
		$targetPostDw->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_IS_AUTOMATED, true);
		$targetPostDw->set('message', $newMessage);

		if (array_key_exists($targetPostDw->get('thread_id'), $threads))
		{
			$thread = $threads[$targetPostDw->get('thread_id')];

			if (array_key_exists($thread['node_id'], $forums))
			{
				$targetPostDw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forums[$thread['node_id']]);
			}
		}

		if ($likePosts)
		{
			$res = $db->query("
				UPDATE IGNORE xf_liked_content SET
					content_id = ?
				WHERE content_type = 'post' AND content_id IN (" . $db->quote(array_keys($likePosts)) . ')
			', $targetPost['post_id']);
			$likesMoved = $res->rowCount();
			// remaining likes will be deleted with the posts, should keep counts accurate

			$latestLikeUsers = $this->getModelFromCache('XenForo_Model_Like')->getLatestContentLikeUsers(
				'post', $targetPost['post_id']
			);
			$targetPostDw->set('likes', $targetPostDw->get('likes') + $likesMoved);
			$targetPostDw->set('like_users', $latestLikeUsers);
		}

		if ($attachPosts)
		{
			$db->update('xf_attachment',
				array('content_id' => $targetPost['post_id']),
				"content_type = 'post' AND content_id IN (" . $db->quote(array_keys($attachPosts)) . ')'
			);
		}
		$targetPostDw->set('attach_count', $targetPostDw->get('attach_count') + array_sum($attachPosts));

		$targetPostDw->save();

		foreach ($posts AS $post)
		{
			$sourcePostDw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
			$sourcePostDw->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_DELETE_DISCUSSION_FIRST_MESSAGE, false);
			$sourcePostDw->setExistingData($post, true);
			$sourcePostDw->set('attach_count', 0); // moved these away, no need to try to delete

			if (array_key_exists($sourcePostDw->get('thread_id'), $threads))
			{
				$thread = $threads[$sourcePostDw->get('thread_id')];

				if (array_key_exists($thread['node_id'], $forums))
				{
					$sourcePostDw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forums[$thread['node_id']]);
				}
			}

			$sourcePostDw->delete();
		}

		if ($options['log'])
		{
			XenForo_Model_Log::logModeratorAction(
				'post', $targetPost, 'merge_target', array('ids' => implode(', ', array_keys($posts)))
			);
		}

		XenForo_Db::commit($db);

		return true;
	}

	/**
	 * Gets the quote text for the specified post.
	 *
	 * @param array $post
	 * @param integer $maxQuoteDepth Max depth of quoted text (-1 for unlimited)
	 *
	 * @return string
	 */
	public function getQuoteTextForPost(array $post, $maxQuoteDepth = 0)
	{
		if ($post['message_state'] != 'visible')
		{
			// non-visible posts shouldn't be quoted
			return '';
		}

		$message = trim(XenForo_Helper_String::stripQuotes($post['message'], $maxQuoteDepth));

		return $this->_getQuoteWrapperBbCode($post, $message);
	}

	/**
	 * Converts some HTML into quotable BB code
	 *
	 * @param array $post
	 * @param string $messageHtml
	 * @param integer $maxQuoteDepth Max depth of quoted text (-1 for unlimited)
	 *
	 * @return string
	 */
	public function getQuoteTextForPostFromHtml(array $post, $messageHtml, $maxQuoteDepth = 0)
	{
		$message = $this->getModelFromCache('XenForo_Model_BbCode')->getBbCodeFromSelectionHtml($messageHtml);
		$message = trim(XenForo_Helper_String::stripQuotes($message, $maxQuoteDepth));
		return $this->_getQuoteWrapperBbCode($post, $message);
	}

	/*
	 * Returns a [QUOTE...][/QUOTE] bb code block
	 *
	 * Note that if this syntax changes, changes must be applied to
	 * XenForo_Model_Post::alertQuotedMembers()
	 * and to
	 * XenForo_BbCode_Formatter_Base::renderTagQuote()
	 *
	 * @param array $post
	 * @param string $message
	 *
	 * @return string
	 */
	protected function _getQuoteWrapperBbCode(array $post, $message)
	{
		return '[QUOTE="' . $post['username']
		. ', post: ' . $post['post_id']
		. (!empty($post['user_id']) ? ', member: ' . $post['user_id'] : '')
		. '"]'
		. $message
		. "[/QUOTE]\n";
	}

	/**
	 * Sends an alert to members directly quoted in a post
	 *
	 * @param array $post
	 * @param array $thread
	 * @param array $forum
	 */
	public function alertQuotedMembers(array $post, array $thread, array $forum)
	{
		$quotedUsers = array();

		if (preg_match_all('/\[quote=("|\'|)([^,]+),\s*post:\s*(\d+?).*\\1\]/siU', $post['message'], $quotes))
		{
			$quotedPosts = $this->getPostsByIds(array_unique($quotes[3]), array(
				'join' => XenForo_Model_Post::FETCH_USER_OPTIONS
					| XenForo_Model_Post::FETCH_USER_PROFILE
					| XenForo_Model_Post::FETCH_THREAD
					| XenForo_Model_Post::FETCH_FORUM
					| XenForo_Model_Post::FETCH_NODE_PERMS
			));
			$userModel = $this->_getUserModel();

			foreach ($quotedPosts AS $quotedPostId => $quotedPost)
			{
				if (!isset($quotedUsers[$quotedPost['user_id']]) && $quotedPost['user_id'] && $quotedPost['user_id'] != $post['user_id'])
				{
					$permissions = XenForo_Permission::unserializePermissions($quotedPost['node_permission_cache']);

					if (!$userModel->isUserIgnored($quotedPost, $post['user_id'])
						&& XenForo_Model_Alert::userReceivesAlert($quotedPost, 'post', 'quote')
						&& $this->canViewPostAndContainer($quotedPost, $quotedPost, $quotedPost, $null, $permissions, $quotedPost)
					)
					{
						$quotedUsers[$quotedPost['user_id']] = true;

						XenForo_Model_Alert::alert($quotedPost['user_id'],
							$post['user_id'], $post['username'],
							'post', $post['post_id'],
							'quote'
						);
					}
				}
			}
		}

		return array_keys($quotedUsers);
	}

	public function alertTaggedMembers(array $post, array $thread, array $forum, array $tagged, array $alreadyAlerted)
	{
		$userIds = XenForo_Application::arrayColumn($tagged, 'user_id');
		$userIds = array_diff($userIds, $alreadyAlerted);
		$alertedUserIds = array();

		if ($userIds)
		{
			$userModel = $this->_getUserModel();
			$users = $userModel->getUsersByIds($userIds, array(
				'join' => XenForo_Model_User::FETCH_USER_OPTION | XenForo_Model_User::FETCH_USER_PROFILE,
				'nodeIdPermissions' => $thread['node_id']
			));
			foreach ($users AS $user)
			{
				if (!isset($alertedUserIds[$user['user_id']]) && $user['user_id'] != $post['user_id'])
				{
					$permissions = XenForo_Permission::unserializePermissions($user['node_permission_cache']);

					if (!$userModel->isUserIgnored($user, $post['user_id'])
						&& XenForo_Model_Alert::userReceivesAlert($user, 'post', 'tag')
						&& $this->canViewPostAndContainer($post, $thread, $forum, $null, $permissions, $user)
					)
					{
						$alertedUserIds[$user['user_id']] = true;

						XenForo_Model_Alert::alert($user['user_id'],
							$post['user_id'], $post['username'],
							'post', $post['post_id'],
							'tag'
						);
					}
				}
			}
		}

		return array_keys($alertedUserIds);
	}

	/**
	 * Attempts to update any instances of an old username in like_users with a new username
	 *
	 * @param integer $oldUserId
	 * @param integer $newUserId
	 * @param string $oldUsername
	 * @param string $newUsername
	 */
	public function batchUpdateLikeUser($oldUserId, $newUserId, $oldUsername, $newUsername)
	{
		$db = $this->_getDb();

		// note that xf_liked_content should have already been updated with $newUserId

		$db->query('
			UPDATE (
				SELECT content_id FROM xf_liked_content
				WHERE content_type = \'post\'
				AND like_user_id = ?
			) AS temp
			INNER JOIN xf_post AS post ON (post.post_id = temp.content_id)
			SET like_users = REPLACE(like_users, ' .
				$db->quote('i:' . $oldUserId . ';s:8:"username";s:' . strlen($oldUsername) . ':"' . $oldUsername . '";') . ', ' .
				$db->quote('i:' . $newUserId . ';s:8:"username";s:' . strlen($newUsername) . ':"' . $newUsername . '";') . ')
		', $newUserId);
	}

	public function sendModeratorActionAlert($action, array $post, array $thread, $reason = '', array $extra = array(), $alertUserId = null)
	{
		$extra = array_merge(array(
			'title' => $thread['title'],
			'link' => XenForo_Link::buildPublicLink('posts', $post),
			'threadLink' => XenForo_Link::buildPublicLink('threads', $thread),
			'reason' => $reason
		), $extra);

		if ($alertUserId === null)
		{
			$alertUserId = $post['user_id'];
		}

		if (!$alertUserId)
		{
			return false;
		}

		XenForo_Model_Alert::alert(
			$alertUserId,
			0, '',
			'user', $alertUserId,
			'post_' . $action,
			$extra
		);
		return true;
	}

	/**
	 * Checks whether a post is marked as deleted
	 *
	 * @param array $post
	 *
	 * @return boolean
	 */
	public function isDeleted(array $post)
	{
		if (!isset($post['message_state']))
		{
			throw new XenForo_Exception('Message state not available in post.');
		}

		return ($post['message_state'] == 'deleted');
	}

	/**
	 * Checks whether a post is marked as moderated
	 *
	 * @param array $post
	 *
	 * @return boolean
	 */
	public function isModerated(array $post)
	{
		if (!isset($post['message_state']))
		{
			throw new XenForo_Exception('Message state not available in post.');
		}

		return ($post['message_state'] == 'moderated');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}
}