<?php

class XenForo_ControllerAdmin_Forum extends XenForo_ControllerAdmin_NodeAbstract
{
	/**
	 * Name of the DataWriter that will handle this node type
	 *
	 * @var string
	 */
	protected $_nodeDataWriterName = 'XenForo_DataWriter_Forum';

	public function actionIndex()
	{
		return $this->responseReroute('XenForo_ControllerAdmin_Node', 'index');
	}

	public function actionAdd()
	{
		return $this->responseReroute('XenForo_ControllerAdmin_Forum', 'edit');
	}

	public function actionEdit()
	{
		$forumModel = $this->_getForumModel();
		$nodeModel = $this->_getNodeModel();
		$prefixModel = $this->_getPrefixModel();

		if ($nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT))
		{
			// if a node ID was specified, we should be editing, so make sure a forum exists
			$forum = $forumModel->getForumById($nodeId);
			if (!$forum)
			{
				return $this->responseError(new XenForo_Phrase('requested_forum_not_found'), 404);
			}

			$nodePrefixes = array_keys($prefixModel->getPrefixesInForum($nodeId));
		}
		else
		{
			// add a new forum
			$forum = array(
				'parent_node_id' => $this->_input->filterSingle('parent_node_id', XenForo_Input::UINT),
				'display_order' => 1,
				'display_in_list' => 1,
				'allow_posting' => 1,
				'allow_poll' => 1,
				'find_new' => 1,
				'count_messages' => 1,
				'allowed_watch_notifications' => 'all',
				'default_sort_order' => 'last_post_date',
				'default_sort_direction' => 'desc',
				'list_date_limit_days' => 0
			);

			$nodePrefixes = array();
		}

		$viewParams = array(
			'forum' => $forum,
			'nodeParentOptions' => $nodeModel->getNodeOptionsArray(
				$nodeModel->getPossibleParentNodes($forum), $forum['parent_node_id'], true
			),
			'styles' => $this->_getStyleModel()->getAllStylesAsFlattenedTree(),

			'prefixGroups' => $prefixModel->getPrefixesByGroups(),
			'prefixOptions' => $prefixModel->getPrefixOptions(),
			'nodePrefixes' => ($nodePrefixes ? $nodePrefixes : array(0))
		);

		return $this->responseView('XenForo_ViewAdmin_Forum_Edit', 'forum_edit', $viewParams);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		if ($this->_input->filterSingle('delete', XenForo_Input::STRING))
		{
			return $this->responseReroute('XenForo_ControllerAdmin_Forum', 'deleteConfirm');
		}

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);

		$prefixIds = $this->_input->filterSingle('available_prefixes', XenForo_Input::UINT, array('array' => true));

		$writerData = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'node_name' => XenForo_Input::STRING,
			'node_type_id' => XenForo_Input::STRING,
			'parent_node_id' => XenForo_Input::UINT,
			'display_order' => XenForo_Input::UINT,
			'display_in_list' => XenForo_Input::UINT,
			'description' => XenForo_Input::STRING,
			'style_id' => XenForo_Input::UINT,
			'moderate_threads' => XenForo_Input::UINT,
			'moderate_replies' => XenForo_Input::UINT,
			'allow_posting' => XenForo_Input::UINT,
			'allow_poll' => XenForo_Input::UINT,
			'count_messages' => XenForo_Input::UINT,
			'find_new' => XenForo_Input::UINT,
			'default_prefix_id' => XenForo_Input::UINT,
			'require_prefix' => XenForo_Input::UINT,
			'allowed_watch_notifications' => XenForo_Input::STRING,
			'default_sort_order' => XenForo_Input::STRING,
			'default_sort_direction' => XenForo_Input::STRING,
			'list_date_limit_days' => XenForo_Input::UINT
		));
		if (!$this->_input->filterSingle('style_override', XenForo_Input::UINT))
		{
			$writerData['style_id'] = 0;
		}

		$writer = $this->_getNodeDataWriter();

		if ($nodeId)
		{
			$writer->setExistingData($nodeId);
		}

		if (!in_array($writerData['default_prefix_id'], $prefixIds))
		{
			$writerData['default_prefix_id'] = 0;
		}

		$writer->bulkSet($writerData);
		$writer->save();

		$this->_getPrefixModel()->updatePrefixForumAssociationByForum($writer->get('node_id'), $prefixIds);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('nodes') . $this->getLastHash($writer->get('node_id'))
		);
	}

	public function actionDeleteConfirm()
	{
		$forumModel = $this->_getForumModel();
		$nodeModel = $this->_getNodeModel();

		$forum = $forumModel->getForumById($this->_input->filterSingle('node_id', XenForo_Input::UINT));
		if (!$forum)
		{
			return $this->responseError(new XenForo_Phrase('requested_forum_not_found'), 404);
		}

		$childNodes = $nodeModel->getChildNodes($forum);

		$viewParams = array(
			'forum' => $forum,
			'childNodes' => $childNodes,
			'nodeParentOptions' => $nodeModel->getNodeOptionsArray(
				$nodeModel->getPossibleParentNodes($forum), $forum['parent_node_id'], true
			)
		);

		return $this->responseView('XenForo_ViewAdmin_Forum_Delete', 'forum_delete', $viewParams);
	}

	/**
	 * Fetches a grouped list of all prefixes available to the specified forum
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPrefixes()
	{
		$this->_assertPostOnly();

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);

		$viewParams = array(
			'nodeId' => $nodeId,
			'prefixGroups' => $this->_getPrefixModel()->getUsablePrefixesInForums($nodeId, null, false),
		);

		return $this->responseView('XenForo_ViewAdmin_Forum_Prefixes', '', $viewParams);
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}

	/**
	 * @return XenForo_Model_ThreadPrefix
	 */
	protected function _getPrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}

	/**
	 * @return XenForo_DataWriter_Forum
	 */
	protected function _getNodeDataWriter()
	{
		return XenForo_DataWriter::create($this->_nodeDataWriterName);
	}
}