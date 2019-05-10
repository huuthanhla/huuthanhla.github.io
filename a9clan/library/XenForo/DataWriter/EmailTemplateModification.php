<?php


class XenForo_DataWriter_EmailTemplateModification extends XenForo_DataWriter_TemplateModificationAbstract
{
	protected $_modTableName = 'xf_email_template_modification';
	protected $_logTableName = 'xf_email_template_modification_log';

	protected function _getFields()
	{
		$fields = parent::_getFields();
		$fields['xf_email_template_modification']['search_location'] = array(
			'type' => self::TYPE_STRING,
			'required' => true,
			'allowedValues' => array('subject', 'body_text', 'body_html')
		);

		return $fields;
	}

	protected function _reparseTemplate($title, $fullCompile = true)
	{
		$templateModel = $this->_getEmailTemplateModel();

		$templates = $templateModel->getEmailTemplatesByTitles(array($title));
		foreach ($templates AS $template)
		{
			$templateModel->reparseTemplate($template, $fullCompile);
		}
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