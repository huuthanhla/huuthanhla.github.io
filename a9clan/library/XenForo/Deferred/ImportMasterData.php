<?php

class XenForo_Deferred_ImportMasterData extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'root' => XenForo_Application::getInstance()->getRootDir() . '/install/data',
			'position' => 0
		), $data);

		$filesRoot = $data['root'];

		if ($data['position'] == 0)
		{
			XenForo_Model::create('XenForo_Model_AdminNavigation')->importAdminNavigationDevelopmentXml($filesRoot . '/admin_navigation.xml');
			XenForo_Model::create('XenForo_Model_Admin')->importAdminPermissionsDevelopmentXml($filesRoot . '/admin_permissions.xml');
		}
		else if ($data['position'] == 1)
		{
			XenForo_Model::create('XenForo_Model_Option')->importOptionsDevelopmentXml($filesRoot . '/options.xml');
			XenForo_Model::create('XenForo_Model_RoutePrefix')->importPrefixesDevelopmentXml($filesRoot . '/route_prefixes.xml');
		}
		else if ($data['position'] == 2)
		{
			XenForo_Model::create('XenForo_Model_StyleProperty')->importStylePropertyDevelopmentXml($filesRoot . '/style_properties.xml', 0);
			XenForo_Model::create('XenForo_Model_StyleProperty')->importStylePropertyDevelopmentXml($filesRoot . '/admin_style_properties.xml', -1);
		}
		else if ($data['position'] == 3)
		{
			XenForo_Model::create('XenForo_Model_CodeEvent')->importEventsDevelopmentXml($filesRoot . '/code_events.xml');
			XenForo_Model::create('XenForo_Model_Cron')->importCronDevelopmentXml($filesRoot . '/cron.xml');
			XenForo_Model::create('XenForo_Model_Permission')->importPermissionsDevelopmentXml($filesRoot . '/permissions.xml');
		}
		else
		{
			XenForo_Model::create('XenForo_Model_Node')->rebuildNodeTypeCache();
			XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
			XenForo_Model::create('XenForo_Model_AddOn')->rebuildActiveAddOnCache();
			XenForo_Model::create('XenForo_Model_Smilie')->rebuildSmilieCache();
			XenForo_Model::create('XenForo_Model_BbCode')->rebuildBbCodeCache();
			XenForo_Model::create('XenForo_Model_UserTitleLadder')->rebuildUserTitleLadderCache();
			XenForo_Model::create('XenForo_Model_UserField')->rebuildUserFieldCache();
			XenForo_Model::create('XenForo_Model_AdminSearch')->rebuildSearchTypesCache();
			XenForo_Model::create('XenForo_Model_CodeEvent')->rebuildEventListenerCache();
			XenForo_Model::create('XenForo_Model_Option')->updateOption('jsLastUpdate', XenForo_Application::$time);

			return false;
		}

		$data['position']++;

		$actionPhrase = new XenForo_Phrase('importing');
		$typePhrase = new XenForo_Phrase('core_master_data');
		$status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

		return $data;
	}
}