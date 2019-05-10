<?php

/**
* Data writer for banned users.
*
* @package XenForo_Banning
*/
class XenForo_DataWriter_UserBan extends XenForo_DataWriter
{
	/**
	 * If non-zero, user is added to the specified group when being banned.
	 * Value is a user group id.
	 *
	 * @var string
	 */
	const OPTION_ADD_GROUP = 'addGroup';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_user_ban' => array(
				'user_id'           => array('type' => self::TYPE_UINT, 'required' => true),
				'ban_user_id'       => array('type' => self::TYPE_UINT, 'required' => true),
				'ban_date'          => array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'end_date'          => array('type' => self::TYPE_UINT, 'required' => true),
				'user_reason'       => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 255),
				'triggered'         => array('type' => self::TYPE_BOOLEAN, 'default' => 0)
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'user_id'))
		{
			return false;
		}

		return array('xf_user_ban' => $this->_getBanningModel()->getBannedUserById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'user_id = ' . $this->_db->quote($this->getExisting('user_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		$options = XenForo_Application::get('options');

		return array(
			self::OPTION_ADD_GROUP => intval($options->addBanUserGroup)
		);
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->isChanged('user_id'))
		{
			$userBan = $this->_getBanningModel()->getBannedUserById($this->get('user_id'));
			if ($userBan)
			{
				$this->error(new XenForo_Phrase('this_user_is_already_banned'), 'user_id');
			}
			else
			{
				$user = $this->getModelFromCache('XenForo_Model_User')->getUserById($this->get('user_id'));
				if (!$user || $user['is_moderator'] || $user['is_admin'])
				{
					$this->error(new XenForo_Phrase('this_user_is_an_admin_or_moderator_choose_another'), 'user_id');
				}
			}
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->isUpdate() && $this->isChanged('user_id'))
		{
			$this->_setIsBanned($this->getExisting('user_id'), false);
		}

		if ($this->isChanged('user_id'))
		{
			$this->_setIsBanned($this->get('user_id'), true);
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$this->_setIsBanned($this->get('user_id'), false);
	}

	/**
	 * Sets the is banned state for the specified user.
	 *
	 * @param integer $userId
	 * @param boolean $isBanned
	 */
	protected function _setIsBanned($userId, $isBanned)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$dw->setExistingData($userId);
		$dw->set('is_banned', $isBanned ? 1 : 0);
		$dw->save();

		if ($dw->isChanged('is_banned'))
		{
			if ($isBanned)
			{
				// newly banned - add to group
				$addGroup = $this->getOption(self::OPTION_ADD_GROUP);
				if ($addGroup)
				{
					$this->_getUserModel()->addUserGroupChange($userId, 'banGroup', $addGroup);
				}
			}
			else
			{
				// newly unbanned - remove
				$this->_getUserModel()->removeUserGroupChange($userId, 'banGroup');
			}
		}
	}

	/**
	 * @return XenForo_Model_Banning
	 */
	protected function _getBanningModel()
	{
		return $this->getModelFromCache('XenForo_Model_Banning');
	}
}