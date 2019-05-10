<?php

class XenForo_Deferred_TemplateReparse extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'startStyle' => 0,
			'startTemplate' => 0,
			'position' => 0
		), $data);

		/* @var $templateModel XenForo_Model_Template */
		$templateModel = XenForo_Model::create('XenForo_Model_Template');

		$actionPhrase = new XenForo_Phrase('reparsing');
		$typePhrase = new XenForo_Phrase('templates');
		$status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

		$result = $templateModel->reparseAllTemplates($targetRunTime, $data['startStyle'], $data['startTemplate']);

		if ($result === true)
		{
			return true;
		}
		else
		{
			if ($result)
			{
				$data['startStyle'] = $result[0];
				$data['startTemplate'] = $result[1];
			}
			$data['position']++;

			return $data;
		}
	}
}