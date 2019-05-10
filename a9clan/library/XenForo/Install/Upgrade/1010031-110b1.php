<?php

class XenForo_Install_Upgrade_1010031 extends XenForo_Install_Upgrade_Abstract
{
	public function getVersionName()
	{
		return '1.1.0 Beta 1';
	}

	public function step1()
	{
		$db = $this->_getDb();
		$tables = XenForo_Install_Data_MySql::getTables();
		$data = XenForo_Install_Data_MySql::getData();

		// new content types
		$db->query("
			INSERT IGNORE INTO xf_content_type
				(content_type, addon_id, fields)
			VALUES
				('attachment', 'XenForo', ''),
				('conversation_message', 'XenForo', '')
		");

		$db->query("
			INSERT IGNORE INTO xf_content_type_field
				(content_type, field_name, field_value)
			VALUES
				('attachment', 'stats_handler_class', 'XenForo_StatsHandler_Attachment'),
				('conversation', 'stats_handler_class', 'XenForo_StatsHandler_Conversation'),
				('conversation_message', 'report_handler_class', 'XenForo_ReportHandler_ConversationMessage'),
				('post', 'stats_handler_class', 'XenForo_StatsHandler_Post'),
				('thread', 'stats_handler_class', 'XenForo_StatsHandler_Thread'),
				('user', 'stats_handler_class', 'XenForo_StatsHandler_User'),
				('profile_post', 'stats_handler_class', 'XenForo_StatsHandler_ProfilePost'),

				('profile_post', 'search_handler_class', 'XenForo_Search_DataHandler_ProfilePost'),

				('post', 'moderator_log_handler_class', 'XenForo_ModeratorLogHandler_Post'),
				('thread', 'moderator_log_handler_class', 'XenForo_ModeratorLogHandler_Thread'),
				('profile_post', 'moderator_log_handler_class', 'XenForo_ModeratorLogHandler_ProfilePost'),

				('user', 'warning_handler_class', 'XenForo_WarningHandler_User'),
				('post', 'warning_handler_class', 'XenForo_WarningHandler_Post'),
				('profile_post', 'warning_handler_class', 'XenForo_WarningHandler_ProfilePost'),

				('conversation_message', 'attachment_handler_class', 'XenForo_AttachmentHandler_ConversationMessage')
		");

		$this->executeUpgradeQuery($tables['xf_stats_daily']);
		$this->executeUpgradeQuery($tables['xf_admin_log']);
		$this->executeUpgradeQuery($tables['xf_moderator_log']);

		// admin search
		$this->executeUpgradeQuery($tables['xf_admin_search_type']);
		$this->executeUpgradeQuery($data['xf_admin_search_type']);

		// misc
		$this->executeUpgradeQuery("ALTER TABLE xf_search ADD user_results MEDIUMBLOB NOT NULL AFTER search_grouping");
		$this->executeUpgradeQuery("ALTER TABLE xf_language ADD text_direction enum('LTR','RTL') NOT NULL DEFAULT 'LTR'");
		$this->executeUpgradeQuery("ALTER TABLE xf_trophy CHANGE criteria user_criteria MEDIUMBLOB NOT NULL");

		// new thread viewing permissions: insert for all groups that can view the board
		$this->applyGlobalPermission('forum', 'viewOthers', 'general', 'view', false);
		$this->applyGlobalPermission('forum', 'viewContent', 'general', 'view', false);

		// new conversation attachment permissions: insert for mods and admins only by default
		$db->query("
			INSERT IGNORE INTO xf_permission_entry
				(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
			VALUES
				(3, 0, 'conversation', 'uploadAttachment', 'allow', 0),
				(4, 0, 'conversation', 'uploadAttachment', 'allow', 0)
		");

		// user group promotions
		$this->executeUpgradeQuery($tables['xf_user_group_promotion']);
		$this->executeUpgradeQuery($tables['xf_user_group_promotion_log']);

		// notices
		$this->executeUpgradeQuery($tables['xf_notice']);
		$this->executeUpgradeQuery($tables['xf_notice_dismissed']);

		// custom user fields and ignore list
		$this->executeUpgradeQuery($tables['xf_user_field']);
		$this->executeUpgradeQuery($tables['xf_user_field_value']);
		$this->executeUpgradeQuery($tables['xf_user_ignored']);
		$this->executeUpgradeQuery("
			ALTER TABLE xf_user_profile
				ADD custom_fields MEDIUMBLOB NOT NULL,
				ADD ignored TEXT NOT NULL COMMENT 'Comma-separated integers from xf_user_ignored'
		");

		// conversation attachments
		$this->executeUpgradeQuery("ALTER TABLE xf_conversation_message ADD attach_count SMALLINT UNSIGNED NOT NULL DEFAULT 0");

		// bb code media site upgrades
		$this->executeUpgradeQuery("
			ALTER TABLE xf_bb_code_media_site
				ADD match_is_regex TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'If 1, match_urls will be treated as regular expressions rather than simple URL matches.' AFTER match_urls,
				ADD match_callback_class VARCHAR(75) NOT NULL DEFAULT '' AFTER match_is_regex,
				ADD match_callback_method VARCHAR(50) NOT NULL DEFAULT '' AFTER match_callback_class,
				ADD embed_html_callback_class VARCHAR(75) NOT NULL DEFAULT '' AFTER embed_html,
				ADD embed_html_callback_method VARCHAR(50) NOT NULL DEFAULT '' AFTER embed_html_callback_class,
				ADD addon_id VARCHAR(25) NOT NULL DEFAULT ''
		");

		$this->executeUpgradeQuery('
			INSERT INTO xf_bb_code_media_site
				(media_site_id, site_title, site_url, match_urls, embed_html, match_is_regex)
			VALUES
				(\'metacafe\', \'Metacafe\', \'http://www.metacafe.com\', \'#metacafe\\.com/watch/(?P<id>\\d+\/[a-z0-9_]+)/#siU\', \'<embed flashVars="playerVars=autoPlay=no"\n	src="http://www.metacafe.com/fplayer/{$id}.swf"\n	width="500" height="300" wmode="transparent"\n	allowFullScreen="true" allowScriptAccess="always"\n	pluginspage="http://www.macromedia.com/go/getflashplayer"\n	type="application/x-shockwave-flash">\n</embed>\', 1),
				(\'dailymotion\', \'Dailymotion\', \'http://www.dailymotion.com\', \'dailymotion.com/video/{$id:alphanum}\', \'<iframe frameborder="0" width="500" height="300" src="http://www.dailymotion.com/embed/video/{$id}?width=500&hideInfos=1"></iframe>\', 0),
				(\'liveleak\', \'Liveleak\', \'http://www.liveleak.com\', \'liveleak.com/view?i={$id}\', \'<object width="500" height="300">\n	<param name="movie" value="http://www.liveleak.com/e/{$id}"></param>\n	<param name="wmode" value="transparent"></param>\n	<param name="allowscriptaccess" value="always"></param>\n	<embed src="http://www.liveleak.com/e/{$id}" type="application/x-shockwave-flash" wmode="transparent" allowscriptaccess="always" width="500" height="300"></embed>\n</object>\', 0)
		');

		// basic warning stuff
		$this->executeUpgradeQuery($tables['xf_warning']);
		$this->executeUpgradeQuery($tables['xf_warning_definition']);
		$this->executeUpgradeQuery($tables['xf_warning_action']);
		$this->executeUpgradeQuery($tables['xf_warning_action_trigger']);

		// warning view based on viewing IPs
		$this->applyGlobalPermission('general', 'viewWarning', 'general', 'viewIps');

		// general give/manage warnings based on spam cleaner
		$this->applyGlobalPermission('general', 'warn', 'general', 'cleanSpam');
		$this->applyGlobalPermission('general', 'manageWarning', 'general', 'cleanSpam');

		// forum warning based on deleting posts
		$this->applyGlobalPermission('forum', 'warn', 'forum', 'deleteAnyPost');
		$this->applyContentPermission('forum', 'warn', 'forum', 'deleteAnyPost');

		// profile post warning based on deleting profile posts
		$this->applyGlobalPermission('profilePost', 'warn', 'profilePost', 'deleteAny');

		// default warnings
		$db->query("
			INSERT IGNORE INTO xf_warning_definition
				(warning_definition_id, points_default, expiry_type, expiry_default, extra_user_group_ids, is_editable)
			VALUES
				(1, 1, 'months', 1, '', 1),
				(2, 1, 'months', 1, '', 1),
				(3, 1, 'months', 1, '', 1),
				(4, 1, 'months', 1, '', 1)
		");

		$db->query("
			INSERT IGNORE INTO xf_phrase
				(language_id, title, phrase_text, global_cache, addon_id)
			VALUES
				(0, 'warning_definition_1_title', 'Inappropriate Content', 0, ''),
				(0, 'warning_definition_1_conversation_title', 'Inappropriate Content', 0, ''),
				(0, 'warning_definition_1_conversation_text', '{name},\n\nYour message ([url={url}]{title}[/url]) contains inappropriate content. Please do not discuss content of this nature on our site. This does not follow our rules. Your message may have been removed or altered.\n\nYour account''s access may be limited based on these actions. Please keep this in mind when posting or using our site.', 0, ''),
				(0, 'warning_definition_2_title', 'Inappropriate Behavior', 0, ''),
				(0, 'warning_definition_2_conversation_title', 'Inappropriate Behavior', 0, ''),
				(0, 'warning_definition_2_conversation_text', '{name},\n\nYour actions in this message ([url={url}]{title}[/url]) are not appropriate. We cannot allow users to be abusive, overly aggressive, threatening, or to \"troll\". This does not follow our rules. Your message may have been removed or altered.\n\nYour account''s access may be limited based on these actions. Please keep this in mind when posting or using our site.', 0, ''),
				(0, 'warning_definition_3_title', 'Inappropriate Language', 0, ''),
				(0, 'warning_definition_3_conversation_title', 'Inappropriate Language', 0, ''),
				(0, 'warning_definition_3_conversation_text', '{name},\n\nYour message ([url={url}]{title}[/url]) contains inappropriate language. This does not follow our rules. Your message may have been removed or altered.\n\nYour account''s access may be limited based on these actions. Please keep this in mind when posting or using our site.', 0, ''),
				(0, 'warning_definition_4_title', 'Inappropriate Advertising / Spam', 0, ''),
				(0, 'warning_definition_4_conversation_title', 'Inappropriate Advertising / Spam', 0, ''),
				(0, 'warning_definition_4_conversation_text', '{name},\n\nYour message ([url={url}]{title}[/url]) contains inappropriate advertising or spam. This does not follow our rules. Your message may have been removed or altered.\n\nYour account''s access may be limited based on these actions. Please keep this in mind when posting or using our site.', 0, '')
		");

		// smilie sprite mode
		$this->executeUpgradeQuery("
			ALTER TABLE xf_smilie
				ADD sprite_mode TINYINT UNSIGNED NOT NULL DEFAULT 0,
				ADD sprite_params TEXT NOT NULL
		");

		return true;
	}

	public function step2()
	{
		$db = $this->_getDb();
		$tables = XenForo_Install_Data_MySql::getTables();

		// thread prefixes, find new threads
		$this->executeUpgradeQuery($tables['xf_forum_prefix']);
		$this->executeUpgradeQuery($tables['xf_thread_prefix']);
		$this->executeUpgradeQuery($tables['xf_thread_prefix_group']);

		$this->executeUpgradeQuery("
			ALTER TABLE xf_forum
  				ADD count_messages TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'If not set, messages posted (directly) within this forum will not contribute to user message totals.',
				ADD find_new TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Include posts from this forum when running /find-new/threads',
				ADD prefix_cache MEDIUMBLOB NOT NULL COMMENT 'Serialized data from xf_forum_prefix, [group_id][prefix_id] => prefix_id',
				ADD default_prefix_id INT UNSIGNED NOT NULL DEFAULT 0
		");

		$this->executeUpgradeQuery("ALTER TABLE xf_thread ADD prefix_id INT UNSIGNED NOT NULL DEFAULT 0");

		return true;
	}

	public function step3()
	{
		// additional warning parts and useful index
		$this->executeUpgradeQuery('ALTER TABLE xf_user ADD warning_points INT UNSIGNED NOT NULL DEFAULT 0');

		$this->executeUpgradeQuery('
			ALTER TABLE xf_profile_post
				ADD warning_id INT UNSIGNED NOT NULL DEFAULT 0,
				ADD warning_message VARCHAR(255) NOT NULL DEFAULT \'\',
				ADD INDEX user_id (user_id)
		');

		return true;
	}

	public function step4()
	{
		// additional warning parts - the biggest query, add useful index
		$this->executeUpgradeQuery('
			ALTER TABLE xf_post
				ADD warning_id INT UNSIGNED NOT NULL DEFAULT 0,
				ADD warning_message VARCHAR(255) NOT NULL DEFAULT \'\',
				ADD INDEX user_id (user_id)
		');

		return true;
	}

	public function step5()
	{
		$db = $this->_getDb();

		if (!$this->executeUpgradeQuery('SELECT 1 FROM xf_identity_service LIMIT 1'))
		{
			return true; // data already removed
		}

		$identities = $db->fetchAll("
			SELECT ident.identity_service_id,
				title.phrase_text AS title,
				hint.phrase_text AS hint
			FROM xf_identity_service AS ident
			LEFT JOIN xf_phrase AS title ON
				(title.language_id = 0 AND title.title = CONCAT('identity_service_name_', ident.identity_service_id))
			LEFT JOIN xf_phrase AS hint ON
				(title.language_id = 0 AND title.title = CONCAT('identity_service_hint_', ident.identity_service_id))
		");

		XenForo_Db::beginTransaction($db);

		$displayOrder = 0;
		foreach ($identities AS $identity)
		{
			$displayOrder += 10;
			$fieldId = $identity['identity_service_id'];

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserField', XenForo_DataWriter::ERROR_SILENT);
			$dw->setImportMode(true);
			$dw->bulkSet(array(
				'field_id' => $fieldId,
				'display_group' => 'contact',
				'display_order' => $displayOrder,
				'field_type' => 'textbox'
			));
			switch ($fieldId)
			{
				case 'aim':
					$dw->set('match_type', 'regex');
					$dw->set('match_regex', '^[a-zA-Z0-9@\. ]+$');
					$dw->set('max_length', '80');
					break;

				case 'msn':
					$dw->set('match_type', 'email');
					break;

				case 'icq':
					$dw->set('match_type', 'number');
					break;

				case 'skype':
					$dw->set('match_type', 'regex');
					$dw->set('match_regex', '^[a-zA-Z0-9-_\.,]{3,30}$');
					$dw->set('max_length', '30');
					break;

				case 'facebook':
					$dw->set('match_type', 'callback');
					$dw->set('match_callback_class', 'XenForo_Helper_UserField');
					$dw->set('match_callback_method', 'verifyFacebook');
					break;

				case 'twitter':
					$dw->set('match_type', 'callback');
					$dw->set('match_callback_class', 'XenForo_Helper_UserField');
					$dw->set('match_callback_method', 'verifyTwitter');
					break;
			}

			try
			{
				$saved = $dw->save();
			}
			catch (Exception $e)
			{
				$saved = false;
			}

			if ($saved)
			{
				$db->query("
					INSERT INTO xf_phrase
						(language_id, title, phrase_text, global_cache, addon_id)
					VALUES
						(0, ?, ?, 1, ''),
						(0, ?, ?, 0, '')
				", array(
					"user_field_$fieldId", strval($identity['title']),
					"user_field_{$fieldId}_desc", strval($identity['hint']),
				));
			}
		}

		XenForo_Db::commit($db);

		return true;
	}

	public function step6($position, array $stepData)
	{
		// convert identity services to fields
		$perPage = 250;

		$db = $this->_getDb();

		if (!$this->executeUpgradeQuery('SELECT 1 FROM xf_identity_service LIMIT 1'))
		{
			return true; // data already removed
		}

		if (!isset($stepData['max']))
		{
			$stepData['max'] = $db->fetchOne('SELECT MAX(user_id) FROM xf_user');
		}

		$userIds = $db->fetchCol($db->limit(
			'
				SELECT user_id
				FROM xf_user AS user
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
			FROM xf_user_identity
			WHERE user_id IN (' . $db->quote($userIds) . ')
		');
		$identitiesGrouped = array();
		while ($result = $queryResults->fetch())
		{
			$identitiesGrouped[$result['user_id']][$result['identity_service_id']] = $result['account_name'];
		}

		XenForo_Db::beginTransaction($db);

		foreach ($identitiesGrouped AS $userId => $identities)
		{
			$userIdQuoted = $db->quote($userId);
			$rows = array();
			foreach ($identities AS $fieldId => $value)
			{
				$rows[] = '(' . $userIdQuoted . ', ' . $db->quote($fieldId) . ', ' . $db->quote($value) . ')';
			}

			$db->query('
				INSERT INTO xf_user_field_value
					(user_id, field_id, field_value)
				VALUES
					' . implode(',', $rows) . '
				ON DUPLICATE KEY UPDATE
					field_value = VALUES(field_value)
			');
			$db->query('
				UPDATE xf_user_profile SET
					custom_fields = ?
				WHERE user_id = ?
			', array(serialize($identities), $userId));
		}

		XenForo_Db::commit($db);

		$nextPosition = end($userIds);

		return array(
			$nextPosition,
			"$nextPosition / $stepData[max]",
			$stepData
		);
	}

	public function step7()
	{
		$db = $this->_getDb();

		// remove identity services
		$this->executeUpgradeQuery('DROP TABLE xf_identity_service');
		$this->executeUpgradeQuery('DROP TABLE xf_user_identity');
		$this->executeUpgradeQuery('ALTER TABLE xf_user_profile DROP identities');
		$this->executeUpgradeQuery("DELETE FROM xf_phrase WHERE title LIKE 'identity_service_%'");

		// switch ident service admin perm to custom user fields
		$db->query("
			UPDATE IGNORE xf_admin_permission_entry SET
				admin_permission_id = 'userField'
			WHERE admin_permission_id = 'identityService'
		");

		return true;
	}
}