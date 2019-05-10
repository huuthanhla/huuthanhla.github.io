<?php

class XenForo_ControllerAdmin_Error extends XenForo_ControllerAdmin_Abstract
{
	public function actionErrorNotFound()
	{
		return $this->getNotFoundResponse();
	}

	public function actionErrorServer()
	{
		$view = $this->responseView(
			'XenForo_ViewAdmin_Error_ServerError',
			'error_server_error',
			array('exception' => $this->_request->getParam('_exception')),
			array('allowManualDeferredRun' => false)
		);
		$view->responseCode = 500;
		return $view;
	}

	public function actionErrorSuperAdmin()
	{
		return $this->responseError(new XenForo_Phrase('you_must_be_super_admin_to_access_this_page'), 403);
	}

	protected function _assertCorrectVersion($action) {}
	protected function _assertInstallLocked($action) {}
	public function assertAdmin() {}
}