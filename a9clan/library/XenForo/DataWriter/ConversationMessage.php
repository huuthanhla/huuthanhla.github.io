<?php

/**
* Data writer for conversation messages.
*
* @package XenForo_Conversation
*/
class XenForo_DataWriter_ConversationMessage extends XenForo_DataWriter
{
	/**
	 * Option that controls whether changes to the conversation should be done
	 * by this data writer. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_UPDATE_CONVERSATION = 'updateConversation';

	/**
	 * Option to control whether the message sender is in the recipient list.
	 * Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_CHECK_SENDER_RECIPIENT = 'checkSenderReceipient';

	/**
	 * Option to control whether or not to log the IP address of the message sender
	 *
	 * @var string
	 */
	const OPTION_SET_IP_ADDRESS = 'setIpAddress';

	/**
	 * Constant for extra data that holds the sending user information
	 *
	 * @var string
	 */
	const DATA_MESSAGE_SENDER = 'sendingUser';

	/**
	 * Holds the temporary hash used to pull attachments and associate them with this message.
	 *
	 * @var string
	 */
	const DATA_ATTACHMENT_HASH = 'attachmentHash';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_conversation_message' => array(
				'message_id'      => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'conversation_id' => array('type' => self::TYPE_UINT,   'required' => true),
				'message_date'    => array('type' => self::TYPE_UINT,   'default' => XenForo_Application::$time),
				'user_id'         => array('type' => self::TYPE_UINT,   'required' => true),
				'username'        => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50),
				'message'         => array('type' => self::TYPE_STRING, 'required' => true, 'requiredError' => 'please_enter_valid_message'),
				'attach_count'    => array('type' => self::TYPE_UINT,   'default' => 0, 'max' => 65535),
				'ip_id'           => array('type' => self::TYPE_UINT,   'default' => 0),
			)
		);
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
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_conversation_message' => $this->_getConversationModel()->getConversationMessageById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'message_id = ' . $this->_db->quote($this->getExisting('message_id'));
	}

	/**
	 * Gets the data writer's default options.
	 *
	 * @return array
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_UPDATE_CONVERSATION => true,
			self::OPTION_CHECK_SENDER_RECIPIENT => true,
			self::OPTION_SET_IP_ADDRESS => true,
		);
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->getOption(self::OPTION_CHECK_SENDER_RECIPIENT))
		{
			$recipients = $this->_getConversationModel()->getConversationRecipients($this->get('conversation_id'));
			if (!isset($recipients[$this->get('user_id')]))
			{
				throw new XenForo_Exception('Non-recipients cannot reply to a conversation.');
			}
		}

		if ($this->isChanged('message'))
		{
			/** @var $taggingModel XenForo_Model_UserTagging */
			$taggingModel = $this->getModelFromCache('XenForo_Model_UserTagging');

			$this->_taggedUsers = $taggingModel->getTaggedUsersInMessage(
				$this->get('message'), $newMessage, 'bb'
			);
			$this->set('message', $newMessage);
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$attachmentHash = $this->getExtraData(self::DATA_ATTACHMENT_HASH);
		if ($attachmentHash)
		{
			$this->_associateAttachments($attachmentHash);
		}

		if ($this->isInsert() && $this->getOption(self::OPTION_UPDATE_CONVERSATION))
		{
			$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
			$conversationDw->setExistingData($this->get('conversation_id'));
			$conversationDw->addReply($this->getMergedData());

			$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $this->getExtraData(self::DATA_MESSAGE_SENDER));
			$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_MESSAGE, $this->get('message'));

			$conversationDw->save();
		}

		if ($this->isInsert() && $this->getOption(self::OPTION_SET_IP_ADDRESS) && !$this->get('ip_id'))
		{
			$this->_updateIpData();
		}
	}

	/**
	 * Pre-delete handling.
	 */
	protected function _preDelete()
	{
		throw new Exception('Conversation message deletion is not implemented at this time.');

		// this won't actually run, as we've thrown an exception...

		if ($this->get('attach_count'))
		{
			$this->_deleteAttachments();
		}
	}

	/**
	 * Associates attachments with this message.
	 *
	 * @param string $attachmentHash
	 */
	protected function _associateAttachments($attachmentHash)
	{
		$rows = $this->_db->update('xf_attachment', array(
			'content_type' => 'conversation_message',
			'content_id' => $this->get('message_id'),
			'temp_hash' => '',
			'unassociated' => 0
		), 'temp_hash = ' . $this->_db->quote($attachmentHash));
		if ($rows)
		{
			$this->set('attach_count', $this->get('attach_count') + $rows, '', array('setAfterPreSave' => true));

			$this->_db->update('xf_conversation_message', array(
				'attach_count' => $this->get('attach_count')
			), 'message_id = ' .  $this->_db->quote($this->get('message_id')));
		}
	}

	/**
	 * Deletes all attachments associated with this message
	 */
	protected function _deleteAttachments()
	{
		$this->getModelFromCache('XenForo_Model_Attachment')->deleteAttachmentsFromContentIds(
			'conversation_message',
			array($this->get('message_id'))
		);
	}

	/**
	* Upates the IP data.
	*/
	protected function _updateIpData()
	{
		if (!empty($this->_extraData['ipAddress']))
		{
			$ipAddress = $this->_extraData['ipAddress'];
		}
		else
		{
			$ipAddress = null;
		}

		$ipId = XenForo_Model_Ip::log($this->get('user_id'), 'conversation_message', $this->get('message_id'), 'insert', $ipAddress);
		$this->set('ip_id', $ipId, '', array('setAfterPreSave' => true));

		$this->_db->update('xf_conversation_message',
			array('ip_id' => $ipId),
			'message_id = ' .  $this->_db->quote($this->get('message_id'))
		);
	}

	/**
	 * @return XenForo_Model_Conversation
	 */
	protected function _getConversationModel()
	{
		return $this->getModelFromCache('XenForo_Model_Conversation');
	}
}