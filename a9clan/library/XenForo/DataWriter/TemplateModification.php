<?php


class XenForo_DataWriter_TemplateModification extends XenForo_DataWriter_TemplateModificationAbstract
{
	protected $_modTableName = 'xf_template_modification';
	protected $_logTableName = 'xf_template_modification_log';

	protected function _reparseTemplate($title, $fullCompile = true)
	{
		$templateModel = $this->_getTemplateModel();

		$templates = $templateModel->getTemplatesByTitles(array($title));
		foreach ($templates AS $template)
		{
			$templateModel->reparseTemplate($template, $fullCompile);
		}
	}

	/**
	 * @return XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		return $this->getModelFromCache('XenForo_Model_Template');
	}

	/**
	 * @return XenForo_Model_TemplateModification
	 */
	protected function _getModificationModel()
	{
		return $this->getModelFromCache('XenForo_Model_TemplateModification');
	}
}