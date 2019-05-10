<?php

class XenForo_Model_AdminTemplateModification extends XenForo_Model_TemplateModificationAbstract
{
	protected $_modTableName = 'xf_admin_template_modification';
	protected $_logTableName = 'xf_admin_template_modification_log';
	protected $_dataWriterName = 'XenForo_DataWriter_AdminTemplateModification';

	public function onAddonActiveSwitch(array $addon)
	{
		$titles = $this->getModificationTemplateTitlesForAddon($addon['addon_id']);

		/** @var $templateModel XenForo_Model_AdminTemplate */
		$templateModel = $this->getModelFromCache('XenForo_Model_AdminTemplate');
		$templates = $templateModel->getAdminTemplatesByTitles($titles);

		$templateIds = array();
		foreach ($templates AS $template)
		{
			$templateIds[] = $template['template_id'];
		}

		if ($templateIds)
		{
			XenForo_Application::defer('AdminTemplatePartialCompile', array(
				'reparseTemplateIds' => $templateIds,
				'recompileTemplateIds' => $templateModel->getIdsToCompileByTemplateIds($templateIds)
			), null, true);

			return true;
		}

		return false;
	}
}
