<?php

class XenForo_Importer_SMF extends XenForo_Importer_Abstract
{
	/**
	 * Source database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_sourceDb;

	protected $_prefix;

	protected $_charset = 'utf-8';
	protected $_defaultLang = null;

	protected $_groupMap = null;
	protected $_userMap = null;

	protected $_config;

	public static function getName()
	{
		return 'SMF 2.0 (Beta)';
	}

	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		if ($config)
		{
			$errors = $this->validateConfiguration($config, $validatedDirectories);
			if ($errors)
			{
				return $controller->responseError($errors);
			}
			else if (!isset($config['attachmentPaths']))
			{
				$attachPaths = $this->_sourceDb->fetchOne('
					SELECT value
					FROM ' . $this->_prefix . 'settings
					WHERE variable = \'attachmentUploadDir\'
				');
				$path = @unserialize($attachPaths);
				if (!$path)
				{
					if ($attachPaths)
					{
						$path = array($attachPaths);
					}
				}

				$viewParams = array(
					'attachPaths' => $path ? $path : array(),
					'config' => $config
				);

				return $controller->responseView('XenForo_ViewAdmin_Import_SMF_Config_Attachments', 'import_smf_config_attachments', $viewParams);
			}

			if ($validatedDirectories)
			{
				return true;
			}
		}
		else
		{
			$viewParams = array();
		}

		return $controller->responseView('XenForo_ViewAdmin_Import_SMF_Config', 'import_smf_config', $viewParams);
	}

	public function validateConfiguration(array &$config, &$validatedDirectories = false)
	{
		$errors = array();

		$config['db']['prefix'] = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);
		$this->_prefix = $config['db']['prefix'];

		try
		{
			$db = Zend_Db::factory('mysqli',
				array(
					'host' => $config['db']['host'],
					'port' => $config['db']['port'],
					'username' => $config['db']['username'],
					'password' => $config['db']['password'],
					'dbname' => $config['db']['dbname'],
					'charset' => 'utf8'
				)
			);
			$db->getConnection();
		}
		catch (Zend_Db_Exception $e)
		{
			$errors[] = new XenForo_Phrase('source_database_connection_details_not_correct_x', array('error' => $e->getMessage()));
		}

		if ($errors)
		{
			return $errors;
		}

		try
		{
			$db->query('
				SELECT id_member
				FROM ' . $config['db']['prefix'] . 'members
				LIMIT 1
			');
		}
		catch (Zend_Db_Exception $e)
		{
			if ($config['db']['dbname'] === '')
			{
				$errors[] = new XenForo_Phrase('please_enter_database_name');
			}
			else
			{
				$errors[] = new XenForo_Phrase('table_prefix_or_database_name_is_not_correct');
			}
		}

		$this->_sourceDb = $db;

		if (isset($config['attachmentPaths']))
		{
			if (is_array($config['attachmentPaths']))
			{
				foreach ($config['attachmentPaths'] AS $path)
				{
					if (!file_exists($path) || !is_dir($path))
					{
						$errors[] = new XenForo_Phrase('attachments_directory_x_not_found', array('path' => $path));
					}
				}
			}
			else
			{
				$errors[] = new XenForo_Phrase('import_smf_config_no_attachment_paths_entered');
			}
		}

		if (!empty($config['avatarPath']))
		{
			if (!file_exists($config['avatarPath']) || !is_dir($config['avatarPath']))
			{
				$errors[] = new XenForo_Phrase('avatars_directory_not_found');
			}
		}

		$validatedDirectories = true;

		return $errors;
	}

	public function getSteps()
	{
		return array(
			'userGroups' => array(
				'title' => new XenForo_Phrase('import_user_groups')
			),
			'users' => array(
				'title' => new XenForo_Phrase('import_users'),
				'depends' => array('userGroups')
			),
			'avatars' => array(
				'title' => new XenForo_Phrase('import_custom_avatars'),
				'depends' => array('users')
			),
			'privateMessages' => array(
				'title' => new XenForo_Phrase('import_private_messages'),
				'depends' => array('users')
			),
			'forums' => array(
				'title' => new XenForo_Phrase('import_forums'),
				'depends' => array('userGroups')
			),
			'moderators' => array(
				'title' => new XenForo_Phrase('import_forum_moderators'),
				'depends' => array('forums', 'users')
			),
			'threads' => array(
				'title' => new XenForo_Phrase('import_threads_and_posts'),
				'depends' => array('forums', 'users')
			),
			'attachments' => array(
				'title' => new XenForo_Phrase('import_attached_files'),
				'depends' => array('threads')
			)
		);
	}

	protected function _bootstrap(array $config)
	{
		if ($this->_sourceDb)
		{
			// already run
			return;
		}

		@set_time_limit(0);

		$this->_config = $config;

		$this->_sourceDb = Zend_Db::factory('mysqli',
			array(
				'host' => $config['db']['host'],
				'port' => $config['db']['port'],
				'username' => $config['db']['username'],
				'password' => $config['db']['password'],
				'dbname' => $config['db']['dbname'],
				'charset' => 'utf8'
			)
		);

		$this->_prefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);
	}

	public function stepUserGroups($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$userGroups = $sDb->fetchAll('
			SELECT *
			FROM ' . $prefix . 'membergroups
			ORDER BY id_group
		');

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($userGroups AS $userGroup)
		{
			$titlePriority = 5;

			switch ($userGroup['id_group'])
			{
				case 4: // Newbie
					$model->logImportData('userGroup', $userGroup['id_group'], XenForo_Model_User::$defaultRegisteredGroupId);
					break;

				case 1: // Administrator
					$model->logImportData('userGroup', $userGroup['id_group'], XenForo_Model_User::$defaultAdminGroupId);
					break;

				case 3: // Moderator
					$model->logImportData('userGroup', $userGroup['id_group'], XenForo_Model_User::$defaultModeratorGroupId);
					break;

				case 2: // Global Moderator
					$titlePriority = 910;
					// fall through intentionally

				default:
					$import = array(
						'title' => $this->_convertToUtf8($userGroup['group_name'], true),
						'display_style_priority' => $titlePriority,
						'permissions' => $this->_convertGlobalPermissionsForGroup($userGroup['id_group'])
					);

					if ($model->importUserGroup($userGroup['id_group'], $import))
					{
						$total++;
					}
			}
		}

		XenForo_Model::create('XenForo_Model_UserGroup')->rebuildDisplayStyleCache();

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return true;
	}

	protected function _convertGlobalPermissionsForGroup($groupId)
	{
		$smfPerms = $this->_getUserGroupPermsForUserGroup($groupId);

		// no equivalents
		$perms['general']['view'] = 'allow';
		$perms['general']['viewNode'] = 'allow';
		$perms['general']['maxTaggedUsers'] = 5;
		$perms['general']['report'] = 'allow';
		$perms['general']['editSignature'] = 'allow';
		$perms['forum']['viewOthers'] = 'allow';
		$perms['forum']['viewContent'] = 'allow';
		$perms['forum']['like'] = 'allow';
		$perms['forum']['votePoll'] = 'allow';

		$this->_setXfPermissionBasedOnPermission($perms, $smfPerms, 'general', 'viewMemberList', 'view_mlist');
		$this->_setXfPermissionBasedOnPermission($perms, $smfPerms, 'general', 'viewProfile', 'profile_view_any');
		$this->_setXfPermissionBasedOnPermission($perms, $smfPerms, 'general', 'search', 'search_posts');
		$this->_setXfPermissionBasedOnPermission($perms, $smfPerms, 'general', 'editProfile', 'profile_extra_own');
		$this->_setXfPermissionBasedOnPermission($perms, $smfPerms, 'general', 'editCustomTitle', 'profile_title_own');
		$this->_setXfPermissionBasedOnPermission($perms, $smfPerms, 'avatar', 'allow', 'profile_upload_avatar');
		$this->_setXfPermissionBasedOnPermission($perms, $smfPerms, 'conversation', 'receive', 'pm_read');
		$this->_setXfPermissionBasedOnPermission($perms, $smfPerms, 'conversation', 'start', 'pm_send');

		if (!empty($perms['general']['viewProfile'])
			&& $perms['general']['viewProfile'] == 'allow'
		)
		{
			$perms['profilePost']['view'] = 'allow';
			$perms['profilePost']['like'] = 'allow';
			$perms['profilePost']['manageOwn'] = 'allow';
			$perms['profilePost']['deleteOwn'] = 'allow';
			$perms['profilePost']['post'] = 'allow';
			$perms['profilePost']['comment'] = 'allow';
			$perms['profilePost']['editOwn'] = 'allow';
		}

		return $perms;
	}

	protected function _setXfPermissionBasedOnPermission(array &$outputPerms, array $smfPerms, $xfPermGroup, $xfPerm, $smfPerm, $allow = 'allow')
	{
		if (!isset($smfPerms[$smfPerm]) || $smfPerms[$smfPerm] == -1)
		{
			return;
		}

		if ($smfPerms[$smfPerm] == 0)
		{
			$outputPerms[$xfPermGroup][$xfPerm] = 'deny';
		}
		else if ($smfPerms[$smfPerm] == 1)
		{
			$outputPerms[$xfPermGroup][$xfPerm] = $allow;
		}
	}

	protected function _getUserGroupPermsForUserGroup($userGroupId)
	{
		return $this->_sourceDb->fetchPairs('
			SELECT permission, add_deny
			FROM ' . $this->_prefix . 'permissions
			WHERE id_group = ?
		', $userGroupId);
	}

	public function configStepUsers(array $options)
	{
		if ($options)
		{
			return false;
		}

		return $this->_controller->responseView('XenForo_ViewAdmin_Import_MyBb_ConfigUsers', 'import_config_users');
	}

	public function stepUsers($start, array $options)
	{
		$options = array_merge(array(
				'limit' => 100,
				'max' => false,
				// all checkbox options must default to false as they may not be submitted
				'mergeEmail' => false,
				'mergeName' => false,
				'gravatar' => false
			), $options
		);

		if ($options['gravatar'])
		{
			$options['limit'] = max(5, floor($options['limit'] / 2));
		}

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(id_member)
				FROM ' . $prefix . 'members
			');
		}

		$users = $sDb->fetchAll(
			$sDb->limit($this->_getSelectUserSql('members.id_member > ' . $sDb->quote($start)), $options['limit'])
		);
		if (!$users)
		{
			return $this->_getNextUserStep();
		}

		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;
		foreach ($users AS $user)
		{
			$next = $user['id_member'];

			$imported = $this->_importOrMergeUser($user, $options);
			if ($imported)
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepUsersMerge($start, array $options)
	{
		$sDb = $this->_sourceDb;

		$manual = $this->_session->getExtraData('userMerge');

		if ($manual)
		{
			$merge = $sDb->fetchAll($this->_getSelectUserSql('members.id_member IN (' . $sDb->quote(array_keys($manual)) . ')'));

			$resolve = $this->_controller->getInput()->filterSingle('resolve', XenForo_Input::ARRAY_SIMPLE);
			if ($resolve && !empty($options['shownForm']))
			{
				$this->_session->unsetExtraData('userMerge');
				$this->_resolveUserConflicts($merge, $resolve);
			}
			else
			{
				// prevents infinite loop if redirected back to step
				$options['shownForm'] = true;
				$this->_session->setStepInfo(0, $options);

				$users = array();
				foreach ($merge AS $user)
				{
					$users[$user['id_member']] = array(
						'username' => $this->_convertToUtf8($user['member_name'], true),
						'email' => $this->_convertToUtf8($user['email_address'], true),
						'message_count' => $user['posts'],
						'register_date' => $user['date_registered'],
						'conflict' => $manual[$user['id_member']]
					);
				}

				return $this->_controller->responseView(
					'XenForo_ViewAdmin_Import_MergeUsers', 'import_merge_users', array('users' => $users)
				);
			}
		}
	}

	public function stepUsersFailed($start, array $options)
	{
		$sDb = $this->_sourceDb;

		$manual = $this->_session->getExtraData('userFailed');

		if ($manual)
		{
			$users = $this->_sourceDb->fetchAll($this->_getSelectUserSql('members.id_member IN (' . $sDb->quote(array_keys($manual)) . ')'));

			$resolve = $this->_controller->getInput()->filterSingle('resolve', XenForo_Input::ARRAY_SIMPLE);
			if ($resolve && !empty($options['shownForm']))
			{
				$this->_session->unsetExtraData('userFailed');
				$this->_resolveUserConflicts($users, $resolve);
			}
			else
			{
				// prevents infinite loop if redirected back to step
				$options['shownForm'] = true;
				$this->_session->setStepInfo(0, $options);

				$failedUsers = array();
				foreach ($users AS $user)
				{
					$failedUsers[$user['id_member']] = array(
						'username' => $this->_convertToUtf8($user['member_name'], true),
						'email' => $this->_convertToUtf8($user['email_address'], true),
						'message_count' => $user['posts'],
						'register_date' => $user['date_registered'],
						'failure' => $manual[$user['id_member']]
					);
				}

				return $this->_controller->responseView(
					'XenForo_ViewAdmin_Import_FailedUsers', 'import_failed_users', array('users' => $failedUsers)
				);
			}
		}

		return $this->_getNextUserStep();
	}

	public function stepUsersFollowing($start, array $options)
	{
		$s = microtime(true);
		$targetRunTime = 10;

		$total = 0;

		$usersFollowing = $this->_session->getExtraData('userFollowing');
		if (!$usersFollowing)
		{
			$this->_session->unsetExtraData('userFollowing');
			return $this->_getNextUserStep();
		}

		XenForo_Db::beginTransaction();

		foreach ($usersFollowing AS $importedUserId => $userFollowing)
		{
			$total++;

			unset ($usersFollowing[$importedUserId]);

			$usersFollowingIds = explode(',', $userFollowing);
			$usersFollowingIds = $this->_importModel->getImportContentMap('user', $usersFollowingIds);

			$this->_importModel->importFollowing($importedUserId, $usersFollowingIds);

			if (microtime(true) - $s > $targetRunTime)
			{
				break;
			}
		}

		XenForo_Db::commit();

		$this->_session->setExtraData('userFollowing', $usersFollowing);
		$this->_session->incrementStepImportTotal($total, 'users');

		return array(1, array(), '');
	}

	public function stepUsersIgnored($start, array $options)
	{
		$s = microtime(true);
		$targetRunTime = 10;

		$total = 0;

		$usersIgnored = $this->_session->getExtraData('userIgnored');
		if (!$usersIgnored)
		{
			$this->_session->unsetExtraData('userIgnored');
			return $this->_getNextUserStep();
		}

		XenForo_Db::beginTransaction();

		foreach ($usersIgnored AS $importedUserId => $userIgnored)
		{
			$total++;

			unset ($usersIgnored[$importedUserId]);

			$usersIgnoredIds = explode(',', $userIgnored);
			$usersIgnoredIds = $this->_importModel->getImportContentMap('user', $usersIgnoredIds);

			$this->_importModel->importFollowing($importedUserId, $usersIgnoredIds);

			if (microtime(true) - $s > $targetRunTime)
			{
				break;
			}
		}

		XenForo_Db::commit();

		$this->_session->setExtraData('userIgnored', $usersIgnored);
		$this->_session->incrementStepImportTotal($total, 'users');

		return array(1, array(), '');
	}

	protected function _resolveUserConflicts(array $users, array $resolve)
	{
		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($users AS $user)
		{
			if (empty($resolve[$user['id_member']]))
			{
				continue;
			}

			$info = $resolve[$user['id_member']];

			if (empty($info['action']) || $info['action'] == 'change')
			{
				if (isset($info['email']))
				{
					$user['email_address'] = $info['email'];
				}
				if (isset($info['username']))
				{
					$user['member_name'] = $info['username'];
				}

				$imported = $this->_importOrMergeUser($user);
				if ($imported)
				{
					$total++;
				}
			}
			else if ($info['action'] == 'merge')
			{
				$im = $this->_importModel;

				if ($match = $im->getUserIdByEmail($this->_convertToUtf8($user['email_address'], true)))
				{
					$this->_mergeUser($user, $match);
				}
				else if ($match = $im->getUserIdByUserName($this->_convertToUtf8($user['member_name'], true)))
				{
					$this->_mergeUser($user, $match);
				}

				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total, 'users');
	}

	protected function _getNextUserStep()
	{
		if ($this->_session->getExtraData('userMerge'))
		{
			return 'usersMerge';
		}

		if ($this->_session->getExtraData('userFailed'))
		{
			return 'usersFailed';
		}

		if ($this->_session->getExtraData('userFollowing'))
		{
			return 'usersFollowing';
		}

		if ($this->_session->getExtraData('userIgnored'))
		{
			return 'usersIgnored';
		}

		return true;
	}

	protected function _importOrMergeUser(array $user, array $options = array())
	{
		$im = $this->_importModel;

		if ($user['email_address'] && $emailMatch = $im->getUserIdByEmail($this->_convertToUtf8($user['email_address'], true)))
		{
			if (!empty($options['mergeEmail']))
			{
				return $this->_mergeUser($user, $emailMatch);
			}
			else
			{
				if ($im->getUserIdByUserName($this->_convertToUtf8($user['member_name'], true)))
				{
					$this->_session->setExtraData('userMerge', $user['uid'], 'both');
				}
				else
				{
					$this->_session->setExtraData('userMerge', $user['uid'], 'email');
				}
				return false;
			}
		}

		$name = utf8_substr($this->_convertToUtf8(trim($user['member_name']), true), 0, 50);

		if ($nameMatch = $im->getUserIdByUserName($name))
		{
			if (!empty($options['mergeName']))
			{
				return $this->_mergeUser($user, $nameMatch);
			}
			else
			{
				$this->_session->setExtraData('userMerge', $user['uid'], 'name');
				return false;
			}
		}

		return $this->_importUser($user, $options);
	}

	protected $_userActivationSetting = array();

	protected function _importUser(array $user, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($this->_groupMap === null)
		{
			$this->_groupMap = $this->_importModel->getImportContentMap('userGroup');
		}

		if ($this->_userActivationSetting === null)
		{
			$this->_userActivationSetting = $sDb->fetchOne('
				SELECT value
				FROM ' . $prefix . 'settings
				WHERE name = \'registration_method\'
			');
		}

		$secondaryGroupIds = array();
		if (!empty($user['additional_groups']))
		{
			$secondaryGroupIds = explode(',', $user['additional_groups']);
			$secondaryGroupIds[] = $user['id_post_group'];
		}

		$user['ban'] = $this->_getUserBan($user);

		$import = array(
			'username' => $this->_convertToUtf8($user['member_name'], true),
			'email' => $this->_convertToUtf8($user['email_address'], true),
			'user_group_id' => $this->_mapLookUp($this->_groupMap, $user['id_group'], XenForo_Model_User::$defaultRegisteredGroupId),
			'secondary_group_ids' => $this->_mapLookUpList($this->_groupMap, $secondaryGroupIds, false),
			'authentication' => array(
				'scheme_class' => 'XenForo_Authentication_SMF',
				'data' => array(
					'hash' => $user['passwd'],
					'username' => strtolower($user['member_name'])
				)
			),
			'last_activity' => $user['last_login'],
			'register_date' => $user['date_registered'],
			'ip' => $user['member_ip'],
			'homepage' => $this->_convertToUtf8($user['website_url'], true),
			'message_count' => $user['posts'],
			'is_admin' => $user['id_group'] == 1 ? 1 : 0,
			'is_banned' => $user['ban'] ? 1 : 0,
			'signature' => $this->_sanitizeBbCode($user['signature']),
			'timezone' => $this->_importModel->resolveTimeZoneOffset($user['time_offset'], true),
			'visible' => $user['show_online'],
			'content_show_signature' => true,
			'receive_admin_email' => $user['notify_announcements'],
			# 'default_watch_state' => $user['auto_notify'] ? 'watch_email' : 'watch_no_email',
			'allow_send_personal_conversation' => 'everyone',
			'email_on_conversation' => $user['pm_email_notify'] ? 1 : 0,
			'user_state' => ($user['is_activated'] == 1) ? 'valid' : 'moderated'
		);

		if ($user['birthdate'])
		{
			$parts = explode('-', $user['birthdate']);
			if (count($parts) == 3)
			{
				$import['dob_day'] = trim($parts[0]);
				$import['dob_month'] = trim($parts[1]);
				$import['dob_year'] = trim($parts[2]);
			}
		}

		if (!empty($options['gravatar']))
		{
			if (!$user['avatar'] && $user['email_address'] && $user['posts']
				&& XenForo_Model_Avatar::gravatarExists($user['email_address'])
			)
			{
				$import['gravatar'] = $import['email'];
			}
		}

		$import['about'] = $this->_convertToUtf8($user['personal_text'], true);
		$import['location'] = $this->_convertToUtf8($user['location'], true);

		if ($user['website_url'] && Zend_Uri::check($user['website_url']))
		{
			$import['homepage'] = $user['website_url'];;
		}

		// custom user fields
		$userFieldDefinitions = $this->_importModel->getUserFieldDefinitions();

		$identityMap = array(
			'icq' => 'icq',
			'aim' => 'aim',
			'yahoo' => 'yim',
			'msn' => 'msn'
		);

		foreach ($identityMap AS $identityType => $smfField)
		{
			if (isset($userFieldDefinitions[$identityType]))
			{
				$import[XenForo_Model_Import::USER_FIELD_KEY][$identityType] = $this->_convertToUtf8($user[$smfField], true);
			}
		}

		if ($import['is_admin'])
		{
			// give all admin permissions
			$adminPerms = XenForo_Model::create('XenForo_Model_Admin')->getAllAdminPermissions();
			$import['admin_permissions'] =  array_keys($adminPerms);
		}

		$importedUserId = $this->_importModel->importUser($user['id_member'], $import, $failedKey);
		if ($importedUserId)
		{
			if ($user['ban'])
			{
				$this->_importModel->importBan(array(
					'user_id' => $importedUserId,
					'ban_user_id' => 0,
					'ban_date' => $user['ban']['ban_time'],
					'end_date' => $user['ban']['expire_time'],
					'user_reason' => $this->_convertToUtf8($user['ban']['reason'], true)
				));
			}

			if ($user['buddy_list'])
			{
				$this->_session->setExtraData('userFollowing', $importedUserId, $user['buddy_list']);
			}

			if ($user['pm_ignore_list'])
			{
				$this->_session->setExtraData('userIgnored', $importedUserId, $user['pm_ignore_list']);
			}
		}
		else if ($failedKey)
		{
			$this->_session->setExtraData('userFailed', $user['id_member'], $failedKey);
		}

		return $importedUserId;
	}

	protected function _getSelectUserSql($where)
	{
		return '
			SELECT members.*
			FROM ' . $this->_prefix . 'members AS members
			WHERE ' . $where . '
			ORDER BY members.id_member
		';
	}

	protected function _mergeUser(array $user, $targetUserId)
	{
		$this->_db->query('
			UPDATE xf_user SET
				message_count = message_count + ?,
				register_date = IF(register_date > ?, ?, register_date)
			WHERE user_id = ?
		', array($user['posts'], $user['date_registered'], $user['date_registered'], $targetUserId));

		$this->_importModel->logImportData('user', $user['id_member'], $targetUserId);

		return $targetUserId;
	}

	/**
	 * SMF bans users on a criteria based system that includes email,
	 * IP address (or parts thereof), or Username.
	 *
	 * Other checks exist but may be somewhat ambiguous
	 * and may be ported over to other XF tables, e.g. IP and email bans.
	 *
	 * @param $user
	 * @return array
	 */
	protected function _getUserBan(array $user)
	{
		$sDb = $this->_sourceDb;

		return $sDb->fetchRow($sDb->limit('
			SELECT banitems.*, bangroups.*
			FROM ' . $this->_prefix . 'ban_items AS banitems
			INNER JOIN ' . $this->_prefix . 'ban_groups AS bangroups ON
				(banitems.id_ban_group = bangroups.id_ban_group)
			WHERE banitems.id_member = ?
				OR (banitems.email_address = ? AND banitems.email_address <> \'\')
			ORDER BY bangroups.ban_time DESC
		', 1), array ($user['id_member'], $user['email_address']));
	}

	public function stepAvatars($start, array $options)
	{
		$options = array_merge(array(
			'avatarPath' => isset($this->_config['avatarPath']) ? $this->_config['avatarPath'] : '',
			'attachmentPaths' => isset($this->_config['attachmentPaths']) ? $this->_config['attachmentPaths'] : '',
			'limit' => 50,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(members.id_member)
				FROM ' . $prefix . 'members AS members
				LEFT JOIN ' . $prefix . 'attachments AS avatar ON
					(members.id_member = avatar.id_member
						AND avatar.attachment_type IN (0, 1))
				WHERE (members.avatar <> \'\' OR avatar.id_attach IS NOT NULL)
					AND (avatar.id_msg IS NULL OR avatar.id_msg = 0)
					AND (avatar.id_member IS NULL OR avatar.id_member <> 0)
			');
		}

		$avatars = $sDb->fetchAll($sDb->limit(
			'
				SELECT members.*, avatar.*,
					IF (avatar.id_member IS NULL, members.id_member, avatar.id_member) AS id_member
				FROM ' . $prefix . 'members AS members
				LEFT JOIN ' . $prefix . 'attachments AS avatar ON
					(members.id_member = avatar.id_member
						AND avatar.attachment_type IN (0, 1))
				WHERE members.id_member > ' . $sDb->quote($start) . '
					AND (members.avatar <> \'\' OR avatar.id_attach IS NOT NULL)
					AND (avatar.id_msg IS NULL OR avatar.id_msg = 0)
					AND (avatar.id_member IS NULL OR avatar.id_member <> 0)
				ORDER BY members.id_member
			', $options['limit']
		));

		if (!$avatars)
		{
			return true;
		}

		$userIdMap = $model->getUserIdsMapFromArray($avatars, 'id_member');

		$next = 0;
		$total = 0;

		foreach ($avatars AS $avatar)
		{
			$next = $avatar['id_member'];

			$newUserId = $this->_mapLookUp($userIdMap, $avatar['id_member']);
			if (!$newUserId)
			{
				continue;
			}

			$avatarFile = null;

			// We have a path to an avatar in the members table, otherwise it's an attachment.
			if ($avatar['avatar'])
			{
				// Only import an avatar if it is a URL, otherwise it's a gallery avatar (skip it).
				if (Zend_Uri::check($avatar['avatar']))
				{
					try
					{
						$httpClient = XenForo_Helper_Http::getClient($avatar['avatar']);

						$response = $httpClient->request('GET');

						if ($response->isSuccessful())
						{
							$avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
							file_put_contents($avatarFile, $response->getBody());
						}
					}
					catch (Zend_Http_Client_Exception $e) {}
				}
			}
			else
			{
				switch ($avatar['attachment_type'])
				{
					// Attachment directory
					case 0:

						if (!isset($options['attachmentPaths'][$avatar['id_folder']]))
						{
							break;
						}

						$attachmentPath = $options['attachmentPaths'][$avatar['id_folder']];
						$filePath = "$attachmentPath/$avatar[id_attach]_$avatar[file_hash]";
						break;

					// Custom avatar directory
					case 1:
						$filePath = "$options[avatarPath]/$avatar[filename]";
						break;

					default:
						continue;
				}

				if (!file_exists($filePath))
				{
					continue;
				}

				$avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				copy ($filePath, $avatarFile);
			}

			if ($this->_importModel->importAvatar($avatar['id_member'], $newUserId, $avatarFile))
			{
				$total++;
			}

			@unlink($avatarFile);
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepPrivateMessages($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 300,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(id_pm)
				FROM ' . $prefix . 'personal_messages
			');
		}

		$pmTexts = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM ' . $prefix . 'personal_messages
				WHERE id_pm > ' . $sDb->quote($start) . '
				ORDER BY id_pm
			', $options['limit']
		));
		if (!$pmTexts)
		{
			return true;
		}
		$next = 0;
		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($pmTexts AS $pmText)
		{
			$next = $pmText['id_pm'];

			$toUsers = $sDb->fetchPairs('
				SELECT recip.id_member, member.member_name
				FROM ' . $prefix . 'pm_recipients AS recip
				INNER JOIN ' . $prefix . 'members AS member ON
					(recip.id_member = member.id_member)
				WHERE recip.id_pm = ?
			', $next);
			if (!$toUsers)
			{
				continue;
			}

			$users = array(
				$pmText['id_member_from'] => $pmText['from_name']
			) + $toUsers;

			$mapUserIds = $model->getImportContentMap('user', array_keys($users));

			$newFromUserId = $this->_mapLookUp($mapUserIds, $pmText['id_member_from']);
			if (!$newFromUserId)
			{
				continue;
			}

			$unreadState = $sDb->fetchPairs('
				SELECT id_member, is_read
				FROM ' . $prefix . 'pm_recipients
				WHERE id_pm = ' . $sDb->quote($pmText['id_pm']) . '
					AND deleted = 0
				GROUP BY id_member
			');

			$recipients = array();
			foreach ($users AS $userId => $username)
			{
				$newUserId = $this->_mapLookUp($mapUserIds, $userId);
				if (!$newUserId)
				{
					continue;
				}

				if (isset($unreadState[$userId]))
				{
					$lastReadDate = ($unreadState[$userId] ? 0 : $pmText['msgtime']);
					$deleted = false;
				}
				else
				{
					$lastReadDate = $pmText['msgtime'];
					$deleted = true;
				}

				$recipients[$newUserId] = array(
					'username' => $this->_convertToUtf8($username, true),
					'last_read_date' => $lastReadDate,
					'recipient_state' => ($deleted ? 'deleted' : 'active')
				);
			}

			$fromUserName = $this->_convertToUtf8($pmText['from_name'], true);

			$conversation = array(
				'title' => $this->_convertToUtf8($pmText['subject'], true),
				'user_id' => $newFromUserId,
				'username' => $fromUserName,
				'start_date' => $pmText['msgtime'],
				'open_invite' => 0,
				'conversation_open' => 1
			);

			$messages = array(
				array(
					'message_date' => $pmText['msgtime'],
					'user_id' => $newFromUserId,
					'username' => $fromUserName,
					'message' => $this->_sanitizeBbCode($pmText['body'])
				)
			);

			if ($model->importConversation($pmText['id_pm'], $conversation, $recipients, $messages))
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepForums($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($start > 0)
		{
			// after importing everything, rebuild the full permission cache so forums appear
			XenForo_Model::create('XenForo_Model_Node')->updateNestedSetInfo();
			XenForo_Model::create('XenForo_Model_Permission')->rebuildPermissionCache();
			return true;
		}

		$forums = $sDb->fetchAll('
			SELECT *
			FROM ' . $prefix . 'boards
			ORDER BY id_board
		');
		$categories = $sDb->fetchAll('
			SELECT *
			FROM ' . $prefix . 'categories
			ORDER BY id_cat
		');

		$nodes = array();

		foreach ($forums AS $key => $forum)
		{
			$forums[$key] = XenForo_Application::arrayFilterKeys($forum, array(
				'id_board', 'id_cat', 'id_parent', 'board_order', 'name', 'member_groups',
				'description', 'redirect', 'num_posts', 'num_topics', 'id_profile'
			));

			$nodes[intval($forum['id_board'])] = $forum['id_board'];
		}

		// Category / forum IDs can overlap. Map them.
		$categoryIdMap = array(0 => 0);

		foreach ($categories AS $category)
		{
			$newId = intval($category['id_cat']);
			while (isset($nodes[$newId]))
			{
				$newId++;
			}
			$categoryIdMap[intval($category['id_cat'])] = $newId;
			$nodes[$newId] = $newId;

			$forums[] = array(
				'id_board' => $newId,
				'id_cat' => 0,
				'id_parent' => 0,
				'board_order' => $category['cat_order'],
				'name' => $category['name'],
				'description' => '',
				'redirect' => '',
				'num_posts' => 0,
				'num_topics' => 0,
				'id_profile' => 0,
				'member_groups' => ''
			);
		}

		if (!$forums)
		{
			return true;
		}

		ksort($forums);

		$forumTree = array();
		$forumPermissions = array();

		foreach ($forums AS $forum)
		{
			$parentId = $categoryIdMap[$forum['id_cat']];

			$forumTree[$parentId][$forum['id_board']] = $forum;

			$forumPermissionSql = $sDb->query('
				SELECT *
				FROM ' . $prefix . 'board_permissions
				WHERE id_profile = ?
			', $forum['id_profile']);
			while ($forumPermission = $forumPermissionSql->fetch())
			{
				$forumPermissions[$forum['id_board']][$forumPermission['id_group']][$forumPermission['permission']] = $forumPermission;
			}
		}

		XenForo_Db::beginTransaction();

		$total = $this->_importForumTree(0, $forumTree, $forumPermissions, array(), array_flip($categoryIdMap));

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array(1, array(), '');
	}

	protected function _importForumTree($parentId, array $forumTree, array $forumPermissions, array $forumIdMap = array(), $categoryIdMap = array())
	{
		if (!isset($forumTree[$parentId]))
		{
			return 0;
		}

		$total = 0;

		foreach ($forumTree[$parentId] AS $forum)
		{
			$import = array(
				'title' => $this->_convertToUtf8($forum['name'], true),
				'description' => $this->_sanitizeBbCode($forum['description'], null, true),
				'display_order' => $forum['board_order'],
				'parent_node_id' => $this->_mapLookUp($forumIdMap, $forum['id_parent'] ? $forum['id_parent'] : $parentId, 0),
				'display_in_list' => 1
			);

			if ($forum['redirect'])
			{
				$import['node_type_id'] = 'LinkForum';
				$import['link_url'] = $forum['redirect'];

				$nodeId = $this->_importModel->importLinkForum($forum['id_board'], $import);
			}
			else if (isset($categoryIdMap[$forum['id_board']]))
			{
				$import['node_type_id'] = 'Category';

				$nodeId = $this->_importModel->importCategory($forum['id_board'], $import);
			}
			else
			{
				$import['node_type_id'] = 'Forum';
				$import['discussion_count'] = $forum['num_topics'];
				$import['message_count'] = $forum['num_posts'];
				$import['last_post_date'] = '';
				$import['last_post_username'] = '';
				$import['last_thread_title'] = '';

				$nodeId = $this->_importModel->importForum($forum['id_board'], $import);
			}

			if ($nodeId)
			{
				if (!empty($forumPermissions[$forum['id_board']]))
				{
					$this->_importForumPermissions($nodeId, $forumPermissions[$forum['id_board']], $forum);
				}

				$forumIdMap[$forum['id_board']] = $nodeId;

				$total++;
				$total += $this->_importForumTree($forum['id_board'], $forumTree, $forumPermissions, $forumIdMap, $categoryIdMap);
			}
		}

		return $total;
	}

	protected function _importForumPermissions($nodeId, array $groupPerms, array $forum)
	{
		if ($this->_groupMap === null)
		{
			$this->_groupMap = $this->_importModel->getImportContentMap('userGroup');
		}

		XenForo_Db::beginTransaction();

		foreach ($groupPerms AS $oldGroupId => $perms)
		{
			$newGroupId = $this->_mapLookUp($this->_groupMap, $oldGroupId);
			if (!$newGroupId)
			{
				continue;
			}

			$groups = array();
			if (!empty($forum['member_groups']))
			{
				$groups = explode(',', $forum['member_groups']);
			}

			$newPerms = $this->_calculateForumPermissions($perms, in_array($oldGroupId, $groups));
			if ($newPerms)
			{
				$this->_importModel->insertNodePermissionEntries($nodeId, $newGroupId, 0, $newPerms);
			}
		}

		XenForo_Db::commit();
	}

	protected function _calculateForumPermissions(array $perms, $canView = false)
	{
		$output = array();

		// no equivalents
		$output['general']['viewNode'] = $canView ? 'content_allow' : 'reset';
		$output['forum']['viewContent'] = $canView ? 'content_allow' : 'reset';
		$output['forum']['viewOthers'] = $canView ? 'content_allow' : 'reset';

		$output['forum']['postThread'] = (!empty($perms['post_new']['add_deny']) ? 'content_allow' : 'reset');
		$output['forum']['postReply'] = (!empty($perms['post_reply_own']['add_deny']) ? 'content_allow' : 'reset');
		$output['forum']['editOwnPost'] = (!empty($perms['modify_own']['add_deny']) ? 'content_allow' : 'reset');
		$output['forum']['deleteOwnPost'] = (!empty($perms['delete_replies']['add_deny']) ? 'content_allow' : 'reset');
		$output['forum']['deleteOwnThread'] = (!empty($perms['delete_own']['add_deny']) ? 'content_allow' : 'reset');
		$output['forum']['viewAttachment'] = (!empty($perms['view_attachments']['add_deny']) ? 'content_allow' : 'reset');
		$output['forum']['uploadAttachment'] = (!empty($perms['post_attachment']['add_deny']) ? 'content_allow' : 'reset');
		$output['forum']['votePoll'] = (!empty($perms['poll_vote']['add_deny']) ? 'content_allow' : 'reset');

		return $output;
	}

	public function stepModerators($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$moderators = $sDb->fetchAll('
			SELECT members.id_member, members.id_group, members.additional_groups, mods.*,
				IF (mods.id_member IS NOT NULL, mods.id_member, members.id_member) AS id_member
			FROM ' . $prefix . 'members AS members
			LEFT JOIN ' . $prefix . 'moderators AS mods ON
				(members.id_member = mods.id_member)
			WHERE mods.id_board IS NOT NULL
				OR (members.id_group = 2 OR FIND_IN_SET(2, members.additional_groups))
		');
		if (!$moderators)
		{
			return true;
		}

		$contentModerators = array();
		$superModerators = array();
		$userIds = array();

		foreach ($moderators AS $moderator)
		{
			if ($moderator['id_board'])
			{
				$contentModerators[$moderator['id_member']][$moderator['id_board']] = $moderator;
			}
			else
			{
				$superModerators[$moderator['id_member']] = $moderator;
			}

			$userIds[] = $moderator['id_member'];
		}

		$nodeMap = $model->getImportContentMap('node');
		$userIdMap = $model->getImportContentMap('user', $userIds);

		$total = 0;

		$superModPerms = XenForo_Model::create('XenForo_Model_Moderator')->getFullPermissionSet();

		XenForo_Db::beginTransaction();

		foreach ($superModerators AS $userId => $moderator)
		{
			$newUserId = $this->_mapLookUp($userIdMap, $userId);
			if (!$newUserId)
			{
				continue;
			}

			$mod = array(
				'user_id' => $newUserId,
				'is_super_moderator' => true,
				'moderator_permissions' => $superModPerms
			);
			$model->importGlobalModerator($userId, $mod);

			$total++;
		}

		foreach ($contentModerators AS $userId => $forums)
		{
			$newUserId = $this->_mapLookUp($userIdMap, $userId);
			if (!$newUserId)
			{
				continue;
			}

			foreach ($forums AS $forumId => $moderator)
			{
				$newNodeId = $this->_mapLookUp($nodeMap, $forumId);
				if (!$newNodeId)
				{
					continue;
				}

				$forum = $sDb->fetchRow('
					SELECT id_board, id_profile
					FROM ' . $prefix . 'boards
					WHERE id_board = ?
				', $forumId);

				$forumPermissionSql = $sDb->query('
					SELECT *
					FROM ' . $prefix . 'board_permissions
					WHERE id_profile = ?
				', $forum['id_profile']);
				while ($forumPermission = $forumPermissionSql->fetch())
				{
					$forumPermissions[$forumPermission['permission']] = $forumPermission;
				}

				$mod = array(
					'user_id' => $newUserId,
					'is_super_moderator' => false,
					'moderator_permissions' => array()
				);
				$model->importGlobalModerator($userId, $mod);

				$mod = array(
					'content_id' => $newNodeId,
					'user_id' => $newUserId,
					'moderator_permissions' => $this->_convertForumPermissionsForUser($forumPermissions)
				);
				$model->importNodeModerator($forumId, $userId, $mod);


				$total++;
			}
		}

		$this->_session->incrementStepImportTotal($total);

		XenForo_Db::commit();

		return true;
	}

	protected function _convertForumPermissionsForUser($perms)
	{
		$output = array();

		$output['forum']['editAny'] = (!empty($perms['modify_any']['add_deny']) ? 'content_allow' : '');
		$output['forum']['lockUnlockThread'] = (!empty($perms['lock_any']['add_deny']) ? 'content_allow' : '');
		$output['forum']['stickUnstickThread'] = (!empty($perms['make_sticky']['add_deny']) ? 'content_allow' : '');

		if (!empty($perms['approve_posts']['add_deny']))
		{
			$output['forum']['approveUnapprove'] = 'content_allow';
			$output['forum']['viewModerated'] = 'content_allow';
		}

		if (!empty($perms['remove_any']['add_deny']))
		{
			$output['forum']['deleteAnyPost'] = 'content_allow';
			$output['forum']['deleteAnyThread'] = 'content_allow';
			$output['forum']['viewDeleted'] = 'content_allow';
			$output['forum']['undelete'] = 'content_allow';
		}

		if (!empty($perms['split_any'])
			|| !empty($perms['move_any'])
			|| !empty($perms['merge_any'])
		)
		{
			$output['forum']['manageAnyThread'] = 'content_allow';
		}

		return $output;
	}

	public function stepThreads($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'postDateStart' => 0,
			'postLimit' => 800,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(id_topic)
				FROM ' . $prefix . 'topics
			'
			);
		}

		// pull threads from things we actually imported as forums
		$threads = $sDb->fetchAll($sDb->limit(
			'
				SELECT topics.*, fp.subject, fp.poster_time,
					lp.poster_time AS last_post_date, lp.poster_name AS last_post_username,
					IF (members.member_name IS NOT NULL, members.member_name, fp.poster_name) AS member_name
				FROM ' . $prefix . 'topics AS topics FORCE INDEX (PRIMARY)
				LEFT JOIN ' . $prefix . 'members AS members ON
					(topics.id_member_started = members.id_member)
				INNER JOIN ' . $prefix . 'messages AS fp ON
					(topics.id_first_msg = fp.id_msg)
				INNER JOIN ' . $prefix . 'messages AS lp ON
					(topics.id_last_msg = lp.id_msg)
				INNER JOIN ' . $prefix . 'boards AS boards ON
					(topics.id_board = boards.id_board)
				WHERE topics.id_topic >= ' . $sDb->quote($start) . '
					AND boards.redirect = \'\'
				ORDER BY topics.id_topic
			', $options['limit']
		));
		if (!$threads)
		{
			return true;
		}

		$next = 0;
		$total = 0;
		$totalPosts = 0;

		$nodeMap = $model->getImportContentMap('node');

		XenForo_Db::beginTransaction();

		foreach ($threads AS $thread)
		{
			if (trim($thread['subject']) === '')
			{
				continue;
			}

			$postDateStart = $options['postDateStart'];

			$next = $thread['id_topic'] + 1; // uses >=, will be moved back down if need to continue
			$options['postDateStart'] = 0;

			$maxPosts = $options['postLimit'] - $totalPosts;
			$posts = $sDb->fetchAll($sDb->limit(
				'
					SELECT messages.*,
						IF(members.member_name IS NOT NULL, members.member_name, messages.poster_name) AS member_name
					FROM ' . $prefix . 'messages AS messages
					LEFT JOIN ' . $prefix . 'members AS members ON (messages.id_member = members.id_member)
					WHERE messages.id_topic = ' . $sDb->quote($thread['id_topic']) . '
						AND messages.poster_time > ' . $sDb->quote($postDateStart) . '
					ORDER BY messages.poster_time
				', $maxPosts
			));

			if (!$posts)
			{
				if ($postDateStart)
				{
					// continuing thread but it has no more posts
					$total++;
				}
				continue;
			}

			if ($postDateStart)
			{
				// continuing thread we already imported
				$threadId = $model->mapThreadId($thread['id_topic']);

				$position = $this->_db->fetchOne('
					SELECT MAX(position)
					FROM xf_post
					WHERE thread_id = ?
				', $threadId);
			}
			else
			{
				$forumId = $this->_mapLookUp($nodeMap, $thread['id_board']);
				if (!$forumId)
				{
					continue;
				}

				if (trim($thread['member_name']) === '')
				{
					$thread['member_name'] = 'Guest';
				}

				$import = array(
					'title' => $this->_convertToUtf8($thread['subject'], true),
					'node_id' => $forumId,
					'user_id' => $model->mapUserId($thread['id_member_started'], 0),
					'username' => $this->_convertToUtf8($thread['member_name'], true),
					'discussion_open' => ($thread['locked'] == 0 ? 1 : 0),
					'post_date' => $thread['poster_time'],
					'reply_count' => $thread['num_replies'],
					'view_count' => $thread['num_views'],
					'sticky' => ($thread['is_sticky'] == 1 ? 1 : 0),
					'last_post_date' => $thread['last_post_date'],
					'last_post_username' => $this->_convertToUtf8($thread['last_post_username'], true),
				);
				if ($thread['approved'])
				{
					$import['discussion_state'] = 'visible';
				}
				else
				{
					$import['discussion_state'] = 'moderated';
				}

				$threadId = $model->importThread($thread['id_topic'], $import);
				if (!$threadId)
				{
					continue;
				}

				if ($thread['id_poll'])
				{
					$poll = $sDb->fetchRow('
						SELECT *
						FROM ' . $prefix . 'polls
						WHERE id_poll = ?
					', $thread['id_poll']);

					$responses = $sDb->fetchPairs('
						SELECT id_choice, label
						FROM ' . $prefix . 'poll_choices
						WHERE id_poll = ?
					', $thread['id_poll']);
					foreach ($responses AS &$value)
					{
						$value = $this->_sanitizeBbCode($value, null, true);
					}

					$import = array(
						'question' => $this->_sanitizeBbCode($poll['question'], true),
						'public_votes' => $poll['hide_results'],
						'max_votes' => $poll['max_votes'] ? 1 : 0,
						'change_vote' => $poll['change_vote'],
						'close_date' => $poll['expire_time']
					);

					$newPollId = $model->importThreadPoll($thread['id_topic'], $threadId, $import, $responses, $responseIds);
					if ($newPollId)
					{
						$votes = $sDb->fetchAll('
							SELECT id_member, id_choice
							FROM ' . $prefix . 'log_polls
							WHERE id_poll = ' . $sDb->quote($thread['id_poll'])
						);

						$userIdMap = $model->getUserIdsMapFromArray($votes, 'id_member');
						foreach ($votes AS $vote)
						{
							$userId = $this->_mapLookUp($userIdMap, $vote['id_member']);
							if (!$userId)
							{
								continue;
							}

							$voteOption = $vote['id_choice'];
							if (!isset($responseIds[$voteOption]))
							{
								continue;
							}

							$model->importPollVote($newPollId, $userId, $responseIds[$voteOption], 0);
						}
					}
				}

				$position = -1;

				$subs = $sDb->fetchCol('
					SELECT id_member
					FROM ' . $prefix . 'log_notify
					WHERE id_topic = ' . $sDb->quote($thread['id_topic'])
				);
				if ($subs)
				{
					$userIdMap = $model->getImportContentMap('user', array_keys($subs));
					foreach ($subs AS $userId => $emailUpdate)
					{
						$newUserId = $this->_mapLookUp($userIdMap, $userId);
						if (!$newUserId)
						{
							continue;
						}

						$model->importThreadWatch($newUserId, $threadId, ($emailUpdate ? 1 : 0));
					}
				}
			}

			if ($threadId)
			{
				$quotedPostIds = array();

				$threadTitleRegex = '#^(re:\s*)?' . preg_quote($thread['subject'], '#') . '$#i';

				$userIdMap = $model->getUserIdsMapFromArray($posts, 'id_member');

				foreach ($posts AS $i => $post)
				{
					if ($post['subject'] !== '' && !preg_match($threadTitleRegex, $post['subject']))
					{
						$post['body'] = '[b]' . $post['subject'] . "[/b]\n\n" . ltrim($post['body']);
					}

					if (trim($post['member_name']) === '')
					{
						$post['member_name'] = 'Guest';
					}

					$post['body'] = $this->_sanitizeBbCode($post['body']);

					$import = array(
						'thread_id' => $threadId,
						'user_id' => $this->_mapLookUp($userIdMap, $post['id_member'], 0),
						'username' => $this->_convertToUtf8($post['member_name'], true),
						'post_date' => $post['poster_time'],
						'message' => $post['body'],
						'attach_count' => 0,
						'ip' => $post['poster_ip']
					);
					if ($post['approved'])
					{
						$import['message_state'] = 'visible';
						$import['position'] = ++$position;
					}
					else
					{
						$import['message_state'] = 'moderated';
						$import['position'] = $position;
					}

					$model->importPost($post['id_msg'], $import);

					$options['postDateStart'] = $post['poster_time'];
					$totalPosts++;
				}

				if (count($posts) < $maxPosts)
				{
					// done this thread
					$total++;
					$options['postDateStart'] = 0;
				}
				else
				{
					// not necessarily done the thread; need to pick it up next page
					break;
				}
			}

			if (count($posts) < $maxPosts)
			{
				// done this thread
				$total++;
				$options['postDateStart'] = 0;
			}
			else
			{
				// not necessarily done the thread; need to pick it up next page
				break;
			}
		}

		if ($options['postDateStart'])
		{
			// not done this thread, need to continue with it
			$next--;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next - 1, $options['max']));
	}

	public function stepAttachments($start, array $options)
	{
		$options = array_merge(array(
			'attachmentPaths' => isset($this->_config['attachmentPaths']) ? $this->_config['attachmentPaths'] : '',
			'limit' => 50,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(id_attach)
				FROM ' . $prefix . 'attachments
			');
		}

		$attachments = $sDb->fetchAll($sDb->limit(
			'
				SELECT attachments.*, messages.id_member, messages.poster_time
				FROM ' . $prefix . 'attachments AS attachments
				INNER JOIN ' . $prefix . 'messages AS messages ON
				 	(messages.id_msg = attachments.id_msg)
				WHERE attachments.id_attach > ' . $sDb->quote($start) . '
					AND attachments.id_msg > 0
					AND attachments.id_member = 0
					AND attachments.attachment_type = 0
				ORDER BY attachments.id_attach
			', $options['limit']
		));
		if (!$attachments)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIdMap = $model->getUserIdsMapFromArray($attachments, 'id_member');
		$postIdMap = $model->getPostIdsMapFromArray($attachments, 'id_msg');

		foreach ($attachments AS $attachment)
		{
			$next = $attachment['id_attach'];

			$newPostId = $this->_mapLookUp($postIdMap, $attachment['id_msg']);
			if (!$newPostId)
			{
				continue;
			}

			$attachmentPath = $options['attachmentPaths'][$attachment['id_folder']];
			$filePath = "$attachmentPath/$attachment[id_attach]_$attachment[file_hash]";

			if (!file_exists($filePath))
			{
				continue;
			}

			$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			copy($filePath, $attachFile);

			$success = $model->importPostAttachment(
				$attachment['id_attach'],
				$this->_convertToUtf8($attachment['filename'], true),
				$attachFile,
				$this->_mapLookUp($userIdMap, $attachment['id_member'], 0),
				$newPostId,
				$attachment['poster_time'],
				array('view_count' => $attachment['downloads'])
			);
			if ($success)
			{
				$total++;
			}

			@unlink($attachFile);
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _sanitizeBbCode($string, $strip = false)
	{
		$string = $this->_convertToUtf8($string, true);

		// Handles <br /> in message content
		$string = preg_replace('#<br\s*/?>#i', "\n", $string);

		// Handles quotes
		$string = preg_replace('#\[quote\sauthor=([a-z0-9\s]+)\slink[^\]]+]#siU', '[quote="$1"]', $string);

		// Handles sizes
		$string = preg_replace_callback(
			'#\[size=([^\]]+)(pt|px|)\](.*)\[/size\]#siU',
			array($this, '_handleBbCodeSizeCallback'),
			$string
		);

		// Handles list items
		$string = str_ireplace('[li]', '[*]', $string);
		$string = str_ireplace('[/li]', '', $string);

		// Handles FTP tags (converts to URLs)
		$string = str_ireplace('[ftp', '[URL', $string);
		$string = str_ireplace('[/ftp]', '[/URL]', $string);

		// close enough to code
		$string = preg_replace('#\[(pre|tt)\](.*)\[/(pre|tt)\]#siU', '[CODE]$2[/CODE]', $string);

		// more or less a URL
		$string = preg_replace('#\[ftp\](.*)\[/ftp\]#siU', '[URL]$1[/URL]', $string);

		// no equivalents, strip
		$string = preg_replace('#\[hr\]#siU', '', $string);
		$string = preg_replace('#\[move\](.*)\[/move\]#siU', '$1', $string);
		$string = preg_replace('#\[sup\](.*)\[/sup\]#siU', '$1', $string);
		$string = preg_replace('#\[sub\](.*)\[/sub\]#siU', '$1', $string);
		$string = preg_replace('#\[glow=([^\]]+)\](.*)\[/glow\]#siU', '$2', $string);
		$string = preg_replace('#\[shadow=([^\]]+)\](.*)\[/shadow\]#siU', '$2', $string);

		$string = preg_replace(
			'#\[([a-z0-9_\*]+(="[^"]*"|=[^\]]*)?)\]#siU',
			($strip ? '' : '[$1]'),
			$string
		);
		$string = preg_replace(
			'#\[/([a-z0-9_\*]+)(:[a-z])?\]#siU',
			($strip ? '' : '[/$1]'),
			$string
		);

		return $string;
	}


	protected function _handleBbCodeSizeCallback(array $match)
	{
		$size = $match[1];
		$unit = $match[2];
		$text = $match[3];

		// Converts pt sizes to px (approximately)...
		$size = ($unit == 'pt') ? round($size * 1.333333) : $size;
		$unit = ($unit == 'pt') ? 'px' : $unit;

		return '[SIZE=' . strval($size) . $unit . ']' . $text . '[/SIZE]';
	}
}