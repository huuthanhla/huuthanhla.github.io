<?php

class XenForo_DataWriter_EditHistory extends XenForo_DataWriter
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_edit_history_log_not_found'; // TODO: phrase

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_edit_history' => array(
				'edit_history_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'content_type'    => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'required' => true),
				'content_id'      => array('type' => self::TYPE_UINT, 'required' => true),
				'edit_user_id'    => array('type' => self::TYPE_UINT, 'required' => true),
				'edit_date'       => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'old_text'        => array('type' => self::TYPE_STRING, 'required' => true)
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

		return array('xf_edit_history' => $this->_getHistoryModel()->getEditHistoryById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'edit_history_id = ' . $this->_db->quote($this->getExisting('edit_history_id'));
	}

	protected function _postSave()
	{
		if ($this->isInsert())
		{
			XenForo_Model_Ip::log(
				$this->get('edit_user_id'), 'edit_history', $this->get('edit_history_id'), 'insert'
			);
		}

	}

	/**
	 * @return XenForo_Model_EditHistory
	 */
	protected function _getHistoryModel()
	{
		return $this->getModelFromCache('XenForo_Model_EditHistory');
	}
}