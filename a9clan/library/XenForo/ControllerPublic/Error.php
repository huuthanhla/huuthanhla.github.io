<?php

class XenForo_ControllerPublic_Error extends XenForo_ControllerPublic_Abstract
{
	public function actionErrorNotFound()
	{
		return $this->getNotFoundResponse();
	}

	public function actionErrorServer()
	{
		$upgradePending = false;
		try
		{
			$db = XenForo_Application::getDb();
			if ($db->isConnected())
			{
				$dbVersionId = $db->fetchOne("SELECT option_value FROM xf_option WHERE option_id = 'currentVersionId'");
				if ($dbVersionId && $dbVersionId != XenForo_Application::$versionId)
				{
					$upgradePending = true;
				}
			}
		}
		catch (Exception $e) {}

		if (XenForo_Application::debugMode())
		{
			$showDetails = true;
		}
		else if (XenForo_Visitor::hasInstance() && XenForo_Visitor::getInstance()->is_admin)
		{
			$showDetails = true;
		}
		else
		{
			$showDetails = false;
		}

		if ($upgradePending && !$showDetails)
		{
			return $this->responseMessage(new XenForo_Phrase('board_currently_being_upgraded'));
		}
		else if ($showDetails)
		{
			$view = $this->responseView(
				'XenForo_ViewPublic_Error_ServerError',
				'error_server_error',
				array('exception' => $this->_request->getParam('_exception'))
			);
			$view->responseCode = 500;
			return $view;
		}
		else
		{
			return $this->responseError(new XenForo_Phrase('server_error_occurred'), 500);
		}
	}

	public function actionNoPermission()
	{
		if (!XenForo_Visitor::getUserId())
		{
			// show login / registration form
			return $this->responseReroute(__CLASS__, 'registrationRequired');
		}
		else
		{
			// show no permission error without login form
			return $this->responseError(new XenForo_Phrase('do_not_have_permission'), 403);
		}
	}

	/**
	 * Response when a user a guest attempts to perform a restricted action
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionRegistrationRequired()
	{
		$viewParams = array(
			//'text' => new XenForo_Phrase('must_be_registered')
			'text' => new XenForo_Phrase('login_required')
		);

		$view = $this->responseView('XenForo_ViewPublic_Error_RegistrationRequired', 'error_with_login', $viewParams);
		$view->responseCode = 403;

		return $view;
	}

	public function actionBanned()
	{
		$userId = XenForo_Visitor::getUserId();

		$bannedUser = $this->getModelFromCache('XenForo_Model_Banning')->getBannedUserById($userId);
		if (!$bannedUser)
		{
			return $this->responseNoPermission();
		}
		else
		{
			if ($bannedUser['triggered'] && !$bannedUser['end_date'])
			{
				/** @var XenForo_Model_Warning $warningModel */
				$warningModel = $this->getModelFromCache('XenForo_Model_Warning');
				$minUnbanDate = $warningModel->getMinimumWarningUnbanDate($userId);
				if ($minUnbanDate)
				{
					$bannedUser['end_date'] = $minUnbanDate;
				}
			}

			if ($bannedUser['user_reason'])
			{
				$message = new XenForo_Phrase('you_have_been_banned_for_following_reason_x', array('reason' => $bannedUser['user_reason']));
			}
			else
			{
				$message = new XenForo_Phrase('you_have_been_banned');
			}
			if ($bannedUser['end_date'] > XenForo_Application::$time)
			{
				$message.= ' ' . new XenForo_Phrase('your_ban_will_be_lifted_on_x', array('date' => XenForo_Locale::dateTime($bannedUser['end_date'])));
			}

			return $this->responseError($message, 403);
		}
	}

	public function actionBannedIp()
	{
		return $this->responseError(new XenForo_Phrase('your_ip_address_has_been_banned'), 403);
	}

	protected function _assertIpNotBanned() {}
	protected function _assertViewingPermissions($action) {}
	protected function _assertNotBanned() {}
	protected function _assertBoardActive($action) {}
	protected function _assertCorrectVersion($action) {}
	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}
}