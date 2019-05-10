<?php

/**
* Data writer for thread prefixes.
*/
class XenForo_DataWriter_ThreadPrefix extends XenForo_DataWriter
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

	const OPTION_MASS_UPDATE = 'massUpdate';


	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_prefix_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_thread_prefix' => array(
				'prefix_id'              => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'prefix_group_id'        => array('type' => self::TYPE_UINT, 'default' => 0),
				'display_order'          => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'materialized_order'     => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'css_class'              => array('type' => self::TYPE_STRING, 'maxLength' => 50, 'default' => ''),
				'allowed_user_group_ids' => array('type' => self::TYPE_UNKNOWN, 'default' => '',
					'verification' => array('$this', '_verifyAllowedUserGroupIds')
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'prefix_id'))
		{
			return false;
		}

		return array('xf_thread_prefix' => $this->_getPrefixModel()->getPrefixById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'prefix_id = ' . $this->_db->quote($this->getExisting('prefix_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_MASS_UPDATE => false
		);
	}

	/**
	 * Verifies the allowed user group IDs.
	 *
	 * @param array|string $userGroupIds Array or comma-delimited list
	 *
	 * @return boolean
	 */
	protected function _verifyAllowedUserGroupIds(&$userGroupIds)
	{
		if (!is_array($userGroupIds))
		{
			$userGroupIds = preg_split('#,\s*#', $userGroupIds);
		}

		$userGroupIds = array_map('intval', $userGroupIds);
		$userGroupIds = array_unique($userGroupIds);
		sort($userGroupIds, SORT_NUMERIC);
		$userGroupIds = implode(',', $userGroupIds);

		return true;
	}

	protected function _preSave()
	{
		if (!$this->getOption(self::OPTION_MASS_UPDATE))
		{
			$titlePhrase = $this->getExtraData(self::DATA_TITLE);
			if ($titlePhrase !== null && strlen($titlePhrase) == 0)
			{
				$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
			}
		}
	}

	protected function _postSave()
	{
		if (!$this->getOption(self::OPTION_MASS_UPDATE))
		{
			$titlePhrase = $this->getExtraData(self::DATA_TITLE);
			if ($titlePhrase !== null)
			{
				$this->_insertOrUpdateMasterPhrase(
					$this->_getTitlePhraseName($this->get('prefix_id')), $titlePhrase,
					'', array('global_cache' => 1)
				);
			}

			if ($this->isChanged('display_order') || $this->isChanged('prefix_group_id'))
			{
				$this->_getPrefixModel()->rebuildPrefixMaterializedOrder();
			}

			$this->_getPrefixModel()->rebuildPrefixCache();
		}
	}

	protected function _postDelete()
	{
		$prefixId = $this->get('prefix_id');

		$this->_deleteMasterPhrase($this->_getTitlePhraseName($prefixId));

		$db = $this->_db;
		$db->delete('xf_forum_prefix', 'prefix_id = ' . $db->quote($prefixId));
		$db->update('xf_forum', array('default_prefix_id' => 0), 'default_prefix_id = ' . $db->quote($prefixId));
		$db->update('xf_thread', array('prefix_id' => 0), 'prefix_id = ' . $db->quote($prefixId));

		$this->_getPrefixModel()->rebuildPrefixCache();
	}

	/**
	 * Gets the name of the title phrase for this prefix.
	 *
	 * @param integer $prefixId
	 *
	 * @return string
	 */
	protected function _getTitlePhraseName($prefixId)
	{
		return $this->_getPrefixModel()->getPrefixTitlePhraseName($prefixId);
	}

	/**
	 * @return XenForo_Model_ThreadPrefix
	 */
	protected function _getPrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}
}