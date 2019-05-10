<?php

/**
* Data writer for warnings.
*/
class XenForo_DataWriter_Warning extends XenForo_DataWriter
{
	const DATA_CONTENT = 'content';

	const DATA_PUBLIC_WARNING = 'publicWarning';

	const DATA_DELETION_REASON = 'deletionReason';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_warning_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_warning' => array(
				'warning_id'            => array('type' => self::TYPE_UINT,    'autoIncrement' => true),
				'content_type'          => array('type' => self::TYPE_STRING,  'required' => true, 'maxLength' => 25),
				'content_id'            => array('type' => self::TYPE_UINT,    'required' => true),
				'content_title'         => array('type' => self::TYPE_STRING,  'default' => '', 'maxLength' => 255),
				'user_id'               => array('type' => self::TYPE_UINT,    'required' => true),
				'warning_date'          => array('type' => self::TYPE_UINT,    'default' => XenForo_Application::$time),
				'warning_user_id'       => array('type' => self::TYPE_UINT,    'required' => true),
				'warning_definition_id' => array('type' => self::TYPE_UINT,    'required' => true),
				'title'                 => array('type' => self::TYPE_STRING,  'required' => true,
					'requiredError' => 'please_enter_valid_title', 'maxLength' => 255
				),
				'notes'                 => array('type' => self::TYPE_STRING,  'default' => ''),
				'points'                => array('type' => self::TYPE_UINT,    'required' => true, 'max' => 65535),
				'expiry_date'           => array('type' => self::TYPE_UINT,    'default' => 0),
				'is_expired'            => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
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
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_warning' => $this->_getWarningModel()->getWarningById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'warning_id = ' . $this->_db->quote($this->getExisting('warning_id'));
	}

	protected function _preSave()
	{
		if ($this->isUpdate() && ($this->isChanged('points') || $this->isChanged('extra_user_group_ids')))
		{
			throw new XenForo_Exception('Cannot change warning points/groups after creation');
		}

		if ($this->get('expiry_date') > 0 AND $this->get('expiry_date') <= XenForo_Application::$time)
		{
			$this->set('is_expired', 1);
		}
	}

	protected function _postSave()
	{
		if ($this->isInsert() || ($this->get('is_expired') == 0 && $this->getExisting('is_expired') == 1))
		{
			if ($this->get('extra_user_group_ids'))
			{
				$this->getModelFromCache('XenForo_Model_User')->addUserGroupChange(
					$this->get('user_id'), 'warning_' . $this->get('warning_id'), $this->get('extra_user_group_ids')
				);
			}

			if ($this->get('points'))
			{
				$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
				if ($userDw->setExistingData($this->get('user_id')))
				{
					$userDw->set('warning_points', $userDw->get('warning_points') + $this->get('points'));
					$userDw->save();
				}
			}

			$warningHandler = $this->_getWarningModel()->getWarningHandler($this->get('content_type'));
			if ($warningHandler)
			{
				$content = $this->getExtraData(self::DATA_CONTENT);
				if (!$content)
				{
					$content = $warningHandler->getContent($this->get('content_id'));
				}

				if ($content)
				{
					$warningHandler->warn($this->getMergedData(), $content,
						(string)$this->getExtraData(self::DATA_PUBLIC_WARNING));

					if ($this->isExtraDataSet(self::DATA_DELETION_REASON))
					{
						$deletionReason = (string)$this->getExtraData(self::DATA_DELETION_REASON);
						$warningHandler->deleteContent($content, $deletionReason);
					}

				}
			}
		}
		else if ($this->isUpdate() && $this->get('is_expired') == 1 && $this->getExisting('is_expired') == 0)
		{
			$this->_warningExpiredOrDeleted();
		}
	}

	protected function _postDelete()
	{
		if (!$this->get('is_expired'))
		{
			$this->_warningExpiredOrDeleted(true);
		}

		$warningHandler = $this->_getWarningModel()->getWarningHandler($this->get('content_type'));
		if ($warningHandler)
		{
			$content = $this->getExtraData(self::DATA_CONTENT);
			if (!$content)
			{
				$content = $warningHandler->getContent($this->get('content_id'));
				if (!$content)
				{
					$content = array();
				}
			}

			$warningHandler->reverseWarning($this->getMergedData(), $content);
		}

		$this->getModelFromCache('XenForo_Model_User')->removeUserGroupChangeLogByKey(
			'warning_' . $this->get('warning_id')
		);
	}

	protected function _warningExpiredOrDeleted($isDelete = false)
	{
		if ($this->get('extra_user_group_ids'))
		{
			$this->getModelFromCache('XenForo_Model_User')->removeUserGroupChange(
				$this->get('user_id'), 'warning_' . $this->get('warning_id')
			);
		}

		if ($this->get('points'))
		{
			$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
			if ($userDw->setExistingData($this->get('user_id')))
			{
				$oldPoints = $userDw->get('warning_points');
				$newPoints = $userDw->get('warning_points') - $this->get('points');

				$userDw->set('warning_points', $newPoints);
				$userDw->save();

				if ($isDelete)
				{
					$warningModel = $this->_getWarningModel();
					$actions = $warningModel->getWarningActions();
					foreach ($actions AS $action)
					{
						if ($oldPoints >= $action['points']
							&& $newPoints < $action['points']
							&& $action['action_length_type'] != 'points' // points threshold actions will be sorted by changing the points
						)
						{
							$warningModel->removeWarningActionEffects($this->get('user_id'), $action);
						}
					}
				}

			}
		}
	}

	/**
	 * @return XenForo_Model_Warning
	 */
	protected function _getWarningModel()
	{
		return $this->getModelFromCache('XenForo_Model_Warning');
	}
}