<?php

class XenForo_ControllerAdmin_AdminTemplateModification extends XenForo_ControllerAdmin_TemplateModificationAbstract
{
	protected $_viewPrefix = 'XenForo_ViewAdmin_AdminTemplateModification_';
	protected $_templatePrefix = 'admin_template_modification_';
	protected $_routePrefix = 'admin-template-mods';
	protected $_dataWriter = 'XenForo_DataWriter_AdminTemplateModification';

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
			$templates = $this->_getAdminTemplateModel()->getAdminTemplatesLikeTitle($q, 'r', 10);
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

		$template = $this->_getAdminTemplateModel()->getAdminTemplateByTitle($templateName);

		$view = $this->responseView();
		$view->jsonParams = array(
			'template' => $template ? $this->_adjustTemplateContentForDisplay($template['template']) : false
		);
		return $view;
	}

	protected function _getTestContent(XenForo_DataWriter_TemplateModificationAbstract $dw)
	{
		$template = $this->_getAdminTemplateModel()->getAdminTemplateByTitle($dw->get('template'));
		return ($template ? $template['template'] : false);
	}

	protected function _adjustTemplateContentForDisplay($content)
	{
		$propertyModel = $this->_getStylePropertyModel();
		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle(-1)
		);

		return $propertyModel->replacePropertiesInTemplateForEditor(
			$content, -1, $properties, true
		);
	}

	protected function _adjustModificationForEdit(array $modification)
	{
		if (!empty($modification['modification_id']))
		{
			$propertyModel = $this->_getStylePropertyModel();
			$properties = $propertyModel->keyPropertiesByName(
				$propertyModel->getEffectiveStylePropertiesInStyle(-1)
			);

			if ($modification['action'] == 'str_replace')
			{
				// can't mess with the regular expression safely
				$modification['find'] = $propertyModel->replacePropertiesInTemplateForEditor(
					$modification['find'], -1, $properties, true
				);
			}

			$modification['replace'] = $propertyModel->replacePropertiesInTemplateForEditor(
				$modification['replace'], -1, $properties, true
			);
		}

		return $modification;
	}

	protected function _modifyModificationDwData(array &$dwData, $modificationId)
	{
		$propertyModel = $this->_getStylePropertyModel();
		$properties = $propertyModel->keyPropertiesByName($propertyModel->getEffectiveStylePropertiesInStyle(-1));

		$propertyModel->translateEditorPropertiesToArray(
			$dwData['find'], $dwData['find'], $properties
		);
		$propertyModel->translateEditorPropertiesToArray(
			$dwData['replace'], $dwData['replace'], $properties
		);
	}

	protected function _getTemplatesByIds(array $ids)
	{
		return $this->_getAdminTemplateModel()->getAdminTemplatesByIds($ids);
	}

	/**
	 * @return XenForo_Model_AdminTemplate
	 */
	protected function _getAdminTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_AdminTemplate');
	}

	/**
	 * @return XenForo_Model_StyleProperty
	 */
	protected function _getStylePropertyModel()
	{
		return $this->getModelFromCache('XenForo_Model_StyleProperty');
	}

	/**
	 * @return XenForo_Model_AdminTemplateModification
	 */
	protected function _getModificationModel()
	{
		return $this->getModelFromCache('XenForo_Model_AdminTemplateModification');
	}
}