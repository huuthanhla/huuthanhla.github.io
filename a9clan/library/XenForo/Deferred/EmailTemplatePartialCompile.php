<?php

class XenForo_Deferred_EmailTemplatePartialCompile extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'reparseTemplateIds' => array(),
			'recompileTemplateIds' => array(),
			'position' => 0,
		), $data);

		/* @var $emailTemplateModel XenForo_Model_EmailTemplate */
		$emailTemplateModel = XenForo_Model::create('XenForo_Model_EmailTemplate');

		$s = microtime(true);
		$outOfTime = false;
		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('email_templates');
		$status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

		if ($data['reparseTemplateIds'])
		{
			foreach ($data['reparseTemplateIds'] AS $k => $templateId)
			{
				$emailTemplateModel->reparseTemplate($templateId, false);
				unset($data['reparseTemplateIds'][$k]);

				$runTime = microtime(true) - $s;
				if ($targetRunTime && $runTime > $targetRunTime)
				{
					$outOfTime = true;
					break;
				}
			}
		}

		if ($data['recompileTemplateIds'] && !$outOfTime)
		{
			foreach ($data['recompileTemplateIds'] AS $k => $templateId)
			{
				$template = $emailTemplateModel->getEmailTemplateById($templateId);
				if (!$template)
				{
					unset($data['recompileTemplateIds'][$k]);
					continue;
				}

				$emailTemplateModel->compileAndInsertNamedEmailTemplate($template['title']);
				unset($data['recompileTemplateIds'][$k]);

				$runTime = microtime(true) - $s;
				if ($targetRunTime && $runTime > $targetRunTime)
				{
					break;
				}
			}
		}

		if (!$data['reparseTemplateIds'] && !$data['recompileTemplateIds'])
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