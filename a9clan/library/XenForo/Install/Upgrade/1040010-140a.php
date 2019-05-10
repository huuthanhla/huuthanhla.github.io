<?php

class XenForo_Install_Upgrade_1040010 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.4.0 Alpha';
	}

	public function step1()
	{
		$tables = XenForo_Install_Data_MySql::getTables();

		$this->executeUpgradeQuery($tables['xf_email_bounce_log']);
		$this->executeUpgradeQuery($tables['xf_email_bounce_soft']);
		$this->executeUpgradeQuery($tables['xf_help_page']);
		$this->executeUpgradeQuery($tables['xf_sitemap']);
		$this->executeUpgradeQuery($tables['xf_thread_reply_ban']);

		$this->applyGlobalPermission('general', 'viewMemberList', 'general', 'view', false);
		$this->applyGlobalPermission('forum', 'threadReplyBan', 'forum', 'deleteAnyPost', true);
		$this->applyContentPermission('forum', 'threadReplyBan', 'forum', 'deleteAnyPost', true);

		$this->executeUpgradeQuery("
			INSERT IGNORE INTO xf_admin_permission_entry
				(user_id, admin_permission_id)
			SELECT user_id, 'help'
			FROM xf_admin_permission_entry
			WHERE admin_permission_id = 'notice'
		");

		$this->executeUpgradeQuery("
			INSERT IGNORE INTO xf_content_type_field
				(content_type, field_name, field_value)
			VALUES
				('node', 'sitemap_handler_class', 'XenForo_SitemapHandler_Node'),
				('thread', 'sitemap_handler_class', 'XenForo_SitemapHandler_Thread'),
				('user', 'sitemap_handler_class', 'XenForo_SitemapHandler_User'),

				('thread', 'alert_handler_class', 'XenForo_AlertHandler_Thread')
		");

		$regDefault = @unserialize($this->_getDb()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'registrationDefaults'
		"));
		if ($regDefault)
		{
			$regDefault['activity_visible'] = !empty($regDefault['visible']) ? '1' : '0';
			$this->_getDb()->update('xf_option', array(
				'option_value' => serialize($regDefault)
			), "option_id = 'registrationDefaults'");
		}
	}

	public function step2()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_bb_code ADD allow_empty TINYINT UNSIGNED NOT NULL DEFAULT '0'
		");

		$this->executeUpgradeQuery("
			ALTER TABLE xf_forum
				CHANGE moderate_messages moderate_replies TINYINT UNSIGNED NOT NULL DEFAULT 0,
				ADD moderate_threads TINYINT UNSIGNED NOT NULL DEFAULT 0,
				ADD allow_poll TINYINT UNSIGNED NOT NULL DEFAULT 1,
				ADD list_date_limit_days SMALLINT UNSIGNED NOT NULL DEFAULT 0
		");
		$this->executeUpgradeQuery("
			UPDATE xf_forum SET moderate_threads = 1 WHERE moderate_replies = 1
		");

		$this->executeUpgradeQuery("RENAME TABLE xf_trophy_user_title TO xf_user_title_ladder");
		$this->executeUpgradeQuery("
			ALTER TABLE xf_user_title_ladder
				CHANGE minimum_points minimum_level INT UNSIGNED NOT NULL
		");
	}

	public function step3()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_poll ADD change_vote TINYINT UNSIGNED NOT NULL DEFAULT 0,
				ADD view_results_unvoted TINYINT UNSIGNED NOT NULL DEFAULT 1,
				ADD max_votes TINYINT UNSIGNED NOT NULL DEFAULT 1
		");
		$this->executeUpgradeQuery("
			UPDATE xf_poll SET max_votes = 0 WHERE multiple = 1
		");
		$this->executeUpgradeQuery("
			ALTER TABLE xf_poll DROP multiple
		");
	}

	public function step4()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_user ADD activity_visible TINYINT UNSIGNED NOT NULL DEFAULT 1
		");
	}

	public function step5()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_user_profile ADD password_date INT UNSIGNED NOT NULL DEFAULT 1
		");
	}
}