<?php

class XenForo_Deferred_AdminTemplate extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'startTemplate' => 0,
			'position' => 0
		), $data);

		/* @var $templateModel XenForo_Model_AdminTemplate */
		$templateModel = XenForo_Model::create('XenForo_Model_AdminTemplate');

		$priority = array('PAGE_CONTAINER_SIMPLE', 'page_container_js', 'tools_cache_rebuild', 'tools_run_deferred');
		$result = $templateModel->compileAllParsedAdminTemplates($targetRunTime, $data['startTemplate'], $priority);

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('admin_templates');
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