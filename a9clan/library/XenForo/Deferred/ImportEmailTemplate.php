<?php

class XenForo_Deferred_ImportEmailTemplate extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'file' => XenForo_Application::getInstance()->getRootDir() . '/install/data/email_templates.xml'
		), $data);

		/* @var $templateModel XenForo_Model_EmailTemplate */
		$templateModel = XenForo_Model::create('XenForo_Model_EmailTemplate');

		$document = XenForo_Helper_DevelopmentXml::scanFile($data['file']);
		$templateModel->importEmailTemplatesAddOnXml($document, 'XenForo', false);

		$actionPhrase = new XenForo_Phrase('importing');
		$typePhrase = new XenForo_Phrase('email_templates');
		$status = sprintf('%s... %s', $actionPhrase, $typePhrase);

		return false;
	}
}