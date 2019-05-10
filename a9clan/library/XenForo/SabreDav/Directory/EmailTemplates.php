<?php

class XenForo_SabreDav_Directory_EmailTemplates extends Sabre_DAV_Directory
{
	protected $_templateType;

	public function __construct($directory)
	{
		$this->_templateType = $directory;
	}

	public function getChildren()
	{
		/* @var $emailTemplateModel XenForo_Model_EmailTemplate */
		$emailTemplateModel = XenForo_Model::create('XenForo_Model_EmailTemplate');

		if ($this->_isCustom())
		{
			$emailTemplates = $emailTemplateModel->getAllEffectiveEmailTemplates();
		}
		else
		{
			$emailTemplates = $emailTemplateModel->getAllMasterEmailTemplates();
		}

		$output = array();
		foreach ($emailTemplates AS $emailTemplate)
		{
			$output[] = new XenForo_SabreDav_File_EmailTemplate($emailTemplate, $this->_templateType);
		}

		return $output;
	}

	public function getChild($title)
	{
		if (substr($title, -5) == '.html')
		{
			$title = substr($title, 0, -5);
		}

		/* @var $emailTemplateModel XenForo_Model_EmailTemplate */
		$emailTemplateModel = XenForo_Model::create('XenForo_Model_EmailTemplate');

		$emailTemplate = $emailTemplateModel->getEmailTemplateByTitleAndType($title, $this->_isCustom());
		if ($emailTemplate)
		{
			return new XenForo_SabreDav_File_EmailTemplate($emailTemplate, $this->_isCustom());
		}
		else
		{
			//throw new XenForo_Exception('Unable to fetch email template: ' . $title);

			//return new XenForo_SabreDav_File_EmailTemplate(null, $this->_isCustom(), $title);
		}
	}

	public function getName()
	{
		return $this->_templateType;
	}

	protected function _isCustom()
	{
		return (
			$this->_templateType == XenForo_SabreDav_Directory_EmailTemplateTypes::CUSTOM_TEMPLATES
			? 1 : 0
		);
	}
}