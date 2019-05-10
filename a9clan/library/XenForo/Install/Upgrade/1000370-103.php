<?php

class XenForo_Install_Upgrade_1000370 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.0.3';
	}

	public function step1()
	{
		$db = $this->_getDb();

		// 'report' permission
		try
		{
			$db->query("
				INSERT IGNORE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT user_group_id, 0, 'general', 'report', 'allow', 0
					FROM xf_user_group
					WHERE xf_user_group.user_group_id <> 1
			");
		}
		catch (Zend_Db_Exception $e) {}

		return true;
	}
}