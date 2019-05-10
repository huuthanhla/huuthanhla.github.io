<?php

abstract class XenForo_ControllerAdmin_TemplateModificationAbstract extends XenForo_ControllerAdmin_Abstract
{
	protected $_viewPrefix = '';
	protected $_templatePrefix = '';
	protected $_routePrefix = '';
	protected $_dataWriter = '';

	/**
	 * @return XenForo_Model_TemplateModificationAbstract
	 */
	abstract protected function _getModificationModel();

	abstract protected function _getTemplatesByIds(array $ids);

	abstract protected function _getTestContent(XenForo_DataWriter_TemplateModificationAbstract $dw);

	abstract public function actionAutoComplete();

	abstract public function actionContents();

	public function actionIndex()
	{
		$modificationModel = $this->_getModificationModel();

		$groupedModifications = $modificationModel->groupModificationsByAddon(
			$modificationModel->getAllModifications()
		);

		$modificationCount = 0;
		foreach ($groupedModifications AS $addOn => $modifications)
		{
			$modificationCount += count($modifications);
		}

		$viewParams = array(
			'groupedModifications' => $groupedModifications,
			'modificationCount' => $modificationCount,
			'logSummary' => $modificationModel->getModificationLogSummary(),
			'addOns' => $this->_getAddOnModel()->getAllAddOns(),
			'canCreateModification' => true
		);

		return $this->responseView($this->_viewPrefix . 'List', $this->_templatePrefix . 'list', $viewParams);
	}

	protected function _adjustModificationForEdit(array $modification)
	{
		return $modification;
	}

	protected function _getModificationAddEditResponse(array $modification)
	{
		$addOnModel = $this->_getAddOnModel();

		$modification = $this->_adjustModificationForEdit($modification);

		$viewParams = array(
			'modification' => $modification,
			'addOnOptions' => XenForo_Application::debugMode() ? $addOnModel->getAddOnOptionsListIfAvailable() : array(),
			'addOnSelected' => (isset($modification['addon_id']) ? $modification['addon_id'] : $addOnModel->getDefaultAddOnId()),
			'canEdit' => $this->_getModificationModel()->canEditModification($modification)
		);

		return $this->responseView($this->_viewPrefix . 'Edit', $this->_templatePrefix . 'edit', $viewParams);
	}

	/**
	 * Displays a form to add a new template mod.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getModificationAddEditResponse(array(
			'action' => 'str_replace',
			'execution_order' => 10,
			'enabled' => 1
		));
	}

	/**
	 * Displays a form to edit an existing template mod.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$modificationId = $this->_input->filterSingle('modification_id', XenForo_Input::UINT);
		$modification = $this->_getModificationOrError($modificationId);

		return $this->_getModificationAddEditResponse($modification);
	}

	/**
	 * Inserts a new template mod or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		if ($this->_input->filterSingle('test', XenForo_Input::STRING))
		{
			return $this->responseReroute($this, 'test');
		}

		$this->_assertPostOnly();

		$dw = $this->_getUpdatedModificationDwFromInput();
		$dw->save();

		$modificationId = $dw->get('modification_id');

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink($this->_routePrefix) . $this->getLastHash($modificationId)
		);
	}

	protected function _modifyModificationDwData(array &$dwData, $modificationId)
	{
	}

	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				$this->_dataWriter, 'modification_id',
				XenForo_Link::buildAdminLink($this->_routePrefix)
			);
		}
		else
		{
			$modificationId = $this->_input->filterSingle('modification_id', XenForo_Input::UINT);

			$viewParams = array(
				'modification' => $this->_getModificationOrError($modificationId)
			);

			return $this->responseView($this->_viewPrefix . 'Delete', $this->_templatePrefix . 'delete', $viewParams);
		}
	}

	public function actionLog()
	{
		$modificationId = $this->_input->filterSingle('modification_id', XenForo_Input::UINT);
		$modification = $this->_getModificationOrError($modificationId);

		$logs = $this->_getModificationModel()->getModificationLogsForModification($modificationId);
		$templates = $this->_getTemplatesByIds(array_keys($logs));

		$viewParams = array(
			'modification' => $modification,
			'logs' => $logs,
			'templates' => $templates
		);

		return $this->responseView($this->_viewPrefix . 'Log', $this->_templatePrefix . 'log', $viewParams);
	}


	protected function _adjustTemplateContentForDisplay($content)
	{
		return $content;
	}

	public function actionTest()
	{
		$dw = $this->_getUpdatedModificationDwFromInput();
		$dw->preSave();

		if ($dw->getError('template'))
		{
			return $this->responseError($dw->getError('template'));
		}
		else if ($dw->getError('find'))
		{
			return $this->responseError($dw->getError('find'));
		}

		$content = $this->_getTestContent($dw);
		if (!is_string($content))
		{
			return $this->responseError(new XenForo_Phrase('requested_template_not_found'));
		}

		$modification = $dw->getMergedData();
		if (empty($modification['modification_id']))
		{
			$modification['modification_id'] = 1;
		}

		$contentModified = $this->_getModificationModel()->applyTemplateModifications($content, array($modification));

		$content = $this->_adjustTemplateContentForDisplay($content);
		$contentModified = $this->_adjustTemplateContentForDisplay($contentModified);

		$diff = new XenForo_Diff();
		$diffs = $diff->findDifferences($content, $contentModified);

		$viewParams = array(
			'modification' => $modification,
			'content' => $content,
			'contentModified' => $contentModified,
			'diffs' => $diffs
		);

		return $this->responseView($this->_viewPrefix . 'Test', $this->_templatePrefix . 'test', $viewParams);
	}

	protected function _getUpdatedModificationDwFromInput($modificationId = null)
	{
		if ($modificationId === null)
		{
			$modificationId = $this->_input->filterSingle('modification_id', XenForo_Input::UINT);
		}

		$dwData = $this->_input->filter(array(
			'template' => XenForo_Input::STRING,
			'modification_key' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'action' => XenForo_Input::STRING,
			'find' => XenForo_Input::STRING,
			'replace' => XenForo_Input::STRING,
			'execution_order' => XenForo_Input::UINT,
			'enabled' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));
		$this->_modifyModificationDwData($dwData, $modificationId);

		$dw = XenForo_DataWriter::create($this->_dataWriter);
		if ($modificationId)
		{
			$dw->setExistingData($modificationId);
			if ($this->_getModificationModel()->canEditModification($dw->getMergedData()))
			{
				$dw->bulkSet($dwData);
			}
			else
			{
				$dw->set('enabled', $dwData['enabled']);
			}
		}
		else
		{
			$dw->bulkSet($dwData);
		}

		return $dw;
	}

	/**
	 * Selectively enables or disables specified modifications
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionToggle()
	{
		return $this->_getToggleResponse(
			$this->_getModificationModel()->getAllModifications(),
			$this->_dataWriter,
			$this->_routePrefix,
			'enabled'
		);
	}

	/**
	 * Gets the specified template modification or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getModificationOrError($id)
	{
		$modificationModel = $this->_getModificationModel();

		return $this->getRecordOrError(
			$id, $modificationModel, 'getModificationById',
			'requested_template_modification_not_found'
		);
	}

	/**
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}