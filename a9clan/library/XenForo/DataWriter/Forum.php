<?php
/**
* Data writer for Forums.
*
* @package XenForo_Forum
*/
class XenForo_DataWriter_Forum extends XenForo_DataWriter_Node implements XenForo_DataWriter_DiscussionContainerInterface
{
	const OPTION_DELETE_THREADS = 'deleteThreads';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_forum_not_found';

	/**
	 * Returns all xf_node fields, plus forum-specific fields
	 */
	protected function _getFields()
	{
		return parent::_getFields() + array('xf_forum' => array(

			'node_id'            => array('type' => self::TYPE_UINT, 'default' => array('xf_node', 'node_id'), 'required' => true),

			// denormalized counters
			'discussion_count'   => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
			'message_count'      => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),

			// denormalized last post info
			'last_post_id'       => array('type' => self::TYPE_UINT,   'default' => 0),
			'last_post_date'     => array('type' => self::TYPE_UINT,   'default' => 0),
			'last_post_user_id'  => array('type' => self::TYPE_UINT,   'default' => 0),
			'last_post_username' => array('type' => self::TYPE_STRING, 'maxLength' => 50, 'default' => ''),
			'last_thread_title'  => array('type' => self::TYPE_STRING, 'maxLength' => 150, 'default' => ''),

			// options
			'moderate_threads'   => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
			'moderate_replies'   => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
			'allow_posting'      => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			'allow_poll'         => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			'count_messages'     => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			'find_new'           => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			'require_prefix'     => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
			'allowed_watch_notifications' => array('type' => self::TYPE_STRING, 'default' => 'all',
				'allowedValues' => array('all', 'thread', 'none')
			),

			// other
			'prefix_cache'       => array('type' => self::TYPE_SERIALIZED, 'default' => ''),
			'default_prefix_id'  => array('type' => self::TYPE_UINT, 'default' => 0),

			'default_sort_order' => array('type' => self::TYPE_STRING, 'default' => 'last_post_date',
				'allowedValues' => array('title', 'post_date', 'reply_count', 'view_count', 'last_post_date')
			),
			'default_sort_direction' => array('type' => self::TYPE_STRING, 'default' => 'desc',
				'allowedValues' => array('asc', 'desc')
			),
			'list_date_limit_days' => array('type' => self::TYPE_UINT_FORCED, 'default' => 0, 'max' => 3650),
		));
	}

	protected function _getDefaultOptions()
	{
		$options = parent::_getDefaultOptions();
		$options[self::OPTION_DELETE_THREADS] = true;

		return $options;
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
		if (!$nodeId = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		$forum = $this->getModelFromCache('XenForo_Model_Forum')->getForumById($nodeId);
		if (!$forum)
		{
			return false;
		}

		return $this->getTablesDataFromArray($forum);
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		$nodeId = $this->get('node_id');

		$db = $this->_db;
		$db->delete('xf_forum_prefix', 'node_id = ' . $db->quote($nodeId));
		$db->delete('xf_forum_watch', 'node_id = ' . $db->quote($nodeId));

		if ($this->getOption(self::OPTION_DELETE_THREADS))
		{
			XenForo_Application::defer('ThreadDelete', array('node_id' => $nodeId), "threadDelete_$nodeId", true);
		}
	}

	/**
	 * Implemented for {@see XenForo_DataWriter_DiscussionContainerInterface}.
	 */
	public function updateCountersAfterDiscussionSave(XenForo_DataWriter_Discussion $discussionDw, $forceInsert = false)
	{
		if ($discussionDw->get('discussion_type') == 'redirect')
		{
			// note: this assumes the discussion type will never change to/from this except at creation
			return;
		}

		if ($discussionDw->get('discussion_state') == 'visible'
			&& ($discussionDw->getExisting('discussion_state') != 'visible' || $forceInsert)
		)
		{
			$this->set('discussion_count', $this->get('discussion_count') + 1);
			$this->set('message_count', $this->get('message_count') + $discussionDw->get('reply_count') + 1);
		}
		else if ($discussionDw->getExisting('discussion_state') == 'visible' && $discussionDw->get('discussion_state') != 'visible')
		{
			$this->set('discussion_count', $this->get('discussion_count') - 1);
			$this->set('message_count', $this->get('message_count') - $discussionDw->get('reply_count') - 1);

			if ($discussionDw->get('last_post_id') == $this->get('last_post_id'))
			{
				$this->updateLastPost();
			}
		}
		else if ($discussionDw->get('discussion_state') == 'visible' && $discussionDw->getExisting('discussion_state') == 'visible')
		{
			// no state change, probably just a reply
			$messageChange = $discussionDw->get('reply_count') - $discussionDw->getExisting('reply_count');
			$this->set('message_count', $this->get('message_count') + $messageChange);
		}

		if ($discussionDw->get('discussion_state') == 'visible' && $discussionDw->get('last_post_date') >= $this->get('last_post_date'))
		{
			$this->set('last_post_date', $discussionDw->get('last_post_date'));
			$this->set('last_post_id', $discussionDw->get('last_post_id'));
			$this->set('last_post_user_id', $discussionDw->get('last_post_user_id'));
			$this->set('last_post_username', $discussionDw->get('last_post_username'));
			$this->set('last_thread_title', $discussionDw->get('title'));
		}
		else if ($discussionDw->get('discussion_state') == 'visible'
			&& $discussionDw->getExisting('discussion_state') == 'visible'
			&& $discussionDw->getExisting('last_post_id') == $this->get('last_post_id')
			&& ($discussionDw->isChanged('last_post_id') || $discussionDw->isChanged('title'))
		)
		{
			$this->updateLastPost();
		}
	}

	/**
	 * Implemented for {@see XenForo_DataWriter_DiscussionContainerInterface}.
	 */
	public function updateCountersAfterDiscussionDelete(XenForo_DataWriter_Discussion $discussionDw)
	{
		if ($discussionDw->get('discussion_type') == 'redirect')
		{
			// note: this assumes the discussion type will never change to/from this except at creation
			return;
		}

		// need to check both of these -- there's a case where we approve a moderated thread
		// and move it simultaneously and this code gets triggered
		if ($discussionDw->get('discussion_state') == 'visible' && $discussionDw->getExisting('discussion_state') == 'visible')
		{
			$this->set('discussion_count', $this->get('discussion_count') - 1);
			$this->set('message_count', $this->get('message_count') - $discussionDw->get('reply_count') - 1);

			if ($discussionDw->get('last_post_id') == $this->get('last_post_id'))
			{
				$this->updateLastPost();
			}
		}
	}

	/**
	 * Updates the last post information for this forum.
	 */
	public function updateLastPost()
	{
		$lastPost = $this->getModelFromCache('XenForo_Model_Thread')->getLastUpdatedThreadInForum($this->get('node_id'));
		if ($lastPost)
		{
			$this->set('last_post_id', $lastPost['last_post_id']);
			$this->set('last_post_date', $lastPost['last_post_date']);
			$this->set('last_post_user_id', $lastPost['last_post_user_id']);
			$this->set('last_post_username', $lastPost['last_post_username']);
			$this->set('last_thread_title', $lastPost['title']);
		}
		else
		{
			$this->set('last_post_id',0);
			$this->set('last_post_date', 0);
			$this->set('last_post_user_id', 0);
			$this->set('last_post_username', '');
			$this->set('last_thread_title', '');
		}
	}

	/**
	 * Rebuilds the counters for this forum.
	 */
	public function rebuildCounters()
	{
		$this->updateLastPost();
		$this->bulkSet($this->getModelFromCache('XenForo_Model_Forum')->getForumCounters($this->get('node_id')));
	}
}