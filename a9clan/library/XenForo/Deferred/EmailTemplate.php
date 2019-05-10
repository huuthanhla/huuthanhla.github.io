<?php

class XenForo_Deferred_EmailTemplate extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		/* @var $templateModel XenForo_Model_EmailTemplate */
		$templateModel = XenForo_Model::create('XenForo_Model_EmailTemplate');

		$templateModel->compileAllEmailTemplates();

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('email_templates');
		$status = sprintf('%s... %s', $actionPhrase, $typePhrase);

		return false;
	}
}