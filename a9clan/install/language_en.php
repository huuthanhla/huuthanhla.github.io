<?php

return array(
	'admin_templates' => 'Admin Templates',
	'admin_templates_importing' => 'Admin Templates (Importing)',
	'core_master_data' => 'Core Master Data',
	'email_templates' => 'Email Templates',
	'email_templates_importing' => 'Email Templates (Importing)',
	'permissions' => 'Permissions',
	'phrases' => 'Phrases',
	'phrases_importing' => 'Phrases (Importing)',
	'templates' => 'Templates',
	'templates_importing' => 'Templates (Importing)',
	'you_have_completed_installation_to_reinstall' => 'You have already completed installation. If you wish to reinstall, please delete the file internal_data/install-lock.php.',
	'you_cannot_proceed_unless_tables_removed' => 'You cannot proceed unless all XenForo database tables are removed.',
	'config_file_x_could_not_be_found' => 'The configuration file {file} could not be found.',
	'following_error_occurred_while_connecting_database' => '
		<div class="baseHtml">
			<p>The following error occurred while connecting to the database:</p>
			<blockquote><b>{error}</b></blockquote>
			<p>This indicates that your configuration information is not correct. Please check the values you have entered. If you are unsure what values are correct or how to proceed, please contact your host for help. These values are specific to your server.</p>
		</div>
	',
	'you_do_not_have_permission_upgrade' => 'You do not have permission to upgrade XenForo. If you are getting this unexpectedly, you should make yourself a super admin via library/config.php.',
	'uh_oh_upgrade_did_not_complete' => 'Uh oh! The upgrade did not complete successfully. <a href="index.php">Please try again.</a>',
	'you_do_not_have_permission_view_page' => 'You do not have permission to view this page or perform this action.',
	'php_version_x_does_not_meet_requirements' => 'PHP 5.2.11 or newer is required. {version} does not meet this requirement. Please ask your host to upgrade PHP.',
	'php_must_not_be_in_safe_mode' => 'PHP must not be running in safe_mode. Please ask your host to disable the PHP safe_mode setting.',
	'required_php_extension_x_not_found' => 'The required PHP extension {extension} could not be found. Please ask your host to install this extension.',
	'gd_jpeg_support_missing' => 'The required PHP extension GD was found, but JPEG support is missing. Please ask your host to add support for JPEG images.',
	'pcre_unicode_support_missing' => 'The required PHP extension PCRE was found, but Unicode support is missing. Please ask your host to add support for Unicode to PCRE.',
	'required_php_xml_extensions_not_found' => 'The required PHP extensions for XML handling (DOM and SimpleXML) could not be found. Please ask your host to install these extensions.',
	'mysql_version_x_does_not_meet_requirements' => 'MySQL 5.0 or newer is required. {version} does not meet this requirement. Please ask your host to upgrade MySQL.',
	'directory_x_must_be_writable' => 'The directory {directory} must be writable. Please change the permissions on this directory to be world writable (chmod 0777). If the directory does not exist, please create it.',
	'all_directories_under_x_must_be_writable' => 'All directories under {directory} must be writable. Please change the permissions on these directories to be world writable (chmod 0777).',
	'pcre_unicode_property_support_missing' => 'The required PHP extension PCRE was found with Unicode support, but Unicode character property support is missing.',
	'php_version_x_outdated_upgrade' => 'Your server is running an outdated and unsupported version of PHP ({version}). If possible, you should upgrade to PHP 5.4 or 5.5.',
	'php_functions_disabled_impossible_check' => 'Your server has disabled functions that make it impossible to detect server information. Other errors may occur.',
	'php_functions_disabled_fundamental' => 'Your server has disabled fundamental core PHP functions via the disable_functions directive in php.ini. This will cause unexpected problems in XenForo. All PHP functions should be enabled.',
	'php_functions_disabled_warning' => 'Your server has disabled core PHP functions via the disable_functions directive in php.ini. Depending on the functions that have been disabled, this may cause unexpected problems in XenForo. All PHP functions should be enabled.',
	'php_no_ssl_support' => 'Your PHP does not have support for SSL connections. This may interfere with integrations into external services, such as Facebook.',

	'upgrade_found_newer_than_version' => 'An upgrade was found for a version of XenForo that is newer than the uploaded files. Please reupload all of the files for the new version and reload this page.',
	'rebuilding' => 'Rebuilding',
	'processing' => 'Processing'
);