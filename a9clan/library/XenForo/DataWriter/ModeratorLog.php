<?php

/**
* Data writer for moderator log entries
*/
class XenForo_DataWriter_ModeratorLog extends XenForo_DataWriter
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_log_entry_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_moderator_log' => array(
				'moderator_log_id'        => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'log_date'                => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'user_id'                 => array('type' => self::TYPE_UINT, 'required' => true),
				'ip_address'              => array('type' => self::TYPE_STRING, 'default' => '',
					'verification' => array('$this', '_verifyIpAddress')
				),
				'content_type'            => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 25),
				'content_id'              => array('type' => self::TYPE_UINT, 'required' => true),
				'content_user_id'         => array('type' => self::TYPE_UINT, 'required' => true),
				'content_username'        => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50),
				'content_title'           => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 150),
				'content_url'             => array('type' => self::TYPE_STRING, 'default' => ''),
				'discussion_content_type' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 25),
				'discussion_content_id'   => array('type' => self::TYPE_UINT, 'required' => true),
				'action'                  => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 25),
				'action_params'           => array('type' => self::TYPE_JSON, 'default' => '')
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

		return array('xf_moderator_log' => $this->_getLogModel()->getModeratorLogById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'moderator_log_id = ' . $this->_db->quote($this->getExisting('moderator_log_id'));
	}

	protected function _verifyIpAddress(&$ipAddress)
	{
		$ipAddress = (string)XenForo_Helper_Ip::convertIpStringToBinary($ipAddress);

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if (!$this->get('discussion_content_type') || !$this->get('discussion_content_id'))
		{
			$this->set('discussion_content_type', $this->get('content_type'));
			$this->set('discussion_content_id', $this->get('content_id'));
		}

		if ($this->isInsert() && !$this->get('ip_address') && isset($_SERVER['REMOTE_ADDR']))
		{
			$this->set('ip_address', $_SERVER['REMOTE_ADDR']);
		}
	}

	/**
	 * @return XenForo_Model_Log
	 */
	protected function _getLogModel()
	{
		return $this->getModelFromCache('XenForo_Model_Log');
	}
}