<?php

class XenForo_Model_EmailTemplateModification extends XenForo_Model_TemplateModificationAbstract
{
	protected $_modTableName = 'xf_email_template_modification';
	protected $_logTableName = 'xf_email_template_modification_log';
	protected $_dataWriterName = 'XenForo_DataWriter_EmailTemplateModification';

	public function onAddonActiveSwitch(array $addon)
	{
		$titles = $this->getModificationTemplateTitlesForAddon($addon['addon_id']);

		/** @var $templateModel XenForo_Model_EmailTemplate */
		$templateModel = $this->getModelFromCache('XenForo_Model_EmailTemplate');
		$templates = $templateModel->getEmailTemplatesByTitles($titles);

		$templateIds = array();
		foreach ($templates AS $template)
		{
			$templateIds[] = $template['template_id'];
		}

		if ($templateIds)
		{
			XenForo_Application::defer('EmailTemplatePartialCompile', array(
				'reparseTemplateIds' => $templateIds,
				'recompileTemplateIds' => $templateIds
			), null, true);

			return true;
		}

		return false;
	}

	protected function _addExtraToAddonXmlImportDw(XenForo_DataWriter_TemplateModificationAbstract $dw, SimpleXMLElement $modification)
	{
		$dw->set('search_location', (string)$modification['search_location']);
	}

	protected function _modifyAddOnXmlNode(DOMElement &$modNode, array $modification)
	{
		$modNode->setAttribute('search_location', $modification['search_location']);
	}
}
