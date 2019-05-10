<?php

/**
* Data writer for notices
*
* @package XenForo_Notices
*/
class XenForo_DataWriter_Notice extends XenForo_DataWriter
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_notice_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_notice' => array(
				'notice_id'     => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'title'         => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 150, 'requiredError' => 'please_enter_valid_title'),
				'message'       => array('type' => self::TYPE_STRING, 'required' => true, 'requiredError' => 'please_enter_valid_message'),
				'dismissible'   => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'active'        => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'wrap'          => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'display_order' => array('type' => self::TYPE_UINT, 'default' => 1),
				'user_criteria' => array('type' => self::TYPE_UNKNOWN, 'required' => true,
					'verification' => array('$this', '_verifyCriteria')),
				'page_criteria' => array('type' => self::TYPE_UNKNOWN, 'required' => true,
					'verification' => array('$this', '_verifyCriteria'))
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

		return array('xf_notice' => $this->_getNoticeModel()->getNoticeById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'notice_id = ' . $this->_db->quote($this->getExisting('notice_id'));
	}

	/**
	 * Verifies that the criteria is valid and formats is correctly.
	 * Expected input format: [] with children: [rule] => name, [data] => info
	 *
	 * @param array|string $criteria Criteria array or serialize string; see above for format. Modified by ref.
	 *
	 * @return boolean
	 */
	protected function _verifyCriteria(&$criteria)
	{
		$criteriaFiltered = XenForo_Helper_Criteria::prepareCriteriaForSave($criteria);
		$criteria = serialize($criteriaFiltered);
		return true;
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$this->_rebuildNoticeCache();
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$this->_db->delete('xf_notice_dismissed', 'notice_id = ' . $this->_db->quote($this->get('notice_id')));
		$this->_rebuildNoticeCache();
	}

	/**
	 * Rebuilds the notice cache.
	 */
	protected function _rebuildNoticeCache()
	{
		$this->_getNoticeModel()->rebuildNoticeCache();
	}

	/**
	 * @return XenForo_Model_Notice
	 */
	protected function _getNoticeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Notice');
	}
}