<?php

abstract class XenForo_ControllerAdmin_StyleAbstract extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('style');
	}

	/**
	 * Helper to get the template add/edit form controller response.
	 *
	 * @param array $template
	 * @param integer $inputStyleId The style this template is being edited in
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getTemplateAddEditResponse(array $template, $inputStyleId)
	{
		$stylePropertyModel = $this->_getStylePropertyModel();
		$templateModel = $this->_getTemplateModel();
		$styleModel = $this->_getStyleModel();
		$addOnModel = $this->_getAddOnModel();

		if ($template['style_id'] != $inputStyleId)
		{
			// actually adding a "copy" of this template in this style
			$template['template_id'] = null;
			$template['style_id'] = $inputStyleId;
		}

		if (!$templateModel->canModifyTemplateInStyle($template['style_id']))
		{
			return $this->responseError(new XenForo_Phrase('templates_in_this_style_can_not_be_modified'));
		}

		if ($template['template_id'])
		{
			$outdated = $this->_getTemplateModel()->getOutdatedTemplates();
			$isOutdated = isset($outdated[$template['template_id']]);
		}
		else
		{
			$isOutdated = false;
		}

		$viewParams = array(
			'template' => $template,
			'style' => $styleModel->getStyleByid($template['style_id'], true),
			'modifications' => !empty($template['title']) ? $this->_getModificationModel()->getModificationsForTemplate($template['title']) : array(),
			'hasHistory' => !empty($template['title']) ? count($this->_getTemplateModel()->getHistoryForTemplate($template['title'], $template['style_id'])) > 0 : false,
			'isOutdated' => $isOutdated,
			'masterStyle' => $styleModel->showMasterStyle() ? $styleModel->getStyleById(0, true) : array(),
			'styles' => $styleModel->getAllStylesAsFlattenedTree($styleModel->showMasterStyle() ? 1 : 0),
			'addOnOptions' => ($template['style_id'] == 0 ? $addOnModel->getAddOnOptionsListIfAvailable() : array()),
			'addOnSelected' => (
				isset($template['addon_id'])
				? $template['addon_id']
				: ($template['style_id'] == 0 ? $addOnModel->getDefaultAddOnId() : '')
			),
		);

		return $this->responseView('XenForo_ViewAdmin_Template_Edit', 'template_edit', $viewParams);
	}

	/**
	 * Gets the named style or throws an error.
	 *
	 * @param integer $styleId Style ID
	 *
	 * @return array
	 */
	protected function _getStyleOrError($styleId, $fetchMaster = false)
	{
		$style = $this->_getStyleModel()->getStyleById($styleId, $fetchMaster);
		if (!$style)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_style_not_found'), 404));
		}

		return $style;
	}

	/**
	 * Gets the named template or throws an error.
	 *
	 * @param integer $id Template ID
	 *
	 * @return array
	 */
	protected function _getTemplateOrError($id)
	{
		return $this->getRecordOrError(
			$id, $this->_getTemplateModel(), 'getTemplateById', 'requested_template_not_found'
		);
	}

	/**
	 * Lazy load the template model object.
	 *
	 * @return  XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}

	/**
	 * @return  XenForo_Model_TemplateModification
	 */
	protected function _getModificationModel()
	{
		return $this->getModelFromCache('XenForo_Model_TemplateModification');
	}

	/**
	 * Lazy load the style model object.
	 *
	 * @return  XenForo_Model_Style
	 */
	protected function _getStyleModel()
	{
		return $this->getModelFromCache('XenForo_Model_Style');
	}

	/**
	 * @return  XenForo_Model_StyleProperty
	 */
	protected function _getStylePropertyModel()
	{
		return $this->getModelFromCache('XenForo_Model_StyleProperty');
	}

	/**
	 * Get the add-on model.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}