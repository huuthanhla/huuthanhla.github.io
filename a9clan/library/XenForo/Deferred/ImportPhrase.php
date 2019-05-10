<?php

class XenForo_Deferred_ImportPhrase extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'file' => XenForo_Application::getInstance()->getRootDir() . '/install/data/phrases.xml',
			'offset' => 0,
			'position' => 0
		), $data);

		/* @var $phraseModel XenForo_Model_Phrase */
		$phraseModel = XenForo_Model::create('XenForo_Model_Phrase');

		$document = XenForo_Helper_DevelopmentXml::scanFile($data['file']);
		$result = $phraseModel->importPhrasesAddOnXml($document, 'XenForo', $targetRunTime, $data['offset']);

		if (is_int($result))
		{
			$data['offset'] = $result;
			$data['position']++;

			$actionPhrase = new XenForo_Phrase('importing');
			$typePhrase = new XenForo_Phrase('phrases');
			$status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

			return $data; // continue again
		}
		else
		{
			return false;
		}
	}
}