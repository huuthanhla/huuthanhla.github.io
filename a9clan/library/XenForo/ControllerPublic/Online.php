<?php

/**
 * Controller for online users list.
 *
 * @package XenForo_Online
 */
class XenForo_ControllerPublic_Online extends XenForo_ControllerPublic_Abstract
{
	/**
	 * List of currently online users.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		if (!$this->_getUserModel()->canViewMemberList())
		{
			return $this->responseNoPermission();
		}

		$sessionModel = $this->_getSessionModel();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$userPerPage = XenForo_Application::get('options')->membersPerPage;

		$bypassUserPrivacy = $this->_getUserModel()->canBypassUserPrivacy();
		$userLimit = $this->_input->filterSingle('type', XenForo_Input::STRING);

		$conditions = array(
			'userLimit' => $userLimit,
			'cutOff' => array('>', $sessionModel->getOnlineStatusTimeout()),
			'getInvisible' => $bypassUserPrivacy,
			'getUnconfirmed' => $bypassUserPrivacy,

			// allow force including of self, even if invisible
			'forceInclude' => ($bypassUserPrivacy ? false : XenForo_Visitor::getUserId())
		);

		$onlineUsers = $sessionModel->getSessionActivityRecords($conditions, array(
			'perPage' => $userPerPage,
			'page' => $page,
			'join' => XenForo_Model_Session::FETCH_USER_FULL,
			'order' => 'view_date'
		));
		$session = XenForo_Application::getSession();
		foreach ($onlineUsers AS &$online)
		{
			if ($online['robot_key'])
			{
				$online['robotInfo'] = $session->getRobotInfo($online['robot_key']);
			}
			$online['ipHex'] = bin2hex($online['ip']);

			$online['canViewCurrentActivity'] = $this->_getUserModel()->canViewUserCurrentActivity($online);
		}

		$visitor = XenForo_Visitor::getInstance();

		//TODO: this is taken directly from the forum list, could be faster if just counts
		$onlineTotals = $sessionModel->getSessionActivityQuickList(
			$visitor->toArray(),
			array('cutOff' => array('>', $sessionModel->getOnlineStatusTimeout())),
			($visitor['user_id'] ? $visitor->toArray() : null)
		);

		$viewParams = array(
			'onlineUsers' => $sessionModel->addSessionActivityDetailsToList($onlineUsers),
			'totalOnlineUsers' => $sessionModel->countSessionActivityRecords($conditions),
			'userLimit' => $userLimit,

			'page' => $page,
			'usersPerPage' => $userPerPage,

			'canViewIps' => $this->_getUserModel()->canViewIps(),

			'onlineTotals' => $onlineTotals
		);

		return $this->responseView('XenForo_ViewPublic_Online_List', 'online_list', $viewParams);
	}

	/**
	 * Fetch the current IP for the specified online user
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUserIp()
	{
		if (!$this->_getUserModel()->canViewIps($errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserModel()->getUserById($userId, array('join' => XenForo_Model_User::FETCH_LAST_ACTIVITY));

		if (!$user || !$user['ip'])
		{
			return $this->responseError(new XenForo_Phrase('no_ip_information_available'));
		}

		$viewParams = array(
			'user' => $user,
			'ipInfo' => $this->getModelFromCache('XenForo_Model_Ip')->getOnlineUserIp($user)
		);

		return $this->responseView('XenForo_ViewPublic_Online_UserIp', 'online_user_ip', $viewParams);
	}

	/**
	 * Fetches the current IP for the specified online guest
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionGuestIp()
	{
		if (!$this->_getUserModel()->canViewIps($errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$ip = preg_replace('#[^0-9a-f]#', '', $this->_input->filterSingle('ip', XenForo_Input::STRING));

		try
		{
			$ip = XenForo_Helper_Ip::convertIpBinaryToString(
				XenForo_Helper_Ip::convertHexToBin($ip)
			);
		}
		catch (Exception $e)
		{
			$ip = false;
		}

		if (!$ip)
		{
			// likely given an invalid IP
			return $this->responseError(new XenForo_Phrase('unexpected_error_occurred'));
		}

		$viewParams = array(
			'ip' => $ip,
			'host' => XenForo_Model_Ip::getHost($ip)
		);

		return $this->responseView('XenForo_ViewPublic_Online_GuestIp', 'online_guest_ip', $viewParams);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('viewing_list_of_online_members');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenForo_Model_Session
	 */
	protected function _getSessionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Session');
	}
}