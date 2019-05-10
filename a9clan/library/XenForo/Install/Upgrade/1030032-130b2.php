<?php

class XenForo_Install_Upgrade_1030032 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.3.0 Beta 2';
	}

	public function step1()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE  `xf_image_proxy` ADD INDEX last_request_date (`last_request_date`)
		");

		$this->executeUpgradeQuery("
			ALTER TABLE  `xf_link_proxy` ADD INDEX last_request_date (`last_request_date`)
		");
	}
}