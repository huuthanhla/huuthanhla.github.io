<?php

class XenForo_ControllerAdmin_HelpPage extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('help');
	}

	public function actionIndex()
	{
		$helpModel = $this->_getHelpModel();
		$pages = $helpModel->getHelpPages();

		$viewParams = array(
			'pages' => $helpModel->preparePages($pages)
		);

		return $this->responseView('XenForo_ViewAdmin_HelpPage_List', 'help_page_list', $viewParams);
	}

	protected function _getHelpPageAddEditResponse(array $page)
	{
		$helpModel = $this->_getHelpModel();

		if (!empty($page['page_id']))
		{
			$title = $helpModel->getHelpPageMasterTitlePhraseValue($page['page_id']);
			$description = $helpModel->getHelpPageMasterDescriptionPhraseValue($page['page_id']);
			$template = $helpModel->getHelpPageTemplate($page['page_id']);
		}
		else
		{
			$title = '';
			$description = '';
			$template = null;
		}

		$viewParams = array(
			'page' => $page,
			'title' => $title,
			'description' => $description,
			'template' => $template,
		);

		return $this->responseView('XenForo_ViewAdmin_HelpPage_Edit', 'help_page_edit', $viewParams);
	}

	public function actionAdd()
	{
		return $this->_getHelpPageAddEditResponse(array(
			'display_order' => 1
		));
	}

	public function actionEdit()
	{
		$page = $this->_getHelpPageOrError();

		return $this->_getHelpPageAddEditResponse($page);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		$pageName = $this->_input->filterSingle('page_name', XenForo_Input::STRING);
		if ($pageName !== '')
		{
			$page = $this->_getHelpPageOrError($pageName);
		}
		else
		{
			$page = null;
		}

		$dwInput = $this->_input->filter(array(
			'display_order' => XenForo_Input::UINT,
			'callback_class' => XenForo_Input::STRING,
			'callback_method' => XenForo_Input::STRING,
		));
		$extraInput = $this->_input->filter(array(
			'new_page_name' => XenForo_Input::STRING,
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'content' => XenForo_Input::STRING
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_HelpPage');
		if ($page)
		{
			$dw->setExistingData($page, true);
		}
		$dw->bulkSet($dwInput);
		$dw->set('page_name', $extraInput['new_page_name']);
		$dw->setExtraData(XenForo_DataWriter_HelpPage::DATA_TITLE, $extraInput['title']);
		$dw->setExtraData(XenForo_DataWriter_HelpPage::DATA_DESCRIPTION, $extraInput['description']);
		$dw->setExtraData(XenForo_DataWriter_HelpPage::DATA_CONTENT, $extraInput['content']);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('help-pages') . $this->getLastHash($dw->get('page_id'))
		);
	}

	public function actionDelete()
	{
		$page = $this->_getHelpPageOrError();

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_HelpPage');
		$dw->setExistingData($page, true);

		if ($this->isConfirmedPost()) // delete add-on
		{
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('help-pages')
			);
		}
		else // show delete confirmation prompt
		{
			$viewParams = array(
				'page' => $page
			);

			return $this->responseView('XenForo_ViewAdmin_HelpPage_Delete', 'help_page_delete', $viewParams);
		}
	}

	protected function _getHelpPageOrError($pageName = null)
	{
		if ($pageName === null)
		{
			$pageName = $this->_input->filterSingle('page_name', XenForo_Input::STRING);
		}

		$info = $this->_getHelpModel()->getHelpPageByName($pageName);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_help_page_not_found'), 404));
		}

		return $this->_getHelpModel()->preparePage($info);
	}

	/**
	 * @return XenForo_Model_Help
	 */
	protected function _getHelpModel()
	{
		return $this->getModelFromCache('XenForo_Model_Help');
	}
}