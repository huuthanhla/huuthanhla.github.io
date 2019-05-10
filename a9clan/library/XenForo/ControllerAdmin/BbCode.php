<?php

class XenForo_ControllerAdmin_BbCode extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('bbCodeSmilie');
	}

	/**
	 * Lists all BB codes.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$bbCodeModel = $this->_getBbCodeModel();
		$bbCodes = $bbCodeModel->getAllBbCodes();

		$viewParams = array(
			'bbCodes' => $bbCodeModel->prepareBbCodes($bbCodes),
			'exportView' => $this->_input->filterSingle('export', XenForo_Input::UINT)
		);
		return $this->responseView('XenForo_ViewAdmin_BbCode_List', 'bb_code_list', $viewParams);
	}

	/**
	 * Gets the BB code add/edit form response.
	 *
	 * @param array $bbCode
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getBbCodeAddEditResponse(array $bbCode)
	{
		$addOnModel = $this->getModelFromCache('XenForo_Model_AddOn');

		if (!empty($bbCode['bb_code_id']))
		{
			$title = $this->_getBbCodeModel()->getBbCodeMasterTitlePhraseValue($bbCode['bb_code_id']);
			$description = $this->_getBbCodeModel()->getBbCodeMasterDescriptionPhraseValue($bbCode['bb_code_id']);
		}
		else
		{
			$title = '';
			$description = '';
		}

		$viewParams = array(
			'bbCode' => $bbCode,
			'title' => $title,
			'description' => $description,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($bbCode['addon_id']) ? $bbCode['addon_id'] : $addOnModel->getDefaultAddOnId())
		);
		return $this->responseView('XenForo_ViewAdmin_BbCode_Edit', 'bb_code_edit', $viewParams);
	}

	/**
	 * Displays a form to create a new BB code.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getBbCodeAddEditResponse(array(
			'bb_code_mode' => 'replace',
			'has_option' => 'no',
			'allow_signature' => 1,
			'active' => 1
		));
	}

	/**
	 * Displays a form to edit an existing BB code.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$bbCode = $this->_getBbCodeOrError();

		return $this->_getBbCodeAddEditResponse($bbCode);
	}

	/**
	 * Updates an existing BB code or inserts a new one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$currentId = $this->_input->filterSingle('bb_code_id', XenForo_Input::STRING);
		$newId = $this->_input->filterSingle('new_bb_code_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'bb_code_mode' => XenForo_Input::STRING,
			'has_option' => XenForo_Input::STRING,
			'replace_html' => array(XenForo_Input::STRING, 'noTrim' => true),
			'replace_html_email' => array(XenForo_Input::STRING, 'noTrim' => true),
			'replace_text' => array(XenForo_Input::STRING, 'noTrim' => true),
			'callback_class' => XenForo_Input::STRING,
			'callback_method' => XenForo_Input::STRING,
			'option_regex' => XenForo_Input::STRING,
			'trim_lines_after' => XenForo_Input::UINT,
			'plain_children' => XenForo_Input::BOOLEAN,
			'disable_smilies' => XenForo_Input::BOOLEAN,
			'disable_nl2br' => XenForo_Input::BOOLEAN,
			'disable_autolink' => XenForo_Input::BOOLEAN,
			'allow_empty' => XenForo_Input::BOOLEAN,
			'allow_signature' => XenForo_Input::BOOLEAN,
			'editor_icon_url' => XenForo_Input::STRING,
			'sprite_mode' => XenForo_Input::BOOLEAN,
			'sprite_params' => array(XenForo_Input::INT, array('array' => true)),
			'example' => XenForo_Input::STRING,
			'active' => XenForo_Input::BOOLEAN,
			'addon_id' => XenForo_Input::STRING,
		));
		$title = $this->_input->filterSingle('title', XenForo_Input::STRING);
		$description = $this->_input->filterSingle('description', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_BbCode');
		if ($currentId)
		{
			$dw->setExistingData($currentId);
		}
		$dw->set('bb_code_id', $newId);
		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_BbCode::DATA_TITLE, $title);
		$dw->setExtraData(XenForo_DataWriter_BbCode::DATA_DESCRIPTION, $description);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('bb-codes') . $this->getLastHash($dw->get('bb_code_id'))
		);
	}

	/**
	 * Deletes the specified BB codes.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_BbCode', 'bb_code_id',
				XenForo_Link::buildAdminLink('bb-codes')
			);
		}
		else // show confirmation dialog
		{
			$bbCode = $this->_getBbCodeOrError();

			$viewParams = array(
				'bbCode' => $bbCode
			);
			return $this->responseView('XenForo_ViewAdmin_BbCode_Delete', 'bb_code_delete', $viewParams);
		}
	}

	/**
	 * Selectively enables or disables specified BB codes
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionToggle()
	{
		return $this->_getToggleResponse(
			$this->_getBbCodeModel()->getAllBbCodes(),
			'XenForo_DataWriter_BbCode',
			'bb-codes',
			'active',
			'bb_code_'
		);
	}

	public function actionExport()
	{
		$this->_assertPostOnly();

		$export = $this->_input->filterSingle('export', array(XenForo_Input::STRING, 'array' => true));
		$bbCodes = $this->_getBbCodeModel()->getBbCodesByIds($export);

		$this->_routeMatch->setResponseType('xml');

		$viewParams = array(
			'xml' => $this->_getBbCodeModel()->getBbCodeExportXml($bbCodes)
		);

		return $this->responseView('XenForo_ViewAdmin_BbCode_Export', '', $viewParams);
	}

	public function actionImport()
	{
		if ($this->isConfirmedPost())
		{
			$upload = XenForo_Upload::getUploadedFile('upload');
			if (!$upload)
			{
				return $this->responseError(new XenForo_Phrase('please_provide_valid_bb_code_xml_file'));
			}

			$document = $this->getHelper('Xml')->getXmlFromFile($upload);
			$this->_getBbCodeModel()->importCustomBbCodeXml($document);

			return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('bb-codes')
				);
		}
		else
		{
			return $this->responseView('XenForo_ViewAdmin_BbCode_Import', 'bb_code_import');
		}
	}

	/**
	 * Gets the specified record or errors.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getBbCodeOrError($id = null)
	{
		if ($id === null)
		{
			$id = $this->_input->filterSingle('bb_code_id', XenForo_Input::STRING);
		}

		$info = $this->_getBbCodeModel()->getBbCodeById($id);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_bb_code_not_found'), 404));
		}

		return $this->_getBbCodeModel()->prepareBbCode($info);
	}

	/**
	 * @return XenForo_Model_BbCode
	 */
	protected function _getBbCodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_BbCode');
	}
}