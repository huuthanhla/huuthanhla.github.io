<?php

/**
 * Controller for managing reported content.
 *
 * @package XenForo_Report
 */
class XenForo_ControllerPublic_Report extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Pre-dispatch, ensure visitor is a moderator.
	 */
	protected function _preDispatch($action)
	{
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor['is_moderator'])
		{
			throw $this->getNoPermissionResponseException();
		}
	}

	/**
	 * Displays a list of active reports for the visiting user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$reportId = $this->_input->filterSingle('report_id', XenForo_Input::UINT);
		if ($reportId)
		{
			return $this->responseReroute(__CLASS__, 'view');
		}

		$reportModel = $this->_getReportModel();

		$activeReports = $reportModel->getActiveReports();

		if (XenForo_Application::isRegistered('reportCounts'))
		{
			$reportCounts = XenForo_Application::get('reportCounts');
			if (count($activeReports) != $reportCounts['activeCount'])
			{
				$reportModel->rebuildReportCountCache(count($activeReports));
			}
		}

		XenForo_Application::getSession()->set('reportLastRead', XenForo_Application::$time);

		$reports = $reportModel->getVisibleReportsForUser($activeReports);

		$session = XenForo_Application::get('session');
		$sessionReportCounts = $session->get('reportCounts');

		if (!is_array($sessionReportCounts) || $sessionReportCounts['total'] != count($reports))
		{
			$sessionReportCounts = $reportModel->getSessionCountsForReports($reports, XenForo_Visitor::getUserId());
			$sessionReportCounts['lastBuildDate'] = XenForo_Application::$time;
			$session->set('reportCounts', $sessionReportCounts);
		}

		$closedReportsRaw = $reportModel->getClosedReportsInTimeFrame(XenForo_Application::$time - 86400, XenForo_Application::$time);

		$viewParams = array(
			'reports' => $reports,
			'recentlyClosed' => $reportModel->getVisibleReportsForUser($closedReportsRaw),
		);

		return $this->responseView('XenForo_ViewPublic_Report_List', 'report_list', $viewParams);
	}

	public function actionSearch()
	{
		/** @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');

		$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
		if ($username)
		{
			$user = $userModel->getUserByName($username);
		}
		else
		{
			$user = $userModel->getUserById($this->_input->filterSingle('user_id', XenForo_Input::UINT));
		}

		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 50;

		$reportModel = $this->_getReportModel();

		$totalReports = $reportModel->countReportsByContentUserId($user['user_id']);
		if (!$totalReports)
		{
			return $this->responseError(new XenForo_Phrase('no_reports_were_found_for_this_users_content'));
		}

		$this->canonicalizePageNumber($page, $perPage, $totalReports, 'reports/search', array('user_id' => $user['user_id']));

		$reports = $reportModel->getReportsByContentUserId($user['user_id'], array(
			'page' => $page,
			'perPage' => $perPage
		));

		$viewParams = array(
			'reports' => $reportModel->flagVisibleReportsForUser($reports),
			'user' => $user,
			'page' => $page,
			'perPage' => $perPage,
			'totalReports' => $totalReports
		);

		return $this->responseView('XenForo_ViewPublic_Report_Search', 'report_search', $viewParams);
	}

	/**
	 * Displays a list of closed reports in a given time frame.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionClosed()
	{
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		if ($page < 1)
		{
			$page = 1;
		}

		$daysInRange = 7;
		$date = new DateTime();
		if ($page > 1)
		{
			$max = floor(XenForo_Application::$time / ($daysInRange * 86400));

			if ($page > $max)
			{
				$params = $_GET;
				$params['page'] = $max;

				$redirectUrl = $this->_buildLink('reports/closed', null, $params);

				throw $this->responseException($this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
					$redirectUrl
				));
			}

			$date->modify('-' . ($daysInRange * ($page - 1)) . ' days');
		}
		$maximumTimestamp = $date->format('U');

		$date->modify("-$daysInRange days");
		$minimumTimestamp = $date->format('U');

		$reportModel = $this->_getReportModel();
		$reportsRaw = $reportModel->getClosedReportsInTimeFrame($minimumTimestamp, $maximumTimestamp);

		$viewParams = array(
			'reports' => $reportModel->getVisibleReportsForUser($reportsRaw),
			'page' => $page,
			'minimumTimestamp' => $minimumTimestamp,
			'maximumTimestamp' => $maximumTimestamp
		);

		return $this->responseView('XenForo_ViewPublic_Report_ListClosed', 'report_list_closed', $viewParams);
	}

	/**
	 * Displays the details of a report.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionView()
	{
		$reportId = $this->_input->filterSingle('report_id', XenForo_Input::UINT);
		$report = $this->_getVisibleReportOrError($reportId);

		if (!$report['report_count'])
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Report', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($report);
			$dw->updateReportCount();
			$dw->save();

			$report['report_count'] = $dw->get('report_count');
			$report['comment_count'] = $dw->get('comment_count');
		}

		/** @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');

		$visitor = XenForo_Visitor::getInstance();

		$reportModel = $this->_getReportModel();

		$viewParams = array(
			'report' => $report,
			'comments' => $reportModel->prepareReportComments($reportModel->getReportComments($reportId)),
			'canUpdateReport' => $reportModel->canUpdateReport($report),
			'canAssignReport' => $reportModel->canAssignReport($report),

			'canCleanSpam' => (XenForo_Permission::hasPermission($visitor['permissions'], 'general', 'cleanSpam') && $userModel->couldBeSpammer($report)),
			'canBanUsers' => ($visitor['is_admin'] && $visitor->hasAdminPermission('ban') && $report['user_id'] != $visitor->getUserId() && !$report['is_admin'] && !$report['is_moderator']),
			'canEditUsers' => ($visitor['is_admin'] && $visitor->hasAdminPermission('user')),
			'canWarn' => $userModel->canWarnUser($report),
		);

		return $this->responseView('XenForo_ViewPublic_Report_View', 'report_view', $viewParams);
	}

	/**
	 * Assigns a report to visiting user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAssign()
	{
		$this->_assertPostOnly();

		$reportId = $this->_input->filterSingle('report_id', XenForo_Input::UINT);
		$report = $this->_getVisibleReportOrError($reportId);

		if (!$this->_getReportModel()->canAssignReport($report))
		{
			return $this->responseError(new XenForo_Phrase('you_can_no_longer_be_assigned_to_this_report'));
		}

		$viewedAssignedUserId = $this->_input->filterSingle('viewed_assigned_user_id', XenForo_Input::UINT);

		if ($report['assigned_user_id'] != $viewedAssignedUserId)
		{
			return $this->responseError(new XenForo_Phrase('this_report_has_been_assigned_to_another_moderator'));
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
		$dw->setExistingData($report, true);
		$dw->set('report_state', 'assigned');
		$dw->set('assigned_user_id', XenForo_Visitor::getUserId());
		$dw->save();

		$visitor = XenForo_Visitor::getInstance();

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ReportComment');
		$dw->bulkSet(array(
			'report_id' => $reportId,
			'user_id' => $visitor['user_id'],
			'username' => $visitor['username'],
			'state_change' => 'assigned'
		));
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('reports', $report)
		);
	}

	public function actionComment()
	{
		$this->_assertPostOnly();

		$reportId = $this->_input->filterSingle('report_id', XenForo_Input::UINT);
		$report = $this->_getVisibleReportOrError($reportId);

		$visitor = XenForo_Visitor::getInstance();

		$comment = $this->_input->filterSingle('comment', XenForo_Input::STRING);
		if ($comment)
		{
			$reopen = $this->_input->filterSingle('reopen', XenForo_Input::UINT);

			if ($reopen && ($report['report_state'] == 'resolved' || $report['report_state'] == 'rejected'))
			{
				// comment on a closed report reopens it
				$reportDw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
				$reportDw->setExistingData($report, true);
				$reportDw->set('report_state', 'open');
				$reportDw->save();

				$newReportState = 'open';
			}
			else
			{
				$newReportState = '';
			}

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ReportComment');
			$dw->bulkSet(array(
				'report_id' => $reportId,
				'user_id' => $visitor['user_id'],
				'username' => $visitor['username'],
				'message' => $comment,
				'state_change' => $newReportState
			));
			$dw->save();

			XenForo_Application::getSession()->set('reportLastRead', XenForo_Application::$time);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('reports', $report)
		);
	}

	/**
	 * Updates the status of a report.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpdate()
	{
		$this->_assertPostOnly();

		$reportId = $this->_input->filterSingle('report_id', XenForo_Input::UINT);
		$report = $this->_getVisibleReportOrError($reportId);

		$input = $this->_input->filter(array(
			'report_state' => XenForo_Input::STRING,
			'comment' => XenForo_Input::STRING
		));

		if (!$this->_getReportModel()->canUpdateReport($report))
		{
			return $this->responseError(new XenForo_Phrase('you_can_no_longer_update_this_report'));
		}

		$visitor = XenForo_Visitor::getInstance();

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ReportComment');
		$dw->bulkSet(array(
			'report_id' => $reportId,
			'user_id' => $visitor['user_id'],
			'username' => $visitor['username'],
			'message' => $input['comment'],
			'state_change' => $input['report_state']
		));
		$dw->save();

		XenForo_Application::getSession()->set('reportLastRead', XenForo_Application::$time);

		if ($input['report_state'])
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
			$dw->setExistingData($report, true);
			$dw->set('report_state', $input['report_state']);
			switch ($input['report_state'])
			{
				case 'assigned':
					$dw->set('assigned_user_id', XenForo_Visitor::getUserId());
					break;

				case 'open':
					$dw->set('assigned_user_id', 0);
					break;
			}
			$dw->setOption(
				XenForo_DataWriter_Report::OPTION_ALERT_REPORTERS,
				$this->_input->filterSingle('send_alert', XenForo_Input::UINT)
			);
			$dw->setOption(
				XenForo_DataWriter_Report::OPTION_ALERT_COMMENT,
				$this->_input->filterSingle('alert_comment', XenForo_Input::STRING)
			);
			$dw->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('reports', $input['report_state'] == 'assigned' ? $report : null)
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('reports', $report)
			);
		}
	}

	public function actionReassign()
	{
		$reportId = $this->_input->filterSingle('report_id', XenForo_Input::UINT);
		$report = $this->_getVisibleReportOrError($reportId);

		if (!$this->_getReportModel()->canUpdateReport($report))
		{
			return $this->responseError(new XenForo_Phrase('you_can_no_longer_update_this_report'));
		}

		$users = $this->_getReportModel()->getUsersWhoCanViewReport($report);

		if ($this->isConfirmedPost())
		{
			$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
			$visitor = XenForo_Visitor::getInstance();

			if (!$userId || isset($users[$userId]))
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_ReportComment');
				$dw->bulkSet(array(
					'report_id' => $reportId,
					'user_id' => $visitor['user_id'],
					'username' => $visitor['username'],
					'state_change' => $userId ? 'assigned' : 'open'
				));
				$dw->save();

				$dw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
				$dw->setExistingData($report, true);
				if ($userId)
				{
					$dw->set('report_state', 'assigned');
					$dw->set('assigned_user_id', $userId);
				}
				else
				{
					$dw->set('report_state', 'open');
					$dw->set('assigned_user_id', 0);
				}
				$dw->save();

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('reports')
				);
			}
		}

		$viewParams = array(
			'report' => $report,
			'users' => $users
		);

		return $this->responseView('XenForo_ViewPublic_Report_Reassign', 'report_reassign', $viewParams);
	}

	/**
	 * Gets the specified report if visible to the visiting user or throws an error.
	 *
	 * @param integer $reportId
	 *
	 * @return array
	 */
	protected function _getVisibleReportOrError($reportId)
	{
		$reportModel = $this->_getReportModel();

		$report = $reportModel->getVisibleReportById($reportId);
		if (!$report)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_report_not_found'), 404));
		}

		return $report;
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('performing_moderation_duties');
	}

	/**
	 * @return XenForo_Model_Report
	 */
	protected function _getReportModel()
	{
		return $this->getModelFromCache('XenForo_Model_Report');
	}
}