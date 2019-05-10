<?php

/**
 * Handler for reported user.
 *
 * @package XenForo_Report
 */
class XenForo_ReportHandler_User extends XenForo_ReportHandler_Abstract
{
	/**
	 * Gets report details from raw array of content (eg, a post record).
	 *
	 * @see XenForo_ReportHandler_Abstract::getReportDetailsFromContent()
	 */
	public function getReportDetailsFromContent(array $content)
	{
		/* @var $userModel XenForo_Model_User */
		$userModel = XenForo_Model::create('XenForo_Model_User');

		$user = $userModel->getUserById($content['user_id']);
		if (!$user)
		{
			return array(false, false, false);
		}

		return array(
			$content['user_id'],
			$content['user_id'],
			array(
				'user' => $user
			)
		);
	}

	/**
	 * Gets the visible reports of this content type for the viewing user.
	 *
	 * @see XenForo_ReportHandler_Abstract:getVisibleReportsForUser()
	 */
	public function getVisibleReportsForUser(array $reports, array $viewingUser)
	{
		/* @var $userModel XenForo_Model_User */
		$userModel = XenForo_Model::create('XenForo_Model_User');

		foreach ($reports AS $reportId => $report)
		{
			$info = unserialize($report['content_info']);

			if (!$info
				|| empty($info['user'])
				|| !$userModel->canWarnUser($info['user'], $null, $viewingUser)
				|| !$userModel->canEditUser($info['user'], $null, $viewingUser))
			{
				unset($reports[$reportId]);
			}
		}

		return $reports;
	}

	/**
	 * Gets the title of the specified content.
	 *
	 * @see XenForo_ReportHandler_Abstract:getContentTitle()
	 */
	public function getContentTitle(array $report, array $contentInfo)
	{
		return new XenForo_Phrase('member_x', array('username' => $contentInfo['user']['username']));
	}

	/**
	 * Gets the link to the specified content.
	 *
	 * @see XenForo_ReportHandler_Abstract::getContentLink()
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		return XenForo_Link::buildPublicLink('members', $contentInfo['user']);
	}

	/**
	 * A callback that is called when viewing the full report.
	 *
	 * @see XenForo_ReportHandler_Abstract::viewCallback()
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		return '';
	}
}