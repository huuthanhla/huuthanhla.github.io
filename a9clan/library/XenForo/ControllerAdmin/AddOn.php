<?php

/**
 * Add-ons controller.
 *
 * @package XenForo_AddOns
 */
class XenForo_ControllerAdmin_AddOn extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('addOn');
	}

	/**
	 * Lists all installed add-ons.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$addOnModel = $this->_getAddOnModel();

		$viewParams = array(
			'addOns' => $addOnModel->getAllAddOns(),
			'canAccessDevelopment' => $addOnModel->canAccessAddOnDevelopmentAreas()
		);

		return $this->responseView('XenForo_ViewAdmin_AddOn_List', 'addon_list', $viewParams);
	}

	/**
	 * Displays a form to create a new add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$this->assertDebugMode();

		$viewParams = array(
			'addOn' => array('active' => true)
		);

		return $this->responseView('XenForo_ViewAdmin_AddOn_Edit', 'addon_edit', $viewParams);
	}

	/**
	 * Displays a form to edit an existing add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$this->assertDebugMode();

		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		$addOn = $this->_getAddOnOrError($addOnId);

		$viewParams = array(
			'addOn' => $addOn
		);

		return $this->responseView('XenForo_ViewAdmin_AddOn_Edit', 'addon_edit', $viewParams);
	}

	/**
	 * Inserts a new add-on or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->assertDebugMode();

		$this->_assertPostOnly();

		$originalAddOnId = $this->_input->filterSingle('original_addon_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'addon_id' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'version_string' => XenForo_Input::STRING,
			'version_id' => XenForo_Input::UINT,
			'install_callback_class'    => XenForo_Input::STRING,
			'install_callback_method'   => XenForo_Input::STRING,
			'uninstall_callback_class'  => XenForo_Input::STRING,
			'uninstall_callback_method' => XenForo_Input::STRING,
			'url' => XenForo_Input::STRING,
			'active' => XenForo_Input::UINT,
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
		if ($originalAddOnId)
		{
			$dw->setExistingData($originalAddOnId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('add-ons') . $this->getLastHash($dwInput['addon_id'])
		);
	}

	/**
	 * Deletes (uninstalls) an add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		$addOn = $this->_getAddOnOrError($addOnId);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
		$dw->setExistingData($addOnId);

		if ($this->isConfirmedPost()) // delete add-on
		{
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('add-ons')
			);
		}
		else // show delete confirmation prompt
		{
			$dw->preDelete();
			if ($errors = $dw->getErrors())
			{
				return $this->responseError($errors);
			}

			$viewParams = array(
				'addOn' => $addOn
			);

			return $this->responseView('XenForo_ViewAdmin_AddOn_Delete', 'addon_delete', $viewParams);
		}
	}

	/**
	 * Exports an add-on's XML data.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionExport()
	{
		$this->assertDebugMode();

		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		$addOn = $this->_getAddOnOrError($addOnId);

		$this->_routeMatch->setResponseType('xml');

		$viewParams = array(
			'addOn' => $addOn,
			'xml' => $this->_getAddOnModel()->getAddOnXml($addOn)
		);

		return $this->responseView('XenForo_ViewAdmin_AddOn_Export', '', $viewParams);
	}

	/**
	 * Installs a new add-on. This cannot be used for upgrading.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInstall()
	{
		if ($this->isConfirmedPost())
		{
			$fileTransfer = new Zend_File_Transfer_Adapter_Http();
			if ($fileTransfer->isUploaded('upload_file'))
			{
				$fileInfo = $fileTransfer->getFileInfo('upload_file');
				$fileName = $fileInfo['upload_file']['tmp_name'];
			}
			else
			{
				$fileName = $this->_input->filterSingle('server_file', XenForo_Input::STRING);
			}

			$this->_getAddOnModel()->installAddOnXmlFromFile($fileName);

			// ugly hack...
			$redirect = XenForo_Link::buildAdminLink('add-ons');
			if (XenForo_Application::isRegistered('addOnRedirect'))
			{
				$redirect = XenForo_Application::get('addOnRedirect');
			}

			if ($redirect instanceof XenForo_ControllerResponse_Abstract)
			{
				return $redirect;
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirect
			);
		}
		else
		{
			return $this->responseView('XenForo_ViewAdmin_AddOn_Install', 'addon_install');
		}
	}

	/**
	 * Upgrades the specified add-on. The given file must match the specified
	 * add-on, or an error will occur.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpgrade()
	{
		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		$addOn = $this->_getAddOnOrError($addOnId);

		if ($this->isConfirmedPost())
		{
			$fileTransfer = new Zend_File_Transfer_Adapter_Http();
			if ($fileTransfer->isUploaded('upload_file'))
			{
				$fileInfo = $fileTransfer->getFileInfo('upload_file');
				$fileName = $fileInfo['upload_file']['tmp_name'];
			}
			else
			{
				$fileName = $this->_input->filterSingle('server_file', XenForo_Input::STRING);
			}

			$this->_getAddOnModel()->installAddOnXmlFromFile($fileName, $addOn['addon_id']);

			// ugly hack...
			$redirect = XenForo_Link::buildAdminLink('add-ons') . $this->getLastHash($addOnId);
			if (XenForo_Application::isRegistered('addOnRedirect'))
			{
				$redirect = XenForo_Application::get('addOnRedirect');
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$redirect
			);
		}
		else
		{
			$viewParams = array(
				'addOn' => $addOn
			);

			return $this->responseView('XenForo_ViewAdmin_AddOn_Upgrade', 'addon_upgrade', $viewParams);
		}
	}

	/**
	 * Helper to switch the active state for an add-on and get the controller response.
	 *
	 * @param string $addOnId Add-on ID
	 * @param integer $activeState O or 1
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _switchAddOnActiveStateAndGetResponse($addOnId, $activeState)
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
		$dw->setExistingData($addOnId);
		$dw->set('active', $activeState);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('add-ons') . $this->getLastHash($addOnId)
		);
	}

	/**
	 * Enables the specified add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEnable()
	{
		// can be requested over GET, so check for the token manually
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		return $this->_switchAddOnActiveStateAndGetResponse($addOnId, 1);
	}

	/**
	 * Disables the specified add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDisable()
	{
		// can be requested over GET, so check for the token manually
		$this->_checkCsrfFromToken($this->_input->filterSingle('_xfToken', XenForo_Input::STRING));

		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		return $this->_switchAddOnActiveStateAndGetResponse($addOnId, 0);
	}

	/**
	 * Selectively enables or disables specified add-ons
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionToggle()
	{
		return $this->_getToggleResponse(
			$this->_getAddOnModel()->getAllAddOns(),
			'XenForo_DataWriter_AddOn',
			'add-ons');
	}

	/**
	 * Gets a valid add-on or throws an exception.
	 *
	 * @param string $addOnId
	 *
	 * @return array
	 */
	protected function _getAddOnOrError($addOnId)
	{
		$info = $this->_getAddOnModel()->getAddOnById($addOnId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_addon_not_found'), 404));
		}

		return $info;
	}

	/**
	 * Gets the add-on model object.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}

	// backwards-compatibility only: -----------------------------------

	/**
	 * Displays a form to let a user choose what add-on to install.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInstallConfirm()
	{
		return $this->responseReroute(__CLASS__, 'install');
	}

	/**
	 * Displays a form to let the user upgrade an existing add-on.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpgradeConfirm()
	{
		return $this->responseReroute(__CLASS__, 'upgrade');
	}
}