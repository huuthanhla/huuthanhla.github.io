<?php

/**
 * Lost password handler.
 *
 * @package XenForo_UserConfirmation
 */
class XenForo_ControllerPublic_LostPassword extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Displays a form to retrieve a lost password.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		if (XenForo_Visitor::getUserId())
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('index')
			);
		}

		$viewParams = array();

		if (XenForo_Application::get('options')->lostPasswordCaptcha)
		{
			$viewParams['captcha'] = XenForo_Captcha_Abstract::createDefault();
		}

		return $this->responseView('XenForo_ViewPublic_LostPassword_Form', 'lost_password', $viewParams);
	}

	/**
	 * Submits a lost password reset request.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionLost()
	{
		if (XenForo_Visitor::getUserId())
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('index')
			);
		}

		$this->_assertPostOnly();

		$options = XenForo_Application::get('options');

		if ($options->lostPasswordCaptcha)
		{
			if (!XenForo_Captcha_Abstract::validateDefault($this->_input))
			{
				return $this->responseError(new XenForo_Phrase('did_not_complete_the_captcha_verification_properly'));
			}
		}

		$usernameOrEmail = $this->_input->filterSingle('username_email', XenForo_Input::STRING);
		$user = $this->_getUserModel()->getUserByNameOrEmail($usernameOrEmail);
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$confirmationModel = $this->_getUserConfirmationModel();

		if ($options->lostPasswordTimeLimit)
		{
			if ($confirmation = $confirmationModel->getUserConfirmationRecord($user['user_id'], 'password'))
			{
				$timeDiff = XenForo_Application::$time - $confirmation['confirmation_date'];

				if ($options->lostPasswordTimeLimit > $timeDiff)
				{
					$wait = $options->lostPasswordTimeLimit - $timeDiff;

					return $this->responseError(new XenForo_Phrase('must_wait_x_seconds_before_performing_this_action', array('count' => $wait)));
				}
			}
		}

		$confirmationModel->sendPasswordResetRequest($user);

		return $this->responseMessage(new XenForo_Phrase('password_reset_request_has_been_emailed_to_you'));
	}

	/**
	 * Confirms a lost password reset request and resets the password.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionConfirm()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		if (!$userId)
		{
			return $this->responseError(new XenForo_Phrase('no_account_specified'));
		}

		$user = $this->_getUserModel()->getUserById($userId);
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('your_password_could_not_be_reset'));
		}

		$confirmationModel = $this->_getUserConfirmationModel();

		$confirmation = $confirmationModel->getUserConfirmationRecord($userId, 'password');
		if (!$confirmation)
		{
			if (XenForo_Visitor::getUserId())
			{
				// probably already been reset
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
					XenForo_Link::buildPublicLink('index')
				);
			}
			else
			{
				return $this->responseError(new XenForo_Phrase('your_password_could_not_be_reset'));
			}
		}

		$confirmationKey = $this->_input->filterSingle('c', XenForo_Input::STRING);
		if ($confirmationKey)
		{
			$accountConfirmed = $confirmationModel->validateUserConfirmationRecord($confirmationKey, $confirmation);
		}
		else
		{
			$accountConfirmed = false;
		}

		if ($accountConfirmed)
		{
			$confirmationModel->resetPassword($userId);
			$confirmationModel->deleteUserConfirmationRecord($userId, 'password');
			XenForo_Visitor::setup(0);

			$this->_getLoginModel()->clearLoginAttempts($user['username']);
			$this->_getLoginModel()->clearLoginAttempts($user['email']);

			return $this->responseMessage(new XenForo_Phrase('your_password_has_been_reset'));
		}
		else
		{
			return $this->responseError(new XenForo_Phrase('your_password_could_not_be_reset'));
		}
	}

	protected function _assertViewingPermissions($action) {}
	protected function _assertBoardActive($action) {}
	protected function _assertCorrectVersion($action) {}
	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}

	/**
	 * @return XenForo_Model_UserConfirmation
	 */
	protected function _getUserConfirmationModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserConfirmation');
	}

	/**
	 * @return XenForo_Model_Login
	 */
	protected function _getLoginModel()
	{
		return $this->getModelFromCache('XenForo_Model_Login');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}