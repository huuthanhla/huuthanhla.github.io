<?php

class XenForo_DataWriter_ForumWatch extends XenForo_DataWriter
{
	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_forum_watch' => array(
				'user_id'    => array('type' => self::TYPE_UINT,    'required' => true),
				'node_id'    => array('type' => self::TYPE_UINT,    'required' => true),
				'notify_on'  => array('type' => self::TYPE_STRING, 'default' => '',
					'allowedValues' => array('', 'thread', 'message')
				),
				'send_alert' => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'send_email' => array('type' => self::TYPE_BOOLEAN, 'default' => 0)
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
		if (!is_array($data))
		{
			return false;
		}
		else if (isset($data['user_id'], $data['node_id']))
		{
			$userId = $data['user_id'];
			$nodeId = $data['node_id'];
		}
		else if (isset($data[0], $data[1]))
		{
			$userId = $data[0];
			$nodeId = $data[1];
		}
		else
		{
			return false;
		}

		return array('xf_forum_watch' => $this->_getForumWatchModel()->getUserForumWatchByForumId($userId, $nodeId));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'user_id = ' . $this->_db->quote($this->getExisting('user_id'))
			. ' AND node_id = ' . $this->_db->quote($this->getExisting('node_id'));
	}

	/**
	 * @return XenForo_Model_ForumWatch
	 */
	protected function _getForumWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ForumWatch');
	}
}