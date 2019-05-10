<?php

/**
 * Controller for managing custom user fields.
 */
class XenForo_ControllerAdmin_UserField extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('userField');
	}

	/**
	 * Displays a list of custom user fields.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$fieldModel = $this->_getFieldModel();

		$fields = $fieldModel->prepareUserFields($fieldModel->getUserFields());

		$viewParams = array(
			'fieldsGrouped' => $fieldModel->groupUserFields($fields),
			'fieldCount' => count($fields),
			'fieldGroups' => $fieldModel->getUserFieldGroups(),
			'fieldTypes' => $fieldModel->getUserFieldTypes()
		);

		return $this->responseView('XenForo_ViewAdmin_UserField_List', 'user_field_list', $viewParams);
	}

	/**
	 * Gets the add/edit form response for a field.
	 *
	 * @param array $field
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getFieldAddEditResponse(array $field)
	{
		$fieldModel = $this->_getFieldModel();

		$typeMap = $fieldModel->getUserFieldTypeMap();
		$validFieldTypes = $fieldModel->getUserFieldTypes();

		if (!empty($field['field_id']))
		{
			$masterTitle = $fieldModel->getUserFieldMasterTitlePhraseValue($field['field_id']);
			$masterDescription = $fieldModel->getUserFieldMasterDescriptionPhraseValue($field['field_id']);

			$existingType = $typeMap[$field['field_type']];
			foreach ($validFieldTypes AS $typeId => $type)
			{
				if ($typeMap[$typeId] != $existingType)
				{
					unset($validFieldTypes[$typeId]);
				}
			}
		}
		else
		{
			$masterTitle = '';
			$masterDescription = '';
			$existingType = false;
		}

		$viewParams = array(
			'field' => $field,
			'masterTitle' => $masterTitle,
			'masterDescription' => $masterDescription,
			'masterFieldChoices' => $fieldModel->getUserFieldChoices($field['field_id'], $field['field_choices'], true),

			'fieldGroups' => $fieldModel->getUserFieldGroups(),
			'validFieldTypes' => $validFieldTypes,
			'fieldTypeMap' => $typeMap,
			'existingType' => $existingType
		);

		return $this->responseView('XenForo_ViewAdmin_UserField_Edit', 'user_field_edit', $viewParams);
	}

	/**
	 * Displays form to add a custom user field.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getFieldAddEditResponse(array(
			'field_id' => null,
			'display_group' => 'personal',
			'display_order' => 1,
			'field_type' => 'textbox',
			'field_choices' => '',
			'match_type' => 'none',
			'match_regex' => '',
			'match_callback_class' => '',
			'match_callback_method' => '',
			'max_length' => 0,
			'required' => 0,
			'show_registration' => 0,
			'user_editable' => 'yes',
			'viewable_profile' => 1,
			'display_template' => '',
			'moderator_editable' => 0
		));
	}

	/**
	 * Displays form to edit a custom user field.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$field = $this->_getFieldOrError($this->_input->filterSingle('field_id', XenForo_Input::STRING));
		return $this->_getFieldAddEditResponse($field);
	}

	/**
	 * Saves a custom user field.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$fieldId = $this->_input->filterSingle('field_id', XenForo_Input::STRING);

		$newFieldId = $this->_input->filterSingle('new_field_id', XenForo_Input::STRING);
		$dwInput = $this->_input->filter(array(
			'display_group' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'field_type' => XenForo_Input::STRING,
			'match_type' => XenForo_Input::STRING,
			'match_regex' => XenForo_Input::STRING,
			'match_callback_class' => XenForo_Input::STRING,
			'match_callback_method' => XenForo_Input::STRING,
			'max_length' => XenForo_Input::UINT,
			'required' => XenForo_Input::UINT,
			'show_registration' => XenForo_Input::UINT,
			'viewable_profile' => XenForo_Input::UINT,
			'viewable_message' => XenForo_Input::UINT,
			'display_template' => XenForo_Input::STRING,
			'moderator_editable' => XenForo_Input::UINT,
		));
		$ueInput = $this->_input->filter(array(
			'user_editable_base' => XenForo_Input::UINT,
			'user_editable_once' => XenForo_Input::UINT
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserField');
		if ($fieldId)
		{
			$dw->setExistingData($fieldId);
		}
		else
		{
			$dw->set('field_id', $newFieldId);
		}

		$dw->bulkSet($dwInput);

		if ($ueInput['user_editable_once'])
		{
			$dw->set('user_editable', 'once');
		}
		else if ($ueInput['user_editable_base'])
		{
			$dw->set('user_editable', 'yes');
		}
		else
		{
			$dw->set('user_editable', 'never');
		}

		$dw->setExtraData(
			XenForo_DataWriter_UserField::DATA_TITLE,
			$this->_input->filterSingle('title', XenForo_Input::STRING)
		);
		$dw->setExtraData(
			XenForo_DataWriter_UserField::DATA_DESCRIPTION,
			$this->_input->filterSingle('description', XenForo_Input::STRING)
		);

		$fieldChoices = $this->_input->filterSingle('field_choice', XenForo_Input::STRING, array('array' => true));
		$fieldChoicesText = $this->_input->filterSingle('field_choice_text', XenForo_Input::STRING, array('array' => true));
		$fieldChoicesCombined = array();
		foreach ($fieldChoices AS $key => $choice)
		{
			if (isset($fieldChoicesText[$key]))
			{
				$fieldChoicesCombined[$choice] = $fieldChoicesText[$key];
			}
		}

		$dw->setFieldChoices($fieldChoicesCombined);

		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('user-fields') . $this->getLastHash($dw->get('field_id'))
		);
	}

	/**
	 * Deletes a custom user field.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_UserField', 'field_id',
				XenForo_Link::buildAdminLink('user-fields')
			);
		}
		else
		{
			$field = $this->_getFieldOrError($this->_input->filterSingle('field_id', XenForo_Input::STRING));

			$viewParams = array(
				'field' => $field
			);

			return $this->responseView('XenForo_ViewAdmin_UserField_Delete', 'user_field_delete', $viewParams);
		}
	}

	/**
	 * Gets the specified field or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getFieldOrError($id)
	{
		$field = $this->getRecordOrError(
			$id, $this->_getFieldModel(), 'getUserFieldById',
			'requested_field_not_found'
		);

		return $this->_getFieldModel()->prepareUserField($field);
	}

	/**
	 * @return XenForo_Model_UserField
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserField');
	}
}