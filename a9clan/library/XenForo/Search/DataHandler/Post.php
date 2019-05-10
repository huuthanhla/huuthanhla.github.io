<?php

/**
 * Handles searching of posts.
 *
 * @package XenForo_Search
 */
class XenForo_Search_DataHandler_Post extends XenForo_Search_DataHandler_Abstract
{
	/**
	 * @var XenForo_Model_Post
	 */
	protected $_postModel = null;

	/**
	 * @var XenForo_Model_Thread
	 */
	protected $_threadModel = null;

	/**
	 * Inserts into (or replaces a record) in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_insertIntoIndex()
	 */
	protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
	{
		if ($data['message_state'] != 'visible')
		{
			return;
		}

		$metadata = array();
		$title = '';

		if ($parentData)
		{
			$thread = $parentData;
			if ($thread['discussion_state'] != 'visible')
			{
				return;
			}

			if ($data['post_id'] == $thread['first_post_id'] || $thread['first_post_id'] === 0)
			{
				$title = $thread['title'];
				if (!empty($thread['prefix_id']))
				{
					$metadata['prefix'] = $thread['prefix_id'];
				}
			}

			$metadata['node'] = $thread['node_id'];
		}

		$metadata['thread'] = $data['thread_id'];

		$indexer->insertIntoIndex(
			'post', $data['post_id'],
			$title, $data['message'],
			$data['post_date'], $data['user_id'], $data['thread_id'], $metadata
		);
	}

	/**
	 * Updates a record in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_updateIndex()
	 */
	protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
	{
		$indexer->updateIndex('post', $data['post_id'], $fieldUpdates);
	}

	/**
	 * Deletes one or more records from the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_deleteFromIndex()
	 */
	protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
	{
		$postIds = array();
		foreach ($dataList AS $data)
		{
			$postIds[] = is_array($data) ? $data['post_id'] : $data;
		}

		$indexer->deleteFromIndex('post', $postIds);
	}

	/**
	 * Rebuilds the index for a batch.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::rebuildIndex()
	 */
	public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
	{
		$postIds = $this->_getPostModel()->getPostIdsInRange($lastId, $batchSize);
		if (!$postIds)
		{
			return false;
		}

		$this->quickIndex($indexer, $postIds);

		return max($postIds);
	}

	/**
	 * Rebuilds the index for the specified content.

	 * @see XenForo_Search_DataHandler_Abstract::quickIndex()
	 */
	public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
	{
		$posts = $this->_getPostModel()->getPostsByIds($contentIds);

		$threadIds = array();
		foreach ($posts AS $post)
		{
			$threadIds[] = $post['thread_id'];
		}

		$threads = $this->_getThreadModel()->getThreadsByIds(array_unique($threadIds));

		foreach ($posts AS $post)
		{
			$thread = (isset($threads[$post['thread_id']]) ? $threads[$post['thread_id']] : null);
			if (!$thread)
			{
				continue;
			}

			$this->insertIntoIndex($indexer, $post, $thread);
		}

		return true;
	}

	public function getInlineModConfiguration()
	{
		return array(
			'name' => new XenForo_Phrase('post'),
			'route' => 'inline-mod/post/switch',
			'cookie' => 'posts',
			'template' => 'inline_mod_controls_post'
		);
	}

	/**
	 * Gets the type-specific data for a collection of results of this content type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getDataForResults()
	 */
	public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
	{
		$postModel = $this->_getPostModel();

		$posts = $postModel->getPostsByIds($ids, array(
			'join' => XenForo_Model_Post::FETCH_THREAD | XenForo_Model_Post::FETCH_FORUM | XenForo_Model_Post::FETCH_USER,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));

		$posts = $postModel->unserializePermissionsInList($posts, 'node_permission_cache');
		foreach ($posts AS $postId => $post)
		{
			if ($post['post_id'] == $post['first_post_id'] && isset($resultsGrouped['thread'][$post['thread_id']]))
			{
				// matched first post and thread, skip the post
				unset($posts[$postId]);
			}
		}

		return $posts;
	}

	/**
	 * Determines if this result is viewable.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::canViewResult()
	 */
	public function canViewResult(array $result, array $viewingUser)
	{
		return $this->_getPostModel()->canViewPostAndContainer(
			$result, $result, $result, $null, $result['permissions'], $viewingUser
		);
	}

	/**
	 * Prepares a result for display.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::prepareResult()
	 */
	public function prepareResult(array $result, array $viewingUser)
	{
		$result = $this->_getPostModel()->preparePost($result, $result, $result, $result['permissions'], $viewingUser);
		$result['title'] = XenForo_Helper_String::censorString($result['title']);

		return $result;
	}

	public function addInlineModOption(array &$result)
	{
		return $this->_getPostModel()->addInlineModOptionToPost($result, $result, $result, $result['permissions']);
	}

	/**
	 * Gets the date of the result (from the result's content).
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getResultDate()
	 */
	public function getResultDate(array $result)
	{
		return $result['post_date'];
	}

	/**
	 * Renders a result to HTML.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::renderResult()
	 */
	public function renderResult(XenForo_View $view, array $result, array $search)
	{
		return $view->createTemplateObject('search_result_post', array(
			'post' => $result,
			'thread' => $result,
			'forum' => array(
				'node_id' => $result['node_id'],
				'title' => $result['node_title'],
				'node_name' => $result['node_name']
			),
			'search' => $search,
			'enableInlineMod' => $this->_inlineModEnabled
		));
	}

	/**
	 * Gets the content types searched in a type-specific search.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getSearchContentTypes()
	 */
	public function getSearchContentTypes()
	{
		return array('post', 'thread');
	}

	/**
	 * Get type-specific constraints from input.
	 *
	 * @param XenForo_Input $input
	 *
	 * @return array
	 */
	public function getTypeConstraintsFromInput(XenForo_Input $input)
	{
		$constraints = array();

		$replyCount = $input->filterSingle('reply_count', XenForo_Input::UINT);
		if ($replyCount)
		{
			$constraints['reply_count'] = $replyCount;
		}

		$prefixes = $input->filterSingle('prefixes', XenForo_Input::UINT, array('array' => true));
		if ($prefixes && reset($prefixes))
		{
			$prefixes = array_unique($prefixes);
			$constraints['prefix'] = implode(' ', $prefixes);
			if (!$constraints['prefix'])
			{
				unset($constraints['prefix']); // just 0
			}
		}

		$threadId = $input->filterSingle('thread_id', XenForo_Input::UINT);
		if ($threadId)
		{
			$constraints['thread'] = $threadId;

			// undo things that don't make sense with this
			$constraints['titles_only'] = false;
		}

		return $constraints;
	}

	/**
	 * Process a type-specific constraint.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::processConstraint()
	 */
	public function processConstraint(XenForo_Search_SourceHandler_Abstract $sourceHandler, $constraint, $constraintInfo, array $constraints)
	{
		switch ($constraint)
		{
			case 'reply_count':
				$replyCount = intval($constraintInfo);
				if ($replyCount > 0)
				{
					return array(
						'query' => array('thread', 'reply_count', '>=', $replyCount)
					);
				}
				break;

			case 'prefix':
				if ($constraintInfo)
				{
					return array(
						'metadata' => array('prefix', preg_split('/\D+/', strval($constraintInfo))),
					);
				}
				break;

			case 'thread':
				$threadId = intval($constraintInfo);
				if ($threadId > 0)
				{
					return array(
						'metadata' => array('thread', $threadId)
					);
				}
				break;
		}

		return false;
	}

	/**
	 * Gets the search form controller response for this type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getSearchFormControllerResponse()
	 */
	public function getSearchFormControllerResponse(XenForo_ControllerPublic_Abstract $controller, XenForo_Input $input, array $viewParams)
	{
		$params = $input->filterSingle('c', XenForo_Input::ARRAY_SIMPLE);

		$viewParams['search']['reply_count'] = empty($params['reply_count']) ? '' : $params['reply_count'];

		if (!empty($params['prefix']))
		{
			$viewParams['search']['prefixes'] = array_fill_keys(explode(' ', $params['prefix']), true);
		}
		else
		{
			$viewParams['search']['prefixes'] = array();
		}

		/** @var $threadPrefixModel XenForo_Model_ThreadPrefix */
		$threadPrefixModel = XenForo_Model::create('XenForo_Model_ThreadPrefix');

		$viewParams['prefixes'] = $threadPrefixModel->getPrefixesByGroups();
		if ($viewParams['prefixes'])
		{
			$visiblePrefixes = $threadPrefixModel->getVisiblePrefixIds();
			foreach ($viewParams['prefixes'] AS $key => &$prefixes)
			{
				foreach ($prefixes AS $prefixId => $prefix)
				{
					if (!isset($visiblePrefixes[$prefixId]))
					{
						unset($prefixes[$prefixId]);
					}
				}

				if (!count($prefixes))
				{
					unset($viewParams['prefixes'][$key]);
				}
			}
		}

		$viewParams['search']['thread'] = array();
		if (!empty($params['thread']))
		{
			$threadModel = $this->_getThreadModel();

			$thread = $threadModel->getThreadById($params['thread'], array(
				'join' => XenForo_Model_Thread::FETCH_FORUM,
				'permissionCombinationId' => XenForo_Visitor::getPermissionCombinationId(),
			));

			if ($thread)
			{
				$permissions = XenForo_Permission::unserializePermissions($thread['node_permission_cache']);

				if ($threadModel->canViewThreadAndContainer($thread, $thread, $null, $permissions))
				{
					$viewParams['search']['thread'] = $this->_getThreadModel()->getThreadById($params['thread']);
				}
			}
		}

		return $controller->responseView('XenForo_ViewPublic_Search_Form_Post', 'search_form_post', $viewParams);
	}

	/**
	 * Gets the search order for a type-specific search.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getOrderClause()
	 */
	public function getOrderClause($order)
	{
		if ($order == 'replies')
		{
			return array(
				array('thread', 'reply_count', 'desc'),
				array('search_index', 'item_date', 'desc')
			);
		}

		return false;
	}

	/**
	 * Gets the necessary join structure information for this type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getJoinStructures()
	 */
	public function getJoinStructures(array $tables)
	{
		$structures = array();
		if (isset($tables['thread']))
		{
			$structures['thread'] = array(
				'table' => 'xf_thread',
				'key' => 'thread_id',
				'relationship' => array('search_index', 'discussion_id'),
			);
		}

		return $structures;
	}

	/**
	 * Gets the content type that will be used when grouping for this type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getGroupByType()
	 */
	public function getGroupByType()
	{
		return 'thread';
	}

	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		if (!$this->_postModel)
		{
			$this->_postModel = XenForo_Model::create('XenForo_Model_Post');
		}

		return $this->_postModel;
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		if (!$this->_threadModel)
		{
			$this->_threadModel = XenForo_Model::create('XenForo_Model_Thread');
		}

		return $this->_threadModel;
	}
}