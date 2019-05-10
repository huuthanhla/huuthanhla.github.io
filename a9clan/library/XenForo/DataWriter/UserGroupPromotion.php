<?php

/**
* Data writer for user group promotions
*/
class XenForo_DataWriter_UserGroupPromotion extends XenForo_DataWriter
{
	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_promotion_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_user_group_promotion' => array(
				'promotion_id'         => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'title'                => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 100,
					'requiredError' => 'please_enter_valid_title'
				),
				'active'               => array('type' => self::TYPE_UINT, 'default' => 1),
				'user_criteria'        => array('type' => self::TYPE_UNKNOWN, 'required' => true,
					'verification' => array('$this', '_verifyCriteria')
				),
				'extra_user_group_ids' => array('type' => self::TYPE_UNKNOWN, 'required' => true,
					'verification' => array('XenForo_DataWriter_Helper_User', 'verifyExtraUserGroupIds'),
					'requiredError' => 'please_select_at_least_one_user_group'
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

		return array('xf_user_group_promotion' => $this->_getPromotionModel()->getPromotionById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'promotion_id = ' . $this->_db->quote($this->getExisting('promotion_id'));
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

		if (!$criteriaFiltered)
		{
			$this->error(new XenForo_Phrase('please_select_criteria_that_must_be_met'), 'user_criteria');
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		// TODO: this doesn't demote users, possibly make that an option
		$this->_db->delete('xf_user_group_promotion_log',
			'promotion_id = ' . $this->_db->quote($this->get('promotion_id'))
		);
		$this->getModelFromCache('XenForo_Model_User')->removeUserGroupChangeLogByKey(
			'ugPromotion' . $this->get('promotion_id')
		);
	}

	/**
	 * @return XenForo_Model_UserGroupPromotion
	 */
	protected function _getPromotionModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroupPromotion');
	}
}