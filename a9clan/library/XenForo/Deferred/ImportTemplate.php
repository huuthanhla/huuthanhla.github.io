<?php

class XenForo_Deferred_ImportTemplate extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'file' => XenForo_Application::getInstance()->getRootDir() . '/install/data/templates.xml',
			'offset' => 0,
			'position' => 0
		), $data);

		/* @var $templateModel XenForo_Model_Template */
		$templateModel = XenForo_Model::create('XenForo_Model_Template');

		$document = XenForo_Helper_DevelopmentXml::scanFile($data['file']);
		$result = $templateModel->importTemplatesAddOnXml($document, 'XenForo', $targetRunTime, $data['offset']);

		if (is_int($result))
		{
			$data['offset'] = $result;
			$data['position']++;

			$actionPhrase = new XenForo_Phrase('importing');
			$typePhrase = new XenForo_Phrase('templates');
			$status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

			return $data; // continue again
		}
		else
		{
			return false;
		}
	}
}