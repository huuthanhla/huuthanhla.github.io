<?php

/**
 * Abstract controller for admin actions.
 *
 * @package XenForo_Mvc
 */
abstract class XenForo_ControllerAdmin_Abstract extends XenForo_Controller
{
	/**
	 * Pre-dispatch behaviors for the whole set of admin controllers.
	 */
	final protected function _preDispatchType($action)
	{
		$this->_assertCorrectVersion($action);
		$this->_assertInstallLocked($action);
		$this->assertAdmin();
	}

	/**
	 * Post-dispatch behaviors for the whole set of admin controllers.
	 */
	final protected function _postDispatchType($controllerResponse, $controllerName, $action)
	{
		$this->_logAdminRequest($controllerResponse, $controllerName, $action);
	}

	protected function _logAdminRequest($controllerResponse, $controllerName, $action)
	{
		if ($this->_request->isPost())
		{
			$this->getModelFromCache('XenForo_Model_Log')->logAdminRequest($this->_request);
		}
	}

	/**
	* Setup the session.
	*
	* @param string $action
	*/
	protected function _setupSession($action)
	{
		if (XenForo_Application::isRegistered('session'))
		{
			return;
		}

		XenForo_Session::startAdminSession($this->_request);
	}

	protected function _buildLink($type, $data = null, array $params = array())
	{
		return XenForo_Link::buildAdminLink($type, $data, $params);
	}

	/**
	 * Gets the response for a generic no permission page.
	 *
	 * @return XenForo_ControllerResponse_Error
	 */
	public function responseNoPermission()
	{
		return $this->responseError(new XenForo_Phrase('do_not_have_permission'), 403);
	}

	/**
	 * Asserts that the installed version of the board matches the files.
	 *
	 * @param string $action
	 */
	protected function _assertCorrectVersion($action)
	{
		if (XenForo_Application::debugMode())
		{
			return;
		}

		if (!XenForo_Application::get('config')->checkVersion)
		{
			return;
		}

		if (XenForo_Application::$versionId != XenForo_Application::get('options')->currentVersionId)
		{
			$response = $this->responseMessage(new XenForo_Phrase('board_waiting_to_be_upgraded_admin'));
			$response->containerParams = array(
				'containerTemplate' => 'PAGE_CONTAINER_SIMPLE'
			);

			throw $this->responseException($response);
		}
	}

	protected function _assertInstallLocked($action)
	{
		$installModel = XenForo_Model::create('XenForo_Install_Model_Install');
		if (!$installModel->isInstalled())
		{
			$installModel->writeInstallLock();
		}
	}

	/**
	 * Generic action for toggling the active state of an item or items
	 *
	 * @param array All items of this type
	 * @param string Name of data writer for this item type
	 * @param string Target for the redirection
	 * @param string Name of the DB field that determines active state
	 * @param string If the IDs from the template are prefixed to be unique, pass that here
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getToggleResponse(array $items, $dwName, $redirectTarget, $activeFieldName = 'active', $idPrefix = '')
	{
		$this->_assertPostOnly();

		$idExists = $this->_input->filterSingle('exists', array(XenForo_Input::UINT, 'array' => true));
		$ids = $this->_input->filterSingle('id', array(XenForo_Input::UINT, 'array' => true));

		foreach ($items AS $id => $item)
		{
			$inputId = $idPrefix . $id;

			if (isset($idExists[$inputId]))
			{
				$itemActive = (!empty($ids[$inputId]) ? 1 : 0);

				if ($item[$activeFieldName] != $itemActive)
				{
					$dw = XenForo_DataWriter::create($dwName);
					$dw->setExistingData($item, true);
					$dw->set($activeFieldName, $itemActive);
					$dw->save();
				}
			}
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink($redirectTarget)
		);
	}

	/**
	 * Returns the hash necessary to find an item in a filter list.
	 *
	 * @param mixed $id
	 *
	 * @return string
	 */
	public function getLastHash($id)
	{
		return '#' . XenForo_Template_Helper_Admin::getListItemId($id);
	}

	/**
	 * Ensures that the user trying to access the admin control panel is actually
	 * an admin.
	 */
	public function assertAdmin()
	{
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor['is_admin'])
		{
			throw $this->responseException(
				$this->responseReroute('XenForo_ControllerAdmin_Login', 'form')
			);
		}
	}

	/**
	 * Ensures that the user trying to access the page is a super admin.
	 */
	public function assertSuperAdmin()
	{
		if (!XenForo_Visitor::getInstance()->isSuperAdmin())
		{
			throw $this->responseException(
				$this->responseReroute('XenForo_ControllerAdmin_Error', 'errorSuperAdmin')
			);
		}
	}

	/**
	 * Asserts that debug mode is enabled.
	 */
	public function assertDebugMode()
	{
		if (!XenForo_Application::debugMode())
		{
			throw new XenForo_Exception(new XenForo_Phrase('page_only_available_debug_mode'), true);
		}
	}

	/**
	 * Asserts that the visiting user has the specified admin permission.
	 *
	 * @param string $permissionId
	 */
	public function assertAdminPermission($permissionId)
	{
		if (!XenForo_Visitor::getInstance()->hasAdminPermission($permissionId))
		{
			throw $this->responseException($this->responseNoPermission());
		}
	}

	/**
	 * Disable updating a user's session activity in the ACP
	 */
	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}
}