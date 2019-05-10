<?php

class XenForo_Model_TemplateModification extends XenForo_Model_TemplateModificationAbstract
{
	protected $_modTableName = 'xf_template_modification';
	protected $_logTableName = 'xf_template_modification_log';
	protected $_dataWriterName = 'XenForo_DataWriter_TemplateModification';

	public function onAddonActiveSwitch(array $addon)
	{
		$titles = $this->getModificationTemplateTitlesForAddon($addon['addon_id']);

		/** @var $templateModel XenForo_Model_Template */
		$templateModel = $this->getModelFromCache('XenForo_Model_Template');
		$templateIds = array_keys($templateModel->getTemplatesByTitles($titles));
		if ($templateIds)
		{
			XenForo_Application::defer('TemplatePartialCompile', array(
				'reparseTemplateIds' => $templateIds,
				'recompileMapIds' => $templateModel->getMapIdsToCompileByTitles($titles)
			), null, true);

			return true;
		}

		return false;
	}
}
