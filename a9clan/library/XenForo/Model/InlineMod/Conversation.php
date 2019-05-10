<?php

class XenForo_Model_InlineMod_Conversation extends XenForo_Model
{
	public $enableLogging = true;

	/**
	 * Leaves the specified conversations.
	 *
	 * @param array $conversationIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function leaveConversations(array $conversationIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$options = array_merge(
			array(
				'deleteType' => '',
			), $options
		);

		if (!$options['deleteType'])
		{
			throw new XenForo_Exception('No delete type specified.');
		}

		$conversations = $this->getConversationData($conversationIds, $viewingUser);
		$this->standardizeViewingUserReference($viewingUser);

		foreach ($conversations AS $conversation)
		{
			$this->_getConversationModel()->deleteConversationForUser(
				$conversation['conversation_id'], $viewingUser['user_id'], $options['deleteType']
			);
		}

		return true;
	}

	/**
	 * Stars the specified conversations.
	 *
	 * @param array $conversationIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function starConversations(array $conversationIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$conversations = $this->getConversationData($conversationIds, $viewingUser);
		$this->standardizeViewingUserReference($viewingUser);

		foreach ($conversations AS $conversation)
		{
			$this->_getConversationModel()->changeConversationStarState(
				$conversation['conversation_id'], $viewingUser['user_id'], true
			);
		}

		return true;
	}

	/**
	 * Unstars the specified conversations.
	 *
	 * @param array $conversationIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function unstarConversations(array $conversationIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$conversations = $this->getConversationData($conversationIds, $viewingUser);
		$this->standardizeViewingUserReference($viewingUser);

		foreach ($conversations AS $conversation)
		{
			$this->_getConversationModel()->changeConversationStarState(
				$conversation['conversation_id'], $viewingUser['user_id'], false
			);
		}

		return true;
	}

	/**
	 * Marks the specified conversations as read.
	 *
	 * @param array $conversationIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function markConversationsRead(array $conversationIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$conversations = $this->getConversationData($conversationIds, $viewingUser);
		$this->standardizeViewingUserReference($viewingUser);

		foreach ($conversations AS $conversation)
		{
			$this->_getConversationModel()->markConversationAsRead(
				$conversation['conversation_id'], $viewingUser['user_id'], XenForo_Application::$time
			);
		}

		return true;
	}

	/**
	 * Marks the specified conversations as unread.
	 *
	 * @param array $conversationIds List of IDs to delete
	 * @param array $options Options that control the delete. Supports deleteType (soft or hard).
	 * @param string $errorKey Modified by reference. If no permission, may include a key of a phrase that gives more info
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean True if permissions were ok
	 */
	public function markConversationsUnread(array $conversationIds, array $options = array(), &$errorKey = '', array $viewingUser = null)
	{
		$conversations = $this->getConversationData($conversationIds, $viewingUser);
		$this->standardizeViewingUserReference($viewingUser);

		foreach ($conversations AS $conversation)
		{
			$this->_getConversationModel()->markConversationAsUnread(
				$conversation['conversation_id'], $viewingUser['user_id']
			);
		}

		return true;
	}

	/**
	 * @param array $ids List of conversation IDs
	 *
	 * @return array
	 */
	public function getConversationData(array $ids, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return $this->_getConversationModel()->getConversationsForUserByIds($viewingUser['user_id'], $ids);
	}

	/**
	 * @return XenForo_Model_Conversation
	 */
	protected function _getConversationModel()
	{
		return $this->getModelFromCache('XenForo_Model_Conversation');
	}
}