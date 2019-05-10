<?php

class XenForo_Install_Upgrade_1040270 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.4.2';
	}

	public function step1()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_session_activity
			DROP PRIMARY KEY,
			ADD PRIMARY KEY (user_id, unique_key) USING BTREE
		");
	}
}