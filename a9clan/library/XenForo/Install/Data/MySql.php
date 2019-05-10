<?php

class XenForo_Install_Data_MySql
{
	public static function getTables()
	{
		$tables = array();

$tables['xf_addon'] = "
	CREATE TABLE xf_addon (
		addon_id VARBINARY(25) NOT NULL,
		title VARCHAR(75) NOT NULL,
		version_string VARCHAR(30) NOT NULL DEFAULT '',
		version_id INT UNSIGNED NOT NULL DEFAULT 0,
		url VARCHAR(100) NOT NULL,
		install_callback_class VARCHAR(75) NOT NULL DEFAULT '',
		install_callback_method VARCHAR(75) NOT NULL DEFAULT '',
		uninstall_callback_class VARCHAR(75) NOT NULL DEFAULT '',
		uninstall_callback_method VARCHAR(75) NOT NULL DEFAULT '',
		active TINYINT UNSIGNED NOT NULL,
		PRIMARY KEY (addon_id),
		KEY title (title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin'] = "
	CREATE TABLE xf_admin (
		user_id INT UNSIGNED NOT NULL,
		extra_user_group_ids VARBINARY(255) NOT NULL,
		last_login INT UNSIGNED NOT NULL DEFAULT 0,
		permission_cache MEDIUMBLOB,
		PRIMARY KEY (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_log'] = "
	CREATE TABLE xf_admin_log (
		admin_log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL,
		ip_address VARBINARY(16) NOT NULL DEFAULT '',
		request_date INT UNSIGNED NOT NULL,
		request_url TEXT NOT NULL,
		request_data MEDIUMBLOB NOT NULL,
		PRIMARY KEY (admin_log_id),
		KEY request_date (request_date),
		KEY user_id_request_date (user_id, request_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_navigation'] = "
	CREATE TABLE xf_admin_navigation (
		navigation_id VARBINARY(25) NOT NULL,
		parent_navigation_id VARBINARY(25) NOT NULL,
		display_order INT UNSIGNED NOT NULL DEFAULT 0,
		link VARCHAR(50) NOT NULL DEFAULT '',
		admin_permission_id VARBINARY(25) NOT NULL DEFAULT '',
		debug_only TINYINT UNSIGNED NOT NULL DEFAULT 0,
		hide_no_children TINYINT UNSIGNED NOT NULL DEFAULT 0,
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		PRIMARY KEY (navigation_id),
		KEY parent_navigation_id_display_order (parent_navigation_id, display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_permission'] = "
	CREATE TABLE xf_admin_permission (
		admin_permission_id VARBINARY(25) NOT NULL,
		display_order INT UNSIGNED NOT NULL DEFAULT 0,
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		PRIMARY KEY (admin_permission_id),
		KEY display_order (display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_permission_entry'] = "
	CREATE TABLE xf_admin_permission_entry (
		user_id INT(11) NOT NULL,
		admin_permission_id VARBINARY(25) NOT NULL,
		PRIMARY KEY (user_id, admin_permission_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_search_type'] = "
	CREATE TABLE xf_admin_search_type (
		search_type VARBINARY(25) NOT NULL,
		handler_class VARCHAR(50) NOT NULL,
		display_order INT UNSIGNED NOT NULL,
		PRIMARY KEY (search_type)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_template'] = "
	CREATE TABLE xf_admin_template (
		template_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		title VARBINARY(50) NOT NULL,
		template MEDIUMTEXT NOT NULL COMMENT 'User-editable HTML and template syntax',
		template_parsed MEDIUMBLOB NOT NULL,
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		UNIQUE KEY title (title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_template_compiled'] = "
	CREATE TABLE xf_admin_template_compiled (
		language_id INT UNSIGNED NOT NULL,
		title VARBINARY(50) NOT NULL,
		template_compiled MEDIUMBLOB NOT NULL COMMENT 'Executable PHP code built by template compiler',
		PRIMARY KEY (language_id, title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_template_include'] = "
	CREATE TABLE xf_admin_template_include (
		source_id INT UNSIGNED NOT NULL,
		target_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (source_id, target_id),
		KEY target (target_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_template_modification'] = "
	CREATE TABLE `xf_admin_template_modification` (
		`modification_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`addon_id` VARBINARY(25) NOT NULL,
		`template` VARBINARY(50) NOT NULL,
		`modification_key` VARBINARY(50) NOT NULL,
		`description` varchar(255) NOT NULL,
		`execution_order` int(10) unsigned NOT NULL,
		`enabled` tinyint(3) unsigned NOT NULL,
		`action` varchar(25) NOT NULL,
		`find` text NOT NULL,
		`replace` text NOT NULL,
		PRIMARY KEY (`modification_id`),
		UNIQUE KEY `modification_key` (`modification_key`),
		KEY `addon_id` (`addon_id`),
		KEY `template_order` (`template`,`execution_order`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_template_modification_log'] = "
	CREATE TABLE `xf_admin_template_modification_log` (
		`template_id` int(10) unsigned NOT NULL,
		`modification_id` int(10) unsigned NOT NULL,
		`status` varchar(25) NOT NULL,
		`apply_count` int(10) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`template_id`,`modification_id`),
		KEY `modification_id` (`modification_id`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_admin_template_phrase'] = "
	CREATE TABLE xf_admin_template_phrase (
		template_id INT UNSIGNED NOT NULL,
		phrase_title VARBINARY(100) NOT NULL,
		PRIMARY KEY (template_id, phrase_title),
		KEY phrase_title (phrase_title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_attachment'] = "
	CREATE TABLE xf_attachment (
		attachment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		data_id INT UNSIGNED NOT NULL,
		content_type VARBINARY(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		attach_date INT UNSIGNED NOT NULL,
		temp_hash VARCHAR(32) NOT NULL DEFAULT '',
		unassociated TINYINT UNSIGNED NOT NULL,
		view_count INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (attachment_id),
		KEY content_type_id_date (content_type, content_id, attach_date),
		KEY temp_hash_attach_date (temp_hash, attach_date),
		KEY unassociated_attach_date (unassociated, attach_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_attachment_data'] = "
	CREATE TABLE xf_attachment_data (
		data_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL,
		upload_date INT UNSIGNED NOT NULL,
		filename VARCHAR(100) NOT NULL,
		file_size INT UNSIGNED NOT NULL,
		file_hash VARCHAR(32) NOT NULL,
		width INT UNSIGNED NOT NULL DEFAULT '0',
		height INT UNSIGNED NOT NULL DEFAULT '0',
		thumbnail_width INT UNSIGNED NOT NULL DEFAULT '0',
		thumbnail_height INT UNSIGNED NOT NULL DEFAULT '0',
		attach_count INT UNSIGNED NOT NULL DEFAULT '0',
		PRIMARY KEY (data_id),
		KEY user_id_upload_date (user_id, upload_date),
		KEY attach_count (attach_count),
		KEY upload_date (upload_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_attachment_view'] = "
	CREATE TABLE xf_attachment_view (
		attachment_id INT UNSIGNED NOT NULL,
		KEY attachment_id (attachment_id)
	) ENGINE = MEMORY CHARACTER SET utf8 COLLATE utf8_general_ci
";


$tables['xf_ban_email'] = "
	CREATE TABLE xf_ban_email (
		banned_email VARCHAR(120) NOT NULL,
		PRIMARY KEY (banned_email)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_bb_code'] = "
	CREATE TABLE xf_bb_code (
		`bb_code_id` varbinary(25) NOT NULL,
		`bb_code_mode` varchar(25) NOT NULL,
		`has_option` varchar(25) NOT NULL,
		`replace_html` text NOT NULL,
		`replace_html_email` text NOT NULL,
		`replace_text` text NOT NULL,
		`callback_class` varchar(75) NOT NULL DEFAULT '',
		`callback_method` varchar(50) NOT NULL DEFAULT '',
		`option_regex` text NOT NULL,
		`trim_lines_after` tinyint(3) unsigned NOT NULL DEFAULT '0',
		`plain_children` tinyint(3) unsigned NOT NULL DEFAULT '0',
		`disable_smilies` tinyint(3) unsigned NOT NULL DEFAULT '0',
		`disable_nl2br` tinyint(3) unsigned NOT NULL DEFAULT '0',
		`disable_autolink` tinyint(3) unsigned NOT NULL DEFAULT '0',
		`allow_empty` tinyint(3) unsigned NOT NULL DEFAULT '0',
		`allow_signature` tinyint(3) unsigned NOT NULL DEFAULT '1',
		`editor_icon_url` varchar(200) NOT NULL DEFAULT '',
		`sprite_mode` tinyint(3) unsigned NOT NULL DEFAULT '0',
		`sprite_params` blob NOT NULL,
		`example` text NOT NULL,
		`active` tinyint(3) unsigned NOT NULL DEFAULT '1',
		`addon_id` varbinary(25) NOT NULL DEFAULT '',
		PRIMARY KEY (`bb_code_id`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_bb_code_media_site'] = "
	CREATE TABLE xf_bb_code_media_site (
		media_site_id VARBINARY(25) NOT NULL,
		site_title VARCHAR(50) NOT NULL,
		site_url VARCHAR(100) NOT NULL DEFAULT '',
		match_urls TEXT NOT NULL,
		match_is_regex TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'If 1, match_urls will be treated as regular expressions rather than simple URL matches.',
		match_callback_class VARCHAR(75) NOT NULL DEFAULT '',
		match_callback_method VARCHAR(50) NOT NULL DEFAULT '',
		embed_html TEXT NOT NULL,
		embed_html_callback_class VARCHAR(75) NOT NULL DEFAULT '',
		embed_html_callback_method VARCHAR(50) NOT NULL DEFAULT '',
		supported TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'If 0, this media type will not be listed as available, but will still be usable.',
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		PRIMARY KEY (media_site_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_bb_code_parse_cache'] = "
	CREATE TABLE xf_bb_code_parse_cache (
		bb_code_parse_cache_id int(10) unsigned NOT NULL AUTO_INCREMENT,
		content_type VARBINARY(25) NOT NULL,
		content_id int(10) unsigned NOT NULL,
		parse_tree mediumblob NOT NULL,
		cache_version int(10) unsigned NOT NULL,
		cache_date int(10) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (bb_code_parse_cache_id),
		UNIQUE KEY content_type_id (content_type,content_id),
		KEY cache_version (cache_version),
		KEY cache_date (cache_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_captcha_question'] = "
	CREATE TABLE xf_captcha_question (
		captcha_question_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		question VARCHAR(250) NOT NULL,
		answers BLOB NOT NULL COMMENT 'Serialized array of possible correct answers.',
		active TINYINT UNSIGNED NOT NULL DEFAULT 1,
		PRIMARY KEY (captcha_question_id),
		KEY active (active)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_captcha_log'] = "
	CREATE TABLE xf_captcha_log (
		hash VARBINARY(40) NOT NULL,
		captcha_type VARCHAR(250) NOT NULL,
		captcha_data VARCHAR(250) NOT NULL,
		captcha_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (hash),
		KEY captcha_date (captcha_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_code_event'] = "
	CREATE TABLE xf_code_event (
		event_id VARBINARY(50) NOT NULL PRIMARY KEY,
		description TEXT NOT NULL,
		addon_id VARBINARY(25) NOT NULL DEFAULT ''
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_code_event_listener'] = "
	CREATE TABLE xf_code_event_listener (
		event_listener_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		event_id VARBINARY(50) NOT NULL,
		execute_order INT UNSIGNED NOT NULL,
		description TEXT NOT NULL,
		callback_class VARCHAR(75) NOT NULL,
		callback_method VARCHAR(50) NOT NULL,
		active TINYINT UNSIGNED NOT NULL,
		addon_id VARBINARY(25) NOT NULL,
		hint VARCHAR(255) NOT NULL DEFAULT '',
		PRIMARY KEY  (event_listener_id),
		KEY event_id_execute_order (event_id, execute_order),
		KEY addon_id_event_id (addon_id, event_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_content_spam_cache'] = "
	CREATE TABLE `xf_content_spam_cache` (
		`spam_cache_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`content_type` VARBINARY(25) NOT NULL,
		`content_id` int(10) unsigned NOT NULL,
		`spam_params` mediumblob NOT NULL,
		`insert_date` int(11) NOT NULL,
		PRIMARY KEY (`spam_cache_id`),
		UNIQUE KEY `content_type` (`content_type`,`content_id`),
		KEY `insert_date` (`insert_date`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";


$tables['xf_content_type'] = "
	CREATE TABLE xf_content_type (
		content_type VARBINARY(25) NOT NULL,
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		fields MEDIUMBLOB NOT NULL,
		PRIMARY KEY (content_type)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_content_type_field'] = "
	CREATE TABLE xf_content_type_field (
		content_type VARBINARY(25) NOT NULL,
		field_name VARBINARY(50) NOT NULL,
		field_value VARCHAR(75) NOT NULL,
		PRIMARY KEY (content_type, field_name),
		KEY field_name (field_name)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_conversation_master'] = "
	CREATE TABLE xf_conversation_master (
		conversation_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(150) NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		start_date INT UNSIGNED NOT NULL,
		open_invite TINYINT UNSIGNED NOT NULL DEFAULT 0,
		conversation_open TINYINT UNSIGNED NOT NULL DEFAULT 1,
		reply_count INT UNSIGNED NOT NULL DEFAULT 0,
		recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
		first_message_id INT UNSIGNED NOT NULL,
		last_message_date INT UNSIGNED NOT NULL,
		last_message_id INT UNSIGNED NOT NULL,
		last_message_user_id INT UNSIGNED NOT NULL,
		last_message_username VARCHAR(50) NOT NULL,
		recipients mediumblob NOT NULL,
		PRIMARY KEY (conversation_id),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_conversation_message'] = "
	CREATE TABLE xf_conversation_message (
		message_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		conversation_id INT UNSIGNED NOT NULL,
		message_date INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		message MEDIUMTEXT NOT NULL,
		attach_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		ip_id INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (message_id),
		KEY conversation_id_message_date (conversation_id, message_date),
		KEY message_date (message_date),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_conversation_recipient'] = "
	CREATE TABLE xf_conversation_recipient (
		conversation_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		recipient_state ENUM('active', 'deleted', 'deleted_ignored') NOT NULL,
		last_read_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (conversation_id, user_id),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_conversation_user'] = "
	CREATE TABLE xf_conversation_user (
		conversation_id INT UNSIGNED NOT NULL,
		owner_user_id INT UNSIGNED NOT NULL,
		is_unread TINYINT UNSIGNED NOT NULL,
		reply_count INT UNSIGNED NOT NULL,
		last_message_date INT UNSIGNED NOT NULL,
		last_message_id INT UNSIGNED NOT NULL,
		last_message_user_id INT UNSIGNED NOT NULL,
		last_message_username VARCHAR(50) NOT NULL,
		is_starred TINYINT UNSIGNED NOT NULL DEFAULT '0',
		PRIMARY KEY (conversation_id, owner_user_id),
		KEY owner_user_id_last_message_date (owner_user_id, last_message_date),
		KEY owner_user_id_is_unread (owner_user_id, is_unread),
		KEY owner_starred_date (owner_user_id, is_starred, last_message_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_cron_entry'] = "
	CREATE TABLE xf_cron_entry (
		entry_id VARBINARY(25) NOT NULL,
		cron_class VARCHAR(75) NOT NULL,
		cron_method VARCHAR(50) NOT NULL,
		run_rules MEDIUMBLOB NOT NULL,
		active TINYINT UNSIGNED NOT NULL,
		next_run INT UNSIGNED NOT NULL,
		addon_id VARBINARY(25) NOT NULL,
		PRIMARY KEY (entry_id),
		KEY active_next_run (active, next_run)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_data_registry'] = "
	CREATE TABLE xf_data_registry (
		data_key VARBINARY(25) NOT NULL PRIMARY KEY,
		data_value MEDIUMBLOB NOT NULL
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_deferred'] = "
	CREATE TABLE xf_deferred (
		deferred_id int(10) unsigned NOT NULL auto_increment,
		unique_key VARBINARY(50) default NULL,
		execute_class varchar(75) NOT NULL,
		execute_data mediumblob NOT NULL,
		manual_execute tinyint(4) NOT NULL,
		trigger_date int(11) NOT NULL,
		PRIMARY KEY  (deferred_id),
		UNIQUE KEY unique_key (unique_key),
		KEY trigger_date (trigger_date),
		KEY manual_execute_date (manual_execute,trigger_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_deletion_log'] = "
	CREATE TABLE xf_deletion_log (
		content_type VARBINARY(25) NOT NULL,
		content_id INT(11) NOT NULL,
		delete_date INT(11) NOT NULL,
		delete_user_id INT(11) NOT NULL,
		delete_username VARCHAR(50) NOT NULL,
		delete_reason VARCHAR(100) NOT NULL DEFAULT '',
		PRIMARY KEY (content_type, content_id),
		KEY delete_user_id_date (delete_user_id, delete_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_draft'] = "
	CREATE TABLE `xf_draft` (
		`draft_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`draft_key` VARBINARY(75) NOT NULL,
		`user_id` int(10) unsigned NOT NULL,
		`last_update` int(10) unsigned NOT NULL,
		`message` mediumtext NOT NULL,
		`extra_data` mediumblob NOT NULL,
		PRIMARY KEY (`draft_id`),
		UNIQUE KEY `draft_key_user` (`draft_key`,`user_id`),
		KEY `last_update` (`last_update`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_edit_history'] = "
	CREATE TABLE xf_edit_history (
		edit_history_id int(10) unsigned NOT NULL auto_increment,
		content_type VARBINARY(25) NOT NULL,
		content_id int(10) unsigned NOT NULL,
		edit_user_id int(10) unsigned NOT NULL,
		edit_date int(10) unsigned NOT NULL,
		old_text mediumtext NOT NULL,
		PRIMARY KEY  (edit_history_id),
		KEY content_type (content_type,content_id,edit_date),
		KEY edit_date (edit_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_email_bounce_log'] = "
	CREATE TABLE xf_email_bounce_log (
		`bounce_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`log_date` int(10) unsigned NOT NULL,
		`email_date` int(10) unsigned NOT NULL,
		`message_type` varchar(25) NOT NULL,
		`action_taken` varchar(25) NOT NULL,
		`user_id` int(10) unsigned DEFAULT NULL,
		`recipient` varchar(255) DEFAULT NULL,
		`raw_message` mediumblob NOT NULL,
		`status_code` varchar(25) DEFAULT NULL,
		`diagnostic_info` text,
		PRIMARY KEY (`bounce_id`),
		KEY `log_date` (`log_date`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_email_bounce_soft'] = "
	CREATE TABLE xf_email_bounce_soft (
		`bounce_soft_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`user_id` int(10) unsigned NOT NULL,
		`bounce_date` date NOT NULL,
		`bounce_total` smallint(5) unsigned NOT NULL,
		PRIMARY KEY (`bounce_soft_id`),
		UNIQUE KEY `user_id` (`user_id`,`bounce_date`),
		KEY `bounce_date` (`bounce_date`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_email_template'] = "
	CREATE TABLE xf_email_template (
		template_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARBINARY(50) NOT NULL,
		custom TINYINT UNSIGNED NOT NULL,
		subject MEDIUMTEXT NOT NULL COMMENT 'User-editable subject with template syntax',
		subject_parsed MEDIUMBLOB NOT NULL,
		body_text MEDIUMTEXT NOT NULL COMMENT 'User-editable plain text body with template syntax',
		body_text_parsed MEDIUMBLOB NOT NULL,
		body_html MEDIUMTEXT NOT NULL COMMENT 'User-editable HTML body t with template syntax',
		body_html_parsed MEDIUMBLOB NOT NULL,
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		PRIMARY KEY (template_id),
		UNIQUE KEY title_custom (title, custom)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_email_template_compiled'] = "
	CREATE TABLE xf_email_template_compiled (
		language_id INT UNSIGNED NOT NULL,
		title VARBINARY(50) NOT NULL,
		template_compiled MEDIUMBLOB NOT NULL COMMENT 'Executable PHP code from compilation. Outputs 3 vars.',
		PRIMARY KEY (title, language_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_email_template_modification'] = "
	CREATE TABLE `xf_email_template_modification` (
		`modification_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`addon_id` VARBINARY(25) NOT NULL,
		`template` VARBINARY(50) NOT NULL,
		`modification_key` VARBINARY(50) NOT NULL,
		`description` varchar(255) NOT NULL,
		`execution_order` int(10) unsigned NOT NULL,
		`enabled` tinyint(3) unsigned NOT NULL,
		`search_location` varchar(25) NOT NULL,
		`action` varchar(25) NOT NULL,
		`find` text NOT NULL,
		`replace` text NOT NULL,
		PRIMARY KEY (`modification_id`),
		UNIQUE KEY `modification_key` (`modification_key`),
		KEY `addon_id` (`addon_id`),
		KEY `template_order` (`template`,`execution_order`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_email_template_modification_log'] = "
	CREATE TABLE `xf_email_template_modification_log` (
		`template_id` int(10) unsigned NOT NULL,
		`modification_id` int(10) unsigned NOT NULL,
		`status` varchar(25) NOT NULL,
		`apply_count` int(10) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`template_id`,`modification_id`),
		KEY `modification_id` (`modification_id`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_email_template_phrase'] = "
	CREATE TABLE xf_email_template_phrase (
		title VARBINARY(50) NOT NULL,
		phrase_title VARBINARY(100) NOT NULL,
		PRIMARY KEY (title, phrase_title),
		KEY phrase_title (phrase_title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_error_log'] = "
	CREATE TABLE xf_error_log (
		error_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		exception_date INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED DEFAULT NULL,
		ip_address VARBINARY(16) NOT NULL DEFAULT '',
		exception_type VARCHAR(75) NOT NULL,
		message TEXT NOT NULL,
		filename VARCHAR(255) NOT NULL,
		line INT UNSIGNED NOT NULL,
		trace_string MEDIUMTEXT NOT NULL,
		request_state MEDIUMBLOB NOT NULL,
		PRIMARY KEY (error_id),
		KEY exception_date (exception_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_feed'] = "
	CREATE TABLE xf_feed (
		feed_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(250) NOT NULL,
		url VARCHAR(2083) NOT NULL,
		frequency INT UNSIGNED NOT NULL DEFAULT 1800,
		node_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL DEFAULT 0,
		prefix_id INT UNSIGNED NOT NULL DEFAULT 0,
		title_template VARCHAR(250) NOT NULL DEFAULT '',
		message_template MEDIUMTEXT NOT NULL,
		discussion_visible TINYINT UNSIGNED NOT NULL DEFAULT 1,
		discussion_open TINYINT UNSIGNED NOT NULL DEFAULT 1,
		discussion_sticky TINYINT UNSIGNED NOT NULL DEFAULT 0,
		last_fetch INT UNSIGNED NOT NULL DEFAULT 0,
		active INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (feed_id),
		KEY active (active)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_feed_log'] = "
	CREATE TABLE xf_feed_log (
		feed_id INT UNSIGNED NOT NULL,
		unique_id VARCHAR(250) NOT NULL,
		hash CHAR(32) NOT NULL COMMENT 'MD5(title + content)',
		thread_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (feed_id,unique_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";


$tables['xf_flood_check'] = "
	CREATE TABLE xf_flood_check (
		user_id INT UNSIGNED NOT NULL,
		flood_action VARCHAR(25) NOT NULL,
		flood_time INT UNSIGNED NOT NULL,
		PRIMARY KEY (user_id, flood_action),
		KEY flood_time (flood_time)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_forum'] = "
	CREATE TABLE xf_forum (
		node_id INT UNSIGNED NOT NULL PRIMARY KEY,
		discussion_count INT UNSIGNED NOT NULL DEFAULT 0,
		message_count INT UNSIGNED NOT NULL DEFAULT 0,
		last_post_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Most recent post_id',
		last_post_date INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Date of most recent post',
		last_post_user_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'User_id of user posting most recently',
		last_post_username VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Username of most recently-posting user',
		last_thread_title VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'Title of thread most recent post is in',
		moderate_threads TINYINT UNSIGNED NOT NULL DEFAULT 0,
		moderate_replies TINYINT UNSIGNED NOT NULL DEFAULT 0,
		allow_posting TINYINT UNSIGNED NOT NULL DEFAULT 1,
		allow_poll TINYINT UNSIGNED NOT NULL DEFAULT 1,
  		count_messages TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'If not set, messages posted (directly) within this forum will not contribute to user message totals.',
		find_new TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Include posts from this forum when running /find-new/threads',
		prefix_cache MEDIUMBLOB NOT NULL COMMENT 'Serialized data from xf_forum_prefix, [group_id][prefix_id] => prefix_id',
		default_prefix_id INT UNSIGNED NOT NULL DEFAULT 0,
		default_sort_order VARCHAR(25) NOT NULL DEFAULT 'last_post_date',
		default_sort_direction VARCHAR(5) NOT NULL DEFAULT 'desc',
		list_date_limit_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		require_prefix TINYINT UNSIGNED NOT NULL DEFAULT '0',
		allowed_watch_notifications VARCHAR(10) NOT NULL DEFAULT 'all'
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_forum_prefix'] = "
	CREATE TABLE xf_forum_prefix (
		node_id INT UNSIGNED NOT NULL,
		prefix_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (node_id, prefix_id),
		KEY prefix_id (prefix_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_forum_read'] = "
	CREATE TABLE xf_forum_read (
		forum_read_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		user_id INT UNSIGNED NOT NULL,
		node_id INT UNSIGNED NOT NULL,
		forum_read_date INT UNSIGNED NOT NULL,
		UNIQUE KEY user_id_node_id (user_id, node_id),
		KEY node_id (node_id),
		KEY forum_read_date (forum_read_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_forum_watch'] = "
	CREATE TABLE xf_forum_watch (
		`user_id` int(10) unsigned NOT NULL,
		`node_id` int(10) unsigned NOT NULL,
		`notify_on` enum('','thread','message') NOT NULL,
		`send_alert` tinyint(3) unsigned NOT NULL,
		`send_email` tinyint(3) unsigned NOT NULL,
		PRIMARY KEY (`user_id`,`node_id`),
		KEY `node_id_notify_on` (`node_id`,`notify_on`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_help_page'] = "
	CREATE TABLE xf_help_page (
		`page_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`page_name` varchar(50) NOT NULL,
		`display_order` int(10) unsigned NOT NULL DEFAULT '0',
		`callback_class` varchar(75) NOT NULL DEFAULT '',
		`callback_method` varchar(75) NOT NULL DEFAULT '',
		PRIMARY KEY (`page_id`),
		UNIQUE KEY `page_name` (`page_name`),
		KEY `display_order` (`display_order`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_image_proxy'] = "
	CREATE TABLE `xf_image_proxy` (
	    `image_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`url` text NOT NULL,
		`url_hash` varbinary(32) NOT NULL,
		`file_size` int(10) unsigned NOT NULL DEFAULT '0',
		`file_name` varchar(250) NOT NULL DEFAULT '',
		`mime_type` varchar(100) NOT NULL DEFAULT '',
		`fetch_date` int(10) unsigned NOT NULL DEFAULT '0',
		`first_request_date` int(10) unsigned NOT NULL DEFAULT '0',
		`last_request_date` int(10) unsigned NOT NULL DEFAULT '0',
		`views` int(10) unsigned NOT NULL DEFAULT '0',
		`pruned` int(10) unsigned NOT NULL DEFAULT '0',
		`is_processing` int(10) unsigned NOT NULL DEFAULT '0',
		`failed_date` int(10) unsigned NOT NULL DEFAULT '0',
		`fail_count` smallint(5) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`image_id`),
		UNIQUE KEY `url_hash` (`url_hash`),
		KEY `pruned_fetch_date` (`pruned`,`fetch_date`),
		KEY last_request_date (last_request_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_import_log'] = "
	CREATE TABLE xf_import_log (
		content_type VARBINARY(25) NOT NULL,
		old_id VARBINARY(50) NOT NULL,
		new_id VARBINARY(50) NOT NULL,
		PRIMARY KEY (content_type, old_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_ip'] = "
	CREATE TABLE xf_ip (
		ip_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL,
		content_type varbinary(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		action varbinary(25) NOT NULL DEFAULT '',
		ip VARBINARY(16) NOT NULL,
		log_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (ip_id),
		KEY user_id_log_date (user_id, log_date),
		KEY ip_log_date (ip, log_date),
		KEY content_type_content_id (content_type, content_id),
		KEY log_date (log_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_ip_match'] = "
	CREATE TABLE xf_ip_match (
		ip VARCHAR(43) NOT NULL,
		match_type ENUM('banned','discouraged') NOT NULL DEFAULT 'banned',
		first_byte BINARY(1) NOT NULL,
		start_range VARBINARY(16) NOT NULL,
		end_range VARBINARY(16) NOT NULL,
		PRIMARY KEY (ip, match_type),
		KEY start_range (start_range)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_language'] = "
	CREATE TABLE xf_language (
		language_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		parent_id INT UNSIGNED NOT NULL,
		parent_list VARBINARY(100) NOT NULL,
		title VARCHAR(50) NOT NULL,
		date_format VARCHAR(30) NOT NULL,
		time_format VARCHAR(15) NOT NULL,
		decimal_point VARCHAR(1) NOT NULL,
		thousands_separator VARCHAR(1) NOT NULL,
		phrase_cache MEDIUMBLOB NOT NULL,
		language_code VARCHAR(25) NOT NULL DEFAULT '',
		text_direction ENUM('LTR','RTL') NOT NULL DEFAULT 'LTR',
		PRIMARY KEY (language_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_liked_content'] = "
	CREATE TABLE xf_liked_content (
		like_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		content_type VARBINARY(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		like_user_id INT UNSIGNED NOT NULL,
		like_date INT UNSIGNED NOT NULL,
		content_user_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (like_id),
		UNIQUE KEY content_type_id_like_user_id (content_type, content_id, like_user_id),
		KEY like_user_content_type_id (like_user_id, content_type, content_id),
		KEY content_user_id_like_date (content_user_id, like_date),
		KEY like_date (like_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_link_proxy'] = "
	CREATE TABLE `xf_link_proxy` (
		`link_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`url` text NOT NULL,
		`url_hash` varbinary(32) NOT NULL,
		`first_request_date` int(10) unsigned NOT NULL DEFAULT '0',
		`last_request_date` int(10) unsigned NOT NULL DEFAULT '0',
		`hits` int(10) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`link_id`),
		UNIQUE KEY `url_hash` (`url_hash`),
		KEY last_request_date (last_request_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_link_forum'] = "
	CREATE TABLE xf_link_forum (
		node_id INT UNSIGNED NOT NULL,
		link_url VARCHAR(150) NOT NULL,
		redirect_count INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (node_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_login_attempt'] = "
	CREATE TABLE xf_login_attempt (
		attempt_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		login VARCHAR(60) NOT NULL,
		ip_address VARBINARY(16) NOT NULL ,
		attempt_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (attempt_id),
		KEY login_check (login, ip_address, attempt_date),
		KEY attempt_date (attempt_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_mail_queue'] = "
	CREATE TABLE `xf_mail_queue` (
		`mail_queue_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`mail_data` mediumblob NOT NULL,
		`queue_date` int(10) unsigned NOT NULL,
		PRIMARY KEY (`mail_queue_id`),
		KEY `queue_date` (`queue_date`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_moderation_queue'] = "
	CREATE TABLE xf_moderation_queue (
		content_type VARBINARY(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		content_date INT UNSIGNED NOT NULL DEFAULT '0',
		PRIMARY KEY (content_type, content_id),
		KEY content_date (content_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_moderator'] = "
	CREATE TABLE xf_moderator (
		user_id INT UNSIGNED NOT NULL,
		is_super_moderator TINYINT UNSIGNED NOT NULL,
		moderator_permissions MEDIUMBLOB NOT NULL,
		extra_user_group_ids VARBINARY(255) NOT NULL,
		PRIMARY KEY (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_moderator_content'] = "
	CREATE TABLE xf_moderator_content (
		moderator_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		content_type VARBINARY(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		moderator_permissions MEDIUMBLOB NOT NULL,
		PRIMARY KEY (moderator_id),
		UNIQUE KEY content_user_id (content_type, content_id, user_id),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_moderator_log'] = "
	CREATE TABLE xf_moderator_log (
		moderator_log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		log_date INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		ip_address VARBINARY(16) NOT NULL DEFAULT '',
		content_type VARBINARY(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		content_user_id INT UNSIGNED NOT NULL,
		content_username VARCHAR(50) NOT NULL,
		content_title VARCHAR(150) NOT NULL,
		content_url text NOT NULL,
		discussion_content_type VARCHAR(25) NOT NULL,
		discussion_content_id INT UNSIGNED NOT NULL,
		action VARCHAR(25) NOT NULL,
		action_params MEDIUMBLOB NOT NULL,
		PRIMARY KEY (moderator_log_id),
		KEY log_date (log_date),
		KEY content_type_id (content_type, content_id),
		KEY discussion_content_type_id (discussion_content_type, discussion_content_id),
		KEY user_id_log_date (user_id, log_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_news_feed'] = "
	CREATE TABLE xf_news_feed (
		news_feed_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL COMMENT 'The user who performed the action',
		username VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Corresponds to user_id',
		content_type VARBINARY(25) NOT NULL COMMENT 'eg: thread',
		content_id INT UNSIGNED NOT NULL,
		action VARCHAR(25) NOT NULL COMMENT 'eg: edit',
		event_date INT UNSIGNED NOT NULL,
		extra_data MEDIUMBLOB NOT NULL COMMENT 'Serialized. Stores any extra data relevant to the action',
		PRIMARY KEY (news_feed_id),
		KEY userId_eventDate (user_id, event_date),
		KEY contentType_contentId (content_type, content_id),
		KEY event_date (event_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_node'] = "
	CREATE TABLE xf_node (
		node_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(50) NOT NULL,
		description TEXT NOT NULL,
 		node_name VARCHAR(50) DEFAULT NULL COMMENT 'Unique column used as string ID by some node types',
		node_type_id VARBINARY(25) NOT NULL,
		parent_node_id INT UNSIGNED NOT NULL DEFAULT 0,
		display_order INT UNSIGNED NOT NULL DEFAULT 1,
		display_in_list TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'If 0, hidden from node list. Still counts for lft/rgt.',
		lft INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nested set info ''left'' value',
		rgt INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nested set info ''right'' value',
		depth INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Depth = 0: no parent',
		style_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Style override for specific node',
		effective_style_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Style override; pushed down tree',
		breadcrumb_data BLOB NULL DEFAULT NULL,
		PRIMARY KEY (node_id),
		UNIQUE KEY node_name_unique (node_name, node_type_id),
		KEY parent_node_id (parent_node_id),
		KEY display_order (display_order),
		KEY display_in_list (display_in_list, lft),
		KEY lft (lft)
	) ENGINE = InnoDB  DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_node_type'] = "
	CREATE TABLE xf_node_type (
		node_type_id VARBINARY(25) NOT NULL,
		handler_class VARCHAR(75) NOT NULL,
		controller_admin_class VARCHAR(75) NOT NULL COMMENT 'extends XenForo_ControllerAdmin_Abstract',
		datawriter_class VARCHAR(75) NOT NULL COMMENT 'extends XenForo_DataWriter_Node',
		permission_group_id VARCHAR(25) NOT NULL DEFAULT '',
		moderator_interface_group_id VARCHAR(50) NOT NULL DEFAULT '',
		public_route_prefix VARCHAR(25) NOT NULL,
		PRIMARY KEY (node_type_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_notice'] = "
	CREATE TABLE xf_notice (
		notice_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(150) NOT NULL,
		message MEDIUMTEXT NOT NULL,
		active TINYINT UNSIGNED NOT NULL DEFAULT 1,
		display_order INT UNSIGNED NOT NULL DEFAULT 0,
		dismissible TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Notice may be hidden when read by users',
		wrap TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Wrap this notice in div.noticeContent',
		user_criteria MEDIUMBLOB NOT NULL,
		page_criteria MEDIUMBLOB NOT NULL,
		PRIMARY KEY (notice_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_notice_dismissed'] = "
	CREATE TABLE xf_notice_dismissed (
		notice_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		dismiss_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (notice_id, user_id),
		KEY user_id (user_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_option'] = "
	CREATE TABLE xf_option (
		option_id VARBINARY(50) NOT NULL,
		option_value MEDIUMBLOB NOT NULL,
		default_value MEDIUMBLOB NOT NULL,
		edit_format ENUM('textbox','spinbox','onoff','radio','select','checkbox','template','callback','onofftextbox') NOT NULL,
		edit_format_params MEDIUMTEXT NOT NULL,
		data_type ENUM('string','integer','numeric','array','boolean','positive_integer','unsigned_integer','unsigned_numeric') NOT NULL,
		sub_options MEDIUMTEXT NOT NULL,
		can_backup TINYINT UNSIGNED NOT NULL,
		validation_class VARCHAR(75) NOT NULL,
		validation_method VARCHAR(50) NOT NULL,
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		PRIMARY KEY (option_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_option_group'] = "
	CREATE TABLE xf_option_group (
		group_id VARBINARY(50) NOT NULL,
		display_order INT UNSIGNED NOT NULL,
		debug_only TINYINT UNSIGNED NOT NULL,
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		PRIMARY KEY (group_id),
		KEY display_order (display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_option_group_relation'] = "
	CREATE TABLE xf_option_group_relation (
		option_id VARBINARY(50) NOT NULL,
		group_id VARBINARY(50) NOT NULL,
		display_order INT UNSIGNED NOT NULL,
		PRIMARY KEY (option_id,group_id),
		KEY group_id_display_order (group_id,display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_page'] = "
	CREATE TABLE xf_page (
		node_id INT UNSIGNED NOT NULL,
		publish_date INT UNSIGNED NOT NULL,
		modified_date INT UNSIGNED NOT NULL DEFAULT 0,
		view_count INT UNSIGNED NOT NULL DEFAULT 0,
		log_visits TINYINT UNSIGNED NOT NULL DEFAULT 0,
		list_siblings TINYINT UNSIGNED NOT NULL DEFAULT 0,
		list_children TINYINT UNSIGNED NOT NULL DEFAULT 0,
		callback_class VARCHAR(75) NOT NULL DEFAULT '',
		callback_method VARCHAR(75) NOT NULL DEFAULT '',
		PRIMARY KEY (node_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission'] = "
	CREATE TABLE xf_permission (
		permission_id VARBINARY(25) NOT NULL,
		permission_group_id VARBINARY(25) NOT NULL,
		permission_type ENUM('flag','integer') NOT NULL,
		interface_group_id VARBINARY(50) NOT NULL,
		depend_permission_id VARBINARY(25) NOT NULL,
		display_order INT UNSIGNED NOT NULL,
		default_value ENUM('allow','deny','unset') NOT NULL,
		default_value_int INT(11) NOT NULL,
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		PRIMARY KEY (permission_id, permission_group_id),
		KEY display_order (display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_cache_content'] = "
	CREATE TABLE xf_permission_cache_content (
		permission_combination_id INT UNSIGNED NOT NULL,
		content_type VARBINARY(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		cache_value MEDIUMBLOB NOT NULL,
		PRIMARY KEY (permission_combination_id, content_type, content_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_combination'] = "
	CREATE TABLE xf_permission_combination (
		permission_combination_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL,
		user_group_list MEDIUMBLOB NOT NULL,
		cache_value MEDIUMBLOB NOT NULL,
		PRIMARY KEY (permission_combination_id),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_combination_user_group'] = "
	CREATE TABLE xf_permission_combination_user_group (
		user_group_id INT UNSIGNED NOT NULL,
		permission_combination_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (user_group_id, permission_combination_id),
		KEY permission_combination_id (permission_combination_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_entry'] = "
	CREATE TABLE xf_permission_entry (
		permission_entry_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_group_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		permission_group_id VARBINARY(25) NOT NULL,
		permission_id VARBINARY(25) NOT NULL,
		permission_value ENUM('unset','allow','deny','use_int') NOT NULL,
		permission_value_int INT NOT NULL,
		PRIMARY KEY (permission_entry_id),
		UNIQUE KEY unique_permission (user_group_id, user_id, permission_group_id, permission_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_entry_content'] = "
	CREATE TABLE xf_permission_entry_content (
		permission_entry_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		content_type VARBINARY(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		user_group_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		permission_group_id VARBINARY(25) NOT NULL,
		permission_id VARBINARY(25) NOT NULL,
		permission_value ENUM('unset','reset','content_allow','deny','use_int') NOT NULL,
		permission_value_int INT NOT NULL,
		PRIMARY KEY (permission_entry_id),
		UNIQUE KEY user_group_id_unique (user_group_id, user_id, content_type, content_id, permission_group_id, permission_id),
		KEY content_type_content_id (content_type, content_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_group'] = "
	CREATE TABLE xf_permission_group (
		permission_group_id VARBINARY(25) NOT NULL,
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		PRIMARY KEY (permission_group_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_permission_interface_group'] = "
	CREATE TABLE xf_permission_interface_group (
		interface_group_id VARBINARY(50) NOT NULL,
		display_order INT UNSIGNED NOT NULL,
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		PRIMARY KEY (interface_group_id),
		KEY display_order (display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_phrase'] = "
	CREATE TABLE xf_phrase (
		phrase_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		language_id INT UNSIGNED NOT NULL,
		title VARBINARY(100) NOT NULL,
		phrase_text MEDIUMTEXT NOT NULL,
		global_cache TINYINT UNSIGNED NOT NULL DEFAULT '0',
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		version_id INT UNSIGNED NOT NULL DEFAULT 0,
		version_string VARCHAR(30) NOT NULL DEFAULT '',
		UNIQUE KEY title (title, language_id),
		KEY language_id_global_cache (language_id, global_cache)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_phrase_compiled'] = "
	CREATE TABLE xf_phrase_compiled (
		language_id INT UNSIGNED NOT NULL,
		title VARBINARY(100) NOT NULL,
		phrase_text MEDIUMTEXT NOT NULL,
		PRIMARY KEY (language_id, title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_phrase_map'] = "
	CREATE TABLE xf_phrase_map (
		phrase_map_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		language_id INT UNSIGNED NOT NULL,
		title VARBINARY(100) NOT NULL,
		phrase_id INT UNSIGNED NOT NULL,
		UNIQUE KEY language_id_title (language_id, title),
		KEY phrase_id (phrase_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_poll'] = "
	CREATE TABLE xf_poll (
		poll_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		content_type VARBINARY(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		question VARCHAR(100) NOT NULL,
		responses MEDIUMBLOB NOT NULL,
		voter_count INT UNSIGNED NOT NULL DEFAULT 0,
		public_votes TINYINT UNSIGNED NOT NULL DEFAULT 0,
		max_votes TINYINT UNSIGNED NOT NULL DEFAULT 1,
		close_date INT UNSIGNED NOT NULL DEFAULT 0,
		change_vote TINYINT UNSIGNED NOT NULL DEFAULT 0,
		view_results_unvoted TINYINT UNSIGNED NOT NULL DEFAULT 1,
		PRIMARY KEY (poll_id),
		UNIQUE KEY content_type_content_id (content_type, content_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_poll_response'] = "
	CREATE TABLE xf_poll_response (
		poll_response_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		poll_id INT UNSIGNED NOT NULL,
		response VARCHAR(100) NOT NULL,
		response_vote_count INT UNSIGNED NOT NULL DEFAULT 0,
		voters MEDIUMBLOB NOT NULL,
		PRIMARY KEY (poll_response_id),
		KEY poll_id_response_id (poll_id, poll_response_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_poll_vote'] = "
	CREATE TABLE xf_poll_vote (
		user_id INT UNSIGNED NOT NULL,
		poll_response_id INT UNSIGNED NOT NULL,
		poll_id INT UNSIGNED NOT NULL,
		vote_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (poll_response_id, user_id),
		KEY poll_id_user_id (poll_id, user_id),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_post'] = "
	CREATE TABLE xf_post (
		post_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		thread_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		post_date INT UNSIGNED NOT NULL,
		message MEDIUMTEXT NOT NULL,
		ip_id INT UNSIGNED NOT NULL DEFAULT 0,
		message_state ENUM('visible', 'moderated', 'deleted') NOT NULL DEFAULT 'visible',
		attach_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		position INT UNSIGNED NOT NULL,
		likes INT UNSIGNED NOT NULL DEFAULT 0,
		like_users BLOB NOT NULL,
		warning_id INT UNSIGNED NOT NULL DEFAULT 0,
		warning_message VARCHAR(255) NOT NULL DEFAULT '',
		last_edit_date INT UNSIGNED NOT NULL DEFAULT 0,
		last_edit_user_id INT UNSIGNED NOT NULL DEFAULT 0,
		edit_count INT UNSIGNED NOT NULL DEFAULT 0,
		KEY thread_id_post_date (thread_id, post_date),
		KEY thread_id_position (thread_id, position),
		KEY user_id (user_id),
		KEY post_date (post_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_profile_post'] = "
	CREATE TABLE xf_profile_post (
		profile_post_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		profile_user_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		post_date INT UNSIGNED NOT NULL,
		message MEDIUMTEXT NOT NULL,
		ip_id INT UNSIGNED  NOT NULL DEFAULT 0,
		message_state ENUM('visible', 'moderated', 'deleted') NOT NULL DEFAULT 'visible',
		attach_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		likes INT UNSIGNED NOT NULL DEFAULT 0,
		like_users BLOB NOT NULL,
		comment_count INT UNSIGNED NOT NULL DEFAULT 0,
		first_comment_date INT UNSIGNED NOT NULL DEFAULT 0,
		last_comment_date INT UNSIGNED NOT NULL DEFAULT 0,
		latest_comment_ids VARBINARY(100) NOT NULL DEFAULT '',
		warning_id INT UNSIGNED NOT NULL DEFAULT 0,
		warning_message VARCHAR(255) NOT NULL DEFAULT '',
		KEY profile_user_id_post_date (profile_user_id, post_date),
		KEY user_id (user_id),
		KEY post_date (post_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_profile_post_comment'] = "
	CREATE TABLE xf_profile_post_comment (
		profile_post_comment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		profile_post_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		comment_date INT UNSIGNED NOT NULL,
		message MEDIUMTEXT NOT NULL,
		ip_id INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (profile_post_comment_id),
		KEY profile_post_id_comment_date (profile_post_id, comment_date),
		KEY user_id (user_id),
		KEY comment_date (comment_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_registration_spam_cache'] = "
	CREATE TABLE xf_registration_spam_cache (
		cache_key VARBINARY(128) NOT NULL DEFAULT '',
		result MEDIUMBLOB NOT NULL,
		timeout INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (cache_key),
		KEY timeout (timeout)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_report'] = "
	CREATE TABLE xf_report (
		report_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		content_type VARBINARY(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		content_user_id INT UNSIGNED NOT NULL,
		content_info MEDIUMBLOB NOT NULL,
		first_report_date INT UNSIGNED NOT NULL,
		report_state ENUM('open', 'assigned', 'resolved', 'rejected') NOT NULL,
		assigned_user_id INT UNSIGNED NOT NULL,
		comment_count INT UNSIGNED NOT NULL DEFAULT 0,
		last_modified_date INT UNSIGNED NOT NULL,
		last_modified_user_id INT UNSIGNED NOT NULL DEFAULT 0,
		last_modified_username VARCHAR(50) NOT NULL DEFAULT '',
		report_count INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (report_id),
		UNIQUE KEY content_type_content_id (content_type, content_id),
		KEY report_state (report_state),
		KEY assigned_user_id_state (assigned_user_id, report_state),
		KEY last_modified_date (last_modified_date),
		KEY content_user_id_modified (content_user_id, last_modified_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_report_comment'] = "
	CREATE TABLE xf_report_comment (
		report_comment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		report_id INT UNSIGNED NOT NULL,
		comment_date INT UNSIGNED  NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		message MEDIUMTEXT NOT NULL,
		state_change ENUM('', 'open', 'assigned', 'resolved', 'rejected') NOT NULL DEFAULT '',
		is_report TINYINT UNSIGNED NOT NULL DEFAULT 1,
		PRIMARY KEY (report_comment_id),
		KEY report_id_date (report_id, comment_date),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_route_filter'] = "
	CREATE TABLE `xf_route_filter` (
		`route_filter_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`route_type` VARBINARY(25) NOT NULL,
		`prefix` varchar(25) NOT NULL,
		`find_route` varchar(255) NOT NULL,
		`replace_route` varchar(255) NOT NULL,
		`enabled` tinyint(3) unsigned NOT NULL DEFAULT '0',
		`url_to_route_only` tinyint(3) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`route_filter_id`),
		KEY `route_type_prefix` (`route_type`,`prefix`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_route_prefix'] = "
	CREATE TABLE xf_route_prefix (
		route_type ENUM('public', 'admin') NOT NULL,
		original_prefix VARCHAR(25) NOT NULL,
		route_class VARCHAR(75) NOT NULL,
		build_link ENUM('all', 'data_only', 'none') NOT NULL DEFAULT 'none',
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		PRIMARY KEY (route_type, original_prefix)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";


$tables['xf_search'] = "
	CREATE TABLE xf_search (
		search_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		search_results MEDIUMBLOB NOT NULL,
		result_count SMALLINT UNSIGNED NOT NULL,
		search_type VARCHAR(25) NOT NULL,
		search_query VARCHAR(200) NOT NULL,
		search_constraints MEDIUMBLOB NOT NULL,
		search_order VARCHAR(50) NOT NULL,
		search_grouping TINYINT NOT NULL DEFAULT 0,
		user_results MEDIUMBLOB NOT NULL,
		warnings MEDIUMBLOB NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		search_date INT UNSIGNED NOT NULL,
		query_hash varchar(32) NOT NULL DEFAULT '',
		PRIMARY KEY (search_id),
		KEY search_date (search_date),
		KEY query_hash (query_hash)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

// note: leaving this content_type as varchar to avoid an alter in upgrades
$tables['xf_search_index'] = "
	CREATE TABLE xf_search_index (
		content_type VARCHAR(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		title VARCHAR(250) NOT NULL DEFAULT '',
		message MEDIUMTEXT NOT NULL,
		metadata MEDIUMTEXT NOT NULL,
		user_id INT UNSIGNED NOT NULL DEFAULT 0,
		item_date INT UNSIGNED NOT NULL,
		discussion_id INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (content_type, content_id),
		FULLTEXT KEY title_message_metadata (title, message, metadata),
		FULLTEXT KEY title_metadata (title, metadata),
		KEY user_id_item_date (user_id, item_date)
	) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_session'] = "
	CREATE TABLE xf_session (
		session_id VARBINARY(32) NOT NULL,
		session_data MEDIUMBLOB NOT NULL,
		expiry_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (session_id),
		KEY expiry_date (expiry_date)
	) ENGINE = MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_session_activity'] = "
	CREATE TABLE xf_session_activity (
		user_id INT UNSIGNED NOT NULL,
		unique_key VARBINARY(16) NOT NULL,
		ip VARBINARY(16) NOT NULL DEFAULT '',
		controller_name VARBINARY(50) NOT NULL,
		controller_action VARBINARY(50) NOT NULL,
		view_state ENUM('valid','error') NOT NULL,
		params VARBINARY(100) NOT NULL,
		view_date INT UNSIGNED NOT NULL,
		robot_key VARBINARY(25) NOT NULL DEFAULT '',
		PRIMARY KEY (user_id, unique_key) USING BTREE,
		KEY view_date (view_date) USING BTREE
	) ENGINE = MEMORY CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_session_admin'] = "
	CREATE TABLE xf_session_admin (
		session_id VARBINARY(32) NOT NULL,
		session_data MEDIUMBLOB NOT NULL,
		expiry_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (session_id),
		KEY expiry_date (expiry_date)
	) ENGINE = MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_sitemap'] = "
	CREATE TABLE xf_sitemap (
		`sitemap_id` int(10) unsigned NOT NULL,
		`is_active` tinyint(3) unsigned NOT NULL,
		`file_count` smallint(5) unsigned NOT NULL,
		`entry_count` INT UNSIGNED NOT NULL DEFAULT '0',
		`is_compressed` tinyint(3) unsigned NOT NULL,
		`complete_date` int(10) unsigned DEFAULT NULL,
		PRIMARY KEY (`sitemap_id`),
		KEY `is_active` (`is_active`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_smilie'] = "
	CREATE TABLE xf_smilie (
		smilie_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(50) NOT NULL,
		smilie_text TEXT NOT NULL,
		image_url VARCHAR(200) NOT NULL,
		sprite_mode TINYINT UNSIGNED NOT NULL DEFAULT 0,
		sprite_params TEXT NOT NULL,
		smilie_category_id INT UNSIGNED NOT NULL DEFAULT 0,
		display_order INT UNSIGNED NOT NULL DEFAULT 1,
		display_in_editor TINYINT UNSIGNED NOT NULL DEFAULT 1,
		PRIMARY KEY (smilie_id),
		KEY display_order (display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_smilie_category'] = "
	CREATE TABLE xf_smilie_category (
		smilie_category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		display_order INT UNSIGNED NOT NULL,
		PRIMARY KEY (smilie_category_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_spam_cleaner_log'] = "
	CREATE TABLE xf_spam_cleaner_log (
		spam_cleaner_log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL DEFAULT 0,
		username VARCHAR(50) NOT NULL DEFAULT '',
		applying_user_id INT UNSIGNED NOT NULL DEFAULT 0,
		applying_username VARCHAR(50) NOT NULL DEFAULT '',
		application_date INT UNSIGNED NOT NULL DEFAULT 0,
		data mediumblob NOT NULL COMMENT 'Serialized array containing log data for undo purposes',
		restored_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (spam_cleaner_log_id),
		KEY application_date (application_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_spam_trigger_log'] = "
	CREATE TABLE `xf_spam_trigger_log` (
		`trigger_log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`content_type` varbinary(25) NOT NULL,
		`content_id` int(10) unsigned DEFAULT NULL,
		`log_date` int(10) unsigned NOT NULL,
		`user_id` int(10) unsigned NOT NULL,
		`ip_address` varbinary(16) NOT NULL,
		`result` varbinary(25) NOT NULL,
		`details` mediumblob NOT NULL,
		`request_state` mediumblob NOT NULL,
		PRIMARY KEY (`trigger_log_id`),
		UNIQUE KEY `content_type` (`content_type`,`content_id`),
		KEY `log_date` (`log_date`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_stats_daily'] = "
	CREATE TABLE xf_stats_daily (
		stats_date INT UNSIGNED NOT NULL,
		stats_type VARBINARY(25) NOT NULL,
		counter BIGINT UNSIGNED NOT NULL,
		PRIMARY KEY (stats_date, stats_type)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_style'] = "
	CREATE TABLE xf_style (
		style_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		parent_id INT UNSIGNED NOT NULL,
		parent_list VARBINARY(100) NOT NULL COMMENT 'IDs of ancestor styles in order, eg: this,parent,grandparent,root',
		title VARCHAR(50) NOT NULL,
		description VARCHAR(100) NOT NULL DEFAULT '',
		properties MEDIUMBLOB NOT NULL COMMENT 'Serialized array of materialized style properties for this style',
		last_modified_date INT UNSIGNED NOT NULL DEFAULT 0,
		user_selectable TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Unselectable styles are unselectable by non-admin visitors',
		PRIMARY KEY (style_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_style_property'] = "
	CREATE TABLE xf_style_property (
		property_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		property_definition_id INT UNSIGNED NOT NULL,
		style_id INT NOT NULL,
		property_value MEDIUMBLOB NOT NULL,
		UNIQUE KEY definition_id_style_id (property_definition_id, style_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_style_property_definition'] = "
	CREATE TABLE xf_style_property_definition (
		property_definition_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		definition_style_id INT NOT NULL,
		group_name VARBINARY(25),
		title VARCHAR(100) NOT NULL,
		description VARCHAR(255) NOT NULL DEFAULT '',
		property_name VARBINARY(100) NOT NULL,
		property_type ENUM('scalar','css') NOT NULL,
		css_components BLOB NOT NULL,
		scalar_type ENUM('','longstring','color','number','boolean','template') NOT NULL DEFAULT '',
		scalar_parameters VARCHAR(250) NOT NULL DEFAULT '' COMMENT 'Additional arguments for the given scalar type',
		addon_id VARBINARY(25) NOT NULL,
		display_order INT UNSIGNED NOT NULL DEFAULT 0,
		sub_group VARCHAR(25) NOT NULL DEFAULT '' COMMENT 'Allows loose grouping of scalars within a group',
		UNIQUE KEY definition_style_id_property_name (definition_style_id, property_name)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_style_property_group'] = "
	CREATE TABLE xf_style_property_group (
		property_group_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		group_name VARBINARY(25) NOT NULL,
		group_style_id INT NOT NULL,
		title VARCHAR(100) NOT NULL,
		description VARCHAR(255) NOT NULL DEFAULT '',
		display_order INT UNSIGNED NOT NULL,
		addon_id VARBINARY(25) NOT NULL,
		UNIQUE KEY group_name_style_id (group_name, group_style_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template'] = "
	CREATE TABLE xf_template (
		template_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		title VARBINARY(50) NOT NULL,
		style_id INT UNSIGNED NOT NULL,
		template MEDIUMTEXT NOT NULL COMMENT 'User-editable HTML and template syntax',
		template_parsed MEDIUMBLOB NOT NULL,
		addon_id VARBINARY(25) NOT NULL DEFAULT '',
		version_id INT UNSIGNED NOT NULL DEFAULT 0,
		version_string VARCHAR(30) NOT NULL DEFAULT '',
		disable_modifications TINYINT UNSIGNED NOT NULL DEFAULT '0',
		last_edit_date INT UNSIGNED NOT NULL DEFAULT '0',
		UNIQUE KEY title_style_id (title, style_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template_compiled'] = "
	CREATE TABLE xf_template_compiled (
		style_id INT UNSIGNED NOT NULL,
		language_id INT UNSIGNED NOT NULL,
		title VARBINARY(50) NOT NULL,
		template_compiled MEDIUMBLOB NOT NULL COMMENT 'Executable PHP code built by template compiler',
		PRIMARY KEY (style_id, language_id, title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template_history'] = "
	CREATE TABLE xf_template_history (
		`template_history_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`title` VARBINARY(50) NOT NULL,
		`style_id` int(11) unsigned NOT NULL,
		`template` mediumtext NOT NULL,
		`edit_date` int(11) unsigned NOT NULL,
		`log_date` int(11) unsigned NOT NULL,
		PRIMARY KEY (`template_history_id`),
		KEY `log_date` (`log_date`),
		KEY `style_id_title` (`style_id`,`title`),
		KEY `title` (`title`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template_include'] = "
	CREATE TABLE xf_template_include (
		source_map_id INT UNSIGNED NOT NULL,
		target_map_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (source_map_id, target_map_id),
		KEY target (target_map_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template_map'] = "
	CREATE TABLE xf_template_map (
		template_map_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		style_id INT UNSIGNED NOT NULL,
		title VARBINARY(50) NOT NULL,
		template_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (template_map_id),
		UNIQUE KEY style_id_title (style_id, title),
		KEY template_id (template_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template_modification'] = "
	CREATE TABLE `xf_template_modification` (
		`modification_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`addon_id` VARBINARY(25) NOT NULL,
		`template` VARBINARY(50) NOT NULL,
		`modification_key` VARBINARY(50) NOT NULL,
		`description` varchar(255) NOT NULL,
		`execution_order` int(10) unsigned NOT NULL,
		`enabled` tinyint(3) unsigned NOT NULL,
		`action` varchar(25) NOT NULL,
		`find` text NOT NULL,
		`replace` text NOT NULL,
		PRIMARY KEY (`modification_id`),
		UNIQUE KEY `modification_key` (`modification_key`),
		KEY `addon_id` (`addon_id`),
		KEY `template_order` (`template`,`execution_order`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template_modification_log'] = "
	CREATE TABLE `xf_template_modification_log` (
		`template_id` int(10) unsigned NOT NULL,
		`modification_id` int(10) unsigned NOT NULL,
		`status` varchar(25) NOT NULL,
		`apply_count` int(10) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`template_id`,`modification_id`),
		KEY `modification_id` (`modification_id`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_template_phrase'] = "
	CREATE TABLE xf_template_phrase (
		template_map_id INT UNSIGNED NOT NULL,
		phrase_title VARBINARY(100) NOT NULL,
		PRIMARY KEY (template_map_id, phrase_title),
		KEY phrase_title (phrase_title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread'] = "
	CREATE TABLE xf_thread (
		thread_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		node_id INT UNSIGNED NOT NULL,
		title VARCHAR(150) NOT NULL,
		reply_count INT UNSIGNED NOT NULL DEFAULT 0,
		view_count INT UNSIGNED NOT NULL DEFAULT 0,
		user_id INT UNSIGNED NOT NULL,
		username VARCHAR(50) NOT NULL,
		post_date INT UNSIGNED NOT NULL,
		sticky TINYINT UNSIGNED NOT NULL DEFAULT 0,
		discussion_state ENUM('visible', 'moderated', 'deleted') NOT NULL DEFAULT 'visible',
		discussion_open TINYINT UNSIGNED NOT NULL DEFAULT 1,
		discussion_type VARCHAR(25) NOT NULL DEFAULT '',
		first_post_id INT UNSIGNED NOT NULL,
		first_post_likes INT UNSIGNED NOT NULL DEFAULT 0,
		last_post_date INT UNSIGNED NOT NULL,
		last_post_id INT UNSIGNED NOT NULL,
		last_post_user_id INT UNSIGNED NOT NULL,
		last_post_username VARCHAR(50) NOT NULL,
		prefix_id INT UNSIGNED NOT NULL DEFAULT 0,
		KEY node_id_last_post_date (node_id, last_post_date),
		KEY node_id_sticky_state_last_post (node_id, sticky, discussion_state, last_post_date),
		KEY last_post_date (last_post_date),
		KEY post_date (post_date),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_prefix'] = "
	CREATE TABLE xf_thread_prefix (
		prefix_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		prefix_group_id INT UNSIGNED NOT NULL,
		display_order INT UNSIGNED NOT NULL,
		materialized_order INT UNSIGNED NOT NULL COMMENT 'Internally-set order, based on prefix_group.display_order, prefix.display_order',
		css_class VARCHAR(50) NOT NULL DEFAULT '',
		allowed_user_group_ids blob NOT NULL,
		PRIMARY KEY (prefix_id),
		KEY materialized_order (materialized_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_prefix_group'] = "
	CREATE TABLE xf_thread_prefix_group (
		prefix_group_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		display_order INT UNSIGNED NOT NULL,
		PRIMARY KEY (prefix_group_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_read'] = "
	CREATE TABLE xf_thread_read (
		thread_read_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		user_id INT UNSIGNED NOT NULL,
		thread_id INT UNSIGNED NOT NULL,
		thread_read_date INT UNSIGNED NOT NULL,
		UNIQUE KEY user_id_thread_id (user_id, thread_id),
		KEY thread_id (thread_id),
		KEY thread_read_date (thread_read_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_redirect'] = "
	CREATE TABLE xf_thread_redirect (
		thread_id INT UNSIGNED NOT NULL,
		target_url TEXT NOT NULL,
		redirect_key VARCHAR(50) NOT NULL DEFAULT '',
		expiry_date INT UNSIGNED NOT NULL DEFAULT '0',
		PRIMARY KEY (thread_id),
		KEY redirect_key_expiry_date (redirect_key, expiry_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_reply_ban'] = "
	CREATE TABLE xf_thread_reply_ban (
		`thread_reply_ban_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`thread_id` int(10) unsigned NOT NULL,
		`user_id` int(10) unsigned NOT NULL,
		`ban_date` int(10) unsigned NOT NULL,
		`expiry_date` int(10) unsigned DEFAULT NULL,
		`reason` varchar(100) NOT NULL DEFAULT '',
		`ban_user_id` int(10) unsigned NOT NULL,
		PRIMARY KEY (`thread_reply_ban_id`),
		UNIQUE KEY `thread_id` (`thread_id`,`user_id`),
		KEY `expiry_date` (`expiry_date`),
		KEY `user_id` (`user_id`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_user_post'] = "
	CREATE TABLE xf_thread_user_post (
		thread_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		post_count INT UNSIGNED NOT NULL,
		PRIMARY KEY (thread_id, user_id),
		KEY user_id (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_view'] = "
	CREATE TABLE xf_thread_view (
		thread_id INT UNSIGNED NOT NULL,
		KEY thread_id (thread_id)
	) ENGINE=MEMORY CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_thread_watch'] = "
	CREATE TABLE xf_thread_watch (
		user_id INT UNSIGNED NOT NULL,
		thread_id INT UNSIGNED NOT NULL,
		email_subscribe TINYINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_id, thread_id),
		KEY thread_id_email_subscribe (thread_id, email_subscribe)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_trophy'] = "
	CREATE TABLE xf_trophy (
		trophy_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		trophy_points INT UNSIGNED NOT NULL,
		user_criteria MEDIUMBLOB NOT NULL,
		PRIMARY KEY (trophy_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_title_ladder'] = "
	CREATE TABLE xf_user_title_ladder (
		minimum_level INT UNSIGNED NOT NULL,
		title VARCHAR(250) NOT NULL,
		PRIMARY KEY (minimum_level)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_upgrade_log'] = "
	CREATE TABLE xf_upgrade_log (
		version_id INT UNSIGNED NOT NULL,
		completion_date INT UNSIGNED NOT NULL DEFAULT 0,
		user_id INT UNSIGNED NOT NULL DEFAULT 0,
		log_type ENUM('install','upgrade') NOT NULL DEFAULT 'upgrade',
		PRIMARY KEY (version_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user'] = "
	CREATE TABLE xf_user (
		user_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		username VARCHAR(50) NOT NULL,
		email VARCHAR(120) NOT NULL,
 		gender ENUM('','male','female') NOT NULL DEFAULT '' COMMENT 'Leave empty for ''unspecified''',
 		custom_title VARCHAR(50) NOT NULL DEFAULT '',
		language_id INT UNSIGNED NOT NULL,
		style_id INT UNSIGNED NOT NULL COMMENT '0 = use system default',
		timezone VARCHAR(50) NOT NULL COMMENT 'Example: ''Europe/London''',
		visible TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Show browsing activity to others',
		activity_visible TINYINT UNSIGNED NOT NULL DEFAULT 1,
		user_group_id INT UNSIGNED NOT NULL,
		secondary_group_ids VARBINARY(255) NOT NULL,
		display_style_group_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'User group ID that provides user styling',
		permission_combination_id INT UNSIGNED NOT NULL,
		message_count INT UNSIGNED NOT NULL DEFAULT 0,
		conversations_unread SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		register_date INT UNSIGNED NOT NULL DEFAULT 0,
		last_activity INT UNSIGNED NOT NULL DEFAULT 0,
		trophy_points INT UNSIGNED NOT NULL DEFAULT 0,
		alerts_unread SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		avatar_date INT UNSIGNED NOT NULL DEFAULT 0,
		avatar_width SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		avatar_height SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		gravatar VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'If specified, this is an email address corresponding to the user''s ''Gravatar''',
		user_state ENUM('valid', 'email_confirm', 'email_confirm_edit', 'moderated', 'email_bounce') NOT NULL DEFAULT 'valid',
		is_moderator TINYINT UNSIGNED NOT NULL DEFAULT 0,
		is_admin TINYINT UNSIGNED NOT NULL DEFAULT 0,
		is_banned TINYINT UNSIGNED NOT NULL DEFAULT 0,
		like_count INT UNSIGNED NOT NULL DEFAULT 0,
		warning_points INT UNSIGNED NOT NULL DEFAULT 0,
		is_staff TINYINT UNSIGNED NOT NULL DEFAULT 0,
		UNIQUE KEY username (username),
		KEY email (email),
		KEY user_state (user_state),
		KEY last_activity (last_activity),
		KEY message_count (message_count),
		KEY trophy_points (trophy_points),
		KEY like_count (like_count),
		KEY register_date (register_date),
		KEY staff_username (is_staff, username)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_alert'] = "
	CREATE TABLE xf_user_alert (
		alert_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		alerted_user_id INT UNSIGNED NOT NULL COMMENT 'User being alerted',
		user_id INT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'User who did the action that caused the alert',
		username VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Corresponds to user_id',
		content_type VARBINARY(25) NOT NULL COMMENT 'eg: trophy',
		content_id INT UNSIGNED NOT NULL DEFAULT '0',
  		action VARBINARY(25) NOT NULL COMMENT 'eg: edit',
		event_date INT UNSIGNED NOT NULL,
		view_date INT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Time when this was viewed by the alerted user',
		extra_data MEDIUMBLOB NOT NULL COMMENT 'Serialized. Stores any extra data relevant to the alert',
		PRIMARY KEY (alert_id),
		KEY alertedUserId_eventDate (alerted_user_id, event_date),
		KEY contentType_contentId (content_type, content_id),
		KEY viewDate_eventDate (view_date, event_date)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_user_alert_optout'] = "
	CREATE TABLE xf_user_alert_optout (
		user_id INT UNSIGNED NOT NULL,
		alert VARBINARY(50) NOT NULL,
		PRIMARY KEY (user_id,alert)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_authenticate'] = "
	CREATE TABLE xf_user_authenticate (
		user_id INT UNSIGNED PRIMARY KEY,
		scheme_class VARCHAR(75) NOT NULL,
		data MEDIUMBLOB NOT NULL,
		remember_key VARBINARY(40) NOT NULL
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_ban'] = "
	CREATE TABLE xf_user_ban (
		user_id INT UNSIGNED NOT NULL,
		ban_user_id INT UNSIGNED NOT NULL,
		ban_date INT UNSIGNED NOT NULL DEFAULT '0',
		end_date INT UNSIGNED NOT NULL DEFAULT '0',
		user_reason VARCHAR(255) NOT NULL,
		triggered TINYINT UNSIGNED NOT NULL DEFAULT '1',
		PRIMARY KEY (user_id),
		KEY ban_date (ban_date),
		KEY end_date (end_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_change_log'] = "
	CREATE TABLE `xf_user_change_log` (
	  `log_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
	  `user_id` int(11) unsigned NOT NULL,
	  `edit_user_id` int(11) unsigned NOT NULL,
	  `edit_date` int(10) unsigned NOT NULL,
	  `field` varchar(100) NOT NULL DEFAULT '',
	  `old_value` text NOT NULL,
	  `new_value` text NOT NULL,
	  PRIMARY KEY (`log_id`),
	  KEY `user_id` (`user_id`),
	  KEY `edit_date` (`edit_date`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_change_temp'] = "
	CREATE TABLE xf_user_change_temp (
		user_change_temp_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`user_id` INT UNSIGNED NOT NULL,
		`change_key` varbinary(50)  NULL,
		`action_type` varbinary(50) NOT NULL,
		`action_modifier` VARBINARY( 255 ) NULL,
		`new_value` mediumblob,
		`old_value` mediumblob,
		`create_date` int(10) unsigned,
		`expiry_date` int(10) unsigned DEFAULT NULL,
		UNIQUE KEY (`user_id`,`change_key`),
		KEY `change_key` (`change_key`),
		KEY `expiry_date` (`expiry_date`)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_confirmation'] = "
	CREATE TABLE xf_user_confirmation (
		user_id INT UNSIGNED NOT NULL,
		confirmation_type VARCHAR(25) NOT NULL,
		confirmation_key VARCHAR(16) NOT NULL,
		confirmation_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (user_id, confirmation_type),
		KEY confirmation_date (confirmation_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_external_auth'] = "
	CREATE TABLE xf_user_external_auth (
		user_id INT UNSIGNED NOT NULL,
		provider VARBINARY(25) NOT NULL,
		provider_key VARBINARY(150) NOT NULL,
		extra_data MEDIUMBLOB NOT NULL,
		PRIMARY KEY (user_id, provider),
		UNIQUE KEY provider (provider, provider_key)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_field'] = "
	CREATE TABLE xf_user_field (
		field_id VARBINARY(25) NOT NULL,
		display_group ENUM('personal','contact','preferences') NOT NULL DEFAULT 'personal',
		display_order INT UNSIGNED NOT NULL DEFAULT 1,
		field_type ENUM('textbox','textarea','select','radio','checkbox','multiselect') NOT NULL DEFAULT 'textbox',
		field_choices BLOB NOT NULL,
		match_type ENUM('none','number','alphanumeric','email','url','regex','callback') NOT NULL DEFAULT 'none',
		match_regex VARCHAR(250) NOT NULL DEFAULT '',
		match_callback_class VARCHAR(75) NOT NULL DEFAULT '',
		match_callback_method VARCHAR(75) NOT NULL DEFAULT '',
		max_length INT UNSIGNED NOT NULL DEFAULT 0,
		required TINYINT UNSIGNED NOT NULL DEFAULT 0,
		show_registration TINYINT UNSIGNED NOT NULL DEFAULT 0,
		user_editable ENUM('yes','once','never') NOT NULL DEFAULT 'yes',
		viewable_profile TINYINT NOT NULL DEFAULT 1,
		viewable_message TINYINT NOT NULL DEFAULT 0,
		display_template TEXT NOT NULL,
		moderator_editable TINYINT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (field_id),
		KEY display_group_order (display_group, display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_field_value'] = "
	CREATE TABLE xf_user_field_value (
		user_id INT UNSIGNED NOT NULL,
		field_id VARBINARY(25) NOT NULL,
		field_value MEDIUMTEXT NOT NULL,
		PRIMARY KEY (user_id, field_id),
		KEY field_id (field_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_follow'] = "
	CREATE TABLE xf_user_follow (
		user_id INT UNSIGNED NOT NULL,
		follow_user_id INT UNSIGNED NOT NULL COMMENT 'User being followed',
		follow_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_id,follow_user_id),
		KEY follow_user_id (follow_user_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_user_group'] = "
	CREATE TABLE xf_user_group (
		user_group_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		title VARCHAR(50) NOT NULL,
		display_style_priority INT UNSIGNED NOT NULL DEFAULT 0,
		username_css TEXT NOT NULL,
		user_title VARCHAR(100) NOT NULL DEFAULT '',
		banner_css_class VARCHAR(75) NOT NULL DEFAULT '',
		banner_text VARCHAR(100) NOT NULL DEFAULT '',
		KEY title (title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_group_change'] = "
	CREATE TABLE xf_user_group_change (
		user_id INT UNSIGNED NOT NULL,
		change_key VARBINARY(50) NOT NULL,
		group_ids VARBINARY(255) NOT NULL,
		PRIMARY KEY (user_id, change_key),
		KEY change_key (change_key)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_group_promotion'] = "
	CREATE TABLE xf_user_group_promotion (
		promotion_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(100) NOT NULL,
		active TINYINT NOT NULL DEFAULT 1,
		user_criteria MEDIUMBLOB NOT NULL,
		extra_user_group_ids VARBINARY(255) NOT NULL,
		PRIMARY KEY (promotion_id),
		KEY title (title)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_group_promotion_log'] = "
	CREATE TABLE xf_user_group_promotion_log (
		promotion_id int(10) unsigned NOT NULL,
		user_id int(10) unsigned NOT NULL,
		promotion_date int(10) unsigned NOT NULL,
		promotion_state enum('automatic','manual','disabled') NOT NULL default 'automatic',
		PRIMARY KEY (promotion_id, user_id),
		KEY promotion_date (promotion_date),
		KEY user_id_date (user_id, promotion_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_group_relation'] = "
	CREATE TABLE xf_user_group_relation (
		user_id INT UNSIGNED NOT NULL,
		user_group_id INT UNSIGNED NOT NULL,
		is_primary TINYINT UNSIGNED NOT NULL,
		PRIMARY KEY (user_id,user_group_id),
		KEY user_group_id_is_primary (user_group_id, is_primary)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_ignored'] = "
	CREATE TABLE xf_user_ignored (
		user_id INT UNSIGNED NOT NULL,
		ignored_user_id INT UNSIGNED NOT NULL,
		PRIMARY KEY (user_id, ignored_user_id),
		KEY ignored_user_id (ignored_user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_news_feed_cache'] = "
	CREATE TABLE xf_user_news_feed_cache (
		user_id INT UNSIGNED NOT NULL,
		news_feed_cache MEDIUMBLOB NOT NULL COMMENT 'Serialized. Contains fetched, parsed news_feed items for user_id',
		news_feed_cache_date INT UNSIGNED NOT NULL COMMENT 'Date at which the cache was last refreshed',
		PRIMARY KEY (user_id)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_option'] = "
	CREATE TABLE xf_user_option (
		user_id INT UNSIGNED NOT NULL,
		show_dob_year TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Show date of month year (thus: age)',
		show_dob_date TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Show date of birth day and month',
		content_show_signature TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Show user''s signatures with content',
		receive_admin_email TINYINT UNSIGNED NOT NULL DEFAULT 1,
		email_on_conversation TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Receive an email upon receiving a conversation message',
		is_discouraged TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT  'If non-zero, this user will be subjected to annoying random system failures.',
		default_watch_state ENUM('', 'watch_no_email', 'watch_email') NOT NULL DEFAULT '',
		alert_optout TEXT NOT NULL COMMENT 'Comma-separated list of alerts from which the user has opted out. Example: ''post_like,user_trophy''',
		enable_rte TINYINT UNSIGNED NOT NULL DEFAULT 1,
		enable_flash_uploader TINYINT UNSIGNED NOT NULL DEFAULT 1,
		PRIMARY KEY (user_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_user_privacy'] = "
	CREATE TABLE xf_user_privacy (
		user_id INT UNSIGNED NOT NULL,
		allow_view_profile ENUM('everyone','members','followed','none') NOT NULL DEFAULT 'everyone',
		allow_post_profile ENUM('everyone','members','followed','none') NOT NULL DEFAULT 'everyone',
		allow_send_personal_conversation ENUM('everyone','members','followed','none') NOT NULL DEFAULT 'everyone',
		allow_view_identities ENUM('everyone','members','followed','none') NOT NULL DEFAULT 'everyone',
		allow_receive_news_feed ENUM('everyone','members','followed','none') NOT NULL DEFAULT 'everyone',
		PRIMARY KEY (user_id)
	) ENGINE = InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci
";

$tables['xf_user_profile'] = "
	CREATE TABLE xf_user_profile (
		user_id INT UNSIGNED NOT NULL,
 		dob_day TINYINT UNSIGNED NOT NULL DEFAULT '0',
		dob_month TINYINT UNSIGNED NOT NULL DEFAULT '0',
		dob_year SMALLINT UNSIGNED NOT NULL DEFAULT '0',
 		status TEXT NOT NULL,
 		status_date INT UNSIGNED NOT NULL DEFAULT 0,
 		status_profile_post_id INT UNSIGNED NOT NULL DEFAULT 0,
 		signature TEXT NOT NULL,
 		homepage TEXT NOT NULL,
 		location VARCHAR(50) NOT NULL DEFAULT '',
 		occupation VARCHAR(50) NOT NULL DEFAULT '',
 		following TEXT NOT NULL COMMENT 'Comma-separated integers from xf_user_follow',
 		ignored TEXT NOT NULL COMMENT 'Comma-separated integers from xf_user_ignored',
 		csrf_token VARCHAR(40) NOT NULL COMMENT 'Anti CSRF data key',
 		avatar_crop_x INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'X-Position from which to start the square crop on the m avatar',
		avatar_crop_y INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Y-Position from which to start the square crop on the m avatar',
		about TEXT NOT NULL,
		custom_fields MEDIUMBLOB NOT NULL,
		external_auth MEDIUMBLOB NOT NULL,
		password_date INT UNSIGNED NOT NULL DEFAULT 1,
 		PRIMARY KEY (user_id),
 		KEY dob (dob_month, dob_day, dob_year)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_status'] = "
	CREATE TABLE xf_user_status (
		profile_post_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		post_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (profile_post_id),
		KEY post_date (post_date)
	) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_trophy'] = "
	CREATE TABLE xf_user_trophy (
		user_id INT UNSIGNED NOT NULL,
		trophy_id INT UNSIGNED NOT NULL,
		award_date INT UNSIGNED NOT NULL,
		PRIMARY KEY (trophy_id, user_id),
		KEY user_id_award_date (user_id, award_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_upgrade'] = "
	CREATE TABLE xf_user_upgrade (
		user_upgrade_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(50) NOT NULL,
		description TEXT NOT NULL,
		display_order INT UNSIGNED NOT NULL DEFAULT 0,
		extra_group_ids VARBINARY(255) NOT NULL DEFAULT '',
		recurring TINYINT UNSIGNED NOT NULL DEFAULT 0,
		cost_amount DECIMAL(10, 2) UNSIGNED NOT NULL,
		cost_currency VARCHAR(3) NOT NULL,
		length_amount TINYINT UNSIGNED NOT NULL,
		length_unit ENUM('day', 'month', 'year', '') NOT NULL DEFAULT '',
		disabled_upgrade_ids VARBINARY(255) NOT NULL DEFAULT '',
		can_purchase TINYINT UNSIGNED NOT NULL DEFAULT 1,
		PRIMARY KEY (user_upgrade_id),
		KEY display_order (display_order)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_upgrade_active'] = "
	CREATE TABLE xf_user_upgrade_active (
		user_upgrade_record_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id INT UNSIGNED NOT NULL,
		user_upgrade_id INT UNSIGNED NOT NULL,
		extra MEDIUMBLOB NOT NULL,
		start_date INT UNSIGNED NOT NULL,
		end_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_upgrade_record_id),
		UNIQUE KEY user_id_upgrade_id (user_id, user_upgrade_id),
		KEY end_date (end_date),
		KEY start_date (start_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_upgrade_expired'] = "
	CREATE TABLE xf_user_upgrade_expired (
		user_upgrade_record_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		user_upgrade_id INT UNSIGNED NOT NULL,
		extra MEDIUMBLOB NOT NULL,
		start_date INT UNSIGNED NOT NULL,
		end_date INT UNSIGNED NOT NULL DEFAULT 0,
		original_end_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_upgrade_record_id),
		KEY end_date (end_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_user_upgrade_log'] = "
	CREATE TABLE xf_user_upgrade_log (
		user_upgrade_log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_upgrade_record_id INT UNSIGNED NOT NULL,
		processor VARCHAR(25) NOT NULL,
		transaction_id VARCHAR(50) NOT NULL,
		subscriber_id VARCHAR(50) NOT NULL DEFAULT '',
		transaction_type ENUM('payment','cancel','info','error') NOT NULL,
		message VARCHAR(255) NOT NULL default '',
		transaction_details MEDIUMBLOB NOT NULL,
		log_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (user_upgrade_log_id),
		KEY transaction_id (transaction_id),
		KEY subscriber_id (subscriber_id),
		KEY log_date (log_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_warning'] = "
	CREATE TABLE xf_warning (
		warning_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		content_type VARBINARY(25) NOT NULL,
		content_id INT UNSIGNED NOT NULL,
		content_title VARCHAR(255) NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		warning_date INT UNSIGNED NOT NULL,
		warning_user_id INT UNSIGNED NOT NULL,
		warning_definition_id INT UNSIGNED NOT NULL,
		title VARCHAR(255) NOT NULL,
		notes TEXT NOT NULL,
		points SMALLINT UNSIGNED NOT NULL,
		expiry_date INT UNSIGNED NOT NULL,
		is_expired TINYINT UNSIGNED NOT NULL,
		extra_user_group_ids VARBINARY(255) NOT NULL,
		PRIMARY KEY (warning_id),
		KEY content_type_id (content_type, content_id),
		KEY user_id_date (user_id, warning_date),
		KEY expiry (expiry_date)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_warning_action'] = "
	CREATE TABLE xf_warning_action (
		warning_action_id INT UNSIGNED NOT NULL auto_increment,
		points SMALLINT UNSIGNED NOT NULL,
		action VARBINARY(25) NOT NULL,
		action_length_type VARBINARY( 25 ) NOT NULL,
		action_length SMALLINT( 5 ) UNSIGNED NOT NULL,
		extra_user_group_ids VARBINARY(255) NOT NULL,
		PRIMARY KEY (warning_action_id),
		KEY points (points)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_warning_action_trigger'] = "
	CREATE TABLE xf_warning_action_trigger (
		action_trigger_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		warning_action_id INT UNSIGNED NOT NULL,
		user_id INT UNSIGNED NOT NULL,
		trigger_points SMALLINT UNSIGNED NOT NULL,
		action_date INT UNSIGNED NOT NULL,
		action VARBINARY(25) NOT NULL,
		min_unban_date INT UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (action_trigger_id),
		KEY user_id_points (user_id, trigger_points)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

$tables['xf_warning_definition'] = "
	CREATE TABLE xf_warning_definition (
		warning_definition_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
		points_default SMALLINT UNSIGNED NOT NULL,
		expiry_type ENUM('never','days','weeks','months','years') NOT NULL,
		expiry_default SMALLINT UNSIGNED NOT NULL,
		extra_user_group_ids VARBINARY(255) NOT NULL,
		is_editable TINYINT UNSIGNED NOT NULL,
		PRIMARY KEY (warning_definition_id),
		KEY points_default (points_default)
	) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
";

		return $tables;
	}

	public static function getData()
	{
		$data = array();

$data['xf_style'] = "
	INSERT INTO xf_style
		(style_id, parent_id, parent_list, title, properties)
	VALUES
		(1, 0, '1,0', 'Default Style', '')";

$data['xf_language'] = "
	INSERT INTO xf_language
		(language_id, parent_id, parent_list, title, date_format, time_format, decimal_point, thousands_separator, phrase_cache, language_code)
	VALUES
		(1, 0, '1,0', 'English (US)', 'M j, Y', 'g:i A', '.', ',', '', 'en-US')
";

$data['xf_node_type'] = "
	INSERT INTO xf_node_type
		(node_type_id, handler_class, controller_admin_class, datawriter_class, permission_group_id,
		moderator_interface_group_id, public_route_prefix)
	VALUES
		('Category', 'XenForo_NodeHandler_Category', 'XenForo_ControllerAdmin_Category', 'XenForo_DataWriter_Category', 'category', '', 'categories'),
		('Forum', 'XenForo_NodeHandler_Forum', 'XenForo_ControllerAdmin_Forum', 'XenForo_DataWriter_Forum', 'forum', 'forumModeratorPermissions', 'forums'),
		('LinkForum', 'XenForo_NodeHandler_LinkForum', 'XenForo_ControllerAdmin_LinkForum', 'XenForo_DataWriter_LinkForum', 'linkForum', '', 'link-forums'),
		('Page', 'XenForo_NodeHandler_Page', 'XenForo_ControllerAdmin_Page', 'XenForo_DataWriter_Page', 'page', '', 'pages')
";

$data['xf_content_type'] = "
	INSERT INTO xf_content_type
		(content_type, addon_id, fields)
	VALUES
		('attachment', 'XenForo', ''),
		('conversation', 'XenForo', ''),
		('conversation_message', 'XenForo', ''),
		('node', 'XenForo', ''),
		('post', 'XenForo', ''),
		('thread', 'XenForo', ''),
		('user', 'XenForo', ''),
		('profile_post', 'XenForo', ''),
		('page',  'XenForo', '')
";

$data['xf_content_type_field'] = "
	INSERT INTO xf_content_type_field
		(content_type, field_name, field_value)
	VALUES
		('attachment', 'stats_handler_class', 'XenForo_StatsHandler_Attachment'),

		('conversation', 'alert_handler_class', 'XenForo_AlertHandler_Conversation'),
		('conversation', 'stats_handler_class', 'XenForo_StatsHandler_Conversation'),
		('conversation', 'spam_handler_class',  'XenForo_SpamHandler_Conversation'),

		('conversation_message', 'report_handler_class', 'XenForo_ReportHandler_ConversationMessage'),
		('conversation_message', 'attachment_handler_class', 'XenForo_AttachmentHandler_ConversationMessage'),

		('node', 'permission_handler_class', 'XenForo_ContentPermission_Node'),
		('node', 'moderator_handler_class', 'XenForo_ModeratorHandler_Node'),
		('node', 'sitemap_handler_class', 'XenForo_SitemapHandler_Node'),

		('page', 'search_handler_class', 'XenForo_Search_DataHandler_Page'),

		('post', 'news_feed_handler_class', 'XenForo_NewsFeedHandler_DiscussionMessage_Post'),
		('post', 'alert_handler_class', 'XenForo_AlertHandler_DiscussionMessage_Post'),
		('post', 'search_handler_class', 'XenForo_Search_DataHandler_Post'),
		('post', 'attachment_handler_class', 'XenForo_AttachmentHandler_Post'),
		('post', 'like_handler_class', 'XenForo_LikeHandler_Post'),
		('post', 'report_handler_class', 'XenForo_ReportHandler_Post'),
		('post', 'moderation_queue_handler_class', 'XenForo_ModerationQueueHandler_Post'),
		('post', 'spam_handler_class', 'XenForo_SpamHandler_Post'),
		('post', 'stats_handler_class', 'XenForo_StatsHandler_Post'),
		('post', 'moderator_log_handler_class', 'XenForo_ModeratorLogHandler_Post'),
		('post', 'warning_handler_class', 'XenForo_WarningHandler_Post'),
		('post', 'edit_history_handler_class', 'XenForo_EditHistoryHandler_Post'),

		('thread', 'alert_handler_class', 'XenForo_AlertHandler_Thread'),
		('thread', 'news_feed_handler_class', 'XenForo_NewsFeedHandler_Discussion_Thread'),
		('thread', 'search_handler_class', 'XenForo_Search_DataHandler_Thread'),
		('thread', 'moderation_queue_handler_class', 'XenForo_ModerationQueueHandler_Thread'),
		('thread', 'spam_handler_class', 'XenForo_SpamHandler_Thread'),
		('thread', 'stats_handler_class', 'XenForo_StatsHandler_Thread'),
		('thread', 'moderator_log_handler_class', 'XenForo_ModeratorLogHandler_Thread'),
		('thread', 'sitemap_handler_class', 'XenForo_SitemapHandler_Thread'),

		('user', 'news_feed_handler_class', 'XenForo_NewsFeedHandler_User'),
		('user', 'alert_handler_class', 'XenForo_AlertHandler_User'),
		('user', 'stats_handler_class', 'XenForo_StatsHandler_User'),
		('user', 'warning_handler_class', 'XenForo_WarningHandler_User'),
		('user', 'report_handler_class', 'XenForo_ReportHandler_User'),
		('user', 'sitemap_handler_class', 'XenForo_SitemapHandler_User'),

		('profile_post', 'news_feed_handler_class', 'XenForo_NewsFeedHandler_DiscussionMessage_ProfilePost'),
		('profile_post', 'alert_handler_class', 'XenForo_AlertHandler_DiscussionMessage_ProfilePost'),
		('profile_post', 'search_handler_class', 'XenForo_Search_DataHandler_ProfilePost'),
		('profile_post', 'report_handler_class', 'XenForo_ReportHandler_ProfilePost'),
		('profile_post', 'moderation_queue_handler_class', 'XenForo_ModerationQueueHandler_ProfilePost'),
		('profile_post', 'like_handler_class', 'XenForo_LikeHandler_ProfilePost'),
		('profile_post', 'spam_handler_class', 'XenForo_SpamHandler_ProfilePost'),
		('profile_post', 'stats_handler_class', 'XenForo_StatsHandler_ProfilePost'),
		('profile_post', 'moderator_log_handler_class', 'XenForo_ModeratorLogHandler_ProfilePost'),
		('profile_post', 'warning_handler_class', 'XenForo_WarningHandler_ProfilePost')
";

$data['xf_user_field'] = "
	INSERT INTO xf_user_field
		(field_id, display_group, display_order, field_type, field_choices, match_type, match_regex, match_callback_class, match_callback_method, max_length, display_template)
	VALUES
		('aim', 'contact', 10, 'textbox', '', 'regex', '^[a-zA-Z0-9@\. ]+$', '', '', 80, ''),
		('icq', 'contact', 30, 'textbox', '', 'number', '', '', '', 0, ''),
		('yahoo', 'contact', 40, 'textbox', '', 'none', '', '', '', 0, ''),
		('skype', 'contact', 50, 'textbox', '', 'regex', '^[a-zA-Z0-9-_.,@:]+$', '', '', 30, ''),
		('gtalk', 'contact', 60, 'textbox', '', 'none', '', '', '', 0, ''),
		('facebook', 'contact', 70, 'textbox', '', 'callback', '', 'XenForo_Helper_UserField', 'verifyFacebook', 0, ''),
		('twitter', 'contact', 80, 'textbox', '', 'callback', '', 'XenForo_Helper_UserField', 'verifyTwitter', 0, '')
";

$data['xf_warning_definition'] = "
	INSERT INTO xf_warning_definition
		(warning_definition_id, points_default, expiry_type, expiry_default, extra_user_group_ids, is_editable)
	VALUES
		(1, 1, 'months', 1, '', 1),
		(2, 1, 'months', 1, '', 1),
		(3, 1, 'months', 1, '', 1),
		(4, 1, 'months', 1, '', 1)
";

$data['xf_phrase'] = "
	INSERT INTO xf_phrase
		(language_id, title, phrase_text, global_cache, addon_id)
	VALUES
		(0, 'user_field_gtalk', 'Google Talk', 1, ''),
		(0, 'user_field_gtalk_desc', '', 0, ''),
		(0, 'user_field_aim', 'AIM', 1, ''),
		(0, 'user_field_aim_desc', '', 0, ''),
		(0, 'user_field_icq', 'ICQ', 1, ''),
		(0, 'user_field_icq_desc', '', 0, ''),
		(0, 'user_field_skype', 'Skype', 1, ''),
		(0, 'user_field_skype_desc', '', 0, ''),
		(0, 'user_field_yahoo', 'Yahoo! Messenger', 1, ''),
		(0, 'user_field_yahoo_desc', '', 0, ''),
		(0, 'user_field_facebook', 'Facebook', 1, ''),
		(0, 'user_field_facebook_desc', '', 0, ''),
		(0, 'user_field_twitter', 'Twitter', 1, ''),
		(0, 'user_field_twitter_desc', '', 0, ''),
		(0, 'trophy_1_description', 'Post a message somewhere on the site to receive this.', 0, ''),
		(0, 'trophy_1_title', 'First Message', 0, ''),
		(0, 'trophy_2_description', '30 messages posted. You must like it here!', 0, ''),
		(0, 'trophy_2_title', 'Keeps Coming Back', 0, ''),
		(0, 'trophy_3_description', 'You''ve posted 100 messages. I hope this took you more than a day!', 0, ''),
		(0, 'trophy_3_title', 'Can''t Stop!', 0, ''),
		(0, 'trophy_4_description', '1,000 messages? Impressive!', 0, ''),
		(0, 'trophy_4_title', 'Addicted', 0, ''),
		(0, 'trophy_5_description', 'Somebody out there liked one of your messages. Keep posting like that for more!', 0, ''),
		(0, 'trophy_5_title', 'Somebody Likes You', 0, ''),
		(0, 'trophy_6_description', 'Your messages have been liked 25 times.', 0, ''),
		(0, 'trophy_6_title', 'I Like It a Lot', 0, ''),
		(0, 'trophy_7_description', 'Content you have posted has attracted 100 likes.', 0, ''),
		(0, 'trophy_7_title', 'Seriously Likeable!', 0, ''),
		(0, 'trophy_8_description', 'Your content has been liked 250 times.', 0, ''),
		(0, 'trophy_8_title', 'Can''t Get Enough of Your Stuff', 0, ''),
		(0, 'trophy_9_description', 'Content you have posted has attracted 500 likes.', 0, ''),
		(0, 'trophy_9_title', 'I LOVE IT!', 0, ''),
		(0, 'warning_definition_1_title', 'Inappropriate Content', 0, ''),
		(0, 'warning_definition_1_conversation_title', 'Inappropriate Content', 0, ''),
		(0, 'warning_definition_1_conversation_text', '{name},\n\nYour message ([url={url}]{title}[/url]) contains inappropriate content:\n[quote]{content}[/quote]\n\nPlease do not discuss content of this nature on our site. This does not follow our rules. Your message may have been removed or altered.\n\nYour account''s access may be limited based on these actions. Please keep this in mind when posting or using our site.', 0, ''),
		(0, 'warning_definition_2_title', 'Inappropriate Behavior', 0, ''),
		(0, 'warning_definition_2_conversation_title', 'Inappropriate Behavior', 0, ''),
		(0, 'warning_definition_2_conversation_text', '{name},\n\nYour actions in this message ([url={url}]{title}[/url]) are not appropriate:\n[quote]{content}[/quote]\n\nWe cannot allow users to be abusive, overly aggressive, threatening, or to \"troll\". This does not follow our rules. Your message may have been removed or altered.\n\nYour account''s access may be limited based on these actions. Please keep this in mind when posting or using our site.', 0, ''),
		(0, 'warning_definition_3_title', 'Inappropriate Language', 0, ''),
		(0, 'warning_definition_3_conversation_title', 'Inappropriate Language', 0, ''),
		(0, 'warning_definition_3_conversation_text', '{name},\n\nYour message ([url={url}]{title}[/url]) contains inappropriate language:\n[quote]{content}[/quote]\n\nThis does not follow our rules. Your message may have been removed or altered.\n\nYour account''s access may be limited based on these actions. Please keep this in mind when posting or using our site.', 0, ''),
		(0, 'warning_definition_4_title', 'Inappropriate Advertising / Spam', 0, ''),
		(0, 'warning_definition_4_conversation_title', 'Inappropriate Advertising / Spam', 0, ''),
		(0, 'warning_definition_4_conversation_text', '{name},\n\nYour message ([url={url}]{title}[/url]) contains inappropriate advertising or spam:\n[quote]{content}[/quote]\n\nThis does not follow our rules. Your message may have been removed or altered.\n\nYour account''s access may be limited based on these actions. Please keep this in mind when posting or using our site.', 0, '')
";

$data['xf_user_group'] = "
	INSERT INTO xf_user_group
		(user_group_id, title, display_style_priority, username_css, user_title)
	VALUES
		(1, 'Unregistered / Unconfirmed', 0, '', 'Guest'),
		(2, 'Registered', 0, '', ''),
		(3, 'Administrative', 1000, '', 'Administrator'),
		(4, 'Moderating', 900, '', 'Moderator')
";

$data['xf_permission_combination'] = "
	INSERT INTO xf_permission_combination
		(permission_combination_id, user_id, user_group_list, cache_value)
	VALUES
		(1, 0, '1', ''),
		(2, 0, '2', '')
";

$data['xf_permission_combination_user_group'] = "
	INSERT INTO xf_permission_combination_user_group
		(user_group_id, permission_combination_id)
	VALUES
		(1, 1),
		(2, 2)
";

$data['xf_permission_entry'] = "
	INSERT INTO xf_permission_entry
		(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
	VALUES
		(1, 0, 'forum', 'viewContent', 'allow', 0),
		(1, 0, 'forum', 'viewOthers', 'allow', 0),
		(1, 0, 'general', 'followModerationRules', 'allow', 0),
		(1, 0, 'general', 'editProfile', 'allow', 0),
		(1, 0, 'general', 'search', 'allow', 0),
		(1, 0, 'general', 'view', 'allow', 0),
		(1, 0, 'general', 'viewNode', 'allow', 0),
		(1, 0, 'general', 'viewProfile', 'allow', 0),
		(1, 0, 'general', 'viewMemberList', 'allow', 0),
		(1, 0, 'profilePost', 'view', 'allow', 0),
		(2, 0, 'avatar', 'allowed', 'allow', 0),
		(2, 0, 'avatar', 'maxFileSize', 'use_int', 51200),
		(2, 0, 'conversation', 'start', 'allow', 0),
		(2, 0, 'conversation', 'receive', 'allow', 0),
		(2, 0, 'conversation', 'maxRecipients', 'use_int', 5),
		(2, 0, 'conversation', 'editOwnPost', 'allow', 0),
		(2, 0, 'conversation', 'editOwnPostTimeLimit', 'use_int', 5),
		(2, 0, 'forum', 'deleteOwnPost', 'allow', 0),
		(2, 0, 'forum', 'editOwnPost', 'allow', 0),
		(2, 0, 'forum', 'editOwnThreadTitle', 'allow', 0),
		(2, 0, 'forum', 'editOwnPostTimeLimit', 'use_int', -1),
		(2, 0, 'forum', 'postReply', 'allow', 0),
		(2, 0, 'forum', 'postThread', 'allow', 0),
		(2, 0, 'forum', 'uploadAttachment', 'allow', 0),
		(2, 0, 'forum', 'viewAttachment', 'allow', 0),
		(2, 0, 'forum', 'viewContent', 'allow', 0),
		(2, 0, 'forum', 'viewOthers', 'allow', 0),
		(2, 0, 'forum', 'votePoll', 'allow', 0),
		(2, 0, 'forum', 'like', 'allow', 0),
		(2, 0, 'general', 'editProfile', 'allow', 0),
		(2, 0, 'general', 'editSignature', 'allow', 0),
		(2, 0, 'general', 'followModerationRules', 'allow', 0),
		(2, 0, 'general', 'search', 'allow', 0),
		(2, 0, 'general', 'view', 'allow', 0),
		(2, 0, 'general', 'viewNode', 'allow', 0),
		(2, 0, 'general', 'viewProfile', 'allow', 0),
		(2, 0, 'general', 'viewMemberList', 'allow', 0),
		(2, 0, 'general', 'report', 'allow', 0),
		(2, 0, 'general', 'maxTaggedUsers', 'use_int', 5),
		(2, 0, 'signature', 'basicText', 'allow', 0),
		(2, 0, 'signature', 'extendedText', 'allow', 0),
		(2, 0, 'signature', 'align', 'allow', 0),
		(2, 0, 'signature', 'list', 'allow', 0),
		(2, 0, 'signature', 'image', 'allow', 0),
		(2, 0, 'signature', 'link', 'allow', 0),
		(2, 0, 'signature', 'media', 'allow', 0),
		(2, 0, 'signature', 'block', 'allow', 0),
		(2, 0, 'signature', 'maxPrintable', 'use_int', -1),
		(2, 0, 'signature', 'maxLines', 'use_int', -1),
		(2, 0, 'signature', 'maxLinks', 'use_int', -1),
		(2, 0, 'signature', 'maxImages', 'use_int', -1),
		(2, 0, 'signature', 'maxSmilies', 'use_int', -1),
		(2, 0, 'signature', 'maxTextSize', 'use_int', -1),
		(2, 0, 'profilePost', 'deleteOwn', 'allow', 0),
		(2, 0, 'profilePost', 'editOwn', 'allow', 0),
		(2, 0, 'profilePost', 'manageOwn', 'allow', 0),
		(2, 0, 'profilePost', 'post', 'allow', 0),
		(2, 0, 'profilePost', 'comment', 'allow', 0),
		(2, 0, 'profilePost', 'view', 'allow', 0),
		(2, 0, 'profilePost', 'like', 'allow', 0),
		(3, 0, 'avatar', 'allowed', 'allow', 0),
		(3, 0, 'avatar', 'maxFileSize', 'use_int', -1),
		(3, 0, 'conversation', 'maxRecipients', 'use_int', -1),
		(3, 0, 'conversation', 'editAnyPost', 'allow', 0),
		(3, 0, 'conversation', 'alwaysInvite', 'allow', 0),
		(3, 0, 'conversation', 'uploadAttachment', 'allow', 0),
		(3, 0, 'forum', 'maxTaggedUsers', 'use_int', -1),
		(3, 0, 'forum', 'deleteOwnThread', 'allow', 0),
		(3, 0, 'forum', 'editOwnPostTimeLimit', 'use_int', -1),
		(3, 0, 'general', 'bypassFloodCheck', 'allow', 0),
		(3, 0, 'general', 'editCustomTitle', 'allow', 0),
		(4, 0, 'avatar', 'maxFileSize', 'use_int', -1),
		(4, 0, 'conversation', 'maxRecipients', 'use_int', -1),
		(4, 0, 'conversation', 'uploadAttachment', 'allow', 0),
		(4, 0, 'forum', 'maxTaggedUsers', 'use_int', -1),
		(4, 0, 'forum', 'editOwnPostTimeLimit', 'use_int', -1),
		(4, 0, 'general', 'bypassFloodCheck', 'allow', 0),
		(4, 0, 'general', 'editCustomTitle', 'allow', 0)
";

$data['xf_bb_code_media_site'] = '
	INSERT INTO xf_bb_code_media_site
		(media_site_id, site_title, site_url, match_urls, embed_html, match_is_regex)
	VALUES
		(\'facebook\', \'Facebook\', \'http://www.facebook.com\', \'facebook.com/*video.php?v={$id:digits}\nfacebook.com/*photo.php?v={\$id:digits}\', \'<div class="fb-post" data-href="https://www.facebook.com/video.php?v={$id}" data-width="500"><div class="fb-xfbml-parse-ignore"><a href="https://www.facebook.com/video.php?v={$id}">https://www.facebook.com/video.php?v={$id}</a></div></div>\', 0),
		(\'vimeo\', \'Vimeo\', \'http://www.vimeo.com\', \'vimeo.com/{$id:digits}\nvimeo.com/groups/*/videos/{$id:digits}\', \'<iframe src="https://player.vimeo.com/video/{$id}" width="500" height="300" frameborder="0"></iframe>\', 0),
		(\'youtube\', \'YouTube\', \'http://www.youtube.com\', \'youtube.com/watch?v={$id}\nyoutube.com/watch?*&v={$id}\nyoutube.com/v/{$id}\nyoutu.be/{$id}\nyoutube.com/*/u/*/{$id}\nyoutube.com/user/*/{$id}\nyoutube.com/embed/{$id}\', \'<iframe width="500" height="300" src="https://www.youtube.com/embed/{$id}?wmode=opaque" frameborder="0" allowfullscreen></iframe>\', 0),
		(\'metacafe\', \'Metacafe\', \'http://www.metacafe.com\', \'#metacafe\\\\.com/watch/(?P<id>[a-z0-9-]+)(/|$)#siU\', \'<iframe src="http://www.metacafe.com/embed/{$id}/" width="500" height="300" allowFullScreen frameborder=0></iframe>\', 1),
		(\'dailymotion\', \'Dailymotion\', \'http://www.dailymotion.com\', \'dailymotion.com/video/{$id:alphanum}\', \'<iframe frameborder="0" width="500" height="300" src="https://www.dailymotion.com/embed/video/{$id}?width=500&hideInfos=1"></iframe>\', 0),
		(\'liveleak\', \'Liveleak\', \'http://www.liveleak.com\', \'liveleak.com/view?i={$id}\', \'<iframe width="500" height="300" src="http://www.liveleak.com/ll_embed?i={$id}" frameborder="0" allowfullscreen></iframe>\', 0)
';

$data['xf_node'] = "
	INSERT INTO xf_node
		(node_id, title, description, node_type_id, parent_node_id, display_order, lft, rgt, depth)
	VALUES
		(1, 'Main Category', '', 'Category', 0, 1, 1, 4, 0),
		(2, 'Main Forum', '', 'Forum', 1, 1, 2, 3, 1)
";

$data['xf_forum'] = "
	INSERT INTO xf_forum
		(node_id, discussion_count, message_count, last_post_id, last_post_date, last_post_user_id, last_post_username, prefix_cache)
	VALUES
		(2, 0, 0, 0, 0, 0, '', '')
";

$data['xf_trophy'] = '
	REPLACE INTO xf_trophy
		(trophy_id, trophy_points, user_criteria)
	VALUES
		(1, 1, \'a:1:{i:0;a:2:{s:4:"rule";s:15:"messages_posted";s:4:"data";a:1:{s:8:"messages";s:1:"1";}}}\'),
		(2, 5, \'a:1:{i:0;a:2:{s:4:"rule";s:15:"messages_posted";s:4:"data";a:1:{s:8:"messages";s:2:"30";}}}\'),
		(3, 10, \'a:1:{i:0;a:2:{s:4:"rule";s:15:"messages_posted";s:4:"data";a:1:{s:8:"messages";s:3:"100";}}}\'),
		(4, 20, \'a:1:{i:0;a:2:{s:4:"rule";s:15:"messages_posted";s:4:"data";a:1:{s:8:"messages";s:4:"1000";}}}\'),
		(5, 2, \'a:1:{i:0;a:2:{s:4:"rule";s:10:"like_count";s:4:"data";a:1:{s:5:"likes";s:1:"1";}}}\'),
		(6, 10, \'a:1:{i:0;a:2:{s:4:"rule";s:10:"like_count";s:4:"data";a:1:{s:5:"likes";s:2:"25";}}}\'),
		(7, 15, \'a:1:{i:0;a:2:{s:4:"rule";s:10:"like_count";s:4:"data";a:1:{s:5:"likes";s:3:"100";}}}\'),
		(8, 20, \'a:1:{i:0;a:2:{s:4:"rule";s:10:"like_count";s:4:"data";a:1:{s:5:"likes";s:3:"250";}}}\'),
		(9, 30, \'a:1:{i:0;a:2:{s:4:"rule";s:10:"like_count";s:4:"data";a:1:{s:5:"likes";s:3:"500";}}}\')
';

$data['xf_user_title_ladder'] = "
	INSERT INTO xf_user_title_ladder
		(minimum_level, title)
	VALUES
		(0, 'New Member'),
		(5, 'Member'),
		(25, 'Active Member'),
		(45, 'Well-Known Member')
";

$data['xf_smilie'] = "
	INSERT INTO xf_smilie
		(display_order, title, smilie_text, image_url, sprite_mode, sprite_params)
	VALUES
		(10,  'Smile', ':)\n:-)\n(:', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:0;s:1:\"y\";i:0;}'),
		(20,  'Wink', ';)', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:-60;s:1:\"y\";i:-21;}'),
		(30,  'Frown', ':(', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:-40;s:1:\"y\";i:-42;}'),
		(40,  'Mad', ':mad:\n>:(\n:@', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:-60;s:1:\"y\";i:0;}'),
		(50,  'Confused', ':confused:', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:-40;s:1:\"y\";i:-21;}'),
		(60,  'Cool', ':cool:\n8-)', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:-40;s:1:\"y\";i:0;}'),
		(70,  'Stick Out Tongue', ':p\n:P\n:-p\n:-P', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:-20;s:1:\"y\";i:-21;}'),
		(80,  'Big Grin', ':D', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:-20;s:1:\"y\";i:0;}'),
		(90,  'Eek!', ':eek:\n:o', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:-20;s:1:\"y\";i:-42;}'),
		(100, 'Oops!', ':oops:', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:0;s:1:\"y\";i:-42;}'),
		(110, 'Roll Eyes', ':rolleyes:', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:0;s:1:\"y\";i:-21;}'),
		(120, 'Er... what?', 'o_O\nO_o\no.O\nO.o', 'styles/default/xenforo/xenforo-smilies-sprite.png', 1, 'a:4:{s:1:\"w\";i:18;s:1:\"h\";i:18;s:1:\"x\";i:-80;s:1:\"y\";i:-42;}')
";

$data['xf_admin_search_type'] = "
	INSERT INTO xf_admin_search_type
		(search_type, handler_class, display_order)
	VALUES
		('admin_navigation', 'XenForo_AdminSearchHandler_AdminNavigation', 0),
		('admin_template', 'XenForo_AdminSearchHandler_AdminTemplate', 710),
		('bb_code_media_site', 'XenForo_AdminSearchHandler_BbCodeMediaSite', 320),
		('feed', 'XenForo_AdminSearchHandler_Feed', 310),
		('language', 'XenForo_AdminSearchHandler_Language', 610),
		('node', 'XenForo_AdminSearchHandler_Node', 120),
		('notice', 'XenForo_AdminSearchHandler_Notice', 410),
		('option', 'XenForo_AdminSearchHandler_Option', 110),
		('phrase', 'XenForo_AdminSearchHandler_Phrase', 620),
		('promotion', 'XenForo_AdminSearchHandler_Promotion', 250),
		('smilie', 'XenForo_AdminSearchHandler_Smilie', 330),
		('style', 'XenForo_AdminSearchHandler_Style', 510),
		('style_property', 'XenForo_AdminSearchHandler_StyleProperty', 530),
		('template', 'XenForo_AdminSearchHandler_Template', 520),
		('user', 'XenForo_AdminSearchHandler_User', 210),
		('user_field', 'XenForo_AdminSearchHandler_UserField', 230),
		('user_group', 'XenForo_AdminSearchHandler_UserGroup', 220),
		('user_upgrade', 'XenForo_AdminSearchHandler_UserUpgrade', 260),
		('warning', 'XenForo_AdminSearchHandler_Warning', 240)
";

// TODO: additional media sites

		return $data;
	}
}