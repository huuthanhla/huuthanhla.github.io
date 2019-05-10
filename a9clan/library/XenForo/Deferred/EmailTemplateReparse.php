<?php

class XenForo_Deferred_EmailTemplateReparse extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'startTemplate' => 0,
			'position' => 0
		), $data);

		/* @var $templateModel XenForo_Model_EmailTemplate */
		$templateModel = XenForo_Model::create('XenForo_Model_EmailTemplate');

		$result = $templateModel->reparseAllEmailTemplates($targetRunTime, $data['startTemplate']);

		$actionPhrase = new XenForo_Phrase('reparsing');
		$typePhrase = new XenForo_Phrase('email_templates');
		$status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

		if ($result === true)
		{
			return false;
		}
		else
		{
			$data['startTemplate'] = $result;
			$data['position']++;

			return $data;
		}
	}
}