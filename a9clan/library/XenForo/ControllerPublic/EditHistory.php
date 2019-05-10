<?php

class XenForo_ControllerPublic_EditHistory extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$contentType = $this->_input->filterSingle('content_type', XenForo_Input::STRING);
		$contentId = $this->_input->filterSingle('content_id', XenForo_Input::UINT);

		$historyModel = $this->_getHistoryModel();

		$handler = $historyModel->getEditHistoryHandler($contentType);
		if (!$handler)
		{
			return $this->responseNoPermission();
		}

		$content = $handler->getContent($contentId);
		if (!$content || !$handler->canViewHistoryAndContent($content))
		{
			return $this->responseNoPermission();
		}

		if (isset($content['edit_count']) && !$content['edit_count'])
		{
			return $this->responseError(new XenForo_Phrase('this_content_has_not_been_edited'));
		}

		$list = $historyModel->getEditHistoryListForContent($contentType, $contentId, array(
			'join' => XenForo_Model_EditHistory::FETCH_USER
		));
		if (!$list)
		{
			return $this->responseError(new XenForo_Phrase('this_content_edit_history_has_been_removed'));
		}

		$oldId = $this->_input->filterSingle('old', XenForo_Input::UINT);
		$newId = $this->_input->filterSingle('new', XenForo_Input::UINT);

		if ($oldId)
		{
			// doing a comparison
			$old = $historyModel->getEditHistoryById($oldId);
			$oldText = $old['old_text'];

			if ($newId)
			{
				$new = $historyModel->getEditHistoryById($newId);
				$newText = $new['old_text'];
			}
			else
			{
				$newText = $handler->getText($content);
			}

			$diffHandler = new XenForo_Diff();
			$diffs = $diffHandler->findDifferences($oldText, $newText, XenForo_Diff::DIFF_TYPE_LINE);
		}
		else
		{
			$diffs = array();
		}

		$newestHistory = reset($list);

		$this->_routeMatch->setSections($handler->getNavigationTab());

		$viewParams = array(
			'content' => $content,
			'contentType' => $contentType,
			'contentId' => $contentId,

			'title' => $handler->getTitle($content),
			'breadCrumbs' => $handler->getBreadcrumbs($content),

			'list' => $list,

			'diffs' => $diffs,
			'oldId' => ($oldId ? $oldId : $newestHistory['edit_history_id']),
			'newId' => $newId
		);
		if ($oldId)
		{
			return $this->responseView('XenForo_ViewPublic_EditHistory_Compare', 'edit_history_compare', $viewParams);
		}
		else
		{
			return $this->responseView('XenForo_ViewPublic_EditHistory_Index', 'edit_history_index', $viewParams);
		}
	}

	public function actionView()
	{
		/** @var $handler XenForo_EditHistoryHandler_Abstract */
		list($history, $content, $handler) = $this->_getHistoryOrError();

		$this->_routeMatch->setSections($handler->getNavigationTab());

		$viewParams = array(
			'content' => $content,
			'breadCrumbs' => $handler->getBreadcrumbs($content),
			'history' => $history,
			'handler' => $handler,
			'canRevert' => $handler->canRevertContent($content)
		);
		return $this->responseView('XenForo_ViewPublic_EditHistory_View', 'edit_history_view', $viewParams);
	}

	public function actionRevert()
	{
		/** @var $handler XenForo_EditHistoryHandler_Abstract */
		list($history, $content, $handler) = $this->_getHistoryOrError();

		if (!$handler->canRevertContent($content) || !$this->isConfirmedPost())
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('edit-history/view', $history)
			);
		}

		$this->_getHistoryModel()->revertToHistory($history, $content, $handler);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$handler->buildContentLink($content)
		);
	}

	protected function _getHistoryOrError($historyId = null)
	{
		if ($historyId === null)
		{
			$historyId = $this->_input->filterSingle('edit_history_id', XenForo_Input::UINT);
		}

		$historyModel = $this->_getHistoryModel();

		$history = $historyModel->getEditHistoryById($historyId);
		if (!$history)
		{
			throw $this->responseException(
				$this->responseError(new XenForo_Phrase('requested_history_not_found'), 404)
			);
		}

		$handler = $historyModel->getEditHistoryHandler($history['content_type']);
		if (!$handler)
		{
			throw $this->getNoPermissionResponseException();
		}

		$content = $handler->getContent($history['content_id']);
		if (!$content || !$handler->canViewHistoryAndContent($content))
		{
			throw $this->getNoPermissionResponseException();
		}

		return array($history, $content, $handler);
	}

	/**
	 * @return XenForo_Model_EditHistory
	 */
	protected function _getHistoryModel()
	{
		return $this->getModelFromCache('XenForo_Model_EditHistory');
	}
}