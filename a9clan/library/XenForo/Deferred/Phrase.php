<?php

class XenForo_Deferred_Phrase extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'startLanguage' => 0,
			'startPhrase' => 0,
			'position' => 0,
			'mapped' => false
		), $data);

		/* @var $phraseModel XenForo_Model_Phrase */
		$phraseModel = XenForo_Model::create('XenForo_Model_Phrase');

		if ($data['startLanguage'] == 0 && $data['startPhrase'] == 0 && !$data['mapped'])
		{
			$s = microtime(true);
			$phraseModel->insertPhraseMapForLanguages($phraseModel->buildPhraseMapForLanguageTree(0), true);
			$data['mapped'] = true;

			$maxExec = ($targetRunTime ? $targetRunTime - (microtime(true) - $s) : 0);
		}
		else
		{
			$maxExec = $targetRunTime;
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('phrases');
		$status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

		if (!$targetRunTime || $maxExec > 1)
		{
			$result = $phraseModel->compileAllPhrases($maxExec, $data['startLanguage'], $data['startPhrase']);
		}
		else
		{
			$result = false;
		}
		if ($result === true)
		{
			return false;
		}
		else
		{
			if ($result)
			{
				$data['startLanguage'] = $result[0];
				$data['startPhrase'] = $result[1];
			}
			$data['position']++;

			return $data;
		}
	}
}