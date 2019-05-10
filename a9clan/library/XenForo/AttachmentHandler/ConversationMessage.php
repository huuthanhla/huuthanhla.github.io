<?php

/**
 * Post-specific attachment handler.
 *
 * @package XenForo_Attachment
 */
class XenForo_AttachmentHandler_ConversationMessage extends XenForo_AttachmentHandler_Abstract
{
	protected $_conversationModel = null;

	/**
	 * Key of primary content in content data array.
	 *
	 * @var string
	 */
	protected $_contentIdKey = 'message_id';

	/**
	 * Route to get to a conversation message
	 *
	 * @var string
	 */
	protected $_contentRoute = 'conversations/message';

	/**
	 * Name of the phrase that describes the conversation_message content type
	 *
	 * @var string
	 */
	protected $_contentTypePhraseKey = 'conversation_message';

	/**
	 * Determines if attachments and be uploaded and managed in this context.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::_canUploadAndManageAttachments()
	 */
	protected function _canUploadAndManageAttachments(array $contentData, array $viewingUser)
	{
		$conversationModel = $this->_getConversationModel();

		if (!empty($contentData['message_id']))
		{
			$message = $conversationModel->getConversationMessageById($contentData['message_id']);
			if ($message)
			{
				$contentData['conversation_id'] = $message['conversation_id'];
			}
		}

		if (!empty($contentData['conversation_id']))
		{
			$conversation = $conversationModel->getConversationForUser($contentData['conversation_id'], $viewingUser);
			if ($conversation)
			{
				if (!empty($contentData['message_id']))
				{
					// editing conversation message, check permission to do so
					if (!$conversationModel->canEditMessage($message, $conversation, $null, $viewingUser))
					{
						return false;
					}
				}

				return $conversationModel->canUploadAndManageAttachment($conversation, $null, $viewingUser);
			}
		}

		return $conversationModel->canUploadAndManageAttachment(array(), $null, $viewingUser);
	}

	/**
	 * Determines if the specified attachment can be viewed.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::_canViewAttachment()
	 */
	protected function _canViewAttachment(array $attachment, array $viewingUser)
	{
		$conversationModel = $this->_getConversationModel();

		$message = $conversationModel->getConversationMessageById($attachment['content_id']);
		if (!$message)
		{
			return false;
		}

		$conversation = $conversationModel->getConversationForUser($message['conversation_id'], $viewingUser);
		if (!$conversation)
		{
			return false;
		}

		return $conversationModel->canViewAttachmentOnConversationMessage($message, $conversation, $null, $viewingUser);
	}

	/**
	 * Code to run after deleting an associated attachment.
	 *
	 * @see XenForo_AttachmentHandler_Abstract::attachmentPostDelete()
	 */
	public function attachmentPostDelete(array $attachment, Zend_Db_Adapter_Abstract $db)
	{
		$db->query('
			UPDATE xf_conversation_message
			SET attach_count = IF(attach_count > 0, attach_count - 1, 0)
			WHERE message_id = ?
		', $attachment['content_id']);
	}

	/**
	 * @see XenForo_AttachmentHandler_Abstract::getContentLink()
	 */
	public function getContentLink(array $attachment, array $extraParams = array(), $skipPrepend = false)
	{
		return false;

		$extraParams = array_merge(array(
			'message_id' => $attachment['content_id']
		), $extraParams);

		return parent::getContentLink($attachment, $extraParams, $skipPrepend);
	}

	/**
	 * @return XenForo_Model_Conversation
	 */
	protected function _getConversationModel()
	{
		if (!$this->_conversationModel)
		{
			$this->_conversationModel = XenForo_Model::create('XenForo_Model_Conversation');
		}

		return $this->_conversationModel;
	}
}