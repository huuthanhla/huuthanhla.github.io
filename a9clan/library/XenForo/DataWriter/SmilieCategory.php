<?php

/**
* Data writer for smilie categories.
*
* @package XenForo_Smilie
*/
class XenForo_DataWriter_SmilieCategory extends XenForo_DataWriter
{
	/**
	 * Constant for extra data that holds the value for the phrase
	 * that is the title of this section.
	 *
	 * This value is required on inserts.
	 *
	 * @var string
	 */
	const DATA_TITLE = 'phraseTitle';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_smilie_category' => array(
				'smilie_category_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'display_order' => array('type' => self::TYPE_UINT),
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'smilie_category_id'))
		{
			return false;
		}

		return array('xf_smilie_category' => $this->_getSmilieModel()->getSmilieCategoryById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'smilie_category_id = ' . $this->_db->quote($this->getExisting('smilie_category_id'));
	}

	/**
	 * Prevent categories from being saved without a title
	 *
	 * @see XenForo_DataWriter::_preSave()
	 */
	protected function _preSave()
	{
		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null && strlen($titlePhrase) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$smilieCategoryId = $this->get('smilie_category_id');

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($smilieCategoryId), $titlePhrase, ''
			);
		}
	}

	protected function _postDelete()
	{
		$smilieCategoryId = $this->get('smilie_category_id');

		$this->_deleteMasterPhrase($this->_getTitlePhraseName($smilieCategoryId));

		$this->_db->update('xf_smilie', array(
			'smilie_category_id' => 0
		), 'smilie_category_id = ' . $this->_db->quote($smilieCategoryId));
	}

	/**
	 * Gets the name of the category's title phrase.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($id)
	{
		return $this->_getSmilieModel()->getSmilieCategoryTitlePhraseName($id);
	}

	/**
	 * Gets the smilie model object.
	 *
	 * @return XenForo_Model_Smilie
	 */
	protected function _getSmilieModel()
	{
		return $this->getModelFromCache('XenForo_Model_Smilie');
	}
}