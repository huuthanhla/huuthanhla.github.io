<?php

class XenForo_Install_Upgrade_1030052 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.3.0 Release Candidate 2';
	}

	public function step1()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_admin_template_phrase CHANGE phrase_title phrase_title VARBINARY(100) NOT NULL
		");

		$this->executeUpgradeQuery("
			ALTER TABLE xf_email_template_phrase CHANGE phrase_title phrase_title VARBINARY(100) NOT NULL
		");

		$this->executeUpgradeQuery("
			ALTER TABLE xf_phrase_compiled CHANGE title title VARBINARY(100) NOT NULL
		");

		$this->executeUpgradeQuery("
			ALTER TABLE xf_phrase_map CHANGE title title VARBINARY(100) NOT NULL
		");

		$this->executeUpgradeQuery("
			ALTER TABLE xf_template_phrase CHANGE phrase_title phrase_title VARBINARY(100) NOT NULL
		");
	}
}