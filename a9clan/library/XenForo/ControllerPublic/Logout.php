<?php

class XenForo_ControllerPublic_Logout extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Single-stage logout procedure
	 */
	public function actionIndex()
	{
		$csrfToken = $this->_input->filterSingle('_xfToken', XenForo_Input::STRING);

		$redirectResponse = $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect(false, false)
		);

		$userId = XenForo_Visitor::getUserId();
		if (!$userId)
		{
			return $redirectResponse;
		}

		if ($this->_noRedirect() || !$csrfToken)
		{
			// request is likely from JSON, probably XenForo.OverlayTrigger, so show a confirmation dialog
			return $this->responseView('XenForo_ViewPublic_LogOut', 'log_out');
		}
		else
		{
			$this->_checkCsrfFromToken($csrfToken);

			// remove an admin session if we're logged in as the same person
			if (XenForo_Visitor::getInstance()->get('is_admin'))
			{
				$class = XenForo_Application::resolveDynamicClass('XenForo_Session');
				$adminSession = new $class(array('admin' => true));
				$adminSession->start();
				if ($adminSession->get('user_id') == $userId)
				{
					$adminSession->delete();
				}
			}

			$this->getModelFromCache('XenForo_Model_Session')->processLastActivityUpdateForLogOut(XenForo_Visitor::getUserId());

			XenForo_Application::get('session')->delete();
			XenForo_Helper_Cookie::deleteAllCookies(
				array('session'),
				array('user' => array('httpOnly' => false))
			);

			XenForo_Visitor::setup(0);

			return $redirectResponse;
		}
	}

	protected function _assertViewingPermissions($action) {}
	protected function _assertBoardActive($action) {}
	protected function _assertCorrectVersion($action) {}
	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}

	/**
	 * Gets the user model.
	 *
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}