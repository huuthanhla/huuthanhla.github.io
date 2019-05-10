<?php

class XenForo_ControllerPublic_InlineMod_Conversation extends XenForo_ControllerPublic_InlineMod_Abstract
{
	/**
	 * Key for inline mod data.
	 *
	 * @var string
	 */
	public $inlineModKey = 'conversations';

	/**
	 * @return XenForo_Model_InlineMod_Conversation
	 */
	public function getInlineModTypeModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Conversation');
	}

	public function actionLeave()
	{
		if ($this->isConfirmedPost())
		{
			$options = array(
				'deleteType' => $this->_input->filterSingle('delete_type', XenForo_Input::STRING)
			);

			return $this->executeInlineModAction('leaveConversations', $options, array('fromCookie' => false));
		}
		else // show confirmation dialog
		{
			$conversationIds = $this->getInlineModIds();

			$redirect = $this->getDynamicRedirect();

			if (!$conversationIds)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$redirect
				);
			}

			$viewParams = array(
				'conversationIds' => $conversationIds,
				'conversationCount' => count($conversationIds),
				'redirect' => $redirect,
			);

			return $this->responseView('XenForo_ViewPublic_InlineMod_Conversation_Leave', 'inline_mod_conversation_leave', $viewParams);
		}
	}

	public function actionStar()
	{
		return $this->executeInlineModAction('starConversations');
	}

	public function actionUnstar()
	{
		return $this->executeInlineModAction('unstarConversations');
	}

	public function actionRead()
	{
		return $this->executeInlineModAction('markConversationsRead');
	}

	public function actionUnread()
	{
		return $this->executeInlineModAction('markConversationsUnread');
	}

	/**
	 * @return XenForo_Model_InlineMod_Conversation
	 */
	protected function _getInlineModConversationModel()
	{
		return $this->getModelFromCache('XenForo_Model_InlineMod_Conversation');
	}
}