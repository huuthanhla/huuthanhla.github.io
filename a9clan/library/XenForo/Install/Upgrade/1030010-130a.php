<?php

class XenForo_Install_Upgrade_1030010 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.3.0 Alpha';
	}

	public function step1()
	{
		$tables = XenForo_Install_Data_MySql::getTables();

		$this->executeUpgradeQuery($tables['xf_image_proxy']);
		$this->executeUpgradeQuery($tables['xf_link_proxy']);
		$this->executeUpgradeQuery($tables['xf_smilie_category']);
		$this->executeUpgradeQuery($tables['xf_bb_code']);
		$this->executeUpgradeQuery($tables['xf_user_change_log']);
		$this->executeUpgradeQuery($tables['xf_user_change_temp']);
		$this->executeUpgradeQuery($tables['xf_spam_trigger_log']);

		// the table storage is different - just replace it
		$this->executeUpgradeQuery("DROP TABLE xf_registration_spam_cache");
		$this->executeUpgradeQuery($tables['xf_registration_spam_cache']);

		$this->executeUpgradeQuery("
			ALTER TABLE xf_user
				CHANGE user_state user_state ENUM('valid', 'email_confirm', 'email_confirm_edit', 'moderated', 'email_bounce') NOT NULL DEFAULT 'valid'
		");

		$this->executeUpgradeQuery("
			ALTER TABLE xf_user_field ADD moderator_editable TINYINT UNSIGNED NOT NULL DEFAULT '0'
		");

		$this->executeUpgradeQuery("
			ALTER TABLE xf_phrase CHANGE title title VARBINARY(100) NOT NULL
		");

		$this->executeUpgradeQuery("
			ALTER TABLE xf_session_activity
				DROP INDEX view_date,
				ADD INDEX view_date (view_date) USING BTREE
		");

		// smilie table enhancements
		$this->executeUpgradeQuery("
			ALTER TABLE xf_smilie
			  ADD smilie_category_id INT UNSIGNED NOT NULL DEFAULT 0,
			  ADD display_order INT UNSIGNED NOT NULL DEFAULT 1,
			  ADD display_in_editor TINYINT UNSIGNED NOT NULL DEFAULT 1,
			  ADD INDEX display_order (display_order)
		");
		$this->executeUpgradeQuery("UPDATE xf_smilie SET display_order = smilie_id");

		$this->applyGlobalPermission('profilePost', 'comment', 'profilePost', 'post', false);
		$this->applyGlobalPermission('conversation', 'receive', 'conversation', 'start', false);
		$this->executeUpgradeQuery("
			INSERT IGNORE INTO xf_permission_entry
				(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
			VALUES
				(2, 0, 'conversation', 'receive', 'allow', 0)
		");

		$this->executeUpgradeQuery("
			INSERT IGNORE INTO xf_content_type_field
				(content_type, field_name, field_value)
			VALUES
				('user', 'report_handler_class', 'XenForo_ReportHandler_User')
		");

		return true;
	}

	public function step2()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_session_activity
				CHANGE unique_key unique_key_old INT UNSIGNED NOT NULL,
				ADD unique_key VARBINARY(16) NOT NULL,
				CHANGE ip ip_old INT UNSIGNED NOT NULL DEFAULT 0,
				ADD ip VARBINARY(16) NOT NULL DEFAULT ''
		");
		$this->executeUpgradeQuery("
			UPDATE IGNORE xf_session_activity SET
				unique_key = IF(user_id, user_id, UNHEX(LPAD(HEX(ip_old), 8, '0'))),
				ip = UNHEX(LPAD(HEX(ip_old), 8, '0'))
		");
		$this->executeUpgradeQuery("
			ALTER IGNORE TABLE xf_session_activity
				DROP PRIMARY KEY,
				ADD PRIMARY KEY (user_id, unique_key),
				DROP unique_key_old,
				DROP ip_old
		");
	}

	public function step3()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_admin_log
				CHANGE ip_address ip_address_old INT UNSIGNED NOT NULL DEFAULT 0,
				ADD ip_address VARBINARY(16) NOT NULL DEFAULT ''
		");
		$this->executeUpgradeQuery("
			UPDATE xf_admin_log SET ip_address = UNHEX(LPAD(HEX(ip_address_old), 8, '0'))
		");
		$this->executeUpgradeQuery("
			ALTER TABLE xf_admin_log DROP ip_address_old
		");
	}

	public function step4()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_moderator_log
				CHANGE ip_address ip_address_old INT UNSIGNED NOT NULL DEFAULT 0,
				ADD ip_address VARBINARY(16) NOT NULL DEFAULT ''
		");
		$this->executeUpgradeQuery("
			UPDATE xf_moderator_log SET ip_address = UNHEX(LPAD(HEX(ip_address_old), 8, '0'))
		");
		$this->executeUpgradeQuery("
			ALTER TABLE xf_moderator_log DROP ip_address_old
		");
	}

	public function step5()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_error_log
				CHANGE ip_address ip_address_old INT UNSIGNED NOT NULL DEFAULT 0,
				ADD ip_address VARBINARY(16) NOT NULL DEFAULT ''
		");
		$this->executeUpgradeQuery("
			UPDATE xf_error_log SET ip_address = UNHEX(LPAD(HEX(ip_address_old), 8, '0'))
		");
		$this->executeUpgradeQuery("
			ALTER TABLE xf_error_log DROP ip_address_old
		");
	}

	public function step6()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_login_attempt
				CHANGE ip_address ip_address_old INT UNSIGNED NOT NULL,
				ADD ip_address VARBINARY(16) NOT NULL
		");
		$this->executeUpgradeQuery("
			UPDATE xf_login_attempt SET ip_address = UNHEX(LPAD(HEX(ip_address_old), 8, '0'))
		");
		$this->executeUpgradeQuery("
			ALTER TABLE xf_login_attempt
				DROP ip_address_old,
				ADD attempt_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
				DROP KEY login_check,
				ADD KEY login_check (login, ip_address, attempt_date)
		");
	}

	public function step7()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_ip_match
				CHANGE ip ip VARCHAR(43) NOT NULL,
				ADD first_byte BINARY(1) NOT NULL,
				CHANGE start_range start_range_old INT UNSIGNED NOT NULL,
				ADD start_range VARBINARY(16) NOT NULL,
				CHANGE end_range end_range_old INT UNSIGNED NOT NULL,
				ADD end_range VARBINARY(16) NOT NULL
		");
		$this->executeUpgradeQuery("
			UPDATE xf_ip_match SET
				first_byte = UNHEX(LPAD(HEX(first_octet), 2, '0')),
				start_range = UNHEX(LPAD(HEX(start_range_old), 8, '0')),
				end_range = UNHEX(LPAD(HEX(end_range_old), 8, '0'))
		");
		$this->executeUpgradeQuery("
			ALTER TABLE xf_ip_match
				DROP first_octet,
				DROP start_range_old,
				DROP end_range_old,
				DROP KEY start_range,
				ADD KEY start_range (start_range)
		");

		try
		{
			XenForo_Model::create('XenForo_Model_Banning')->rebuildBannedIpCache();
			XenForo_Model::create('XenForo_Model_Banning')->rebuildDiscouragedIpCache();
		}
		catch (Exception $e) {}
	}

	public function step8()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_ip
				CHANGE ip ip_old INT UNSIGNED NOT NULL,
				ADD ip VARBINARY(16) NOT NULL
		");
	}

	public function step9()
	{
		$this->executeUpgradeQuery("
			UPDATE xf_ip SET ip = UNHEX(LPAD(HEX(ip_old), 8, '0'))
		");
	}

	public function step10()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_ip
				DROP ip_old,
				DROP KEY ip_log_date,
				ADD KEY ip_log_date (ip, log_date)
		");
	}

	public function step11()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE `xf_warning_action`
				CHANGE `action` `action` VARBINARY( 25 ) NOT NULL ,
				CHANGE `ban_length_type` `action_length_type` VARBINARY( 25 ) NOT NULL ,
				CHANGE `ban_length` `action_length` SMALLINT( 5 ) UNSIGNED NOT NULL
		");
		$this->executeUpgradeQuery("
			ALTER TABLE  `xf_warning_action_trigger` CHANGE  `action`  `action` VARBINARY( 25 ) NOT NULL
		");
	}

	public function step12()
	{
		$this->executeUpgradeQuery("
			UPDATE xf_warning_action_trigger
			SET action = 'ban'
			WHERE action = 'ban_points'
		");
		$this->executeUpgradeQuery("
			INSERT IGNORE INTO xf_user_change_temp
				(user_id, change_key, action_type, action_modifier, new_value, old_value, create_date, expiry_date)
			SELECT user_id, CONCAT('warning_action_', warning_action_id, '_discourage'),
				'field', 'is_discouraged', '1', '0', action_date, NULL
			FROM xf_warning_action_trigger
			WHERE action = 'discourage'
		");
		$this->executeUpgradeQuery("
			INSERT IGNORE INTO xf_user_change_temp
				(user_id, change_key, action_type, action_modifier, new_value, old_value, create_date, expiry_date)
			SELECT user_id, CONCAT('warning_action_', warning_action_id, '_groups'),
				'groups', CONCAT('warning_action_', warning_action_id), '', '', action_date, NULL
			FROM xf_warning_action_trigger
			WHERE action = 'groups'
		");

		$this->executeUpgradeQuery("
			UPDATE xf_warning_action
			SET action_length_type = 'points', action_length = 0
			WHERE action IN ('ban_points', 'discourage', 'groups')
		");
		$this->executeUpgradeQuery("
			UPDATE xf_warning_action
			SET action = 'ban'
			WHERE action IN ('ban_length', 'ban_points')
		");
	}

	public function step13()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_user_profile
				DROP facebook_auth_id,
				ADD external_auth MEDIUMBLOB NOT NULL
		");
	}

	public function step14($position, array $stepData)
	{
		$perPage = 250;
		$db = $this->_getDb();

		if (!isset($stepData['max']))
		{
			$stepData['max'] = $db->fetchOne('SELECT MAX(user_id) FROM xf_user_external_auth');
		}

		$userIds = $db->fetchCol($db->limit(
			'
				SELECT DISTINCT user_id
				FROM xf_user_external_auth AS user
				WHERE user_id > ?
				ORDER BY user_id
			', $perPage
		), $position);
		if (!$userIds)
		{
			return true;
		}

		$queryResults = $db->query('
			SELECT *
			FROM xf_user_external_auth
			WHERE user_id IN (' . $db->quote($userIds) . ')
			ORDER BY user_id, provider
		');
		$authGrouped = array();
		while ($result = $queryResults->fetch())
		{
			$authGrouped[$result['user_id']][$result['provider']] = $result['provider_key'];
		}

		XenForo_Db::beginTransaction($db);

		foreach ($authGrouped AS $userId => $cache)
		{
			$db->query('
				UPDATE xf_user_profile SET
					external_auth = ?
				WHERE user_id = ?
			', array(serialize($cache), $userId));
		}

		XenForo_Db::commit($db);

		$nextPosition = end($userIds);

		return array(
			$nextPosition,
			"$nextPosition / $stepData[max]",
			$stepData
		);
	}

	public function step15()
	{
		$db = $this->_getDb();

		$values = $db->fetchOne("SELECT option_value FROM xf_option WHERE option_id = 'censorWords'");
		$values = @unserialize($values);

		$output = array();

		if ($values && is_array($values))
		{
			$oldFormat = false;

			if (!empty($values['exact']))
			{
				$oldFormat = true;

				foreach ($values['exact'] AS $word => $replace)
				{
					$cache = XenForo_Option_CensorWords::buildCensorCacheValue(
						$word, is_int($replace) ? '' : $replace
					);
					if ($cache)
					{
						$output[] = $cache;
					}
				}
			}
			if (!empty($values['any']))
			{
				$oldFormat = true;

				foreach ($values['any'] AS $word => $replace)
				{
					$word = '*' . $word . '*';
					$cache = XenForo_Option_CensorWords::buildCensorCacheValue(
						$word, is_int($replace) ? '' : $replace
					);
					if ($cache)
					{
						$output[] = $cache;
					}
				}
			}

			if (!$oldFormat)
			{
				// likely already converted
				$output = $values;
			}
		}

		$db->query("
			UPDATE xf_option
			SET option_value = ?
			WHERE option_id = 'censorWords'
		", array(serialize($output)));
	}

	public function step16()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_conversation_message
				ADD INDEX user_id (user_id),
				ADD INDEX message_date (message_date)
		");
	}

	public function step17()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_conversation_recipient
				ADD INDEX user_id (user_id)
		");
	}

	public function step18()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_thread
				ADD INDEX user_id (user_id),
				ADD INDEX post_date (post_date)
		");
	}

	public function step19()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_post
				ADD INDEX post_date (post_date)
		");
	}

	public function step20()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_attachment_data
				ADD INDEX upload_date (upload_date)
		");
	}

	public function step21()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_liked_content
				ADD INDEX like_date (like_date)
		");
	}

	public function step22()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_profile_post
				ADD INDEX post_date (post_date)
		");
	}

	public function step23()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_profile_post_comment
				ADD INDEX user_id (user_id),
				ADD INDEX comment_date (comment_date)
		");
	}

	public function step24()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_poll_vote
				ADD INDEX user_id (user_id)
		");
	}

	public function step25()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_report_comment
				ADD INDEX user_id (user_id)
		");
	}

	public function step26()
	{
		$this->executeUpgradeQuery("
			ALTER TABLE xf_notice_dismissed
				ADD INDEX user_id (user_id)
		");
	}

	public function step27()
	{
		// note: metacafe and liveleak do not support HTTPS at this time
		$this->executeUpgradeQuery("
			UPDATE xf_bb_code_media_site
			SET embed_html = REPLACE(embed_html, 'http:', 'https:')
			WHERE media_site_id IN ('facebook', 'vimeo', 'youtube', 'dailymotion')
		");

		$oldRegex = '#metacafe\\\\.com/watch/(?P' . '<id>\\\\d+)/#siU';
		$newRegex = '#metacafe\\\\.com/watch/(?P' . '<id>[a-z0-9-]+)(/|$)#siU';

		$this->executeUpgradeQuery("
			UPDATE xf_bb_code_media_site
			SET match_urls = IF(match_urls = ?, ?, match_urls),
				embed_html = REPLACE(embed_html, '{\$id:digits}', '{\$id}')
			WHERE media_site_id = 'metacafe'
		", array($oldRegex, $newRegex));
	}
}