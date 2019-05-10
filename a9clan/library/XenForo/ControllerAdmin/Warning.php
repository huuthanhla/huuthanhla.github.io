<?php

class XenForo_ControllerAdmin_Warning extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('warning');
	}

	public function actionIndex()
	{
		$warningModel = $this->_getWarningModel();

		$viewParams = array(
			'warnings' => $warningModel->prepareWarningDefinitions($warningModel->getWarningDefinitions()),
			'warningActions' => $warningModel->getWarningActions()
		);
		return $this->responseView('XenForo_ViewAdmin_Warning_List', 'warning_list', $viewParams);
	}

	protected function _getWarningAddEditResponse(array $warning)
	{
		$userGroupOptions = $this->getModelFromCache('XenForo_Model_UserGroup')->getUserGroupOptions(
			$warning['extra_user_group_ids']
		);

		if (!empty($warning['warning_definition_id']))
		{
			$masterValues = $this->_getWarningModel()->getWarningDefinitionMasterPhraseValues($warning['warning_definition_id']);
		}
		else
		{
			$masterValues = array(
				'title' => '',
				'conversationTitle' => '',
				'conversationText' => ''
			);
		}

		$viewParams = array(
			'warning' => $warning,
			'userGroupOptions' => $userGroupOptions,

			'masterTitle' => $masterValues['title'],
			'masterConversationTitle' => $masterValues['conversationTitle'],
			'masterConversationText' => $masterValues['conversationText'],
		);
		return $this->responseView('XenForo_ViewAdmin_Warning_Edit', 'warning_edit', $viewParams);
	}

	public function actionAdd()
	{
		return $this->_getWarningAddEditResponse(array(
			'points_default' => 1,
			'expiry_type' => 'never',
			'expiry_default' => 0,
			'extra_user_group_ids' => '',
			'is_editable' => 1
		));
	}

	public function actionEdit()
	{
		$warningDefinitionId = $this->_input->filterSingle('warning_definition_id', XenForo_Input::UINT);
		$warning = $this->_getWarningDefinitionOrError($warningDefinitionId);

		return $this->_getWarningAddEditResponse($warning);
	}

	public function actionSave()
	{
		$warningDefinitionId = $this->_input->filterSingle('warning_definition_id', XenForo_Input::UINT);
		$dwInput = $this->_input->filter(array(
			'points_default' => XenForo_Input::UINT,
			'expiry_type' => XenForo_Input::STRING,
			'expiry_default' => XenForo_Input::UINT,
			'extra_user_group_ids' => array(XenForo_Input::UINT, 'array' => true),
			'is_editable' => XenForo_Input::UINT
		));
		$phrases = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'conversationTitle' => XenForo_Input::STRING,
			'conversationText' => XenForo_Input::STRING
		));

		if ($this->_input->filterSingle('expiry_type_base', XenForo_Input::STRING) == 'never')
		{
			$dwInput['expiry_type'] = 'never';
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_WarningDefinition');
		if ($warningDefinitionId)
		{
			$dw->setExistingData($warningDefinitionId);
		}
		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_WarningDefinition::DATA_TITLE, $phrases['title']);
		$dw->setExtraData(XenForo_DataWriter_WarningDefinition::DATA_CONVERSATION_TITLE, $phrases['conversationTitle']);
		$dw->setExtraData(XenForo_DataWriter_WarningDefinition::DATA_CONVERSATION_TEXT, $phrases['conversationText']);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('warnings') . '#_warning-' . $dw->get('warning_definition_id')
		);
	}

	/**
	 * Deletes a warning.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_WarningDefinition', 'warning_definition_id',
				XenForo_Link::buildAdminLink('warnings')
			);
		}
		else
		{
			$warningDefinitionId = $this->_input->filterSingle('warning_definition_id', XenForo_Input::UINT);
			$warning = $this->_getWarningDefinitionOrError($warningDefinitionId);

			$viewParams = array(
				'warning' => $warning
			);

			return $this->responseView('XenForo_ViewAdmin_Warning_Delete', 'warning_delete', $viewParams);
		}
	}

	protected function _getActionAddEditResponse(array $action)
	{
		$userGroupOptions = $this->getModelFromCache('XenForo_Model_UserGroup')->getUserGroupOptions(
			$action['extra_user_group_ids']
		);

		$viewParams = array(
			'action' => $action,
			'userGroupOptions' => $userGroupOptions
		);
		return $this->responseView('XenForo_ViewAdmin_Warning_ActionEdit', 'warning_action_edit', $viewParams);
	}

	public function actionActionAdd()
	{
		return $this->_getActionAddEditResponse(array(
			'points' => 1,
			'action' => 'groups',
			'action_length_type' => 'permanent',
			'action_length' => 0,
			'extra_user_group_ids' => ''
		));
	}

	public function actionActionEdit()
	{
		$warningActionId = $this->_input->filterSingle('warning_action_id', XenForo_Input::UINT);
		$action = $this->_getWarningActionOrError($warningActionId);

		return $this->_getActionAddEditResponse($action);
	}

	public function actionActionSave()
	{
		$warningActionId = $this->_input->filterSingle('warning_action_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array(
			'points' => XenForo_Input::UINT,
			'action' => XenForo_Input::STRING,
			'extra_user_group_ids' => array(XenForo_Input::UINT, 'array' => true)
		));

		$actionTypeBase = $this->_input->filterSingle('action_length_type_base', XenForo_Input::STRING);
		if ($actionTypeBase == 'temporary')
		{
			$dwInput['action_length_type'] = $this->_input->filterSingle('action_length_type', XenForo_Input::STRING);
			$dwInput['action_length'] = $this->_input->filterSingle('action_length', XenForo_Input::UINT);
		}
		else
		{
			$dwInput['action_length_type'] = $actionTypeBase;
			$dwInput['action_length'] = 0;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_WarningAction');
		if ($warningActionId)
		{
			$dw->setExistingData($warningActionId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('warnings') . '#_action-' . $dw->get('warning_action_id')
		);
	}

	/**
	 * Deletes a warning action.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionActionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_WarningAction', 'warning_action_id',
				XenForo_Link::buildAdminLink('warnings')
			);
		}
		else
		{
			$warningActionId = $this->_input->filterSingle('warning_action_id', XenForo_Input::UINT);
			$action = $this->_getWarningActionOrError($warningActionId);

			$viewParams = array(
				'action' => $action
			);

			return $this->responseView('XenForo_ViewAdmin_Warning_ActionDelete', 'warning_action_delete', $viewParams);
		}
	}

	/**
	 * Gets the specified warning def or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getWarningDefinitionOrError($id)
	{
		$result = $this->getRecordOrError(
			$id, $this->_getWarningModel(), 'getWarningDefinitionById',
			'requested_warning_not_found'
		);

		return $this->_getWarningModel()->prepareWarningDefinition($result);
	}

	/**
	 * Gets the specified warning action or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getWarningActionOrError($id)
	{
		$result = $this->getRecordOrError(
			$id, $this->_getWarningModel(), 'getWarningActionById',
			'requested_warning_action_not_found'
		);

		return $result;
	}

	/**
	 * @return XenForo_Model_Warning
	 */
	protected function _getWarningModel()
	{
		return $this->getModelFromCache('XenForo_Model_Warning');
	}
}