<?php

class XenForo_ControllerAdmin_EmailTemplateModification extends XenForo_ControllerAdmin_TemplateModificationAbstract
{
	protected $_viewPrefix = 'XenForo_ViewAdmin_EmailTemplateModification_';
	protected $_templatePrefix = 'email_template_modification_';
	protected $_routePrefix = 'email-template-mods';
	protected $_dataWriter = 'XenForo_DataWriter_EmailTemplateModification';

	protected function _preDispatch($action)
	{
		$this->assertDebugMode();
		$this->assertAdminPermission('dev');
	}

	public function actionAutoComplete()
	{
		$q = $this->_input->filterSingle('q', XenForo_Input::STRING);

		if ($q)
		{
			$templates = $this->_getEmailTemplateModel()->getMasterEmailTemplatesLikeTitle($q, 'r', 10);
		}
		else
		{
			$templates = array();
		}

		$view = $this->responseView();
		$view->jsonParams = array(
			'results' => XenForo_Application::arrayColumn($templates, 'title', 'title')
		);
		return $view;
	}

	public function actionContents()
	{
		$templateName = $this->_input->filterSingle('template', XenForo_Input::STRING);

		$template = $this->_getEmailTemplateModel()->getEffectiveEmailTemplateByTitle($templateName);

		$view = $this->responseView();
		if ($template)
		{
			$subject = new XenForo_Phrase('subject');
			$plainTextBody = new XenForo_Phrase('plain_text_body');
			$htmlBody = new XenForo_Phrase('html_body');

			$view->jsonParams = array(
				'template' => "$subject\n----------\n$template[subject]\n\n"
					. "$plainTextBody\n----------\n$template[body_text]\n\n"
					. "$htmlBody\n----------\n$template[body_html]"
			);
		}
		else
		{
			$view->jsonParams = array(
				'template' => false
			);
		}
		return $view;
	}

	protected function _getModificationAddEditResponse(array $modification)
	{
		if (empty($modification['modification_id']))
		{
			$modification['search_location'] = 'body_html';
		}

		return parent::_getModificationAddEditResponse($modification);
	}

	protected function _modifyModificationDwData(array &$dwData, $modificationId)
	{
		$dwData['search_location'] = $this->_input->filterSingle('search_location', XenForo_Input::STRING);
	}

	protected function _getTestContent(XenForo_DataWriter_TemplateModificationAbstract $dw)
	{
		$field = $dw->get('search_location') ? $dw->get('search_location') : 'body_html';
		$template = $this->_getEmailTemplateModel()->getEmailTemplateByTitleAndType($dw->get('template'), 0);
		return ($template ? $template[$field] : false);
	}

	protected function _getTemplatesByIds(array $ids)
	{
		return $this->_getEmailTemplateModel()->getEmailTemplatesByIds($ids);
	}

	/**
	 * @return XenForo_Model_EmailTemplate
	 */
	protected function _getEmailTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_EmailTemplate');
	}

	/**
	 * @return XenForo_Model_EmailTemplateModification
	 */
	protected function _getModificationModel()
	{
		return $this->getModelFromCache('XenForo_Model_EmailTemplateModification');
	}
}