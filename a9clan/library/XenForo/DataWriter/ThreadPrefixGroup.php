<?php

/**
* Data writer for thread prefix groups.
*/
class XenForo_DataWriter_ThreadPrefixGroup extends XenForo_DataWriter
{
	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the title of this prefix.
	 *
	 * This value is required on inserts.
	 *
	 * @var string
	 */
	const DATA_TITLE = 'phraseTitle';


	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_prefix_group_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_thread_prefix_group' => array(
				'prefix_group_id'        => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'display_order'          => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'prefix_group_id'))
		{
			return false;
		}

		return array('xf_thread_prefix_group' => $this->_getPrefixModel()->getPrefixGroupById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'prefix_group_id = ' . $this->_db->quote($this->getExisting('prefix_group_id'));
	}

	protected function _preSave()
	{
		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null && strlen($titlePhrase) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}
	}

	protected function _postSave()
	{
		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($this->get('prefix_group_id')), $titlePhrase,
				'', array('global_cache' => 1)
			);
		}

		if ($this->isChanged('display_order'))
		{
			$this->_getPrefixModel()->rebuildPrefixMaterializedOrder();
		}

		$this->_getPrefixModel()->rebuildPrefixCache();
	}

	protected function _postDelete()
	{
		$prefixGroupId = $this->get('prefix_group_id');

		$this->_deleteMasterPhrase($this->_getTitlePhraseName($prefixGroupId));

		$this->_db->update('xf_thread_prefix', array('prefix_group_id' => 0), 'prefix_group_id = ' . $this->_db->quote($prefixGroupId));

		$this->_getPrefixModel()->rebuildPrefixMaterializedOrder();
		$this->_getPrefixModel()->rebuildPrefixCache();
	}

	/**
	 * Gets the name of the title phrase for this prefix.
	 *
	 * @param integer $prefixId
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($prefixGroupId)
	{
		return $this->_getPrefixModel()->getPrefixGroupTitlePhraseName($prefixGroupId);
	}

	/**
	 * @return XenForo_Model_ThreadPrefix
	 */
	protected function _getPrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}
}