<?php

/**
* Data writer for moderators (global records)
*
* @package XenForo_Moderator
*/
class XenForo_DataWriter_Moderator extends XenForo_DataWriter
{
	const OPTION_SET_IS_STAFF = 'setIsStaff';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_moderator_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_moderator' => array(
				'user_id'               => array('type' => self::TYPE_UINT,    'required' => true),
				'is_super_moderator'    => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'moderator_permissions' => array('type' => self::TYPE_SERIALIZED, 'required' => true),
				'extra_user_group_ids'  => array('type' => self::TYPE_UNKNOWN, 'default' => '',
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
		if (!$id = $this->_getExistingPrimaryKey($data, 'user_id'))
		{
			return false;
		}

		return array('xf_moderator' => $this->_getModeratorModel()->getGeneralModeratorByUserId($id));
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

	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_SET_IS_STAFF => null
		);
	}

	/**
	 * Sets the general permissions for a moderator. This does not manipulate
	 * non-general permissions that are already set.
	 *
	 * @param array $generalPermissions New set of general permissions. Overwrites old permissions.
	 */
	public function setGeneralPermissions(array $generalPermissions)
	{
		$validGeneralPermissions = $this->_getModeratorModel()->getGeneralModeratorPermissions();

		if ($this->isUpdate())
		{
			$outputPermissions = unserialize($this->getExisting('moderator_permissions'));
			foreach ($validGeneralPermissions AS $generalGroupId => $generalGroup)
			{
				foreach ($generalGroup AS $generalPermissionId => $general)
				{
					unset($outputPermissions[$generalGroupId][$generalPermissionId]);
				}
			}
		}
		else
		{
			$outputPermissions = array();
		}

		foreach ($generalPermissions AS $generalGroupId => $generalGroup)
		{
			foreach ($generalGroup AS $generalPermissionId => $general)
			{
				if (isset($validGeneralPermissions[$generalGroupId][$generalPermissionId]))
				{
					$outputPermissions[$generalGroupId][$generalPermissionId] = $general;
				}
			}
		}

		$this->set('moderator_permissions', $outputPermissions);
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->isChanged('extra_user_group_ids'))
		{
			$this->getModelFromCache('XenForo_Model_User')->addUserGroupChange(
				$this->get('user_id'), 'moderator', $this->get('extra_user_group_ids')
			);
		}

		if ($this->isChanged('moderator_permissions'))
		{
			$this->_updatePermissions($this->get('moderator_permissions'), $this->getExisting('moderator_permissions'));
		}

		$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$userDw->setExistingData($this->get('user_id'));
		$userDw->set('is_moderator', 1);
		$setIsStaff = $this->getOption(self::OPTION_SET_IS_STAFF);
		if ($setIsStaff !== null)
		{
			$userDw->set('is_staff', $setIsStaff ? 1 : 0);
		}
		$userDw->save();

		$this->getModelFromCache('XenForo_Model_UserIgnore')->rebuildUserIgnoreCacheByIgnoring($this->get('user_id'));

		// permissions may change, so we need to update the last build date
		$this->getModelFromCache('XenForo_Model_ModerationQueue')->rebuildModerationQueueCountCache();
		$this->getModelFromCache('XenForo_Model_Report')->rebuildReportCountCache();
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$contentModerators = $this->_getModeratorModel()->getContentModeratorsByUserId($this->get('user_id'));
		foreach ($contentModerators AS $contentModerator)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ModeratorContent');
			$dw->setExistingData($contentModerator, true);
			$dw->setOption(XenForo_DataWriter_ModeratorContent::OPTION_CHECK_GENERAL_MOD_ON_DELETE, false);
			$dw->delete();
		}

		$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$userDw->setExistingData($this->get('user_id'));
		$userDw->set('is_moderator', 0);
		if ($this->getOption(self::OPTION_SET_IS_STAFF) !== null)
		{
			$userDw->set('is_staff', 0);
		}
		$userDw->save();

		$this->_updatePermissions(array(), $this->getExisting('moderator_permissions'));

		$this->getModelFromCache('XenForo_Model_User')->removeUserGroupChange(
			$this->get('user_id'), 'moderator'
		);

		$this->getModelFromCache('XenForo_Model_UserIgnore')->rebuildUserIgnoreCacheByIgnoring($this->get('user_id'));
	}

	/**
	 * Helper to update permissions for this moderator.
	 *
	 * @param array|string $newPermissions Set of new permissions
	 * @param array|string $existingPermissions Set of existing permissions
	 */
	protected function _updatePermissions($newPermissions, $existingPermissions)
	{
		$finalPermissions = $this->_getModeratorModel()->getModeratorPermissionsForUpdate($newPermissions, $existingPermissions);
		$this->_getPermissionModel()->updateGlobalPermissionsForUserCollection($finalPermissions, 0, $this->get('user_id'));
	}

	/**
	 * Updates the extra user group IDs this user belongs to.
	 *
	 * @param string $newGroupIds
	 * @param string $oldGroupIds
	 */
	protected function _updateExtraUserGroupIds($newGroupIds, $oldGroupIds)
	{
		XenForo_DataWriter_Helper_User::updateSecondaryUserGroupIds(
			$this->get('user_id'), $newGroupIds, $oldGroupIds
		);
	}

	/**
	 * @return XenForo_Model_Moderator
	 */
	protected function _getModeratorModel()
	{
		return $this->getModelFromCache('XenForo_Model_Moderator');
	}

	/**
	 * @return XenForo_Model_Permission
	 */
	protected function _getPermissionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Permission');
	}
}