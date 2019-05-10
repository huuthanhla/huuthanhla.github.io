<?php

/**
 * Model for reporting content.
 *
 * @package XenForo_Report
 */
class XenForo_Model_Report extends XenForo_Model
{
	/**
	 * Gets the specified report.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getReportById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT report.*,
				user.*,
				user_profile.*,
				assigned.username AS assigned_username
			FROM xf_report AS report
			LEFT JOIN xf_user AS assigned ON (assigned.user_id = report.assigned_user_id)
			LEFT JOIN xf_user AS user ON (user.user_id = report.content_user_id)
			LEFT JOIN xf_user_profile AS user_profile ON (user_profile.user_id = report.content_user_id)
			WHERE report.report_id = ?
		', $id);
	}

	/**
	 * Gets the report for a specified content if it exists.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 *
	 * @return array|false
	 */
	public function getReportByContent($contentType, $contentId)
	{
		return $this->_getDb()->fetchRow('
			SELECT report.*,
				user.*,
				assigned.username AS assigned_username
			FROM xf_report AS report
			LEFT JOIN xf_user AS assigned ON (assigned.user_id = report.assigned_user_id)
			LEFT JOIN xf_user AS user ON (user.user_id = report.content_user_id)
			WHERE report.content_type = ?
				AND report.content_id = ?
		', array($contentType, $contentId));
	}

	public function getReportsByContentIds($contentType, array $contentIds)
	{
		if (!$contentIds)
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT report.*,
				user.*,
				assigned.username AS assigned_username
			FROM xf_report AS report
			LEFT JOIN xf_user AS assigned ON (assigned.user_id = report.assigned_user_id)
			LEFT JOIN xf_user AS user ON (user.user_id = report.content_user_id)
			WHERE report.content_type = ?
				AND report.content_id IN (' . $this->_getDb()->quote($contentIds) . ')
		', 'report_id', $contentType);
	}

	public function getReportsByContentUserId($contentUserId, array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT report.*,
					user.*,
					assigned.username AS assigned_username
				FROM xf_report AS report
				LEFT JOIN xf_user AS assigned ON (assigned.user_id = report.assigned_user_id)
				LEFT JOIN xf_user AS user ON (user.user_id = report.content_user_id)
				WHERE report.content_user_id = ?
				ORDER BY report.last_modified_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		), 'report_id', $contentUserId);
	}

	public function countReportsByContentUserId($contentUserId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_report AS report
			WHERE report.content_user_id = ?
		', $contentUserId);
	}

	/**
	 * Gets all the active (open, assigned) reports.
	 *
	 * @return array [report id] => info
	 */
	public function getActiveReports()
	{
		return $this->fetchAllKeyed('
			SELECT report.*,
				user.*,
				assigned.username AS assigned_username
			FROM xf_report AS report
			LEFT JOIN xf_user AS assigned ON (assigned.user_id = report.assigned_user_id)
			LEFT JOIN xf_user AS user ON (user.user_id = report.content_user_id)
			WHERE report.report_state IN (\'open\', \'assigned\')
			ORDER BY report.last_modified_date DESC
		', 'report_id');
	}

	/**
	 * Gets closed (resolved, rejected) in the specified time frame.
	 *
	 * @param integer $minimumTimestamp Minimum timestamp to display reports from
	 * @param integer|null $maximumTimestamp Maximum timestamp to display reports to; null means until now
	 *
	 * @return array [report id] => info
	 */
	public function getClosedReportsInTimeFrame($minimumTimestamp, $maximumTimestamp = null)
	{
		if ($maximumTimestamp === null)
		{
			$maximumTimestamp = XenForo_Application::$time;
		}

		return $this->fetchAllKeyed('
			SELECT report.*,
				user.*,
				assigned.username AS assigned_username
			FROM xf_report AS report
			LEFT JOIN xf_user AS assigned ON (assigned.user_id = report.assigned_user_id)
			LEFT JOIN xf_user AS user ON (user.user_id = report.content_user_id)
			WHERE report.report_state IN (\'resolved\', \'rejected\')
				AND report.last_modified_date > ?
				AND report.last_modified_date <= ?
			ORDER BY report.last_modified_date DESC
		', 'report_id', array($minimumTimestamp, $maximumTimestamp));
	}

	/**
	 * Filters out the reports a user cannot see from a list. Automatically prepares reports for display.
	 *
	 * @param array $reports List of reports; keyed by report ID
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return array Visible reports; [report id] => info (prepared)
	 */
	public function getVisibleReportsForUser(array $reports, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		if (!$viewingUser['user_id'])
		{
			return array();
		}

		$reportsGrouped = array();
		foreach ($reports AS $reportId => $report)
		{
			$reportsGrouped[$report['content_type']][$reportId] = $report;
		}

		if (!$reportsGrouped)
		{
			return array();
		}

		$reportHandlers = $this->getReportHandlers();

		$userReports = array();
		foreach ($reportsGrouped AS $contentType => $typeReports)
		{
			if (!empty($reportHandlers[$contentType]))
			{
				$handler = $reportHandlers[$contentType];

				$typeReports = $handler->getVisibleReportsForUser($typeReports, $viewingUser);
				$userReports += $handler->prepareReports($typeReports);
			}
		}

		$outputReports = array();
		foreach ($reports AS $reportId => $null)
		{
			if (isset($userReports[$reportId]))
			{
				$outputReports[$reportId] = $userReports[$reportId];
				$outputReports[$reportId]['isVisible'] = true;
			}
		}

		return $outputReports;
	}

	/**
	 * Filters out the reports a user cannot see from a list. Automatically prepares reports for display.
	 *
	 * @param array $reports List of reports; keyed by report ID
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return array [report id] => info (prepared) with isVisible flag
	 */
	public function flagVisibleReportsForUser(array $reports, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		if (!$viewingUser['user_id'])
		{
			return array();
		}

		$reportsGrouped = array();
		foreach ($reports AS $reportId => $report)
		{
			$reportsGrouped[$report['content_type']][$reportId] = $report;
		}

		if (!$reportsGrouped)
		{
			return array();
		}

		$reportHandlers = $this->getReportHandlers();

		$userReports = array();
		foreach ($reportsGrouped AS $contentType => $typeReports)
		{
			if (!empty($reportHandlers[$contentType]))
			{
				$handler = $reportHandlers[$contentType];

				$typeReports = $handler->getVisibleReportsForUser($typeReports, $viewingUser);
				$userReports += $handler->prepareReports($typeReports);
			}
		}

		$outputReports = array();
		foreach ($reports AS $reportId => $report)
		{
			if (isset($userReports[$reportId]))
			{
				$outputReports[$reportId] = $userReports[$reportId];
				$outputReports[$reportId]['isVisible'] = true;
			}
			else if (!empty($reportHandlers[$report['content_type']]))
			{
				$handler = $reportHandlers[$report['content_type']];
				$outputReports[$reportId] = $handler->prepareReport($report);
				$outputReports[$reportId]['isVisible'] = false;
			}
		}

		return $outputReports;
	}

	/**
	 * Gets counters for all active reports for a specified user.
	 *
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return array Keys: total, assigned, lastUpdate
	 */
	public function getActiveReportsCountsForUser(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$visibleReports = $this->getVisibleReportsForUser($this->getActiveReports(), $viewingUser);

		return $this->getSessionCountsForReports($visibleReports, $viewingUser['user_id']);
	}

	/**
	 * Gets the counts for the session data, using the given reports. Reports are assumed
	 * to be visible.
	 *
	 * @param array $reports
	 *
	 * @param integer $userId
	 *
	 * @return array Keys: total, assigned
	 */
	public function getSessionCountsForReports(array $reports, $userId)
	{
		$counts = array(
			'total' => count($reports),
			'assigned' => 0,
			'lastUpdate' => 0
		);
		foreach ($reports AS $report)
		{
			if ($report['assigned_user_id'] == $userId)
			{
				$counts['assigned']++;
			}
			if ($report['last_modified_date'] > $counts['lastUpdate'])
			{
				$counts['lastUpdate'] = $report['last_modified_date'];
			}
		}

		return $counts;
	}

	/**
	 * Gets the specified report if it is visable to the viewing user.
	 *
	 * @param integer $reportId
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return array|false
	 */
	public function getVisibleReportById($reportId, array $viewingUser = null)
	{
		$report = $this->getReportById($reportId);
		$reports = $this->getVisibleReportsForUser(array($report['report_id'] => $report), $viewingUser);
		return reset($reports);
	}

	public function getUsersWhoCanViewReport(array $report, array $potentialUsers = null)
	{
		$handler = $this->getReportHandler($report['content_type']);
		if (!$handler)
		{
			return array();
		}

		if ($potentialUsers === null)
		{
			/** @var $moderatorModel XenForo_Model_Moderator */
			$moderatorModel = $this->getModelFromCache('XenForo_Model_Moderator');
			$moderators = $moderatorModel->getAllGeneralModerators();

			/** @var $userModel XenForo_Model_User */
			$userModel = $this->getModelFromCache('XenForo_Model_User');
			$potentialUsers = $userModel->getUsersByIds(array_keys($moderators), array(
				'join' => XenForo_Model_User::FETCH_USER_FULL | XenForo_Model_User::FETCH_USER_PERMISSIONS
			));
		}

		$reports = array($report['report_id'] => $report);

		$users = array();
		foreach ($potentialUsers AS $potentialUser)
		{
			if (!$potentialUser['is_moderator'])
			{
				continue;
			}

			if (!isset($potentialUser['permissions']))
			{
				$potentialUser['permissions'] = XenForo_Permission::unserializePermissions($potentialUser['global_permission_cache']);
			}

			if ($handler->getVisibleReportsForUser($reports, $potentialUser))
			{
				$users[$potentialUser['user_id']] = $potentialUser;
			}
		}

		return $users;
	}

	/**
	 * Prepares the specified report using the necessary handler.
	 *
	 * @param array $report
	 *
	 * @return array
	 */
	public function prepareReport(array $report)
	{
		$handler = $this->getReportHandler($report['content_type']);
		if ($handler)
		{
			$report = $handler->prepareReport($report);
		}

		return $report;
	}

	/**
	 * Reports a piece of content.
	 *
	 * @param string $contentType
	 * @param array $content Information about content
	 * @param string $message
	 * @param array|null $viewingUser User reporting; null means visitor
	 *
	 * @return bool|integer Report ID or false if no report was made, true if reported into a forum
	 */
	public function reportContent($contentType, array $content, $message, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		$handler = $this->getReportHandler($contentType);
		if (!$handler)
		{
			return false;
		}

		list($contentId, $contentUserId, $contentInfo) = $handler->getReportDetailsFromContent($content);
		if (!$contentId)
		{
			return false;
		}

		$reportForumId = XenForo_Application::getOptions()->reportIntoForumId;
		if ($reportForumId)
		{
			/** @var $forumModel XenForo_Model_Forum */
			$forumModel = $this->getModelFromCache('XenForo_Model_Forum');
			$reportForum = $forumModel->getForumById($reportForumId);
			if ($reportForum)
			{
				$report = array(
					'content_type' => $contentType,
					'content_id' => $contentId,
					'content_user_id' => $contentUserId,
					'content_info' => $contentInfo,
					'first_report_date' => XenForo_Application::$time,
					'report_state' => 'open',
					'assigned_user_id' => 0,
					'comment_count' => 0,
					'report_count' => 0
				);

				$params = $handler->getContentForThread($report, $contentInfo);

				$user = $this->getModelFromCache('XenForo_Model_User')->getUserById($contentUserId);
				if ($user)
				{
					$params['username'] = $user['username'];
				}
				$params['userLink'] = XenForo_Link::buildPublicLink('canonical:members', $user);
				$params['reporter'] = $viewingUser['username'];
				$params['reporterLink'] = XenForo_Link::buildPublicLink('canonical:members', $viewingUser);
				$params['reportReason'] = $message;

				foreach ($params AS &$param)
				{
					if ($param instanceof XenForo_Phrase)
					{
						// make sure that params in phrases don't get escaped
						$newParam = clone $param;
						$newParam->setEscapeCallback(false);
						$param = $newParam;
					}
				}

				$threadTitle = new XenForo_Phrase('reported_thread_title', $params, false);

				/** @var $threadDw XenForo_DataWriter_Discussion_Thread */
				$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread', XenForo_DataWriter::ERROR_SILENT);
				$threadDw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $reportForum);
				$threadDw->setOption(XenForo_DataWriter_Discussion::OPTION_TRIM_TITLE, true);
				$threadDw->bulkSet(array(
					'node_id' => $reportForum['node_id'],
					'title' => $threadTitle->render(),
					'user_id' => $viewingUser['user_id'],
					'username' => $viewingUser['username']
				));
				$threadDw->set('discussion_state', $this->getModelFromCache('XenForo_Model_Post')->getPostInsertMessageState(array(), $reportForum));

				$message = new XenForo_Phrase('reported_thread_message', $params, false);

				$postWriter = $threadDw->getFirstMessageDw();
				$postWriter->set('message', $message->render());
				$postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $reportForum);

				return $threadDw->save();
			}

			return false;
		}

		$newReportState = '';

		$report = $this->getReportByContent($contentType, $contentId);
		if ($report)
		{
			$reportId = $report['report_id'];

			if ($report['report_state'] == 'resolved' || $report['report_state'] == 'rejected')
			{
				// re-open an existing report
				$reportDw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
				$reportDw->setExistingData($report, true);
				$reportDw->set('report_state', 'open');
				$reportDw->save();

				$newReportState = 'open';
			}
		}
		else
		{
			$reportDw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
			$reportDw->bulkSet(array(
				'content_type' => $contentType,
				'content_id' => $contentId,
				'content_user_id' => $contentUserId,
				'content_info' => $contentInfo
			));
			$reportDw->save();

			$reportId = $reportDw->get('report_id');
		}

		$reasonDw = XenForo_DataWriter::create('XenForo_DataWriter_ReportComment');
		$reasonDw->bulkSet(array(
			'report_id' => $reportId,
			'user_id' => $viewingUser['user_id'],
			'username' => $viewingUser['username'],
			'message' => $message,
			'state_change' => $newReportState,
			'is_report' => 1
		));
		$reasonDw->save();

		return $reportId;
	}

	/**
	 * Determines if the specified user can update the given report.
	 *
	 * @param array $report
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canUpdateReport(array $report, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($report['report_state'] == 'resolved' || $report['report_state'] == 'rejected')
		{
			return false;
		}

		return true;
	}

	/**
	 * Determines if the specified user can be assigned to the given report.
	 * Note that this does allow a user to steal an assignement.
	 *
	 * @param array $report
	 * @param array|null $viewingUser Viewing user reference
	 *
	 * @return boolean
	 */
	public function canAssignReport(array $report, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($report['report_state'] == 'resolved' || $report['report_state'] == 'rejected')
		{
			return false;
		}

		return ($report['assigned_user_id'] != $viewingUser['user_id']);
	}

	/**
	 * Gets all the comments for a report.
	 *
	 * @param integer $reportId
	 *
	 * @return array Format: [reason id] => info
	 */
	public function getReportComments($reportId)
	{
		return $this->fetchAllKeyed('
			SELECT report_comment.*,
				user.*
			FROM xf_report_comment AS report_comment
			LEFT JOIN xf_user AS user ON (user.user_id = report_comment.user_id)
			WHERE report_comment.report_id = ?
			ORDER BY report_comment.comment_date
		', 'report_comment_id', $reportId);
	}

	public function prepareReportComments(array $comments)
	{
		return array_map(array($this, 'prepareReportComment'), $comments);
	}

	public function prepareReportComment(array $comment)
	{
		switch ($comment['state_change'])
		{
			case 'open': $comment['stateChange'] = new XenForo_Phrase('open_report'); break;
			case 'assigned': $comment['stateChange'] = new XenForo_Phrase('assigned'); break;
			case 'resolved': $comment['stateChange'] = new XenForo_Phrase('resolved'); break;
			case 'rejected': $comment['stateChange'] = new XenForo_Phrase('rejected'); break;
			default: $comment['stateChange'] = '';
		}

		return $comment;
	}

	public function updateReports(array $reports, $state, $changeActiveOnly = false, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$total = 0;

		foreach ($reports AS $report)
		{
			if ($changeActiveOnly && !in_array($report['report_state'], array('open', 'assigned')))
			{
				continue;
			}

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Report', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($report);
			$dw->set('report_state', $state);
			$dw->set('assigned_user_id', $viewingUser['user_id']);
			if ($dw->save())
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_ReportComment', XenForo_DataWriter::ERROR_SILENT);
				$dw->bulkSet(array(
					'report_id' => $report['report_id'],
					'user_id' => $viewingUser['user_id'],
					'username' => $viewingUser['username'],
					'message' => '',
					'state_change' => $state
				));
				$dw->save();

				$total++;
			}
		}

		return $total;
	}

	public function getReportingUsersForReport($reportId)
	{
		return $this->fetchAllKeyed("
			SELECT DISTINCT user.*
			FROM xf_report_comment AS report_comment
			INNER JOIN xf_user AS user ON (report_comment.user_id = user.user_id)
			WHERE report_comment.report_id = ?
				AND report_comment.is_report = 1
			ORDER BY user.username
		", 'user_id', $reportId);
	}

	public function sendAlertsOnReportResolution(array $report, $comment = '')
	{
		$handler = $this->getReportHandler($report['content_type']);
		if (!$handler)
		{
			return;
		}

		$report = $handler->prepareReport($report);

		$users = $this->getReportingUsersForReport($report['report_id']);
		foreach ($users AS $user)
		{
			XenForo_Model_Alert::alert(
				$user['user_id'],
				0,
				'',
				'user',
				$user['user_id'],
				'report_resolved',
				array(
					'comment' => $comment,
					'title' => htmlspecialchars_decode(strval($report['contentTitle'])),
					'link' => strval($report['contentLink'])
				)
			);
		}
	}

	public function sendAlertsOnReportRejection(array $report, $comment = '')
	{
		$handler = $this->getReportHandler($report['content_type']);
		if (!$handler)
		{
			return;
		}

		$report = $handler->prepareReport($report);

		$users = $this->getReportingUsersForReport($report['report_id']);
		foreach ($users AS $user)
		{
			XenForo_Model_Alert::alert(
				$user['user_id'],
				0,
				'',
				'user',
				$user['user_id'],
				'report_rejected',
				array(
					'comment' => $comment,
					'title' => htmlspecialchars_decode(strval($report['contentTitle'])),
					'link' => strval($report['contentLink'])
				)
			);
		}
	}

	/**
	 * Gets the report handler object for the specified content.
	 *
	 * @param string $contentType
	 *
	 * @return XenForo_ReportHandler_Abstract|false
	 */
	public function getReportHandler($contentType)
	{
		$handlerClass = $this->getContentTypeField($contentType, 'report_handler_class');
		if (!$handlerClass || !class_exists($handlerClass))
		{
			return false;
		}

		$handlerClass = XenForo_Application::resolveDynamicClass($handlerClass);
		return new $handlerClass();
	}

	/**
	 * Gets the timestamp of the latest report modification.
	 *
	 * @return integer
	 */
	public function getLatestReportModificationDate()
	{
		$date = $this->_getDb()->fetchOne('
			SELECT MAX(last_modified_date)
			FROM xf_report
		');
		return ($date ? $date : 0);
	}

	/**
	 * Rebuilds the report count cache.
	 *
	 * @param integer|null $activeCount Number of active reports; null to calculate automatically
	 *
	 * @return array
	 */
	public function rebuildReportCountCache($activeCount = null)
	{
		if ($activeCount === null)
		{
			$activeCount = count($this->getActiveReports());
		}

		$cache = array(
			'activeCount' => $activeCount,
			'lastModifiedDate' => XenForo_Application::$time
		);

		$this->_getDataRegistryModel()->set('reportCounts', $cache);

		return $cache;
	}

	/**
	 * Gets all report handler classes.
	 *
	 * @return XenForo_ReportHandler_Abstract[]
	 */
	public function getReportHandlers()
	{
		$classes = $this->getContentTypesWithField('report_handler_class');
		$handlers = array();
		foreach ($classes AS $contentType => $class)
		{
			if (!class_exists($class))
			{
				continue;
			}

			$handlers[$contentType] = new $class();
		}

		return $handlers;
	}
}