<?php

/**
* Data writer for reports
*
* @package XenForo_Report
*/
class XenForo_DataWriter_Report extends XenForo_DataWriter
{
	const OPTION_ALERT_REPORTERS = 'alertReporters';
	const OPTION_ALERT_COMMENT = 'alertComment';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_report' => array(
				'report_id'          => array('type' => self::TYPE_UINT,    'autoIncrement' => true),
				'content_type'       => array('type' => self::TYPE_STRING,  'required' => true, 'maxLength' => 25),
				'content_id'         => array('type' => self::TYPE_UINT,    'required' => true),
				'content_user_id'    => array('type' => self::TYPE_UINT,    'required' => true),
				'content_info'       => array('type' => self::TYPE_SERIALIZED, 'required' => true),
				'first_report_date'  => array('type' => self::TYPE_UINT,    'default' => XenForo_Application::$time),
				'report_state'       => array('type' => self::TYPE_STRING,  'default' => 'open',
						'allowedValues' => array('open', 'assigned', 'resolved', 'rejected')
				),
				'assigned_user_id'   => array('type' => self::TYPE_UINT,    'default' => 0),
				'comment_count'      => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'report_count'       => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'last_modified_date' => array('type' => self::TYPE_UINT,    'default' => XenForo_Application::$time),
				'last_modified_user_id'  => array('type' => self::TYPE_UINT,   'default' => 0),
				'last_modified_username' => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 50),
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

		return array('xf_report' => $this->_getReportModel()->getReportById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'report_id = ' . $this->_db->quote($this->getExisting('report_id'));
	}

	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_ALERT_REPORTERS => false,
			self::OPTION_ALERT_COMMENT => ''
		);
	}

	protected function _preSave()
	{
		if ($this->get('report_state') == 'open')
		{
			$this->set('assigned_user_id', 0);
		}
	}

	protected function _postSave()
	{
		if ($this->isChanged('last_modified_date'))
		{
			$this->_getReportModel()->rebuildReportCountCache();
		}

		if ($this->getOption(self::OPTION_ALERT_REPORTERS)
			&& $this->isChanged('report_state')
			&& $this->get('report_state') == 'resolved'
		)
		{
			$this->_getReportModel()->sendAlertsOnReportResolution(
				$this->getMergedData(),
				$this->getOption(self::OPTION_ALERT_COMMENT)
			);
		}
		else if ($this->getOption(self::OPTION_ALERT_REPORTERS)
			&& $this->isChanged('report_state')
			&& $this->get('report_state') == 'rejected'
		)
		{
			$this->_getReportModel()->sendAlertsOnReportRejection(
				$this->getMergedData(),
				$this->getOption(self::OPTION_ALERT_COMMENT)
			);
		}
	}

	public function updateReportCount()
	{
		$count = $this->_db->fetchRow("
			SELECT COUNT(IF(is_report = 1, 1, NULL)) AS report_count,
				COUNT(IF(is_report = 0, 1, NULL)) AS comment_count
			FROM xf_report_comment
			WHERE report_id = ?
				AND message <> ''
		", $this->get('report_id'));

		if (!$count['report_count'])
		{
			$count['report_count']++;
			$count['comment_count']--;
		}

		$this->set('report_count', $count['report_count']);
		$this->set('comment_count', $count['comment_count']);
	}

	/**
	 * @return XenForo_Model_Report
	 */
	protected function _getReportModel()
	{
		return $this->getModelFromCache('XenForo_Model_Report');
	}
}