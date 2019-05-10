<?php

/**
 * Inline moderation actions for posts
 *
 * @package XenForo_Post
 */
class XenForo_ControllerPublic_InlineMod_Post extends XenForo_ControllerPublic_InlineMod_Abstract
{
	/**
	 * Key for inline mod data.
	 *
	 * @var string
	 */
	public $inlineModKey = 'posts';

	/**
	 * @return XenForo_Model_InlineMod_Post
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Post');
	}

	/**
	 * Post deletion handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::STRING);
			$options = array(
				'deleteType' => ($hardDelete ? 'hard' : 'soft'),
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING),
				'authorAlert' => $this->_input->filterSingle('send_author_alert', XenForo_Input::BOOLEAN),
				'authorAlertReason' => $this->_input->filterSingle('author_alert_reason', XenForo_Input::STRING)
			);

			$routeUrlMatch = $this->parseRouteUrl($this->getDynamicRedirect());
			if ($routeUrlMatch && !empty($routeUrlMatch['params']['thread_id']))
			{
				$thread = $this->getModelFromCache('XenForo_Model_Thread')->getThreadById(
					$routeUrlMatch['params']['thread_id'],
					array('join' => XenForo_Model_Thread::FETCH_FORUM)
				);
				if ($thread && in_array($thread['first_post_id'], $this->getInlineModIds(false)))
				{
					// going to be redirecting to the thread and we're deleting it
					$this->_request->setParam('redirect', XenForo_Link::buildPublicLink('forums', $thread));
				}
			}

			return $this->executeInlineModAction('deletePosts', $options, array('fromCookie' => false));
		}
		else // show confirmation dialog
		{
			$postIds = $this->getInlineModIds();

			$handler = $this->_getInlineModPostModel();
			if (!$handler->canDeletePosts($postIds, 'soft', $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$postIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$posts = $this->_getPostModel()->getPostsByIds($postIds, array(
				'join' => XenForo_Model_Post::FETCH_THREAD
			));
			$firstPostCount = 0;
			foreach ($posts AS $post)
			{
				if ($post['post_id'] == $post['first_post_id'])
				{
					$firstPostCount++;
				}
			}

			$viewParams = array(
				'postIds' => $postIds,
				'postCount' => count($postIds),
				'canHardDelete' => $handler->canDeletePosts($postIds, 'hard'),
				'redirect' => $redirect,
				'firstPostCount' => $firstPostCount
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Post_Delete', 'inline_mod_post_delete', $viewParams);
		}
	}

	/**
	 * Undeletes the specified posts.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUndelete()
	{
		return $this->executeInlineModAction('undeletePosts');
	}

	/**
	 * Approves the specified posts.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionApprove()
	{
		return $this->executeInlineModAction('approvePosts');
	}

	/**
	 * Unapproves the specified posts.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnapprove()
	{
		return $this->executeInlineModAction('unapprovePosts');
	}

	/**
	 * Post move handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMove()
	{
		return $this->_moveOrCopyPostsAction(
			'canMovePosts', 'movePosts',
			'XenForo_ViewPublic_InlineMod_Post_Move', 'inline_mod_post_move'
		);
	}

	/**
	 * Post copy handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCopy()
	{
		return $this->_moveOrCopyPostsAction(
			'canCopyPosts', 'copyPosts',
			'XenForo_ViewPublic_InlineMod_Post_Copy', 'inline_mod_post_copy'
		);
	}

	protected function _moveOrCopyPostsAction($permissionMethod, $actionMethod, $viewName, $templateName)
	{
		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'thread_type' => XenForo_Input::STRING,
				'node_id' => XenForo_Input::UINT,
				'title' => XenForo_Input::STRING,
				'prefix_id' => XenForo_Input::UINT,
				'existing_url' => XenForo_Input::STRING,

				'send_author_alert' => XenForo_Input::BOOLEAN,
				'author_alert_reason' => XenForo_Input::STRING
			));

			if ($input['thread_type'] == 'new')
			{
				$viewableNodes = $this->getModelFromCache('XenForo_Model_Node')->getViewableNodeList();
				if (!isset($viewableNodes[$input['node_id']]))
				{
					return $this->responseNoPermission();
				}

				$options = array(
					'threadType' => 'new',
					'threadNodeId' => $input['node_id'],
					'threadTitle' => $input['title'],
					'threadPrefixId' => $input['prefix_id'],
				);
			}
			else
			{
				$threadId = 0;

				$results = $this->parseRouteUrl($input['existing_url']);
				if ($results && !empty($results['params']['thread_id']))
				{
					/** @var XenForo_Model_Thread $threadModel */
					$threadModel = $this->getModelFromCache('XenForo_Model_Thread');
					$thread = $threadModel->getThreadById($results['params']['thread_id'], array(
						'join' => XenForo_Model_Thread::FETCH_FORUM,
						'permissionCombinationId' => XenForo_Visitor::getPermissionCombinationId()
					));
					if ($thread)
					{
						$permissions = XenForo_Permission::unserializePermissions($thread['node_permission_cache']);
						if ($threadModel->canViewThreadAndContainer($thread, $thread, $null, $permissions)
							&& $threadModel->canMergeThread($thread, $thread, $null, $permissions)
						)
						{
							$threadId = $thread['thread_id'];
						}
					}
				}

				if (!$threadId)
				{
					return $this->responseError(new XenForo_Phrase('existing_thread_not_found_or_no_merge_permission'));
				}

				$options = array(
					'threadType' => 'existing',
					'threadId' => $threadId
				);
			}

			$options['authorAlert'] = $input['send_author_alert'];
			$options['authorAlertReason'] = $input['author_alert_reason'];

			$this->_assertPostOnly();

			$ids = $this->getInlineModIds(false);

			$newThread = $this->getInlineModTypeModel()->$actionMethod($ids, $options, $errorPhraseKey);
			if (!$newThread)
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$this->clearCookie();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('threads', $newThread)
			);
		}
		else // show confirmation dialog
		{
			$postIds = $this->getInlineModIds();

			$handler = $this->_getInlineModPostModel();
			if (!$handler->$permissionMethod($postIds, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$redirect = $this->getDynamicRedirect();

			if (!$postIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$firstPost = $this->_getPostModel()->getPostById(reset($postIds), array(
				'join' => XenForo_Model_Post::FETCH_THREAD + XenForo_Model_Post::FETCH_FORUM
			));

			$viewParams = array(
				'postIds' => $postIds,
				'postCount' => count($postIds),
				'firstPost' => $firstPost,
				'nodes' => $this->getModelFromCache('XenForo_Model_Node')->getViewableNodeList(),
				'redirect' => $redirect,

				// we're showing the current forum prefixes intentionally; it's the best option right now
				'prefixes' => $this->_getPrefixModel()->getUsablePrefixesInForums($firstPost['node_id']),
				'forcePrefixes' => (XenForo_Application::get('threadPrefixes') ? true : false),
			);

			return $this->responseView($viewName, $templateName, $viewParams);
		}
	}

	/**
	 * Post merge handler
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMerge()
	{
		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'target_post_id' => XenForo_Input::UINT,
			));
			$input['new_message'] = $this->getHelper('Editor')->getMessageText('new_message', $this->_input);
			$input['new_message'] = XenForo_Helper_String::autoLinkBbCode($input['new_message']);

			$options = array(
				'targetPostId' => $input['target_post_id'],
				'newMessage' => $input['new_message']
			);

			return $this->executeInlineModAction('mergePosts', $options, array('fromCookie' => false));
		}
		else // show confirmation dialog
		{
			$postIds = $this->getInlineModIds();

			$handler = $this->_getInlineModPostModel();
			if (!$handler->canMergePosts($postIds, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$posts = $this->_getPostModel()->getPostsByIds($postIds);
			ksort($posts);

			$newMessage = array();
			foreach ($posts AS $post)
			{
				$newMessage[] = $post['message'];
			}

			$redirect = $this->getDynamicRedirect();

			if (!$postIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'postIds' => $postIds,
				'postCount' => count($postIds),
				'posts' => $posts,
				'newMessage' => implode("\n\n", $newMessage),
				'redirect' => $redirect,
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Post_Merge', 'inline_mod_post_merge', $viewParams);
		}
	}

	/**
	 * @return XenForo_Model_InlineMod_Post
	 */
	protected function _getInlineModPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Post');
	}

	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}

	/**
	 * @return XenForo_Model_ThreadPrefix
	 */
	protected function _getPrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}
}