<?php

class XenForo_ControllerAdmin_TemplateModification extends XenForo_ControllerAdmin_TemplateModificationAbstract
{
	protected $_viewPrefix = 'XenForo_ViewAdmin_TemplateModification_';
	protected $_templatePrefix = 'template_modification_';
	protected $_routePrefix = 'template-modifications';
	protected $_dataWriter = 'XenForo_DataWriter_TemplateModification';

	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('style');
	}

	public function actionIndex()
	{
		$response = parent::actionIndex();
		if ($response instanceof XenForo_ControllerResponse_View)
		{
			$response->params['canCreateModification'] = XenForo_Application::debugMode();
		}

		return $response;
	}

	public function actionLog()
	{
		$response = parent::actionLog();
		if ($response instanceof XenForo_ControllerResponse_View)
		{
			/** @var $styleModel XenForo_Model_Style */
			$styleModel = $this->getModelFromCache('XenForo_Model_Style');

			$response->params['styles'] = $styleModel->getAllStyles();
		}

		return $response;
	}

	public function actionAutoComplete()
	{
		$q = $this->_input->filterSingle('q', XenForo_Input::STRING);

		if ($q)
		{
			$templates = $this->_getTemplateModel()->getEffectiveTemplateListForStyle(0,
				array('title' => array($q, 'r')),
				array('limit' => 10)
			);
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

		$template = $this->_getTemplateModel()->getEffectiveTemplateByTitle($templateName, 0);

		$view = $this->responseView();
		$view->jsonParams = array(
			'template' => $template ? $this->_adjustTemplateContentForDisplay($template['template']) : false
		);
		return $view;
	}

	protected function _getTestContent(XenForo_DataWriter_TemplateModificationAbstract $dw)
	{
		$template = $this->_getTemplateModel()->getTemplateInStyleByTitle($dw->get('template'));
		return ($template ? $template['template'] : false);
	}

	protected function _adjustTemplateContentForDisplay($content)
	{
		$propertyModel = $this->_getStylePropertyModel();
		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle(0)
		);

		return $propertyModel->replacePropertiesInTemplateForEditor(
			$content, 0, $properties, true
		);
	}

	protected function _adjustModificationForEdit(array $modification)
	{
		if (!empty($modification['modification_id']))
		{
			$propertyModel = $this->_getStylePropertyModel();
			$properties = $propertyModel->keyPropertiesByName(
				$propertyModel->getEffectiveStylePropertiesInStyle(0)
			);

			if ($modification['action'] == 'str_replace')
			{
				// can't mess with the regular expression safely
				$modification['find'] = $propertyModel->replacePropertiesInTemplateForEditor(
					$modification['find'], 0, $properties, true
				);
			}

			$modification['replace'] = $propertyModel->replacePropertiesInTemplateForEditor(
				$modification['replace'], 0, $properties, true
			);
		}

		return $modification;
	}

	protected function _modifyModificationDwData(array &$dwData, $modificationId)
	{
		$propertyModel = $this->_getStylePropertyModel();
		$properties = $propertyModel->keyPropertiesByName($propertyModel->getEffectiveStylePropertiesInStyle(0));

		$propertyModel->translateEditorPropertiesToArray(
			$dwData['find'], $dwData['find'], $properties
		);
		$propertyModel->translateEditorPropertiesToArray(
			$dwData['replace'], $dwData['replace'], $properties
		);
	}

	protected function _getTemplatesByIds(array $ids)
	{
		return $this->_getTemplateModel()->getTemplatesByIds($ids);
	}

	/**
	 * @return XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}

	/**
	 * @return XenForo_Model_StyleProperty
	 */
	protected function _getStylePropertyModel()
	{
		return $this->getModelFromCache('XenForo_Model_StyleProperty');
	}

	/**
	 * @return XenForo_Model_TemplateModification
	 */
	protected function _getModificationModel()
	{
		return $this->getModelFromCache('XenForo_Model_TemplateModification');
	}
}