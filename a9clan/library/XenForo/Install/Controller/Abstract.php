<?php

abstract class XenForo_Install_Controller_Abstract extends XenForo_Controller
{
	protected function _preDispatchFirst($action)
	{
		$configFile = XenForo_Application::getInstance()->getConfigDir() . '/config.php';
		if (file_exists($configFile))
		{
			XenForo_Application::getInstance()->loadDefaultData();
		}
		else
		{
			XenForo_Application::set('config', XenForo_Application::getInstance()->loadDefaultConfig());
		}

		XenForo_Application::setDebugMode(true);

		@set_time_limit(120);
	}

	protected function _manualDeferredRunner($submitUrl, $redirect)
	{
		$input = $this->_input->filter(array(
			'execute' => XenForo_Input::UINT,
		));

		/* @var $deferModel XenForo_Model_Deferred */
		$deferModel = $this->getModelFromCache('XenForo_Model_Deferred');
		$status = '';

		if ($input['execute'] && $this->_request->isPost())
		{
			$continued = $deferModel->runByUniqueKey('installUpgradeRebuild', null, $status);
			if (!$continued)
			{
				return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $redirect);
			}
		}

		$viewParams = array(
			'status' => $status,
			'redirect' => $redirect,
			'submitUrl' => $submitUrl
		);

		return $this->responseView('XenForo_Install_View_RunDeferred', 'run_deferred', $viewParams);
	}

	protected function _assertCorrectVersion($action) {}
	public function updateSession($controllerResponse, $controllerName, $action) {}
	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}

	/**
	 * Gets the response for a generic no permission page.
	 *
	 * @return XenForo_ControllerResponse_Error
	 */
	public function responseNoPermission()
	{
		return $this->responseError(new XenForo_Phrase('you_do_not_have_permission_view_page'), 403);
	}
}