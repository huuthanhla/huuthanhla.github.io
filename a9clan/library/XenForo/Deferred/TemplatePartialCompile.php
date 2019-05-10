<?php

class XenForo_Deferred_TemplatePartialCompile extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'reparseTemplateIds' => array(),
			'recompileMapIds' => array(),
			'position' => 0,
		), $data);

		/* @var $templateModel XenForo_Model_Template */
		$templateModel = XenForo_Model::create('XenForo_Model_Template');

		$s = microtime(true);
		$outOfTime = false;
		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('templates');
		$status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

		if ($data['reparseTemplateIds'])
		{
			foreach ($data['reparseTemplateIds'] AS $k => $templateId)
			{
				$templateModel->reparseTemplate($templateId, false);
				unset($data['reparseTemplateIds'][$k]);

				$runTime = microtime(true) - $s;
				if ($targetRunTime && $runTime > $targetRunTime)
				{
					$outOfTime = true;
					break;
				}
			}
		}

		if ($data['recompileMapIds'] && !$outOfTime)
		{
			foreach ($data['recompileMapIds'] AS $k => $templateMapId)
			{
				$templateMap = $templateModel->getEffectiveTemplateByMapId($templateMapId);
				if (!$templateMap)
				{
					unset($data['recompileMapIds'][$k]);
					continue;
				}
				$parsedTemplate = unserialize($templateMap['template_parsed']);

				$templateModel->compileAndInsertParsedTemplate(
					$templateMap['template_map_id'],
					$parsedTemplate,
					$templateMap['title'],
					$templateMap['map_style_id']
				);
				unset($data['recompileMapIds'][$k]);

				$runTime = microtime(true) - $s;
				if ($targetRunTime && $runTime > $targetRunTime)
				{
					break;
				}
			}
		}

		if (!$data['reparseTemplateIds'] && !$data['recompileMapIds'])
		{
			return true;
		}
		else
		{
			$data['position']++;

			return $data;
		}
	}
}