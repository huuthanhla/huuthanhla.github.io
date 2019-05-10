<?php

/**
 * Model for threads.
 *
 * @package XenForo_Thread
 */
class XenForo_Model_Thread extends XenForo_Model
{
	/**
	 * Constants to allow joins to extra tables in certain queries
	 *
	 * @var integer Join user table
	 * @var integer Join node table
	 * @var integer Join post table
	 * @var integer Join user table to fetch avatar info of first poster
	 * @var integer Join forum table to fetch forum options
	 */
	const FETCH_USER = 0x01;
	const FETCH_FORUM = 0x02;
	const FETCH_FIRSTPOST = 0x04;
	const FETCH_AVATAR = 0x08;
	const FETCH_DELETION_LOG = 0x10;
	const FETCH_FORUM_OPTIONS = 0x20;


	const FETCH_REPLY_BAN_FORUM = 0x01;

	/**
	 * Returns a thread record based
	 *
	 * @param integer $threadId
	 * @param array $fetchOptions Collection of options related to fetching
	 *
	 * @return array|false
	 */
	public function getThreadById($threadId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareThreadFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT thread.*
				' . $joinOptions['selectFields'] . '
			FROM xf_thread AS thread
			' . $joinOptions['joinTables'] . '
			WHERE thread.thread_id = ?
		', $threadId);
	}

	/**
	 * Gets the named threads.
	 *
	 * @param array $threadIds
	 * @param array $fetchOptions Collection of options related to fetching
	 *
	 * @return array Format: [thread id] => info
	 */
	public function getThreadsByIds(array $threadIds, array $fetchOptions = array())
	{
		if (!$threadIds)
		{
			return array();
		}

		$joinOptions = $this->prepareThreadFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT thread.*
				' . $joinOptions['selectFields'] . '
			FROM xf_thread AS thread' . $joinOptions['joinTables'] . '
			WHERE thread.thread_id IN (' . $this->_getDb()->quote($threadIds) . ')
		', 'thread_id');
	}

	/**
	 * Checks the 'join' key of the incoming array for the presence of the FETCH_x bitfields in this class
	 * and returns SQL snippets to join the specified tables if required
	 *
	 * @param array $fetchOptions containing a 'join' integer key build from this class's FETCH_x bitfields
	 *
	 * @return array Containing selectFields, joinTables, orderClause keys.
	 * 		Example: selectFields = ', user.*, foo.title'; joinTables = ' INNER JOIN foo ON (foo.id = other.id) '; orderClause = ORDER BY x.y
	 */
	public function prepareThreadFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';

		if (!empty($fetchOptions['order']))
		{
			$orderBySecondary = '';

			switch ($fetchOptions['order'])
			{
				case 'title':
				case 'post_date':
				case 'view_count':
					$orderBy = 'thread.' . $fetchOptions['order'];
					break;

				case 'reply_count':
				case 'first_post_likes':
					$orderBy = 'thread.' . $fetchOptions['order'];
					$orderBySecondary = ', thread.last_post_date DESC';
					break;

				case 'last_post_date':
				default:
					$orderBy = 'thread.last_post_date';
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] == 'desc')
			{
				$orderBy .= ' DESC';
			}
			else
			{
				$orderBy .= ' ASC';
			}

			$orderBy .= $orderBySecondary;
		}

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					user.*, IF(user.username IS NULL, thread.username, user.username) AS username';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = thread.user_id)';
			}
			else if ($fetchOptions['join'] & self::FETCH_AVATAR)
			{
				$selectFields .= ',
					user.gender, user.avatar_date, user.gravatar';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = thread.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_FORUM)
			{
				$selectFields .= ',
					node.title AS node_title, node.node_name';
				$joinTables .= '
					LEFT JOIN xf_node AS node ON
						(node.node_id = thread.node_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_FORUM_OPTIONS)
			{
				$selectFields .= ',
					forum.*,
					forum.last_post_id AS forum_last_post_id,
					forum.last_post_date AS forum_last_post_date,
					forum.last_post_user_id AS forum_last_post_user_id,
					forum.last_post_username AS forum_last_post_username,
					forum.last_thread_title AS forum_last_thread_title,
					thread.last_post_id,
					thread.last_post_date,
					thread.last_post_user_id,
					thread.last_post_username';
				$joinTables .= '
					LEFT JOIN xf_forum AS forum ON
						(forum.node_id = thread.node_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_FIRSTPOST)
			{
				$selectFields .= ',
					post.message, post.attach_count';
				$joinTables .= '
					LEFT JOIN xf_post AS post ON
						(post.post_id = thread.first_post_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_DELETION_LOG)
			{
				$selectFields .= ',
					deletion_log.delete_date, deletion_log.delete_reason,
					deletion_log.delete_user_id, deletion_log.delete_username';
				$joinTables .= '
					LEFT JOIN xf_deletion_log AS deletion_log ON
						(deletion_log.content_type = \'thread\' AND deletion_log.content_id = thread.thread_id)';
			}
		}

		if (isset($fetchOptions['readUserId']))
		{
			if (!empty($fetchOptions['readUserId']))
			{
				$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);

				$joinTables .= '
					LEFT JOIN xf_thread_read AS thread_read ON
						(thread_read.thread_id = thread.thread_id
						AND thread_read.user_id = ' . $this->_getDb()->quote($fetchOptions['readUserId']) . ')';

				$joinForumRead = (!empty($fetchOptions['includeForumReadDate'])
					|| (!empty($fetchOptions['join']) && $fetchOptions['join'] & self::FETCH_FORUM)
				);
				if ($joinForumRead)
				{
					$joinTables .= '
						LEFT JOIN xf_forum_read AS forum_read ON
							(forum_read.node_id = thread.node_id
							AND forum_read.user_id = ' . $this->_getDb()->quote($fetchOptions['readUserId']) . ')';

					$selectFields .= ",
						GREATEST(COALESCE(thread_read.thread_read_date, 0), COALESCE(forum_read.forum_read_date, 0), $autoReadDate) AS thread_read_date";
				}
				else
				{
					$selectFields .= ",
						IF(thread_read.thread_read_date > $autoReadDate, thread_read.thread_read_date, $autoReadDate) AS thread_read_date";
				}
			}
			else
			{
				$selectFields .= ',
					NULL AS thread_read_date';
			}
		}

		if (isset($fetchOptions['replyBanUserId']))
		{
			if (!empty($fetchOptions['replyBanUserId']))
			{
				$selectFields .= ',
					IF(reply_ban.user_id IS NULL, 0,
						IF(reply_ban.expiry_date IS NULL OR reply_ban.expiry_date > '
						. $this->_getDb()->quote(XenForo_Application::$time) . ', 1, 0)) AS thread_reply_banned';
				$joinTables .= '
					LEFT JOIN xf_thread_reply_ban AS reply_ban
						ON (reply_ban.thread_id = thread.thread_id
						AND reply_ban.user_id = ' . $this->_getDb()->quote($fetchOptions['replyBanUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS thread_reply_banned';
			}
		}

		if (isset($fetchOptions['watchUserId']))
		{
			if (!empty($fetchOptions['watchUserId']))
			{
				$selectFields .= ',
					IF(thread_watch.user_id IS NULL, 0,
						IF(thread_watch.email_subscribe, \'watch_email\', \'watch_no_email\')) AS thread_is_watched';
				$joinTables .= '
					LEFT JOIN xf_thread_watch AS thread_watch
						ON (thread_watch.thread_id = thread.thread_id
						AND thread_watch.user_id = ' . $this->_getDb()->quote($fetchOptions['watchUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS thread_is_watched';
			}
		}

		if (isset($fetchOptions['forumWatchUserId']))
		{
			if (!empty($fetchOptions['forumWatchUserId']))
			{
				$selectFields .= ',
					IF(forum_watch.user_id IS NULL, 0, 1) AS forum_is_watched';
				$joinTables .= '
					LEFT JOIN xf_forum_watch AS forum_watch
						ON (forum_watch.node_id = thread.node_id
						AND forum_watch.user_id = ' . $this->_getDb()->quote($fetchOptions['forumWatchUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS forum_is_watched';
			}
		}

		if (isset($fetchOptions['draftUserId']))
		{
			if (!empty($fetchOptions['draftUserId']))
			{
				$selectFields .= ',
					draft.message AS draft_message, draft.extra_data AS draft_extra';
				$joinTables .= '
					LEFT JOIN xf_draft AS draft
						ON (draft.draft_key = CONCAT(\'thread-\', thread.thread_id)
						AND draft.user_id = ' . $this->_getDb()->quote($fetchOptions['draftUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					\'\' AS draft_message, NULL AS draft_extra';
			}
		}

		if (isset($fetchOptions['postCountUserId']))
		{
			if (!empty($fetchOptions['postCountUserId']))
			{
				$selectFields .= ',
					thread_user_post.post_count AS user_post_count';
				$joinTables .= '
					LEFT JOIN xf_thread_user_post AS thread_user_post
						ON (thread_user_post.thread_id = thread.thread_id
						AND thread_user_post.user_id = ' . $this->_getDb()->quote($fetchOptions['postCountUserId']) . ')';
			}
			else
			{
				$selectFields .= ',
					0 AS user_post_count';
			}
		}

		if (!empty($fetchOptions['permissionCombinationId']))
		{
			$selectFields .= ',
				permission.cache_value AS node_permission_cache';
			$joinTables .= '
				LEFT JOIN xf_permission_cache_content AS permission
					ON (permission.permission_combination_id = ' . $this->_getDb()->quote($fetchOptions['permissionCombinationId']) . '
						AND permission.content_type = \'node\'
						AND permission.content_id = thread.node_id)';
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables,
			'orderClause'  => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}

	/**
	 * Prepares a collection of thread fetching related conditions into an SQL clause
	 *
	 * @param array $conditions List of conditions
	 * @param array $fetchOptions Modifiable set of fetch options (may have joins pushed on to it)
	 *
	 * @return string SQL clause (at least 1=1)
	 */
	public function prepareThreadConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['thread_id_gt']))
		{
			$sqlConditions[] = 'thread.thread_id > ' . $db->quote($conditions['thread_id_gt']);
		}

		if (!empty($conditions['title']))
		{
			if (is_array($conditions['title']))
			{
				$sqlConditions[] = 'thread.title LIKE ' . XenForo_Db::quoteLike($conditions['title'][0], $conditions['title'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'thread.title LIKE ' . XenForo_Db::quoteLike($conditions['title'], 'lr', $db);
			}
		}

		if (!empty($conditions['forum_id']) && empty($conditions['node_id']))
		{
			$conditions['node_id'] = $conditions['forum_id'];
		}

		if (!empty($conditions['node_id']))
		{
			if (is_array($conditions['node_id']))
			{
				$sqlConditions[] = 'thread.node_id IN (' . $db->quote($conditions['node_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'thread.node_id = ' . $db->quote($conditions['node_id']);
			}
		}

		if (!empty($conditions['discussion_type']))
		{
			if (is_array($conditions['discussion_type']))
			{
				$sqlConditions[] = 'thread.discussion_type IN (' . $db->quote($conditions['discussion_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'thread.discussion_type = ' . $db->quote($conditions['discussion_type']);
			}
		}

		if (!empty($conditions['not_discussion_type']))
		{
			if (is_array($conditions['not_discussion_type']))
			{
				$sqlConditions[] = 'thread.discussion_type NOT IN (' . $db->quote($conditions['not_discussion_type']) . ')';
			}
			else
			{
				$sqlConditions[] = 'thread.discussion_type <> ' . $db->quote($conditions['not_discussion_type']);
			}
		}

		if (!empty($conditions['prefix_id']))
		{
			if (is_array($conditions['prefix_id']))
			{
				if (in_array(-1, $conditions['prefix_id'])) {
					$conditions['prefix_id'][] = 0;
				}
				$sqlConditions[] = 'thread.prefix_id IN (' . $db->quote($conditions['prefix_id']) . ')';
			}
			else if ($conditions['prefix_id'] == -1)
			{
				$sqlConditions[] = 'thread.prefix_id = 0';
			}
			else
			{
				$sqlConditions[] = 'thread.prefix_id = ' . $db->quote($conditions['prefix_id']);
			}
		}

		if (isset($conditions['sticky']))
		{
			$sqlConditions[] = 'thread.sticky = ' . ($conditions['sticky'] ? 1 : 0);
		}

		if (isset($conditions['discussion_open']))
		{
			$sqlConditions[] = 'thread.discussion_open = ' . ($conditions['discussion_open'] ? 1 : 0);
		}

		if (!empty($conditions['discussion_state']))
		{
			if (is_array($conditions['discussion_state']))
			{
				$sqlConditions[] = 'thread.discussion_state IN (' . $db->quote($conditions['discussion_state']) . ')';
			}
			else
			{
				$sqlConditions[] = 'thread.discussion_state = ' . $db->quote($conditions['discussion_state']);
			}
		}

		if (isset($conditions['deleted']) || isset($conditions['moderated']))
		{
			$sqlConditions[] = $this->prepareStateLimitFromConditions($conditions, 'thread', 'discussion_state');
		}

		if (!empty($conditions['last_post_date']) && is_array($conditions['last_post_date']))
		{
			$sqlConditions[] = $this->getCutOffCondition("thread.last_post_date", $conditions['last_post_date']);
		}

		if (!empty($conditions['post_date']) && is_array($conditions['post_date']))
		{
			$sqlConditions[] = $this->getCutOffCondition("thread.post_date", $conditions['post_date']);
		}

		if (!empty($conditions['reply_count']) && is_array($conditions['reply_count']))
		{
			$sqlConditions[] = $this->getCutOffCondition("thread.reply_count", $conditions['reply_count']);
		}

		if (!empty($conditions['first_post_likes']) && is_array($conditions['first_post_likes']))
		{
			$sqlConditions[] = $this->getCutOffCondition("thread.first_post_likes", $conditions['first_post_likes']);
		}

		if (!empty($conditions['view_count']) && is_array($conditions['view_count']))
		{
			$sqlConditions[] = $this->getCutOffCondition("thread.view_count", $conditions['view_count']);
		}

		// fetch threads only from forums with find_new = 1
		if (!empty($conditions['find_new']) && isset($fetchOptions['join']) && $fetchOptions['join'] & self::FETCH_FORUM_OPTIONS)
		{
			$sqlConditions[] = 'forum.find_new = 1';
		}

		// thread starter
		if (isset($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				$sqlConditions[] = 'thread.user_id IN (' . $db->quote($conditions['user_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'thread.user_id = ' . $db->quote($conditions['user_id']);
			}
		}

		// watch limit
		if (!empty($conditions['watch_only']))
		{
			$parts = array();
			if (!empty($fetchOptions['forumWatchUserId']))
			{
				$parts[] = 'forum_watch.node_id IS NOT NULL';
			}
			if (!empty($fetchOptions['watchUserId']))
			{
				$parts[] = 'thread_watch.thread_id IS NOT NULL';
			}
			if (!$parts)
			{
				$sqlConditions[] = '0'; // no watch info - return nothing
			}
			else
			{
				$sqlConditions[] = '(' . implode(' OR ', $parts) . ')';
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Gets threads that match the given conditions.
	 *
	 * @param array $conditions Conditions to apply to the fetching
	 * @param array $fetchOptions Collection of options that relate to fetching
	 *
	 * @return array Format: [thread id] => info
	 */
	public function getThreads(array $conditions, array $fetchOptions = array())
	{
		$whereConditions = $this->prepareThreadConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareThreadFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$forceIndex = (!empty($fetchOptions['forceThreadIndex']) ? 'FORCE INDEX (' . $fetchOptions['forceThreadIndex'] . ')' : '');

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT thread.*
					' . $sqlClauses['selectFields'] . '
				FROM xf_thread AS thread ' . $forceIndex . '
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereConditions . '
				' . $sqlClauses['orderClause'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'thread_id');
	}

	/**
	 * Gets thread ids with the specified criteria.
	 *
	 * @param array $conditions Conditions to apply to the fetching
	 * @param array $fetchOptions for limiting only
	 *
	 * @return array
	 */
	public function getThreadIds(array $conditions, array $fetchOptions = array())
	{
		$fetchOptionsInner = array();
		$whereConditions = $this->prepareThreadConditions($conditions, $fetchOptionsInner);
		$sqlClauses = $this->prepareThreadFetchOptions($fetchOptionsInner);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchCol($this->limitQueryResults(
			'
				SELECT thread.thread_id
				FROM xf_thread AS thread
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereConditions . '
				ORDER BY thread.thread_id
			', $limitOptions['limit'], $limitOptions['offset']
		));
	}

	/**
	 * Gets the count of threads with the specified criteria.
	 *
	 * @param array $conditions Conditions to apply to the fetching
	 *
	 * @return integer
	 */
	public function countThreads(array $conditions)
	{
		$fetchOptions = array();
		$whereConditions = $this->prepareThreadConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareThreadFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_thread AS thread
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereConditions . '
		');
	}

	/**
	 * Gets an array of the node IDs in which the specified threads reside
	 *
	 * @param array $threads
	 *
	 * @return array
	 */
	public function getNodeIdsFromThreads(array $threads)
	{
		$nodeIds = array();

		foreach ($threads AS $thread)
		{
			$nodeIds[] = $thread['node_id'];
		}

		return array_unique($nodeIds);
	}

	/**
	 * Gets threads that belong to the specified forum.
	 *
	 * @param integer $forumId
	 * @param array $conditions Conditions to apply to the fetching
	 * @param array $fetchOptions Collection of options that relate to fetching
	 *
	 * @return array Format: [thread id] => info
	 */
	public function getThreadsInForum($forumId, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['forum_id'] = $forumId;
		return $this->getThreads($conditions, $fetchOptions);
	}

	/**
	 * Gets all sticky threads in a particular forum.
	 *
	 * @param integer $forumId
	 * @param array $fetchOptions Collection of options that relate to fetching
	 *
	 * @return array Format: [thread id] => info
	 */
	public function getStickyThreadsInForum($forumId, array $conditions = array(), array $fetchOptions = array())
	{
		$conditions['forum_id'] = $forumId;
		$conditions['sticky'] = 1;
		return $this->getThreads($conditions, $fetchOptions);
	}

	/**
	 * Gets the count of threads in the specified forum.
	 *
	 * @param integer $forumId
	 * @param array $conditions Conditions to apply to the fetching
	 *
	 * @return integer
	 */
	public function countThreadsInForum($forumId, array $conditions = array())
	{
		$conditions['forum_id'] = $forumId;
		return $this->countThreads($conditions);
	}

	/**
	 * Gets the thread with the most recent post in the specified forum.
	 * Doesn't include redirects.
	 *
	 * @param integer $forumId
	 * @param array $fetchOptions Collection of options that relate to fetching
	 *
	 * @return array|false
	 */
	public function getLastUpdatedThreadInForum($forumId, array $fetchOptions = array())
	{
		$db = $this->_getDb();

		$stateLimit = $this->prepareStateLimitFromConditions($fetchOptions, '', 'discussion_state');

		return $db->fetchRow($db->limit('
			SELECT *
			FROM xf_thread
			WHERE node_id = ?
				AND discussion_type <> \'redirect\'
				AND (' . $stateLimit . ')
			ORDER BY last_post_date DESC
		', 1), $forumId);
	}

	/**
	 * Gets thread IDs in the specified range. The IDs returned will be those immediately
	 * after the "start" value (not including the start), up to the specified limit.
	 *
	 * @param integer $start IDs greater than this will be returned
	 * @param integer $limit Number of posts to return
	 *
	 * @return array List of IDs
	 */
	public function getThreadIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT thread_id
			FROM xf_thread
			WHERE thread_id > ?
			ORDER BY thread_id
		', $limit), $start);
	}

	/**
	 * Gets the IDs of threads that the specified user has not read. Doesn't not work for guests.
	 * Doesn't include deleted.
	 *
	 * @param integer $userId
	 * @param array $fetchOptions Fetching options; limit only
	 * @param boolean $watchedOnly If true, only returns results in watched threads/forums
	 *
	 * @return array List of thread IDs
	 */
	public function getUnreadThreadIds($userId, array $fetchOptions = array(), $watchedOnly = false)
	{
		if (!$userId)
		{
			return array();
		}

		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);
		$userId = intval($userId);

		if ($watchedOnly)
		{
			$joinExtra = '
				LEFT JOIN xf_forum_watch AS forum_watch ON (forum_watch.node_id = thread.node_id AND forum_watch.user_id = ' . $userId . ')
				LEFT JOIN xf_thread_watch AS thread_watch ON (thread_watch.thread_id = thread.thread_id AND thread_watch.user_id = ' . $userId . ')
			';
			$whereExtra = 'AND (forum_watch.node_id IS NOT NULL OR thread_watch.thread_id IS NOT NULL)';
		}
		else
		{
			$joinExtra = '';
			$whereExtra = '';
		}

		return $this->_getDb()->fetchCol($this->limitQueryResults(
			'
				SELECT thread.thread_id
				FROM xf_thread AS thread
				INNER JOIN xf_forum AS forum ON
					(forum.node_id = thread.node_id)
				LEFT JOIN xf_thread_read AS thread_read ON
					(thread_read.thread_id = thread.thread_id AND thread_read.user_id = ?
					AND thread_read.thread_read_date >= thread.last_post_date)
				LEFT JOIN xf_forum_read AS forum_read ON
					(forum_read.node_id = thread.node_id AND forum_read.user_id = ?
					AND forum_read.forum_read_date >= thread.last_post_date)
				' . $joinExtra . '
				WHERE thread_read.thread_read_date IS NULL
					AND forum_read.forum_read_date IS NULL
					AND thread.last_post_date > ?
					AND thread.discussion_type <> \'redirect\'
					AND thread.discussion_state <> \'deleted\'
					AND forum.find_new = 1
					' . $whereExtra . '
				ORDER BY thread.last_post_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), array($userId, $userId, $autoReadDate));
	}

	/**
	 * Determines if the thread can be viewed with the given permissions.
	 * This does not check forum viewing permissions.
	 *
	 * @param array $thread Info about the thread
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewThread(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (isset($thread['thread_user_id']))
		{
			$thread['user_id'] = $thread['thread_user_id'];
		}

		if (!XenForo_Permission::hasContentPermission($nodePermissions, 'view'))
		{
			return false;
		}
		if (!XenForo_Permission::hasContentPermission($nodePermissions, 'viewOthers') && ($viewingUser['user_id'] != $thread['user_id'] || !$viewingUser['user_id']))
		{
			return false;
		}
		if (!XenForo_Permission::hasContentPermission($nodePermissions, 'viewContent'))
		{
			// TODO: specific error message?
			return false;
		}

		if ($this->isModerated($thread))
		{
			if (!XenForo_Permission::hasContentPermission($nodePermissions, 'viewModerated'))
			{
				if (!$viewingUser['user_id'] || $viewingUser['user_id'] != $thread['user_id'])
				{
					$errorPhraseKey = 'requested_thread_not_found';
					return false;
				}
			}
		}
		else if ($this->isDeleted($thread))
		{
			if (!XenForo_Permission::hasContentPermission($nodePermissions, 'viewDeleted'))
			{
				$errorPhraseKey = 'requested_thread_not_found';
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines if the thread can be viewed with the given permissions.
	 * This will check that any parent container can be viewed as well.
	 *
	 * @param array $thread Info about the thread
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewThreadAndContainer(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$this->_getForumModel()->canViewForum($forum, $errorPhraseKey, $nodePermissions, $viewingUser))
		{
			return false;
		}

		return $this->canViewThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser);
	}

	/**
	 * Checks whether a user can view deleted posts in a thread
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array $nodePermissions
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewDeletedPosts(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		return (XenForo_Permission::hasContentPermission($nodePermissions, 'viewDeleted'));
	}

	/**
	 * Checks whether a user can view moderated posts in a thread
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array $nodePermissions
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewModeratedPosts(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		return (XenForo_Permission::hasContentPermission($nodePermissions, 'viewModerated'));
	}

	/**
	 * Checks whether a user can view attachments in a thread
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array $nodePermissions
	 * @param array $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewAttachmentsInThread(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		return XenForo_Permission::hasContentPermission($nodePermissions, 'viewAttachment');
	}

	/**
	 * Determines if a new reply can be posted in the specified thread,
	 * with the given permissions. This does not check viewing permissions.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canReplyToThread(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if ($this->isRedirect($thread) || $this->isDeleted($thread))
		{
			return false;
		}

		if (!$thread['discussion_open'] && !$this->canLockUnlockThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser))
		{
			$errorPhraseKey = 'you_may_not_perform_this_action_because_discussion_is_closed';
			return false;
		}

		if (empty($forum['allow_posting']))
		{
			$errorPhraseKey = 'you_may_not_perform_this_action_because_forum_does_not_allow_posting';
			return false;
		}

		if (!XenForo_Permission::hasContentPermission($nodePermissions, 'postReply'))
		{
			return false;
		}

		if ($viewingUser['user_id'])
		{
			if (!isset($thread['thread_reply_banned']))
			{
				$result = $this->_getDb()->fetchRow("
					SELECT expiry_date
					FROM xf_thread_reply_ban
					WHERE thread_id = ?
						AND user_id = ?
				", array($thread['thread_id'], $viewingUser['user_id']));
				$thread['thread_reply_banned'] = (
					$result
					&& ($result['expiry_date'] === null || $result['expiry_date'] > XenForo_Application::$time)
				);
			}
			if ($thread['thread_reply_banned'])
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines if the user can ban a specific user from replying or
	 * can view/manage existing thread reply bans.
	 *
	 * @param array|null $user If attempting to ban a specific user. If just managing the null.
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return bool
	 */
	public function canReplyBanUserFromThread(array $user = null, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if ($user)
		{
			if ($user['is_staff'])
			{
				$errorPhraseKey = 'staff_members_cannot_be_reply_banned';
				return false;
			}
		}

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return XenForo_Permission::hasContentPermission($nodePermissions, 'threadReplyBan');
	}

	/**
	 * Determines if the thread can be edited with the given permissions.
	 * This does not check thread viewing permissions.
	 *
	 * @param array $thread Info about the thread
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditThread(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread'));
	}

	/**
	 * Determines if the thread title be edited with the given permissions.
	 * This does not check thread viewing permissions.
	 *
	 * @param array $thread Info about the thread
	 * @param array $forum Info about the forum the thread is in
	 * @param string $errorPhraseKey Returned phrase key for a specific error
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditThreadTitle(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (!$thread['discussion_open'] && !$this->canLockUnlockThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser))
		{
			$errorPhraseKey = 'you_may_not_perform_this_action_because_discussion_is_closed';
			return false;
		}

		if (XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread'))
		{
			return true;
		}

		if ($thread['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'editOwnPost'))
		{
			$editLimit = XenForo_Permission::hasContentPermission($nodePermissions, 'editOwnPostTimeLimit');

			if ($editLimit != -1 && (!$editLimit || $thread['post_date'] < XenForo_Application::$time - 60 * $editLimit))
			{
				$errorPhraseKey = array('message_edit_time_limit_expired', 'minutes' => $editLimit);
				return false;
			}

			if (empty($forum['allow_posting']))
			{
				$errorPhraseKey = 'you_may_not_perform_this_action_because_forum_does_not_allow_posting';
				return false;
			}

			return XenForo_Permission::hasContentPermission($nodePermissions, 'editOwnThreadTitle');
		}

		return false;
	}

	/**
	 * Determines if the thread can be locked/unlocked with the given permissions.
	 * This does not check viewing permissions.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canLockUnlockThread(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'lockUnlockThread'));
	}

	/**
	 * Determines if the thread can be stuck/unstuck with the given permissions.
	 * This does not check viewing permissions.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canStickUnstickThread(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'stickUnstickThread'));
	}

	/**
	 * Determines if the thread can be deleted with the given permissions.
	 * This does not check viewing permissions.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canDeleteThread(array $thread, array $forum, $deleteType = 'soft', &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($deleteType != 'soft' && !XenForo_Permission::hasContentPermission($nodePermissions, 'hardDeleteAnyThread'))
		{
			// fail immediately on hard delete without permission
			return false;
		}

		if (!$thread['discussion_open'] && !$this->canLockUnlockThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser))
		{
			$errorPhraseKey = 'you_may_not_perform_this_action_because_discussion_is_closed';
			return false;
		}

		if (XenForo_Permission::hasContentPermission($nodePermissions, 'deleteAnyThread'))
		{
			return true;
		}
		else if ($thread['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'deleteOwnThread'))
		{
			$editLimit = XenForo_Permission::hasContentPermission($nodePermissions, 'editOwnPostTimeLimit');

			if ($editLimit != -1 && (!$editLimit || $thread['post_date'] < XenForo_Application::$time - 60 * $editLimit))
			{
				$errorPhraseKey = array('message_edit_time_limit_expired', 'minutes' => $editLimit);
				return false;
			}

			if (empty($forum['allow_posting']))
			{
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Determines if the thread can be undeleted with the given permissions.
	 * This does not check thread viewing permissions.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canUndeleteThread(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'undelete'));
	}

	/**
	 * Determines if the thread moderator log can be viewed.
	 * This does not check thread viewing permissions.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewThreadModeratorLog(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		return (
			XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'editAnyPost')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'deleteAnyPost')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'deleteAnyThread')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'hardDeleteAnyPost')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'hardDeleteAnyThread')
		);
	}

	/**
	 * Determines if the thread can be approved/unapproved with the given permissions.
	 * This does not check thread viewing permissions.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canApproveUnapproveThread(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'approveUnapprove'));
	}

	/**
	 * Determines if the thread can be moved with the given permissions.
	 * This does not check thread viewing permissions.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMoveThread(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread'));
	}

	/**
	 * Determines if the thread can be merged with another with the given permissions.
	 * This does not check thread viewing permissions.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canMergeThread(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread'));
	}

	/**
	 * Determines if the thread's discussion_state can be altered to a new value.
	 * This does not check thread viewing permissions.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $state (intended new discussion_state)
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canAlterThreadState(array $thread, array $forum, $state, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		if ($state == $thread['discussion_state'])
		{
			// not attempting to change, so allow
			return true;
		}

		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		switch ($state)
		{
			case 'visible':
			{
				if ($this->isModerated($thread)
					&& !$this->canApproveUnapproveThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser)
				)
				{
					return false;
				}

				if ($this->isDeleted($thread)
					&& !$this->canUndeleteThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser)
				)
				{
					return false;
				}

				break;
			}

			case 'moderated':
			{
				if ($this->isVisible($thread)
					&& !$this->canApproveUnapproveThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser)
				)
				{
					return false;
				}

				if ($this->isDeleted($thread) &&
					(
						!$this->canUndeleteThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser)
						||
						!$this->canApproveUnapproveThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser)
					)
				)
				{
					return false;
				}

				break;
			}

			case 'deleted':
			{
				if (!$this->canDeleteThread($thread, $forum, 'soft', $errorPhraseKey, $nodePermissions, $viewingUser))
				{
					return false;
				}

				break;
			}

			default:
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines if the thread can be watched with the given permissions.
	 * This does not check thread viewing permissions.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canWatchThread(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] ? true : false);
	}

	/**
	 * Determines if the poll in the given thread can be voted on. This does not
	 * check if the user has already voted on the poll.
	 *
	 * @param array $poll
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canVoteOnPoll(array $poll, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		if (!$thread['discussion_open'])
		{
			$errorPhraseKey = 'you_may_not_perform_this_action_because_discussion_is_closed';
			return false;
		}

		if (empty($forum['allow_posting']))
		{
			$errorPhraseKey = 'you_may_not_perform_this_action_because_forum_does_not_allow_posting';
			return false;
		}

		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
		return ($viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'votePoll'));
	}

	/**
	 * Determines if the poll in the given thread can be edited.
	 * This does not check thread viewing permissions.
	 *
	 * @param array $poll
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditPoll(array $poll, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (!$thread['discussion_open'] && !$this->canLockUnlockThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser))
		{
			$errorPhraseKey = 'you_may_not_perform_this_action_because_discussion_is_closed';
			return false;
		}

		if (XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread'))
		{
			return true;
		}

		if ($thread['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'editOwnPost'))
		{
			$editLimit = XenForo_Permission::hasContentPermission($nodePermissions, 'editOwnPostTimeLimit');

			if ($editLimit != -1 && (!$editLimit || $thread['post_date'] < XenForo_Application::$time - 60 * $editLimit))
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
	 * Determines if the poll details (question, response) for the associated
	 * thread can be edited. Does not check thread viewing or base poll editing
	 * permissions.
	 *
	 * @param array $poll
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canEditPollDetails(array $poll, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread'))
		{
			return true;
		}

		return ($poll['voter_count'] == 0);
	}

	/**
	 * Determines if a poll can be reset (votes removed) or removed. Does not check thread viewing or base poll editing
	 * permissions.
	 *
	 * @param array $poll
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canResetPoll(array $poll, array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread'))
		{
			return true;
		}

		return ($poll['voter_count'] == 0);
	}

	/**
	 * Determines if a poll can be added to this thread.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canAddPoll(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		if ($thread['discussion_type'] !== '')
		{
			return false;
		}

		if (!$this->_getForumModel()->canPostPollInForum($forum, $null, $nodePermissions, $viewingUser))
		{
			return false;
		}

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if (!$thread['discussion_open'] && !$this->canLockUnlockThread($thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser))
		{
			$errorPhraseKey = 'you_may_not_perform_this_action_because_discussion_is_closed';
			return false;
		}

		if (XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread'))
		{
			return true;
		}

		if ($thread['user_id'] == $viewingUser['user_id'] && XenForo_Permission::hasContentPermission($nodePermissions, 'editOwnPost'))
		{
			$editLimit = XenForo_Permission::hasContentPermission($nodePermissions, 'editOwnPostTimeLimit');

			if ($editLimit != -1 && (!$editLimit || $thread['post_date'] < XenForo_Application::$time - 60 * $editLimit))
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
	 * Determines if the specified user can view IP addresses
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canViewIps(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null, array $viewingUser = null)
	{
		return $this->getModelFromCache('XenForo_Model_User')->canViewIps($errorPhraseKey, $viewingUser);
	}

	/**
	 * Determines if a user can reply to the thread using Quick Reply.
	 * Note that this always assumes the visitor!
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $errorPhraseKey
	 * @param array|null $nodePermissions
	 *
	 * @return boolean
	 */
	public function canQuickReply(array $thread, array $forum, &$errorPhraseKey = '', array $nodePermissions = null)
	{
		if (!$this->canReplyToThread($thread, $forum, $errorPhraseKey, $nodePermissions))
		{
			return false;
		}

		$visitor = XenForo_Visitor::getInstance();

		if (!$visitor['user_id'] || $visitor->showCaptcha())
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Adds the canInlineMod value to the provided thread and returns the
	 * specific list of inline mod actions that are allowed on this thread.
	 *
	 * @param array $thread Thread info
	 * @param array $forum Forum the thread is in
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return array List of allowed inline mod actions, format: [action] => true
	 */
	public function addInlineModOptionToThread(array &$thread, array $forum, array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		$modOptions = array();
		$canInlineMod = ($viewingUser['user_id'] && (
			XenForo_Permission::hasContentPermission($nodePermissions, 'deleteAnyThread')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'undelete')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'approveUnapprove')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'lockUnlockThread')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'stickUnstickThread')
			|| XenForo_Permission::hasContentPermission($nodePermissions, 'manageAnyThread')
		));

		if ($canInlineMod)
		{
			if ($this->canDeleteThread($thread, $forum, 'soft', $null, $nodePermissions, $viewingUser))
			{
				$modOptions['delete'] = true;
			}
			if ($this->canUndeleteThread($thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$modOptions['undelete'] = true;
			}
			if ($this->canApproveUnapproveThread($thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$modOptions['approve'] = true;
				$modOptions['unapprove'] = true;
			}
			if ($this->canLockUnlockThread($thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$modOptions['lock'] = true;
				$modOptions['unlock'] = true;
			}
			if ($this->canStickUnstickThread($thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$modOptions['stick'] = true;
				$modOptions['unstick'] = true;
			}
			if ($this->canMoveThread($thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$modOptions['move'] = true;
			}
			if ($this->canMergeThread($thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$modOptions['merge'] = true;
			}
			if ($this->canEditThread($thread, $forum, $null, $nodePermissions, $viewingUser))
			{
				$modOptions['edit'] = true;
			}
		}

		$thread['canInlineMod'] = (count($modOptions) > 0);

		return $modOptions;
	}

	/**
	 * Prepares a thread for display, generally within the context of a specific forum.
	 *
	 * @param array $thread Thread to prepare
	 * @param array $forum Forum thread is in
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return array Prepared version of thread
	 */
	public function prepareThread(array $thread, array $forum, array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		$thread['lastPostInfo'] = array(
			'post_date' => $thread['last_post_date'],
			'post_id' => $thread['last_post_id'],
			'user_id' => $thread['last_post_user_id'],
			'username' => $thread['last_post_username']
		);

		if (isset($thread['node_title']))
		{
			$thread['forum'] = array(
				'node_id' => $thread['node_id'],
				'title' => $thread['node_title'],
				'node_name' => isset($thread['node_name']) ? $thread['node_name'] : null
			);
		}

		if ($thread['view_count'] <= $thread['reply_count'])
		{
			$thread['view_count'] = $thread['reply_count'] + 1;
		}

		if (!empty($thread['delete_date']))
		{
			$thread['deleteInfo'] = array(
				'user_id' => $thread['delete_user_id'],
				'username' => $thread['delete_username'],
				'date' => $thread['delete_date'],
				'reason' => $thread['delete_reason'],
			);
		}

		if (!isset($thread['canInlineMod']))
		{
			$this->addInlineModOptionToThread($thread, $forum, $nodePermissions, $viewingUser);
		}

		$thread['canEditThread'] = $this->canEditThread($thread, $forum, $_null, $nodePermissions, $viewingUser);

		$thread['isNew'] = $this->isNew($thread, $forum);
		if ($thread['isNew'])
		{
			$readDate = $this->getMaxThreadReadDate($thread, $forum);
			$thread['haveReadData'] = ($readDate > XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400));
		}
		else
		{
			$thread['haveReadData'] = false;
		}

		$thread['hasPreview'] = $this->hasPreview($thread, $forum, $nodePermissions, $viewingUser);
		$thread['canViewContent'] = $this->_getForumModel()->canViewForumContent($forum, $null, $nodePermissions, $viewingUser);

		$thread['isRedirect']  = $this->isRedirect($thread);
		$thread['isDeleted']   = $this->isDeleted($thread);
		$thread['isModerated'] = $this->isModerated($thread);

		$thread['title'] = XenForo_Helper_String::censorString($thread['title']);
		$thread['titleCensored'] = true;

		$thread['lastPageNumbers'] = $this->getLastPageNumbers($thread['reply_count']);

		if (array_key_exists('user_group_id', $thread))
		{
			$thread = $this->getModelFromCache('XenForo_Model_User')->prepareUser($thread);
		}

		return $thread;
	}

	/**
	 * Determines if a thread is a redirect (based on discussion_type)
	 *
	 * @param array $thread
	 *
	 * @return boolean
	 */
	public function isRedirect(array $thread)
	{
		return ($thread['discussion_type'] == 'redirect');
	}

	/**
	 * Determines if a thread is deleted (based on discussion_state)
	 *
	 * @param array $thread
	 *
	 * @return boolean
	 */
	public function isDeleted(array $thread)
	{
		return ($thread['discussion_state'] == 'deleted');
	}

	/**
	 * Determines if a thread is moderated (based on discussion_state)
	 *
	 * @param array $thread
	 *
	 * @return boolean
	 */
	public function isModerated(array $thread)
	{
		return ($thread['discussion_state'] == 'moderated');
	}

	/**
	 * Determines if a thread is visible (based on discussion_state)
	 *
	 * @param array $thread
	 *
	 * @return boolean
	 */
	public function isVisible(array $thread)
	{
		return ($thread['discussion_state'] == 'visible');
	}

	/**
	 * Determines if a thread is new / unread
	 *
	 * @param array $thread (expects thread_read_date or forum_read_date, discussion_type and last_post_date indeces)
	 *
	 * @return boolean
	 */
	public function isNew(array $thread, array $forum)
	{
		if (isset($thread['thread_read_date']) || isset($forum['forum_read_date']))
		{
			if ($this->isRedirect($thread) || $this->isDeleted($thread))
			{
				return false;
			}
			else
			{
				return ($this->getMaxThreadReadDate($thread, $forum) < $thread['last_post_date']);
			}
		}

		return false;
	}

	/**
	 * Determines if a thread can have a thread preview
	 *
	 * @param array $thread (expects first_post_id and discussion_type indeces)
	 * @param array $forum
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function hasPreview(array $thread, array $forum, array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

		return
		(
			$thread['first_post_id']
			&& XenForo_Application::get('options')->discussionPreviewLength
			&& $this->isRedirect($thread) == false
			&& XenForo_Permission::hasContentPermission($nodePermissions, 'viewContent')
		);
	}

	/**
	 * Gets permission-based conditions that apply to thread fetching functions.
	 *
	 * @param array $forum Forum the threads will belong to
	 * @param array|null $nodePermissions
	 * @param array|null $viewingUser
	 *
	 * @return array Keys: deleted (boolean), moderated (boolean or integer, if can only view single user's)
	 */
	public function getPermissionBasedThreadFetchConditions(array $forum, array $nodePermissions = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReferenceForNode($forum['node_id'], $viewingUser, $nodePermissions);

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

		$conditions = array(
			'deleted' => XenForo_Permission::hasContentPermission($nodePermissions, 'viewDeleted'),
			'moderated' => $viewModerated
		);

		if (!XenForo_Permission::hasContentPermission($nodePermissions, 'viewOthers'))
		{
			$conditions['user_id'] = $viewingUser['user_id'] ? $viewingUser['user_id'] : -1;
		}

		return $conditions;
	}

	/**
	 * Helper to delete the specified thread, via a soft or hard delete.
	 *
	 * @param integer $threadId ID of the thread to delete
	 * @param string $deleteType Type of deletion (soft or hard)
	 * @param array $options Deletion options. Currently unused.
	 *
	 * @return XenForo_DataWriter_Discussion_Thread The DW used to delete the thread
	 */
	public function deleteThread($threadId, $deleteType, array $options = array())
	{
		$options = array_merge(array(
			'reason' => ''
		), $options);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
		$dw->setExistingData($threadId);
		if ($deleteType == 'hard')
		{
			$dw->delete();
		}
		else
		{
			$dw->setExtraData(XenForo_DataWriter_Discussion::DATA_DELETE_REASON, $options['reason']);
			$dw->set('discussion_state', 'deleted');
			$dw->save();
		}

		return $dw;
	}

	/**
	 * Rebuilds the thread user post counters for a specific thread. If a user ID is specified,
	 * the counters are only updated for that user.
	 *
	 * @param integer $threadId
	 * @param integer|null $userId
	 */
	public function rebuildThreadUserPostCounters($threadId, $userId = null)
	{
		if ($userId === 0)
		{
			return;
		}

		$db = $this->_getDb();

		$records = $db->fetchPairs('
			SELECT user_id, COUNT(*)
			FROM xf_post
			WHERE thread_id = ?
				AND message_state = \'visible\'
				' . ($userId !== null ? ' AND user_id = ' . $db->quote($userId) : '') . '
			GROUP BY user_id
		', $threadId);

		$this->replaceThreadUserPostCounters($threadId, $records, $userId);
	}

	/**
	 * Replaces the thread counters in a specified thread with the given set.
	 * Old post count records are removed. If a user ID is not null, only that
	 * user's post count record is removed, so the array is must only contain
	 * records for that user.
	 *
	 * @param integer $threadId
	 * @param array $counters [user id] => post count
	 * @param integer|null $userId
	 */
	public function replaceThreadUserPostCounters($threadId, array $counters, $userId = null)
	{
		$db = $this->_getDb();

		XenForo_Db::beginTransaction($db);

		$userIdConstraint = ($userId !== null ? ' AND user_id = ' . $db->quote($userId) : ' AND 1=1');
		$db->delete('xf_thread_user_post', 'thread_id = ' . $db->quote($threadId) . $userIdConstraint);

		foreach ($counters AS $userId => $count)
		{
			if (!$userId)
			{
				continue;
			}

			$db->insert('xf_thread_user_post', array(
				'thread_id' => $threadId,
				'user_id' => $userId,
				'post_count' => $count
			));
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Modifies the count of posts a user has made in a thread.
	 *
	 * @param integer $threadId
	 * @param integer $userId
	 * @param integer $modifyValue How to modify the count (eg, 1 or -1)
	 */
	public function modifyThreadUserPostCount($threadId, $userId, $modifyValue)
	{
		$userId = intval($userId);
		if (!$userId)
		{
			return false;
		}

		$db = $this->_getDb();

		$postCount = $db->fetchOne('
			SELECT post_count
			FROM xf_thread_user_post
			WHERE thread_id = ?
				AND user_id = ?
		', array($threadId, $userId));

		if (!$modifyValue)
		{
			return $postCount;
		}

		if ($postCount === false)
		{
			// insert
			if ($modifyValue < 0)
			{
				return false;
			}

			$db->insert('xf_thread_user_post', array(
				'thread_id' => $threadId,
				'user_id' => $userId,
				'post_count' => $modifyValue
			));

			return $modifyValue;
		}
		else
		{
			// update
			$finalValue = $postCount + $modifyValue;

			$condition = 'thread_id = ' . $db->quote($threadId) . ' AND user_id = ' . $db->quote($userId);

			if ($finalValue <= 0)
			{
				$db->delete('xf_thread_user_post', $condition);
				return false;
			}
			else
			{
				$db->update('xf_thread_user_post', array('post_count' => $finalValue), $condition);
				return $finalValue;
			}
		}
	}

	/**
	 * From a list of thread IDs, gets info about the threads and
	 * the forums the threads are in.
	 *
	 * If a permission combination ID is passed, the forums will retrieve permission info.
	 *
	 * @param array $threadIds List of thread Ids
	 * @param integer $permissionCombinationId Permission combination ID that will be retrieved with the forums, into nodePermissions.
	 *
	 * @return array Format: [0] => list of threads, [1] => list of forums
	 */
	public function getThreadsAndParentData(array $threadIds, $permissionCombinationId = null)
	{
		if ($permissionCombinationId === null)
		{
			$visitor = XenForo_Visitor::getInstance();
			$permissionCombinationId = $visitor['permission_combination_id'];
		}

		$forums = array();
		$threads = $this->getThreadsByIds($threadIds);

		if ($threads)
		{
			$forumIds = array();
			foreach ($threads AS $thread)
			{
				$forumIds[] = $thread['node_id'];
			}

			$forums = $this->_getForumModel()->getForumsByIds(
				$forumIds, array('permissionCombinationId' => $permissionCombinationId)
			);

			foreach ($forums AS &$forum)
			{
				$forum['nodePermissions'] = isset($forum['node_permission_cache'])
					? XenForo_Permission::unserializePermissions($forum['node_permission_cache'])
					: array()
				;
			}

			foreach ($threads AS $threadId => $thread)
			{
				if (!isset($forums[$thread['node_id']]))
				{
					unset($threads[$threadId]);
				}
			}
		}

		return array($threads, $forums);
	}

	/**
	 * Marks the given thread as read up to a certain point (usually the most recent post read).
	 * Thread must have thread_read_date key. (Forum should have forum_read_date key.)
	 *
	 * @param array $thread Thread info
	 * @param array $forum Forum info
	 * @param integer $readDate Timestamp to mark
	 * @param array|null $viewingUser
	 *
	 * @return boolean True if marked as read
	 */
	public function markThreadRead(array $thread, array $forum, $readDate, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$userId = $viewingUser['user_id'];
		if (!$userId)
		{
			return false;
		}

		if (!array_key_exists('thread_read_date', $thread))
		{
			$thread['thread_read_date'] = $this->getUserThreadReadDate($userId, $thread['thread_id']);
		}

		if ($readDate <= $this->getMaxThreadReadDate($thread, $forum))
		{
			return false;
		}

		$this->_getDb()->query('
			INSERT INTO xf_thread_read
				(user_id, thread_id, thread_read_date)
			VALUES
				(?, ?, ?)
			ON DUPLICATE KEY UPDATE thread_read_date = VALUES(thread_read_date)
		', array($userId, $thread['thread_id'], $readDate));

		if ($readDate < $thread['last_post_date'])
		{
			// we haven't finished reading this thread - forum won't be read
			return false;
		}

		$this->_getForumModel()->markForumReadIfNeeded($forum, $viewingUser);

		return true;
	}

	/**
	 * Get the time when a user has marked the given thread as read.
	 *
	 * @param integer $userId
	 * @param integer $threadId
	 *
	 * @return integer|null Null if guest; timestamp otherwise
	 */
	public function getUserThreadReadDate($userId, $threadId)
	{
		if (!$userId)
		{
			return null;
		}

		$readDate = $this->_getDb()->fetchOne('
			SELECT thread_read_date
			FROM xf_thread_read
			WHERE user_id = ?
				AND thread_id = ?
		', array($userId, $threadId));

		$autoReadDate = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);
		return max($readDate, $autoReadDate);
	}

	/**
	 * Get the maximum thread read timestamp based on when the thread/forum has been read.
	 *
	 * @param array $thread
	 * @param array $forum
	 *
	 * @return integer Read timestamp (may be 0)
	 */
	public function getMaxThreadReadDate(array $thread, array $forum)
	{
		$readOptions = array(0);
		if (isset($thread['thread_read_date'])) { $readOptions[] = $thread['thread_read_date']; }
		if (isset($forum['forum_read_date'])) { $readOptions[] = $forum['forum_read_date']; }

		return max($readOptions);
	}

	/**
	 * Merge multiple threads into a single thread
	 *
	 * @param array $threads
	 * @param integer $targetThreadId
	 * @param array $options
	 *
	 * @return boolean|array False if failure, otherwise thread array of merged thread
	 */
	public function mergeThreads(array $threads, $targetThreadId, array $options = array())
	{
		if (!isset($threads[$targetThreadId]))
		{
			return false;
		}

		$targetThread = $threads[$targetThreadId];
		unset($threads[$targetThreadId]);

		$mergeFromThreadIds = array_keys($threads);
		if (!$mergeFromThreadIds)
		{
			return false;
		}

		$options = array_merge(
			array(
				'redirect' => false,
				'redirectExpiry' => 0,
				'log' => true,

				'starterAlert' => false,
				'starterAlertReason' => ''
			),
			$options
		);

		$postModel = $this->_getPostModel();
		$db = $this->_getDb();

		$movePosts = $this->fetchAllKeyed('
			SELECT post_id, thread_id, user_id, message_state
			FROM xf_post
			WHERE thread_id IN (' . $db->quote($mergeFromThreadIds) . ')
		', 'post_id');
		$movePostIds = array_keys($movePosts);

		XenForo_Db::beginTransaction($db);

		$idsQuoted = $db->quote($mergeFromThreadIds);

		$db->update('xf_post',
			array('thread_id' => $targetThreadId),
			'post_id IN (' . $db->quote($movePostIds) . ')'
		);
		$db->query("
			UPDATE IGNORE xf_thread_watch SET
				thread_id = ?
			WHERE thread_id IN ($idsQuoted)
		", array($targetThreadId));
		$db->query("
			UPDATE IGNORE xf_thread_reply_ban SET
				thread_id = ?
			WHERE thread_id IN ($idsQuoted)
		", array($targetThreadId));

		$newCounters = $postModel->recalculatePostPositionsInThread($targetThreadId);
		if (!$newCounters['firstPostId'])
		{
			XenForo_Db::rollback($db);
			return false;
		}

		// TODO: user message counts will go off if merging from a visible thread into a hidden one or vice versa
		// TODO: user message counts will also go off if merging from a thread in a counting forum into a non-counting forum, or vice versa

		$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
		$threadDw->setExistingData($targetThreadId);
		$threadDw->rebuildDiscussionCounters(
			$newCounters['visibleCount'] - 1, $newCounters['firstPostId'], $newCounters['lastPostId']
		);
		$viewCount = $threadDw->get('view_count');
		foreach ($threads AS $thread)
		{
			$viewCount += $thread['view_count'];
		}
		$threadDw->set('view_count', $viewCount);

		if ($threadDw->get('discussion_type') == '')
		{
			foreach ($threads AS $thread)
			{
				if ($thread['discussion_type'] == 'poll')
				{
					$pollMoved = $db->update('xf_poll',
						array('content_id' => $targetThreadId),
						"content_type = 'thread' AND content_id = " . $db->quote($thread['thread_id'])
					);
					if ($pollMoved)
					{
						$threadDw->set('discussion_type', 'poll');
						break;
					}
				}
			}
		}

		$threadDw->save();
		$targetThread = $threadDw->getMergedData();

		foreach ($threads AS $thread)
		{
			if ($thread['discussion_state'] == 'visible' && $options['starterAlert'])
			{
				$this->sendModeratorActionAlert('merge', $thread, $options['starterAlertReason'], array(
					'targetTitle' => $targetThread['title'],
					'targetLink' => XenForo_Link::buildPublicLink('threads', $targetThread)
				));
			}
		}

		if ($options['redirect'])
		{
			$targetUrl = XenForo_Link::buildPublicLink('threads', $targetThread);
			$redirectKey = "thread-$targetThread[thread_id]-";

			foreach ($threads AS $thread)
			{
				$redirectDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
				$redirectDw->setExistingData($thread, true);
				$redirectDw->set('discussion_type', 'redirect');
				$redirectDw->save();

				$this->getModelFromCache('XenForo_Model_ThreadRedirect')->insertThreadRedirect(
					$thread['thread_id'], $targetUrl, $redirectKey, $options['redirectExpiry']
				);
			}

			$db->delete('xf_thread_watch', "thread_id IN ($idsQuoted)");
			$db->delete('xf_thread_reply_ban', "thread_id IN ($idsQuoted)");
			$db->delete('xf_thread_user_post', "thread_id IN ($idsQuoted)");
			$db->delete('xf_poll', "content_type = 'thread' AND content_id IN ($idsQuoted)");
		}
		else
		{
			foreach ($threads AS $thread)
			{
				$deleteDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
				$deleteDw->setExistingData($thread, true);
				$deleteDw->delete();
			}
		}

		$forumIds = array();
		foreach ($threads AS $thread)
		{
			$forumIds[$thread['node_id']] = $thread['node_id'];
		}
		foreach ($forumIds AS $forumId)
		{
			$forumDw = XenForo_DataWriter::create('XenForo_DataWriter_Forum', XenForo_DataWriter::ERROR_SILENT);
			$forumDw->setExistingData($forumId);
			$forumDw->rebuildCounters();
			$forumDw->save();
		}

		$this->replaceThreadUserPostCounters($targetThreadId, $newCounters['userPosts']);

		XenForo_Application::defer('SearchIndexPartial', array('contentType' => 'post', 'contentIds' => $movePostIds));

		if ($options['log'])
		{
			XenForo_Model_Log::logModeratorAction(
				'thread', $targetThread, 'merge_target', array('ids' => implode(', ', array_keys($threads)))
			);
		}

		XenForo_Db::commit($db);

		return $targetThread;
	}

	/**
	 * Logs the viewing of a thread.
	 *
	 * @param integer $threadId
	 */
	public function logThreadView($threadId)
	{
		$this->_getDb()->query('
			INSERT ' . (XenForo_Application::get('options')->enableInsertDelayed ? 'DELAYED' : '') . ' INTO xf_thread_view
				(thread_id)
			VALUES
				(?)
		', $threadId);
	}

	/**
	 * Updates thread views in bulk.
	 */
	public function updateThreadViews()
	{
		$db = $this->_getDb();

		$db->query('
			UPDATE xf_thread
			INNER JOIN (
				SELECT thread_id, COUNT(*) AS total
				FROM xf_thread_view
				GROUP BY thread_id
			) AS xf_tv ON (xf_tv.thread_id = xf_thread.thread_id)
			SET xf_thread.view_count = xf_thread.view_count + xf_tv.total
		');

		$db->query('TRUNCATE TABLE xf_thread_view');
	}

	/**
	 * Returns the last few page numbers of a thread
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
	 * Use the 'thread_is_watched' info in a thread array to get the watch state text
	 *
	 * @param array $thread
	 * @param boolean $useDefaultIfNotWatching
	 * @param array $viewingUser
	 *
	 * @return string
	 */
	public function getThreadWatchStateFromThread(array $thread, $useDefaultIfNotWatching = true, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!empty($thread['thread_is_watched']))
		{
			return $thread['thread_is_watched'];
		}
		else if ($useDefaultIfNotWatching)
		{
			return $viewingUser['default_watch_state'];
		}
		else
		{
			return '';
		}
	}

	/**
	 * Sends an alert to a user for a moderator action.
	 * The alert will be viewable even if the thread isn't.
	 *
	 * The alert content type will always be user.
	 *
	 * @param string $action Alert will become thread_<action>
	 * @param array $thread
	 * @param string $reason
	 * @param array $extra
	 * @param null|integer $alertUserId If null, alerts the thread starter
	 *
	 * @return bool
	 */
	public function sendModeratorActionAlert($action, array $thread, $reason = '', array $extra = array(), $alertUserId = null)
	{
		$extra = array_merge(array(
			'title' => $thread['title'],
			'link' => XenForo_Link::buildPublicLink('threads', $thread),
			'reason' => $reason
		), $extra);

		if ($alertUserId === null)
		{
			$alertUserId = $thread['user_id'];
		}

		if (!$alertUserId)
		{
			return false;
		}

		XenForo_Model_Alert::alert(
			$alertUserId,
			0, '',
			'user', $alertUserId,
			'thread_' . $action,
			$extra
		);
		return true;
	}

	public function getThreadReplyBansByThreadId($threadId)
	{
		$conditions = array('thread_id' => $threadId);
		$fetchOptions = array('order' => 'ban_date');

		return $this->getThreadReplyBans($conditions, $fetchOptions);
	}

	public function getThreadReplyBans(array $conditions, array $fetchOptions = array())
	{
		$whereConditions = $this->prepareThreadBanConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareThreadBanFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults('
			SELECT reply_ban.*,
				user.*, ban_user.username AS ban_username
				' . $sqlClauses['selectFields'] . '
			FROM xf_thread_reply_ban AS reply_ban
			LEFT JOIN xf_user AS user ON (user.user_id = reply_ban.user_id)
			LEFT JOIN xf_user AS ban_user ON (ban_user.user_id = reply_ban.ban_user_id)
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereConditions . '
			' . $sqlClauses['orderClause'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'thread_reply_ban_id');

	}

	public function countThreadReplyBans(array $conditions)
	{
		$fetchOptions = array();
		$whereConditions = $this->prepareThreadBanConditions($conditions, $fetchOptions);
		$sqlClauses = $this->prepareThreadBanFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_thread_reply_ban AS reply_ban
			' . $sqlClauses['joinTables'] . '
			WHERE ' . $whereConditions . '
		');
	}

	public function prepareThreadBanConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['thread_id']))
		{
			$sqlConditions[] = 'reply_ban.thread_id = ' . $db->quote($conditions['thread_id']);
		}
		if (!empty($conditions['user_id']))
		{
			$sqlConditions[] = 'reply_ban.user_id = ' . $db->quote($conditions['user_id']);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function prepareThreadBanFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_REPLY_BAN_FORUM)
			{
				$selectFields .= ',
					node.*, forum.*, node.title AS node_title, thread.title, thread.prefix_id';
				$joinTables .= '
					INNER JOIN xf_thread AS thread ON
						(thread.thread_id = reply_ban.thread_id)
					INNER JOIN xf_node AS node ON
						(node.node_id = thread.node_id)
					INNER JOIN xf_forum AS forum ON
						(forum.node_id = thread.node_id)';
			}
		}

		$orderBy = '';
		if (!empty($fetchOptions['order']))
		{
			switch ($fetchOptions['order'])
			{
				case 'ban_date':
				default:
					$orderBy = 'reply_ban.ban_date';
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] == 'desc')
			{
				$orderBy .= ' DESC';
			}
			else
			{
				$orderBy .= ' ASC';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables,
			'orderClause'  => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}

	/**
	 * Bans a specific user from replying to a thread for the specified amount of time
	 *
	 * @param array $thread
	 * @param array $user
	 * @param null|integer $expiryDate
	 * @param string $reason
	 * @param boolean $sendAlert
	 * @param null|integer $banUserId
	 *
	 * @return bool
	 */
	public function insertThreadReplyBan(array $thread, array $user, $expiryDate = null, $reason = '', $sendAlert = false, $banUserId = null)
	{
		if (!$user['user_id'])
		{
			return false;
		}
		if ($expiryDate && $expiryDate < XenForo_Application::$time)
		{
			return false;
		}

		if (!$banUserId)
		{
			$banUserId = XenForo_Visitor::getUserId();
		}

		$reason = utf8_substr($reason, 0, 100);

		$this->_getDb()->query("
			INSERT INTO xf_thread_reply_ban
				(thread_id, user_id, ban_date, expiry_date, reason, ban_user_id)
			VALUES
				(?, ?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				expiry_date = VALUES(expiry_date),
				ban_user_id = VALUES(ban_user_id)
		", array($thread['thread_id'], $user['user_id'], XenForo_Application::$time, $expiryDate, $reason, $banUserId));

		XenForo_Model_Log::logModeratorAction(
			'thread', $thread, 'reply_ban', array('name' => $user['username'], 'reason' => $reason)
		);

		if ($sendAlert)
		{
			XenForo_Model_Alert::alert(
				$user['user_id'],
				0, '',
				'thread', $thread['thread_id'],
				'reply_ban', array('reason' => $reason, 'expiry' => $expiryDate)
			);
		}

		return true;
	}

	public function deleteThreadReplyBan(array $thread, array $user)
	{
		$res = $this->_getDb()->query("
			DELETE FROM xf_thread_reply_ban
			WHERE thread_id = ?
				AND user_id = ?
		", array($thread['thread_id'], $user['user_id']));

		if ($res->rowCount())
		{
			XenForo_Model_Log::logModeratorAction(
				'thread', $thread, 'reply_ban_delete', array('name' => $user['username'])
			);

			/** @var XenForo_Model_Alert $alertModel */
			$alertModel = $this->getModelFromCache('XenForo_Model_Alert');
			$alertModel->deleteAlerts('thread', $thread['thread_id'], $user['user_id'], 'reply_ban');

			return true;
		}
		else
		{
			return false;
		}
	}

	public function cleanUpExpiredThreadReplyBans($expiryDate = null)
	{
		if ($expiryDate === null)
		{
			$expiryDate = XenForo_Application::$time;
		}

		$this->_getDb()->query("
			DELETE FROM xf_thread_reply_ban
			WHERE expiry_date <= ?
		", $expiryDate);
	}

	/**
	 * Gets the date of the earliest posted thread in the database
	 *
	 * @return integer
	 */
	public function getEarliestThreadDate()
	{
		return (int)$this->_getDb()->fetchOne('SELECT MIN(post_date) FROM xf_thread');
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}

	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}
}