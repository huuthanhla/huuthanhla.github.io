<?php

class XenForo_Deferred_Template extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'startStyle' => 0,
			'startTemplate' => 0,
			'position' => 0,
			'mapped' => false
		), $data);

		/* @var $templateModel XenForo_Model_Template */
		$templateModel = XenForo_Model::create('XenForo_Model_Template');

		if ($data['startStyle'] == 0 && $data['startTemplate'] == 0 && !$data['mapped'])
		{
			$s = microtime(true);
			$templateModel->insertTemplateMapForStyles($templateModel->buildTemplateMapForStyleTree(0), true);
			$data['mapped'] = true;

			$maxExec = ($targetRunTime ? $targetRunTime - (microtime(true) - $s) : 0);
		}
		else
		{
			$maxExec = $targetRunTime;
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('templates');
		$status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

		if (!$targetRunTime || $maxExec > 1)
		{
			$result = $templateModel->compileAllTemplates($maxExec, $data['startStyle'], $data['startTemplate']);
		}
		else
		{
			$result = false;
		}

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