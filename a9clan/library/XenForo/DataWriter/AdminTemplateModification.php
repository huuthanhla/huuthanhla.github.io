<?php


class XenForo_DataWriter_AdminTemplateModification extends XenForo_DataWriter_TemplateModificationAbstract
{
	protected $_modTableName = 'xf_admin_template_modification';
	protected $_logTableName = 'xf_admin_template_modification_log';

	protected function _reparseTemplate($title, $fullCompile = true)
	{
		$templateModel = $this->_getAdminTemplateModel();

		$templates = $templateModel->getAdminTemplatesByTitles(array($title));
		foreach ($templates AS $template)
		{
			$templateModel->reparseTemplate($template, $fullCompile);
		}
	}

	/**
	 * @return XenForo_Model_AdminTemplate
	 */
	protected function _getAdminTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_AdminTemplate');
	}

	/**
	 * @return XenForo_Model_AdminTemplateModification
	 */
	protected function _getModificationModel()
	{
		return $this->getModelFromCache('XenForo_Model_AdminTemplateModification');
	}
}