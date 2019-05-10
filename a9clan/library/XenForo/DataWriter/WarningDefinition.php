<?php

/**
* Data writer for warning definitions.
*/
class XenForo_DataWriter_WarningDefinition extends XenForo_DataWriter
{
	/**
	 * Constants for extra data that holds the value for the phrases
	 * that are the title, conversation title, and conversation text for this warning.
	 *
	 * The title is required on inserts.
	 *
	 * @var string
	 * @var string
	 * @var string
	 */
	const DATA_TITLE = 'phraseTitle';
	const DATA_CONVERSATION_TITLE = 'phraseConversationTitle';
	const DATA_CONVERSATION_TEXT = 'phraseConversationText';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_warning_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_warning_definition' => array(
				'warning_definition_id' => array('type' => self::TYPE_UINT,    'autoIncrement' => true),
				'points_default'        => array('type' => self::TYPE_UINT,    'required' => true, 'max' => 65535),
				'expiry_type'           => array('type' => self::TYPE_STRING,  'default' => 'never',
						'allowedValues' => array('never', 'days', 'weeks', 'months', 'years')
				),
				'expiry_default'        => array('type' => self::TYPE_UINT,    'default' => 0, 'max' => 65535),
				'extra_user_group_ids'  => array('type' => self::TYPE_UNKNOWN, 'default' => '',
						'verification' => array('XenForo_DataWriter_Helper_User', 'verifyExtraUserGroupIds')
				),
				'is_editable'           => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
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

		return array('xf_warning_definition' => $this->_getWarningModel()->getWarningDefinitionById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'warning_definition_id = ' . $this->_db->quote($this->getExisting('warning_definition_id'));
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null && strlen($titlePhrase) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}

		if ($this->get('expiry_default') == 0)
		{
			$this->set('expiry_type', 'never');
		}
		else if ($this->get('expiry_type') == 'never')
		{
			$this->set('expiry_default', 0);
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$id = $this->get('warning_definition_id');

		$phraseData = array(
			self::DATA_TITLE => $this->_getTitlePhraseName($id),
			self::DATA_CONVERSATION_TITLE => $this->_getConversationTitlePhraseName($id),
			self::DATA_CONVERSATION_TEXT => $this->_getConversationTextPhraseName($id)
		);

		foreach ($phraseData AS $phraseDataElement => $phraseName)
		{
			$phraseText = $this->getExtraData($phraseDataElement);
			if ($phraseText !== null)
			{
				$this->_insertOrUpdateMasterPhrase($phraseName, $phraseText);
			}
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$id = $this->get('warning_definition_id');

		$this->_deleteMasterPhrase($this->_getTitlePhraseName($id));
		$this->_deleteMasterPhrase($this->_getConversationTitlePhraseName($id));
		$this->_deleteMasterPhrase($this->_getConversationTextPhraseName($id));
	}

	/**
	 * Gets the name of the title phrase for this warning.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($id)
	{
		return $this->_getWarningModel()->getWarningDefinitionTitlePhraseName($id);
	}

	/**
	 * Gets the name of the conversation title phrase for this warning.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	protected function _getConversationTitlePhraseName($id)
	{
		return $this->_getWarningModel()->getWarningDefinitionConversationTitlePhraseName($id);
	}

	/**
	 * Gets the name of the conversation text phrase for this warning.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	protected function _getConversationTextPhraseName($id)
	{
		return $this->_getWarningModel()->getWarningDefinitionConversationTextPhraseName($id);
	}

	/**
	 * @return XenForo_Model_Warning
	 */
	protected function _getWarningModel()
	{
		return $this->getModelFromCache('XenForo_Model_Warning');
	}
}