<?php

/**
 * Controller to manage smilies in the admin control panel.
 *
 * @package XenForo_Smilie
 */
class XenForo_ControllerAdmin_Smilie extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('bbCodeSmilie');
	}

	/**
	 * Displays a list of smilies.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionIndex()
	{
		$smilieModel = $this->_getSmilieModel();

		$smilieCategories = $smilieModel->getAllSmilieCategoriesWithSmilies();

		$smilieCategories = $smilieModel->prepareCategorizedSmiliesForList($smilieCategories, $totalSmilies);

		$viewParams = array(
			'smilieCategories' => $smilieCategories,
			'totalSmilies' => $totalSmilies,
		);

		return $this->responseView('XenForo_ViewAdmin_Smilie_List', 'smilie_list', $viewParams);
	}

	/**
	 * Displays a form to add a smilie.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionAdd()
	{
		$smilieCategoryId = $this->_input->filterSingle('smilie_category_id', XenForo_Input::UINT);

		$smilieModel = $this->_getSmilieModel();

		$viewParams = array(
			'smilie' => array(
				'sprite_params' => $smilieModel->getDefaultSmilieSpriteParams(),
				'smilie_category_id' => $smilieCategoryId,
				'display_order' => 10,
				'display_in_editor' => 1,
			),
			'smilieCategories' => $smilieModel->getSmilieCategoryOptions(),
		);
		return $this->responseView('XenForo_ViewAdmin_Smilie_Edit', 'smilie_edit', $viewParams);
	}

	/**
	 * Displays a form to edit an existing smilie.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionEdit()
	{
		$smilieId = $this->_input->filterSingle('smilie_id', XenForo_Input::UINT);
		$smilie = $this->_getSmilieOrError($smilieId);

		$viewParams = array(
			'smilie' => $smilie,
			'smilieCategories' => $this->_getSmilieModel()->getSmilieCategoryOptions(),
		);
		return $this->responseView('XenForo_ViewAdmin_Smilie_Edit', 'smilie_edit', $viewParams);
	}

	/**
	 * Adds a new smilie or updates an existing one.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$smilieId = $this->_input->filterSingle('smilie_id', XenForo_Input::UINT);
		$dwInput = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'smilie_text' => XenForo_Input::STRING,
			'image_url' => XenForo_Input::STRING,
			'sprite_mode' => XenForo_Input::UINT,
			'sprite_params' => array(XenForo_Input::INT, array('array' => true)),
			'smilie_category_id' => XenForo_Input::UINT,
			'display_order' => XenForo_Input::UINT,
			'display_in_editor' => XenForo_Input::UINT,
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Smilie');
		if ($smilieId)
		{
			$dw->setExistingData($smilieId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('smilies') . $this->getLastHash($smilieId)
		);
	}

	public function actionToggle()
	{
		return $this->_getToggleResponse(
			$this->_getSmilieModel()->getAllSmilies(),
			'XenForo_DataWriter_Smilie',
			'smilies',
			'display_in_editor'
		);
	}

	/**
	 * Validates the specified smilie field.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionValidateField()
	{
		$this->_assertPostOnly();

		return $this->_validateField('XenForo_DataWriter_Smilie', array(
			'existingDataKey' => $this->_input->filterSingle('smilie_id', XenForo_Input::INT)
		));
	}

	/**
	 * Deletes the specified smilie.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_Smilie', 'smilie_id',
				XenForo_Link::buildAdminLink('smilies')
			);
		}
		else
		{
			$smilieId = $this->_input->filterSingle('smilie_id', XenForo_Input::UINT);
			$smilie = $this->_getSmilieOrError($smilieId);

			$viewParams = array(
				'smilie' => $smilie
			);
			return $this->responseView('XenForo_ViewAdmin_Smilie_Delete', 'smilie_delete', $viewParams);
		}
	}

	public function actionDisplayOrder()
	{
		$order = $this->_input->filterSingle('order', XenForo_Input::ARRAY_SIMPLE);

		$this->_assertPostOnly();
		$this->_getSmilieModel()->massUpdateDisplayOrder($order);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('smilies')
		);
	}

	public function actionExport()
	{
		$this->_assertPostOnly();

		$smilieIds = $this->_input->filterSingle('smilieId', XenForo_Input::ARRAY_SIMPLE);
		if (!$smilieIds)
		{
			return $this->responseError(new XenForo_Phrase('please_select_at_least_one_item'));
		}

		$this->_routeMatch->setResponseType('xml');

		$viewParams = array(
			'xml' => $this->_getSmilieModel()->getSmiliesXml($smilieIds)
		);

		return $this->responseView('XenForo_ViewAdmin_Smilie_Export', '', $viewParams);
	}

	public function actionImport()
	{
		if ($this->isConfirmedPost())
		{
			if ($_input = $this->_getInputFromSerialized('_xfSmilieImportData', true))
			{
				$this->_input = $_input;
			}

			$input = $this->_input->filter(array(
				'smilieCategories' => XenForo_Input::ARRAY_SIMPLE,
				'import' => XenForo_Input::ARRAY_SIMPLE,
				'smilies' => XenForo_Input::ARRAY_SIMPLE,
			));

			$smilies = array();

			foreach ($input['import'] AS $smilieId)
			{
				if (empty($input['smilies'][$smilieId]))
				{
					continue;
				}

				$smilieInput = new XenForo_Input($input['smilies'][$smilieId]);

				$smilies[$smilieId] = $smilieInput->filter(array(
					'title' => XenForo_Input::STRING,
					'smilie_text' => XenForo_Input::STRING,
					'image_url' => XenForo_Input::STRING,
					'sprite_mode' => XenForo_Input::UINT,
					'sprite_params' => array(XenForo_Input::INT, array('array' => true)),
					'smilie_category_id' => XenForo_Input::STRING,
					'display_order' => XenForo_Input::UINT,
					'display_in_editor' => XenForo_Input::UINT,
				));
			}

			$this->_getSmilieModel()->massImportSmilies($smilies, $input['smilieCategories'], $errors);

			if (empty($errors))
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('smilies')
				);
			}
			else
			{
				return $this->responseError($errors);
			}
		}
		else
		{
			return $this->responseView('XenForo_ViewAdmin_Smilie_Import', 'smilie_import', array());
		}

	}

	public function actionValidateImportField()
	{
		$field = $this->_getFieldValidationInputParams();

		$fieldName = preg_replace('/^(.+)__\d+$/si', '\1', $field['name']);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Smilie');
		$dw->set($fieldName, $field['value']);

		if ($errors = $dw->getErrors())
		{
			$errors = array($field['name'] => $errors[$fieldName]);

			return $this->responseError($errors);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			'',
			new XenForo_Phrase('redirect_field_validated', array('name' => $field['name'], 'value' => $field['value']))
		);
	}

	public function actionImportForm()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'mode' => XenForo_Input::STRING,
			'directory' => XenForo_Input::STRING,
		));

		$smilieModel = $this->_getSmilieModel();

		$smilieCategoryOptions = $smilieModel->getSmilieCategoryOptions();

		if ($input['mode'] == 'upload')
		{
			$upload = XenForo_Upload::getUploadedFile('upload');
			if (!$upload)
			{
				return $this->responseError(new XenForo_Phrase('please_upload_valid_smilies_xml_file'));
			}

			$document = $this->getHelper('Xml')->getXmlFromFile($upload);
			$smilieData = $smilieModel->getSmilieDataFromXml($document, $smilieCategoryOptions);
		}
		else
		{
			$smilieData = $smilieModel->getSmilieDataFromDirectory($input['directory']);
		}

		$viewParams = array(
			'uploadMode' => ($input['mode'] == 'upload'),
			'smilies' => $smilieData['smilies'],
			'newSmilieCategories' => $smilieData['newSmilieCategories'],
			'newSmilieCategoryOptions' => $smilieData['newSmilieCategoryOptions'],
			'smilieCategoryOptions' => $smilieCategoryOptions,
		);

		return $this->responseView('XenForo_ViewAdmin_Smilie_ImportForm', 'smilie_import_form', $viewParams);
	}

	/**
	 * Gets a valid smilie or throws an exception.
	 *
	 * @param string $smilieId
	 *
	 * @return array
	 */
	protected function _getSmilieOrError($smilieId)
	{
		$smilieModel = $this->_getSmilieModel();

		$info = $smilieModel->getSmilieById($smilieId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_smilie_not_found'), 404));
		}

		$smilie = $smilieModel->prepareSmilie($info);

		if (empty($smilie['sprite_params']))
		{
			$smilie['sprite_params'] = $this->_getSmilieModel()->getDefaultSmilieSpriteParams();
		}

		return $smilie;
	}

	/**
	 * @return XenForo_Model_Smilie
	 */
	protected function _getSmilieModel()
	{
		return $this->getModelFromCache('XenForo_Model_Smilie');
	}
}