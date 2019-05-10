<?php

class XenForo_EditHistoryHandler_Post extends XenForo_EditHistoryHandler_Abstract
{
	protected $_prefix = 'posts';

	protected function _getContent($contentId, array $viewingUser)
	{
		/* @var $postModel XenForo_Model_Post */
		$postModel = XenForo_Model::create('XenForo_Model_Post');

		$post = $postModel->getPostById($contentId, array(
			'join' => XenForo_Model_Post::FETCH_THREAD | XenForo_Model_Post::FETCH_FORUM | XenForo_Model_Post::FETCH_USER,
			'permissionCombinationId' => $viewingUser['permission_combination_id']
		));
		if ($post)
		{
			$post['permissions'] = XenForo_Permission::unserializePermissions($post['node_permission_cache']);
		}

		return $post;
	}

	protected function _canViewHistoryAndContent(array $content, array $viewingUser)
	{
		/* @var $postModel XenForo_Model_Post */
		$postModel = XenForo_Model::create('XenForo_Model_Post');

		return (
			$postModel->canViewPostAndContainer(
				$content, $content, $content, $null, $content['permissions'], $viewingUser
			)
			&& $postModel->canViewPostHistory(
				$content, $content, $content, $null, $content['permissions'], $viewingUser
			)
		);
	}

	protected function _canRevertContent(array $content, array $viewingUser)
	{
		/* @var $postModel XenForo_Model_Post */
		$postModel = XenForo_Model::create('XenForo_Model_Post');

		return $postModel->canEditPost(
			$content, $content, $content, $null, $content['permissions'], $viewingUser
		);
	}

	public function getTitle(array $content)
	{
		return new XenForo_Phrase('post_in_thread_x', array('title' => $content['title']));
	}

	public function getText(array $content)
	{
		return $content['message'];
	}

	public function getBreadcrumbs(array $content)
	{
		/* @var $nodeModel XenForo_Model_Node */
		$nodeModel = XenForo_Model::create('XenForo_Model_Node');

		$node = $nodeModel->getNodeById($content['node_id']);
		if ($node)
		{
			$crumb = $nodeModel->getNodeBreadCrumbs($node);
			$crumb[] = array(
				'href' => XenForo_Link::buildPublicLink('full:posts', $content),
				'value' => $content['title']
			);
			return $crumb;
		}
		else
		{
			return array();
		}
	}

	public function getNavigationTab()
	{
		return 'forums';
	}

	public function formatHistory($string, XenForo_View $view)
	{
		$parser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $view)));
		return new XenForo_BbCode_TextWrapper($string, $parser);
	}

	public function revertToVersion(array $content, $revertCount, array $history, array $previous = null)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
		$dw->setExistingData($content);
		$dw->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_EDIT_DATE_DELAY, -1);
		$dw->setOption(XenForo_DataWriter_DiscussionMessage::OPTION_IS_AUTOMATED, true);
		$dw->set('message', $history['old_text']);
		$dw->set('edit_count', $dw->get('edit_count') + 1);
		if ($dw->get('edit_count'))
		{
			if (!$previous || $previous['edit_user_id'] != $content['user_id'])
			{
				// if previous is a mod edit, don't show as it may have been hidden
				$dw->set('last_edit_date', 0);
			}
			else if ($previous && $previous['edit_user_id'] == $content['user_id'])
			{
				$dw->set('last_edit_date', $previous['edit_date']);
				$dw->set('last_edit_user_id', $previous['edit_user_id']);
			}
		}

		return $dw->save();
	}
}