<?php

class XenForo_Install_Upgrade_1010035 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.1.0 Beta 5';
	}

	public function step1()
	{
		// fix metacafe match URL
		$this->executeUpgradeQuery('
			UPDATE xf_bb_code_media_site
			SET match_urls = REPLACE(match_urls,
				\'#metacafe\\.com/watch/(?P<id>\\d+\/[a-z0-9_]+)/#siU\',
				\'#metacafe\\\\.com/watch/(?P<id>\\\\d+/[a-z0-9_]+)/#siU\'
			)
			WHERE media_site_id = \'metacafe\'
		');

		return true;
	}
}