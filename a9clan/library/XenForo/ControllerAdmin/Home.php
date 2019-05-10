<?php

class XenForo_ControllerAdmin_Home extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		$this->getModelFromCache('XenForo_Model_Cron')->retriggerCron();

		$boardTotals = $this->getModelFromCache('XenForo_Model_DataRegistry')->get('boardTotals');
		if (!$boardTotals)
		{
			$boardTotals = $this->getModelFromCache('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
		}

		$userModel = $this->getModelFromCache('XenForo_Model_User');
		$addOnModel = $this->getModelFromCache('XenForo_Model_AddOn');

		$sessionModel = $this->getModelFromCache('XenForo_Model_Session');

		$onlineUsers = $sessionModel->countSessionActivityRecords(array(
			'cutOff' => array('>', $sessionModel->getOnlineStatusTimeout())
		));

		$visitor = XenForo_Visitor::getInstance();

		if ($visitor->hasAdminPermission('style'))
		{
			$outdatedTemplates = count($this->getModelFromCache('XenForo_Model_Template')->getOutdatedTemplates());
		}
		else
		{
			$outdatedTemplates = 0;
		}

		if ($visitor->hasAdminPermission('viewLogs'))
		{
			$serverErrorLogs = $this->getModelFromCache('XenForo_Model_Log')->countServerErrors();
		}
		else
		{
			$serverErrorLogs = 0;
		}

		$viewParams = array(
			'canManageOptions' => $visitor->hasAdminPermission('option'),
			'canManageNodes' => $visitor->hasAdminPermission('node'),
			'canManageUsers' => $visitor->hasAdminPermission('user'),
			'canManageAddOns' => $visitor->hasAdminPermission('addOn'),
			'canManageStyles' => $visitor->hasAdminPermission('style'),
			'canManageLanguages' => $visitor->hasAdminPermission('language'),
			'canManageBbCode' => $visitor->hasAdminPermission('bbCodeSmilie'),

			'addOns' => $addOnModel->getAllAddOns(),

			'boardTotals' => $boardTotals,
			'outdatedTemplates' => $outdatedTemplates,
			'serverErrorLogs' => $serverErrorLogs,
			'manualDeferredTotal' => count($this->getModelFromCache('XenForo_Model_Deferred')->getStoppedManualDefers()),

			'users' => array(
				'total' => $boardTotals['users'],
				'awaitingApproval' => $userModel->countUsers(array('user_state' => 'moderated')),
				'online' => $onlineUsers
			)
		);

		return $this->responseView('XenForo_ViewAdmin_Home', 'home', $viewParams);
	}
}