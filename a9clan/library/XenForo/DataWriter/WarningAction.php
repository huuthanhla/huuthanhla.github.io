<?php

/**
* Data writer for warning actions.
*/
class XenForo_DataWriter_WarningAction extends XenForo_DataWriter
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_warning_action_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_warning_action' => array(
				'warning_action_id'    => array('type' => self::TYPE_UINT,    'autoIncrement' => true),
				'points'               => array('type' => self::TYPE_UINT,    'required' => true, 'min' => 1, 'max' => 65535),
				'action'               => array('type' => self::TYPE_STRING,  'default' => 'groups',
						'allowedValues' => array('ban', 'discourage', 'groups')
				),
				'action_length_type'   => array('type' => self::TYPE_STRING,  'default' => 'permanent',
						'allowedValues' => array('points', 'permanent', 'days', 'weeks', 'months', 'years')
				),
				'action_length'        => array('type' => self::TYPE_UINT,    'default' => 0, 'max' => 65535),
				'extra_user_group_ids' => array('type' => self::TYPE_UNKNOWN, 'default' => '',
						'verification' => array('XenForo_DataWriter_Helper_User', 'verifyExtraUserGroupIds')
				)
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

		return array('xf_warning_action' => $this->_getWarningModel()->getWarningActionById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'warning_action_id = ' . $this->_db->quote($this->getExisting('warning_action_id'));
	}

	protected function _preSave()
	{
		if ($this->get('action_length_type') == 'permanent' || $this->get('action_length_type') == 'points')
		{
			$this->set('action_length', 0);
		}
		else if ($this->get('action_length') == 0)
		{
			$this->set('action_length_type', 'permanent');
		}
	}

	protected function _postDelete()
	{
		$this->getModelFromCache('XenForo_Model_User')->removeUserGroupChangeLogByKey(
			'warning_' . $this->get('warning_id')
		);
	}

	/**
	 * @return XenForo_Model_Warning
	 */
	protected function _getWarningModel()
	{
		return $this->getModelFromCache('XenForo_Model_Warning');
	}
}