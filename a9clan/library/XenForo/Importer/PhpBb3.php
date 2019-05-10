<?php

class XenForo_Importer_PhpBb3 extends XenForo_Importer_Abstract
{
	/**
	 * Source database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_sourceDb;

	protected $_prefix;

	protected $_charset = 'utf-8';
	protected $_defaultLang = 'en';
	protected $_defaultLangId = 1;

	protected $_groupMap = null;
	protected $_userFieldMap = null;

	protected $_config;

	public static function getName()
	{
		return 'phpBB 3.0';
	}

	public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
	{
		if ($config)
		{
			$errors = $this->validateConfiguration($config);
			if ($errors)
			{
				return $controller->responseError($errors);
			}

			return true;
		}
		else
		{
			$viewParams = array();
		}

		return $controller->responseView('XenForo_ViewAdmin_Import_PhpBb3_Config', 'import_phpbb3_config', $viewParams);
	}

	public function validateConfiguration(array &$config)
	{
		$errors = array();

		$config['db']['prefix'] = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

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
				SELECT user_id
				FROM ' . $config['db']['prefix'] . 'users
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

		if (!file_exists($config['attachmentPath']) || !is_dir($config['attachmentPath']))
		{
			$errors[] = new XenForo_Phrase('attachments_directory_not_found');
		}

		if (!file_exists($config['avatarPath']) || !is_dir($config['avatarPath']))
		{
			$errors[] = new XenForo_Phrase('avatars_directory_not_found');
		}

		return $errors;
	}

	public function getSteps()
	{
		return array(
			'userGroups' => array(
				'title' => new XenForo_Phrase('import_user_groups')
			),
			'userFields' => array(
				'title' => new XenForo_Phrase('import_custom_user_fields'),
			),
			'users' => array(
				'title' => new XenForo_Phrase('import_users'),
				'depends' => array('userGroups', 'userFields')
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
			'forumPermissions' => array(
				'title' => new XenForo_Phrase('import_forum_permissions'),
				'depends' => array('forums')
			),
			'moderators' => array(
				'title' => new XenForo_Phrase('import_forum_moderators'),
				'depends' => array('forums', 'users')
			),
			'threads' => array(
				'title' => new XenForo_Phrase('import_threads_and_posts'),
				'depends' => array('forums', 'users')
			),
			'polls' => array(
				'title' => new XenForo_Phrase('import_polls'),
				'depends' => array('threads')
			),
			'attachments' => array(
				'title' => new XenForo_Phrase('import_attached_files'),
				'depends' => array('threads')
			),
		);

		// TODO: admins and admin permissions, super moderators and permissions, ideally some sort of permission import
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

		$this->_defaultLang = $this->_sourceDb->fetchOne("
			SELECT config_value
			FROM " . $this->_prefix . "config
			WHERE config_name = 'default_lang'
		");
		$this->_defaultLangId = $this->_sourceDb->fetchOne('
			SELECT lang_id
			FROM ' . $this->_prefix . 'lang
			WHERE lang_iso = ' . $this->_sourceDb->quote($this->_defaultLang)
		);
	}

	public function stepUserGroups($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$userGroups = $sDb->fetchAll('
			SELECT *
			FROM ' . $prefix . 'groups
			ORDER BY group_id
		');

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($userGroups AS $userGroup)
		{
			$titlePriority = 5;

			switch ($userGroup['group_id'])
			{
				case 1: // guests
				case 6: // bots
					$model->logImportData('userGroup', $userGroup['group_id'], XenForo_Model_User::$defaultGuestGroupId);
					break;

				case 2: // registered users
				case 3: // coppa
					$model->logImportData('userGroup', $userGroup['group_id'], XenForo_Model_User::$defaultRegisteredGroupId);
					break;

				case 5: // admins
					$model->logImportData('userGroup', $userGroup['group_id'], XenForo_Model_User::$defaultAdminGroupId);
					continue;

				case 4: // super mods
					$titlePriority = 910;
					// fall through intentionally

				default:
					$import = array(
						'title' => $this->_convertToUtf8($userGroup['group_name'], true),
						'display_style_priority' => $titlePriority,
						'permissions' => $this->_convertGlobalPermissionsForGroup($userGroup['group_id'])
					);

					if ($model->importUserGroup($userGroup['group_id'], $import))
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
		$phpBbPerms = $this->_getGroupPermissions($groupId, 0);
		$perms = array();

		// no equivalents
		$perms['general']['view'] = 'allow';
		$perms['general']['viewNode'] = 'allow';
		$perms['general']['followModerationRules'] = 'allow';
		$perms['general']['report'] = 'allow';
		$perms['forum']['viewOthers'] = 'allow';
		$perms['forum']['viewContent'] = 'allow';
		$perms['forum']['like'] = 'allow';
		$perms['forum']['votePoll'] = 'allow';
		$perms['conversation']['receive'] = 'allow';

		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'general', 'editSignature', 'u_sig');
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'general', 'bypassFloodCheck', 'u_ignoreflood');
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'general', 'bypassUserPrivacy', 'u_viewonline');
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'general', 'viewProfile', 'u_viewprofile');
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'general', 'viewMemberList', 'u_viewprofile');
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'general', 'search', 'u_search');
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'forum', 'uploadAttachment', 'u_attach');
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'forum', 'viewAttachment', 'u_download');
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'avatar', 'allowed', 'u_chgavatar');
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'conversation', 'start', 'u_sendpm');
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'conversation', 'editOwnPost', 'u_pm_edit');
		if ($this->_hasPhpBbPerm($phpBbPerms, 'u_pm_edit'))
		{
			$perms['conversation']['editOwnPostTimeLimit'] = 5;
		}
		if ($this->_hasPhpBbPerm($phpBbPerms, 'u_viewprofile'))
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

	protected function _getGroupPermissions($ids, $forumId = 0)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		$perms = array();

		if (!is_array($ids))
		{
			$ids = array($ids);
		}
		if (!$ids)
		{
			return array();
		}

		$groupPermsResult = $sDb->query('
			SELECT o.auth_option, g.auth_setting
			FROM ' . $prefix . 'acl_groups AS g
			INNER JOIN ' . $prefix . 'acl_options AS o ON (g.auth_option_id = o.auth_option_id)
			WHERE g.group_id IN (' . $sDb->quote($ids) . ')
				AND g.auth_option_id > 0
				AND g.forum_id = ' . $sDb->quote($forumId) . '
		');
		while ($perm = $groupPermsResult->fetch())
		{
			$this->_mergePermissions($perms, $perm);
		}

		$rolePermsResult = $sDb->query('
			SELECT o.auth_option, r.auth_setting
			FROM ' . $prefix . 'acl_groups AS g
			INNER JOIN ' . $prefix . 'acl_roles_data AS r ON (g.auth_role_id = r.role_id)
			INNER JOIN ' . $prefix . 'acl_options AS o ON (r.auth_option_id = o.auth_option_id)
			WHERE g.group_id IN (' . $sDb->quote($ids) . ')
				AND g.auth_role_id > 0
				AND g.forum_id = ' . $sDb->quote($forumId) . '
		');
		while ($perm = $rolePermsResult->fetch())
		{
			$this->_mergePermissions($perms, $perm);
		}

		return $perms;
	}

	protected function _getUserPermissions($id, $forumId = 0)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		$perms = array();

		$userPermsResult = $sDb->query('
			SELECT o.auth_option, u.auth_setting
			FROM ' . $prefix . 'acl_users AS u
			INNER JOIN ' . $prefix . 'acl_options AS o ON (u.auth_option_id = o.auth_option_id)
			WHERE u.user_id = ' . $sDb->quote($id) . '
				AND u.auth_option_id > 0
				AND u.forum_id = ' . $sDb->quote($forumId) . '
		');
		while ($perm = $userPermsResult->fetch())
		{
			$this->_mergePermissions($perms, $perm);
		}

		$rolePermsResult = $sDb->query('
			SELECT o.auth_option, r.auth_setting
			FROM ' . $prefix . 'acl_users AS u
			INNER JOIN ' . $prefix . 'acl_roles_data AS r ON (u.auth_role_id = r.role_id)
			INNER JOIN ' . $prefix . 'acl_options AS o ON (r.auth_option_id = o.auth_option_id)
			WHERE u.user_id = ' . $sDb->quote($id) . '
				AND u.auth_role_id > 0
				AND u.forum_id = ' . $sDb->quote($forumId) . '
		');
		while ($perm = $rolePermsResult->fetch())
		{
			$this->_mergePermissions($perms, $perm);
		}

		$groups = $sDb->fetchCol('
			SELECT group_id
			FROM ' . $prefix . 'user_group
			WHERE user_id = ' . $sDb->quote($id)
		);
		$groupPerms = $this->_getGroupPermissions($groups, $forumId);
		foreach ($groupPerms AS $permId => $permSetting)
		{
			$perm = array('auth_option' => $permId, 'auth_setting' => $permSetting);
			$this->_mergePermissions($perms, $perm);
		}

		return $perms;
	}

	protected function _mergePermissions(array &$perms, array $perm)
	{
		if (!isset($perms[$perm['auth_option']]))
		{
			$perms[$perm['auth_option']] = intval($perm['auth_setting']);
		}
		else if ($perms[$perm['auth_option']] == 0 || $perm['auth_option'] == 0)
		{
			$perms[$perm['auth_option']] = 0;
		}
		else if ($perm['auth_option'] == 1)
		{
			$perms[$perm['auth_option']] = 1;
		}
		else
		{
			$perms[$perm['auth_option']] = -1;
		}
	}

	protected function _hasPhpBbPerm(array $phpBbPerms, $perm)
	{
		return (isset($phpBbPerms[$perm]) && $phpBbPerms[$perm] == 1);
	}

	protected function _setXfPermissionBasedOnPermission(array &$outputPerms, array $phpBbPerms, $xfPermGroup, $xfPerm, $phpBbPerm, $allow = 'allow')
	{
		if (!isset($phpBbPerms[$phpBbPerm]) || $phpBbPerms[$phpBbPerm] == -1)
		{
			return;
		}

		if ($phpBbPerms[$phpBbPerm] == 0)
		{
			$outputPerms[$xfPermGroup][$xfPerm] = 'deny';
		}
		else if ($phpBbPerms[$phpBbPerm] == 1)
		{
			$outputPerms[$xfPermGroup][$xfPerm] = $allow;
		}
	}

	public function stepUserFields($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$profileFields = $sDb->fetchAll('
			SELECT pf.*, pl.*
			FROM ' . $prefix . 'profile_fields AS pf
			INNER JOIN ' . $prefix . 'profile_lang AS pl ON
				(pf.field_id = pl.field_id AND pl.lang_id = ' . $sDb->quote($this->_defaultLangId) . ')
		');

		$profileFieldOptions = array();
		$pfoResult = $sDb->query('
			SELECT *
			FROM ' . $prefix . 'profile_fields_lang
			WHERE lang_id = ' . $sDb->quote($this->_defaultLangId)
		);
		while ($pfo = $pfoResult->fetch())
		{
			$profileFieldOptions[$pfo['field_id']][$pfo['option_id']] = $this->_convertToUtf8($pfo['lang_value'], true);
		}

		$existingFields = XenForo_Model::create('XenForo_Model_UserField')->getUserFields();

		$userFieldLookups = array();
		$total = 0;

		XenForo_Db::beginTransaction($this->_db);

		foreach ($profileFields AS $profileField)
		{
			$title = $this->_convertToUtf8($profileField['lang_name'], true);
			$description = $this->_convertToUtf8($profileField['lang_explain'], true);

			$fieldId = $model->getUniqueFieldId(
				str_replace('-', '_', XenForo_Link::getTitleForUrl($profileField['field_name'], true)),
				$existingFields,
				25
			);

			$import = array(
				'field_id' => $fieldId,
				'title' => $title,
				'description' => $description,
				'display_order' => $profileField['field_order'],
				'max_length' => $profileField['field_maxlen'],
				'viewable_profile' => !$profileField['field_no_view'],
				'user_editable' => $profileField['field_show_profile'] ? 'yes' : 'never',
				'viewable_message' => (isset($profileField['field_show_on_vt']) ? (bool)$profileField['field_show_on_vt'] : 0),
				'show_registration' => (bool)$profileField['field_show_on_reg'],
				'required' => (bool)$profileField['field_required']
			);

			if ($profileField['field_validation'] && $profileField['field_validation'] != '.*')
			{
				$import['match_type'] = 'regex';
				$import['match_regex'] = '^' . $this->_convertToUtf8($profileField['field_validation']) . '$';
			}

			switch ($profileField['field_type'])
			{
				case 1: // numbers
					$import['field_type'] = 'textbox';
					$import['match_type'] = 'number';
					break;

				case 2: // text box
					$import['field_type'] = 'textbox';
					break;

				case 3: // text area
					$import['field_type'] = 'textarea';
					break;

				case 4: // boolean
				case 5: // drop down
					$import['field_type'] = ($profileField['field_type'] == 4 ? 'radio' : 'select');
					if (empty($profileFieldOptions[$profileField['field_id']]))
					{
						continue;
					}
					$import['field_choices'] = $profileFieldOptions[$profileField['field_id']];
					$userFieldLookups[$profileField['field_name']] = $profileFieldOptions[$profileField['field_id']];
					break;

				case 6: // date
					$import['field_type'] = 'textbox';
					break;

				default:
					$import['field_type'] = 'textbox';
					break;
			}

			if ($imported = $model->importUserField($profileField['field_name'], $import))
			{
				$total++;
			}
		}

		XenForo_Db::commit($this->_db);

		$this->_session->setExtraData('userFieldLookups', $userFieldLookups);
		$this->_session->incrementStepImportTotal($total);

		return true;
	}

	public function configStepUsers(array $options)
	{
		if ($options)
		{
			return false;
		}

		return $this->_controller->responseView('XenForo_ViewAdmin_Import_PhpBb3_ConfigUsers', 'import_config_users');
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
		), $options);

		if ($options['gravatar'])
		{
			$options['limit'] = max(5, floor($options['limit'] / 2));
		}

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(user_id)
				FROM ' . $prefix . 'users
			');
		}

		$users = $sDb->fetchAll(
			$sDb->limit($this->_getSelectUserSql('users.user_id > ' . $sDb->quote($start)), $options['limit'])
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
			$next = $user['user_id'];

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
			$merge = $sDb->fetchAll($this->_getSelectUserSql('users.user_id IN (' . $sDb->quote(array_keys($manual)) . ')'));

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
					$users[$user['user_id']] = array(
						'username' => $this->_convertToUtf8($user['username'], true),
						'email' => $this->_convertToUtf8($user['user_email'], true),
						'message_count' => $user['user_posts'],
						'register_date' => $user['user_regdate'],
						'conflict' => $manual[$user['user_id']]
					);
				}

				return $this->_controller->responseView(
					'XenForo_ViewAdmin_Import_MergeUsers', 'import_merge_users', array('users' => $users)
				);
			}
		}

		return $this->_getNextUserStep();
	}

	public function stepUsersFailed($start, array $options)
	{
		$sDb = $this->_sourceDb;

		$manual = $this->_session->getExtraData('userFailed');

		if ($manual)
		{
			$users = $this->_sourceDb->fetchAll($this->_getSelectUserSql('users.user_id IN (' . $sDb->quote(array_keys($manual)) . ')'));

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
					$failedUsers[$user['user_id']] = array(
						'username' => $this->_convertToUtf8($user['username'], true),
						'email' => $this->_convertToUtf8($user['user_email'], true),
						'message_count' => $user['user_posts'],
						'register_date' => $user['user_regdate'],
						'failure' => $manual[$user['user_id']]
					);
				}

				return $this->_controller->responseView(
					'XenForo_ViewAdmin_Import_FailedUsers', 'import_failed_users', array('users' => $failedUsers)
				);
			}
		}

		return $this->_getNextUserStep();
	}

	protected function _resolveUserConflicts(array $users, array $resolve)
	{
		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($users AS $user)
		{
			if (empty($resolve[$user['user_id']]))
			{
				continue;
			}

			$info = $resolve[$user['user_id']];

			if (empty($info['action']) || $info['action'] == 'change')
			{
				if (isset($info['email']))
				{
					$user['user_email'] = $info['email'];
				}
				if (isset($info['username']))
				{
					$user['username'] = $info['username'];
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

				if ($match = $im->getUserIdByEmail($this->_convertToUtf8($user['user_email'], true)))
				{
					$this->_mergeUser($user, $match);
				}
				else if ($match = $im->getUserIdByUserName($this->_convertToUtf8($user['username'], true)))
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

		return true;
	}

	protected function _importOrMergeUser(array $user, array $options = array())
	{
		$im = $this->_importModel;

		if ($user['user_email'] && $emailMatch = $im->getUserIdByEmail($this->_convertToUtf8($user['user_email'], true)))
		{
			if (!empty($options['mergeEmail']))
			{
				return $this->_mergeUser($user, $emailMatch);
			}
			else
			{
				if ($im->getUserIdByUserName($this->_convertToUtf8($user['username'], true)))
				{
					$this->_session->setExtraData('userMerge', $user['user_id'], 'both');
				}
				else
				{
					$this->_session->setExtraData('userMerge', $user['user_id'], 'email');
				}
				return false;
			}
		}

		$name = utf8_substr($this->_convertToUtf8(trim($user['username']), true), 0, 50);

		if ($nameMatch = $im->getUserIdByUserName($name))
		{
			if (!empty($options['mergeName']))
			{
				return $this->_mergeUser($user, $nameMatch);
			}
			else
			{
				$this->_session->setExtraData('userMerge', $user['user_id'], 'name');
				return false;
			}
		}

		return $this->_importUser($user, $options);
	}

	protected $_userActivationSetting = null;

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

		if ($this->_userFieldMap === null)
		{
			$this->_userFieldMap = $this->_importModel->getImportContentMap('userField');
		}

		if ($this->_userActivationSetting === null)
		{
			$this->_userActivationSetting = $sDb->fetchOne('
				SELECT config_value
				FROM ' . $prefix . 'config
				WHERE config_name = \'require_activation\'
			');
		}

		if ($user['user_type'] == 2)
		{
			return false; // ignore this user
		}

		$groups = $sDb->fetchCol('
			SELECT group_id
			FROM ' . $prefix . 'user_group
			WHERE user_id = ' . $sDb->quote($user['user_id']) . '
				AND group_id <> ' . $sDb->quote($user['group_id']) . '
				AND user_pending = 0
		');

		$user['user_options'] = intval($user['user_options']);

		$import = array(
			'username' => $this->_convertToUtf8($user['username'], true),
			'email' => $this->_convertToUtf8($user['user_email'], true),
			'user_group_id' => $this->_mapLookUp($this->_groupMap, $user['group_id'], XenForo_Model_User::$defaultRegisteredGroupId),
			'secondary_group_ids' => $this->_mapLookUpList($this->_groupMap, $groups),
			'authentication' => array(
				'scheme_class' => 'XenForo_Authentication_PhpBb3',
				'data' => array(
					'hash' => $user['user_password']
				)
			),
			'last_activity' => $user['user_lastvisit'],
			'register_date' => $user['user_regdate'],
			'ip' => $user['user_ip'],
			'homepage' => $this->_convertToUtf8($user['user_website'], true),
			'message_count' => $user['user_posts'],
			'is_admin' => ($user['user_type'] == 3 ? 1 : 0), // founders get all admin permissions
			'is_banned' => ($user['ban_id'] ? 1 : 0),
			'signature' => $this->_sanitizeBbCode($user['user_sig']),
			'timezone' => $this->_importModel->resolveTimeZoneOffset($user['user_timezone'], $user['user_dst']),
			'visible' => $user['user_allow_viewonline'],
			'content_show_signature' => (($user['user_options'] & 1 << 3) ? 1 : 0), // view sigs
			'receive_admin_email' => $user['user_allow_massemail'],
			'default_watch_state' => ($user['user_notify'] ? 'watch_email' : ''),
			'allow_send_personal_conversation' => ($user['user_allow_pm'] ? 'everyone' : 'none'),
			'email_on_conversation' => $user['user_notify_pm']
		);

		if ($user['group_id'] == 3) // coppa
		{
			$import['user_state'] = 'moderated';
		}
		else if ($user['user_type'] == 1 && $user['user_inactive_reason'] == 1) // inactive at registration
		{
			$import['user_state'] = ($this->_userActivationSetting == 2 ? 'moderated' : 'email_confirm');
		}
		else if ($user['user_type'] == 1 && $user['user_inactive_reason'] == 2) // inactive at profile edit
		{
			$import['user_state'] = 'email_confirm_edit';
		}
		else
		{
			$import['user_state'] = 'valid';
		}

		if ($user['user_birthday'])
		{
			$parts = explode('-', $user['user_birthday']);
			if (count($parts) == 3)
			{
				$import['dob_day'] = trim($parts[0]);
				$import['dob_month'] = trim($parts[1]);
				$import['dob_year'] = trim($parts[2]);
			}
		}

		// try to give users without an avatar that have actually posted a gravatar
		if (!empty($options['gravatar']))
		{
			// 1 = uploaded avatar
			if ($user['user_avatar_type'] != 1 && $user['user_email'] && $user['user_lastpost_time']
				&& XenForo_Model_Avatar::gravatarExists($user['user_email'])
			)
			{
				$import['gravatar'] = $import['email'];
			}
		}

		$import['about'] = $this->_convertToUtf8($user['user_interests'], true);
		$import['location'] = $this->_convertToUtf8($user['user_from'], true);
		$import['occupation'] = $this->_convertToUtf8($user['user_occ'], true);

		// custom user fields
		$userFieldDefinitions = $this->_importModel->getUserFieldDefinitions();

		$identityMap = array(
			'icq' => 'user_icq',
			'aim' => 'user_aim',
			'yahoo' => 'user_yim',
			'msn' => 'user_msnm'
		);

		foreach ($identityMap AS $identityType => $phpBbField)
		{
			if (isset($userFieldDefinitions[$identityType]))
			{
				$import[XenForo_Model_Import::USER_FIELD_KEY][$identityType] = $this->_convertToUtf8($user[$phpBbField], true);
			}
		}

		$userFieldLookups = $this->_session->getExtraData('userFieldLookups');

		foreach ($this->_userFieldMap AS $oldFieldId => $newFieldId)
		{
			if (!isset($userFieldDefinitions[$newFieldId]))
			{
				continue;
			}

			$userFieldValue = '';

			if (isset($user["pf_$oldFieldId"]) && $user["pf_$oldFieldId"] !== '')
			{
				if (isset($userFieldLookups[$oldFieldId]))
				{
					$fieldInfo = $userFieldLookups[$oldFieldId];
					$fieldChoiceId = max(0, $user["pf_$oldFieldId"] - 1); // option ids are 0 keyed, values are 1 keyed

					if (isset($fieldInfo['choices'][$fieldChoiceId]))
					{
						$userFieldValue = $fieldInfo['choices'][$fieldChoiceId];
					}
				}
				else
				{
					// set the field value directly
					$userFieldValue = $this->_convertToUtf8($user["pf_$oldFieldId"], true);
				}
			}

			$import[XenForo_Model_Import::USER_FIELD_KEY][$newFieldId] = $userFieldValue;
		}

		if ($import['is_admin'])
		{
			// give all admin permissions
			$adminPerms = XenForo_Model::create('XenForo_Model_Admin')->getAllAdminPermissions();
			$import['admin_permissions'] =  array_keys($adminPerms);
		}

		$importedUserId = $this->_importModel->importUser($user['user_id'], $import, $failedKey);
		if ($importedUserId)
		{
			if ($user['ban_id'])
			{
				$this->_importModel->importBan(array(
					'user_id' => $importedUserId,
					'ban_user_id' => 0,
					'ban_date' => $user['ban_start'],
					'end_date' => $user['ban_end'],
					'user_reason' => $this->_convertToUtf8($user['ban_give_reason'], true)
				));
			}

			// TODO: this doesn't necessarily work, as the users may not be imported yet.
			// It could work with maintained user IDs though.
			$friends = array();
			$foes = array();
			$zebraResults = $sDb->query('
				SELECT zebra_id, friend, foe
				FROM ' . $prefix . 'zebra
				WHERE user_id = ' . $sDb->quote($user['user_id'])
			);
			while ($zebra = $zebraResults->fetch())
			{
				if ($zebra['foe'])
				{
					$foes[] = $zebra['zebra_id'];
				}
				if ($zebra['friend'])
				{
					$friends[] = $zebra['zebra_id'];
				}
			}
			if ($friends)
			{
				$friends = $this->_importModel->getImportContentMap('user', $friends);
				$this->_importModel->importFollowing($importedUserId, $friends);
			}
			if ($foes)
			{
				$foes = $this->_importModel->getImportContentMap('user', $foes);
				$this->_importModel->importIgnored($importedUserId, $foes);
			}
		}
		else if ($failedKey)
		{
			$this->_session->setExtraData('userFailed', $user['user_id'], $failedKey);
		}

		return $importedUserId;
	}

	protected function _getSelectUserSql($where)
	{
		return '
			SELECT users.*, pfd.*, ban.*, users.user_id
			FROM ' . $this->_prefix . 'users AS users
			LEFT JOIN ' . $this->_prefix . 'profile_fields_data AS pfd ON
				(pfd.user_id = users.user_id)
			LEFT JOIN ' . $this->_prefix . 'banlist AS ban ON
				(ban.ban_userid = users.user_id AND (ban.ban_end = 0 OR ban.ban_end > ' . XenForo_Application::$time . '))
			WHERE ' . $where . '
				AND users.user_type <> 2
			ORDER BY users.user_id
		';
	}

	protected function _mergeUser(array $user, $targetUserId)
	{
		$this->_db->query('
			UPDATE xf_user SET
				message_count = message_count + ?,
				register_date = IF(register_date > ?, ?, register_date)
			WHERE user_id = ?
		', array($user['user_posts'], $user['user_regdate'], $user['user_regdate'], $targetUserId));

		$this->_importModel->logImportData('user', $user['user_id'], $targetUserId);

		return $targetUserId;
	}

	public function stepAvatars($start, array $options)
	{
		$options = array_merge(array(
			'path' => isset($this->_config['avatarPath']) ? $this->_config['avatarPath'] : '',
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
				SELECT MAX(user_id)
				FROM ' . $prefix . 'users
				WHERE user_avatar_type = 1
			');
		}

		$avatars = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM ' . $prefix . 'users
				WHERE user_id > ' . $sDb->quote($start) . '
					AND user_avatar_type = 1
				ORDER BY user_id
			', $options['limit']
		));
		if (!$avatars)
		{
			return true;
		}

		$avatarSalt = $sDb->fetchOne('
			SELECT config_value
			FROM ' . $prefix . 'config
			WHERE config_name = \'avatar_salt\'
		');

		$userIdMap = $model->getUserIdsMapFromArray($avatars, 'user_id');

		$next = 0;
		$total = 0;

		foreach ($avatars AS $avatar)
		{
			$next = $avatar['user_id'];

			$newUserId = $this->_mapLookUp($userIdMap, $avatar['user_id']);
			if (!$newUserId)
			{
				continue;
			}

			$userId = intval($avatar['user_avatar']);
			$ext = substr(strrchr($avatar['user_avatar'], '.'), 1);

			$avatarFileOrig = "$options[path]/{$avatarSalt}_$userId.$ext";
			if (!file_exists($avatarFileOrig) || !is_file($avatarFileOrig))
			{
				continue;
			}

			$avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			copy($avatarFileOrig, $avatarFile);

			if ($this->_importModel->importAvatar($avatar['user_id'], $newUserId, $avatarFile))
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
				SELECT MAX(msg_id)
				FROM ' . $prefix . 'privmsgs
			');
		}

		$pmTexts = $sDb->fetchAll($sDb->limit(
			'
				SELECT pms.*, users.username
				FROM ' . $prefix . 'privmsgs AS pms
				LEFT JOIN ' . $prefix . 'users AS users ON (pms.author_id = users.user_id)
				WHERE pms.msg_id > ' . $sDb->quote($start) . '
				ORDER BY pms.msg_id
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
			$next = $pmText['msg_id'];

			$toUserIds = array();
			if (preg_match_all('#u_(\d+)#', $pmText['to_address'], $matches))
			{
				$toUserIds = $matches[1];
			}
			if (!$toUserIds)
			{
				continue;
			}
			$toUsers = $sDb->fetchPairs('
				SELECT user_id, username
				FROM ' . $prefix . 'users
				WHERE user_id IN (' . $sDb->quote($toUserIds) . ')
			');
			if (!$toUsers)
			{
				continue;
			}

			$users = array(
				$pmText['author_id'] => $pmText['username']
			) + $toUsers;

			$mapUserIds = $model->getImportContentMap('user', array_keys($users));

			$newFromUserId = $this->_mapLookUp($mapUserIds, $pmText['author_id']);
			if (!$newFromUserId)
			{
				continue;
			}

			$unreadState = $sDb->fetchPairs('
				SELECT user_id, MIN(IF(folder_id < 0, 0, pm_unread))
				FROM ' . $prefix . 'privmsgs_to
				WHERE msg_id = ' . $sDb->quote($pmText['msg_id']) . '
					AND pm_deleted = 0
				GROUP BY user_id
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
					$lastReadDate = ($unreadState[$userId] ? 0 : $pmText['message_time']);
					$deleted = false;
				}
				else
				{
					$lastReadDate = $pmText['message_time'];
					$deleted = true;
				}

				$recipients[$newUserId] = array(
					'username' => $this->_convertToUtf8($username, true),
					'last_read_date' => $lastReadDate,
					'recipient_state' => ($deleted ? 'deleted' : 'active')
				);
			}

			$fromUserName = $this->_convertToUtf8($pmText['username'], true);

			$conversation = array(
				'title' => $this->_convertToUtf8($pmText['message_subject'], true),
				'user_id' => $newFromUserId,
				'username' => $fromUserName,
				'start_date' => $pmText['message_time'],
				'open_invite' => 0,
				'conversation_open' => 1
			);

			$messages = array(
				array(
					'message_date' => $pmText['message_time'],
					'user_id' => $newFromUserId,
					'username' => $fromUserName,
					'message' => $this->_sanitizeBbCode($pmText['message_text'])
				)
			);

			if ($model->importConversation($pmText['msg_id'], $conversation, $recipients, $messages))
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
			FROM ' . $prefix . 'forums
		');
		if (!$forums)
		{
			return true;
		}

		$forumTree = array();
		foreach ($forums AS $forum)
		{
			$forumTree[$forum['parent_id']][$forum['forum_id']] = $forum;
		}

		XenForo_Db::beginTransaction();

		$total = $this->_importForumTree(0, $forumTree);

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array(1, array(), '');
	}

	protected function _importForumTree($parentId, array $forumTree, array $forumIdMap = array())
	{
		if (!isset($forumTree[$parentId]))
		{
			return 0;
		}

		XenForo_Db::beginTransaction();

		$total = 0;

		foreach ($forumTree[$parentId] AS $forum)
		{
			$import = array(
				'title' => $this->_convertToUtf8($forum['forum_name'], true),
				'description' => $this->_sanitizeBbCode($forum['forum_desc'], null, true),
				'display_order' => $forum['left_id'],
				'parent_node_id' => $this->_mapLookUp($forumIdMap, $forum['parent_id'], 0),
				'display_in_list' => 1
			);

			if ($forum['forum_type'] == 2)
			{
				$import['node_type_id'] = 'LinkForum';
				$import['link_url'] = $forum['forum_link'];

				$nodeId = $this->_importModel->importLinkForum($forum['forum_id'], $import);
			}
			else if ($forum['forum_type'] == 1)
			{
				$import['node_type_id'] = 'Forum';
				$import['discussion_count'] = $forum['forum_topics'];
				$import['message_count'] = $forum['forum_posts'];
				$import['last_post_date'] = $forum['forum_last_post_time'];
				$import['last_post_username'] = $this->_convertToUtf8($forum['forum_last_poster_name'], true);
				$import['last_thread_title'] = $this->_convertToUtf8($forum['forum_last_post_subject'], true);

				$nodeId = $this->_importModel->importForum($forum['forum_id'], $import);
			}
			else
			{
				$import['node_type_id'] = 'Category';

				$nodeId = $this->_importModel->importCategory($forum['forum_id'], $import);
			}

			if ($nodeId)
			{
				$forumIdMap[$forum['forum_id']] = $nodeId;

				$total++;
				$total += $this->_importForumTree($forum['forum_id'], $forumTree, $forumIdMap);
			}
		}

		XenForo_Db::commit();

		return $total;
	}

	public function configStepForumPermissions(array $options)
	{
		if ($options)
		{
			return false;
		}

		$this->_bootstrap($this->_session->getConfig());

		$nodeMap = $this->_importModel->getImportContentMap('node');
		$forumStates = $this->_guessForumPermissions($nodeMap);

		/* @var $nodeModel XenForo_Model_Node */
		$nodeModel = $this->_importModel->getModelFromCache('XenForo_Model_Node');

		$nodes = $nodeModel->getAllNodes();

		$displayNodes = array();

		foreach ($nodes AS $nodeId => $node)
		{
			if (in_array($nodeId, $nodeMap))
			{
				$node['permissionState'] = $forumStates[$nodeId];

				$displayNodes[$nodeId] = $node;
			}
		}

		$viewParams = array('nodes' => $displayNodes);

		return $this->_controller->responseView(
			'XenForo_ViewAdmin_Import_PhpBb3_ConfigForumPermissions',
			'import_phpbb3_config_forumpermissions',
			$viewParams
		);
	}

	protected function _guessForumPermissions(array $nodeMap)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$forumPermissions = array();

		$forumIds = $sDb->fetchCol('
			SELECT forum_id
			FROM ' . $prefix . 'forums
		');
		foreach ($forumIds AS $forumId)
		{
			$newForumId = $this->_mapLookUp($nodeMap, $forumId);
			if (!$newForumId)
			{
				continue;
			}

			$guestPermissions = $this->_getGroupPermissions(1, $forumId);
			if ($this->_hasPhpBbPerm($guestPermissions, 'f_list'))
			{
				$state = 'public';
			}
			else
			{
				$userPermissions = $this->_getGroupPermissions(2, $forumId);
				if ($this->_hasPhpBbPerm($userPermissions, 'f_list'))
				{
					$state = 'memberOnly';
				}
				else
				{
					$state = 'staffOnly';
				}
			}

			$forumPermissions[$newForumId] = $state;
		}

		return $forumPermissions;
	}

	public function stepForumPermissions($start, array $options)
	{
		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($start > 0)
		{
			//rebuild the full permission cache so forums appear
			XenForo_Model::create('XenForo_Model_Permission')->rebuildPermissionCache();

			return true;
		}

		$reset = array('general' => array('viewNode' => 'reset'));
		$allow = array('general' => array('viewNode' => 'content_allow'));

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($options AS $nodeId => $permission)
		{
			switch ($permission)
			{
				case 'memberOnly':
				{
					// revoke view permissions for guests (1)
					$model->insertNodePermissionEntries($nodeId, 1, 0, $reset);

					$total++;

					break;
				}

				case 'staffOnly':
				{
					// revoke view permissions for all but staff
					$model->insertNodePermissionEntries($nodeId, 0, 0, $reset);

					// allow 'Administrating' group (3)
					$model->insertNodePermissionEntries($nodeId, 3, 0, $allow);

					// allow 'Moderating' group (4)
					$model->insertNodePermissionEntries($nodeId, 4, 0, $allow);

					$total++;

					break;
				}

				case 'public':
				default:
					// no change required
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array(1, array(), '');
	}

	public function stepModerators($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$moderators = $sDb->fetchAll('
			SELECT mods.*
			FROM ' . $prefix . 'moderator_cache AS mods
			INNER JOIN ' . $prefix . 'users AS users ON (mods.user_id = users.user_id)
		');
		if (!$moderators)
		{
			return true;
		}

		$modGrouped = array();
		foreach ($moderators AS $moderator)
		{
			$modGrouped[$moderator['user_id']][$moderator['forum_id']] = $moderator;
		}

		$nodeMap = $model->getImportContentMap('node');
		$userIdMap = $model->getImportContentMap('user', array_keys($modGrouped));

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($modGrouped AS $userId => $forums)
		{
			$newUserId = $this->_mapLookUp($userIdMap, $userId);
			if (!$newUserId)
			{
				continue;
			}

			$inserted = false;

			foreach ($forums AS $forumId => $moderator)
			{
				$newNodeId = $this->_mapLookUp($nodeMap, $forumId);
				if (!$newNodeId)
				{
					continue;
				}

				$mod = array(
					'content_id' => $newNodeId,
					'user_id' => $newUserId,
					'moderator_permissions' => $this->_convertForumPermissionsForUser($userId, $forumId)
				);
				$model->importNodeModerator($forumId, $userId, $mod);

				$total++;
				$inserted = true;
			}

			if ($inserted)
			{
				$mod = array(
					'user_id' => $newUserId,
					'is_super_moderator' => false,
					'moderator_permissions' => array()
				);
				$model->importGlobalModerator($userId, $mod);
			}
		}

		$this->_session->incrementStepImportTotal($total);

		XenForo_Db::commit();

		return true;
	}

	protected function _convertForumPermissionsForUser($userId, $forumId)
	{
		$phpBbPerms = $this->_getUserPermissions($userId, $forumId);
		$perms = array('forum' => array());

		if ($this->_hasPhpBbPerm($phpBbPerms, 'm_merge') || $this->_hasPhpBbPerm($phpBbPerms, 'm_move') || $this->_hasPhpBbPerm($phpBbPerms, 'm_split'))
		{
			$perms['forum']['manageAnyThread'] = 'content_allow';
			$perms['forum']['stickUnstickThread'] = 'content_allow';
		}
		if ($this->_hasPhpBbPerm($phpBbPerms, 'm_approve'))
		{
			$perms['forum']['approveUnapprove'] = 'content_allow';
			$perms['forum']['viewModerated'] = 'content_allow';
		}
		if ($this->_hasPhpBbPerm($phpBbPerms, 'm_delete'))
		{
			$perms['forum']['deleteAnyPost'] = 'content_allow';
			$perms['forum']['deleteAnyThread'] = 'content_allow';
			$perms['forum']['undelete'] = 'content_allow';
			$perms['forum']['viewDeleted'] = 'content_allow';
		}
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'forum', 'editAnyPost', 'm_edit', 'content_allow');
		$this->_setXfPermissionBasedOnPermission($perms, $phpBbPerms, 'forum', 'lockUnlockThread', 'm_lock', 'content_allow');

		return $perms;
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
				SELECT MAX(topic_id)
				FROM ' . $prefix . 'topics
			');
		}

		// pull threads from things we actually imported as forums
		$threads = $sDb->fetchAll($sDb->limit(
			'
				SELECT topics.*,
					IF(users.username IS NOT NULL, users.username, topics.topic_first_poster_name) AS username
				FROM ' . $prefix . 'topics AS topics FORCE INDEX (PRIMARY)
				LEFT JOIN ' . $prefix . 'users AS users ON (topics.topic_poster = users.user_id)
				INNER JOIN ' . $prefix . 'forums AS forums ON
					(topics.forum_id = forums.forum_id)
				WHERE topics.topic_id >= ' . $sDb->quote($start) . '
					AND topics.topic_status <> 2 # redirect
				ORDER BY topics.topic_id
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
			if (trim($thread['topic_title']) === '')
			{
				continue;
			}

			$postDateStart = $options['postDateStart'];

			$next = $thread['topic_id'] + 1; // uses >=, will be moved back down if need to continue
			$options['postDateStart'] = 0;

			$maxPosts = $options['postLimit'] - $totalPosts;
			$posts = $sDb->fetchAll($sDb->limit(
				'
					SELECT posts.*,
						IF(users.username IS NOT NULL, users.username, posts.post_username) AS username
					FROM ' . $prefix . 'posts AS posts
					LEFT JOIN ' . $prefix . 'users AS users ON (posts.poster_id = users.user_id)
					WHERE posts.topic_id = ' . $sDb->quote($thread['topic_id']) . '
						AND posts.post_time > ' . $sDb->quote($postDateStart) . '
					ORDER BY posts.post_time
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
				$threadId = $model->mapThreadId($thread['topic_id']);

				$position = $this->_db->fetchOne('
					SELECT MAX(position)
					FROM xf_post
					WHERE thread_id = ?
				', $threadId);
			}
			else
			{
				$forumId = $this->_mapLookUp($nodeMap, $thread['forum_id']);
				if (!$forumId)
				{
					continue;
				}

				if (trim($thread['username']) === '')
				{
					$thread['username'] = 'Guest';
				}

				$import = array(
					'title' => $this->_convertToUtf8($thread['topic_title'], true),
					'node_id' => $forumId,
					'user_id' => $model->mapUserId($thread['topic_poster'], 0),
					'username' => $this->_convertToUtf8($thread['username'], true),
					'discussion_open' => ($thread['topic_status'] == 0 ? 1 : 0),
					'post_date' => $thread['topic_time'],
					'reply_count' => $thread['topic_replies'],
					'view_count' => $thread['topic_views'],
					'sticky' => ($thread['topic_type'] == 1 ? 1 : 0),
					'last_post_date' => $thread['topic_last_post_time'],
					'last_post_username' => $this->_convertToUtf8($thread['topic_last_poster_name'], true),
				);
				if ($thread['topic_approved'])
				{
					$import['discussion_state'] = 'visible';
				}
				else
				{
					$import['discussion_state'] = 'moderated';
				}

				$threadId = $model->importThread($thread['topic_id'], $import);
				if (!$threadId)
				{
					continue;
				}

				$position = -1;

				$subs = $sDb->fetchPairs('
					SELECT user_id, notify_status
					FROM ' . $prefix . 'topics_watch
					WHERE topic_id = ' . $sDb->quote($thread['topic_id'])
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

				$threadTitleRegex = '#^(re:\s*)?' . preg_quote($thread['topic_title'], '#') . '$#i';

				$userIdMap = $model->getUserIdsMapFromArray($posts, 'poster_id');

				foreach ($posts AS $i => $post)
				{
					if ($post['post_subject'] !== '' && !preg_match($threadTitleRegex, $post['post_subject']))
					{
						$post['post_text'] = '[b]' . $post['post_subject'] . "[/b]\n\n" . ltrim($post['post_text']);
					}

					if (trim($post['username']) === '')
					{
						$post['username'] = 'Guest';
					}

					$post['post_text'] = $this->_sanitizeBbCode($post['post_text']);

					$import = array(
						'thread_id' => $threadId,
						'user_id' => $this->_mapLookUp($userIdMap, $post['poster_id'], 0),
						'username' => $this->_convertToUtf8($post['username'], true),
						'post_date' => $post['post_time'],
						'message' => $post['post_text'],
						'attach_count' => 0,
						'ip' => $post['poster_ip']
					);
					if ($post['post_approved'])
					{
						$import['message_state'] = 'visible';
						$import['position'] = ++$position;
					}
					else
					{
						$import['message_state'] = 'moderated';
						$import['position'] = $position;
					}

					$model->importPost($post['post_id'], $import);

					$options['postDateStart'] = $post['post_time'];
					$totalPosts++;
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

	public function stepPolls($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(topic_id)
				FROM ' . $prefix . 'poll_options
			');
		}

		$topicIds = $sDb->fetchCol($sDb->limit(
			'
				SELECT topic_id
				FROM ' . $prefix . 'poll_options
				WHERE topic_id > ' . $sDb->quote($start) . '
				GROUP BY topic_id
				ORDER BY topic_id
			', $options['limit']
		));
		if (!$topicIds)
		{
			return true;
		}

		$threads = $sDb->fetchAll('
				SELECT topics.*
				FROM ' . $prefix . 'topics AS topics
				WHERE topics.topic_id IN (' . $sDb->quote($topicIds) . ')
				ORDER BY topics.topic_id
		');
		if (!$threads)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$threadIdMap = $model->getImportContentMap('thread', $topicIds);

		XenForo_Db::beginTransaction();

		foreach ($threads AS $thread)
		{
			$next = $thread['topic_id'];

			$newThreadId = $this->_mapLookUp($threadIdMap, $thread['topic_id']);
			if (!$newThreadId)
			{
				continue;
			}

			if (empty($thread['poll_start']))
			{
				continue;
			}

			$import = array(
				'question' => $this->_sanitizeBbCode($thread['poll_title'], null, true),
				'max_votes' => $thread['poll_max_options'],
				'close_date' => ($thread['poll_length'] ? $thread['poll_start'] + $thread['poll_length'] : 0)
			);
			$responses = $sDb->fetchPairs('
				SELECT poll_option_id, poll_option_text
				FROM ' . $prefix . 'poll_options
				WHERE topic_id = ' . $sDb->quote($thread['topic_id'])
			);
			foreach ($responses AS &$value)
			{
				$value = $this->_sanitizeBbCode($value, null, true);
			}

			$newPollId = $model->importThreadPoll($thread['topic_id'], $newThreadId, $import, $responses, $responseIds);
			if ($newPollId)
			{
				$votes = $sDb->fetchAll('
					SELECT vote_user_id, poll_option_id
					FROM ' . $prefix . 'poll_votes
					WHERE topic_id = ' . $sDb->quote($thread['topic_id'])
				);

				$userIdMap = $model->getUserIdsMapFromArray($votes, 'vote_user_id');
				foreach ($votes AS $vote)
				{
					$userId = $this->_mapLookUp($userIdMap, $vote['vote_user_id']);
					if (!$userId)
					{
						continue;
					}

					$voteOption = $vote['poll_option_id'];
					if (!isset($responseIds[$voteOption]))
					{
						continue;
					}

					$model->importPollVote($newPollId, $userId, $responseIds[$voteOption], 0);
				}
			}

			$total++;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepAttachments($start, array $options)
	{
		$options = array_merge(array(
			'path' => isset($this->_config['attachmentPath']) ? $this->_config['attachmentPath'] : '',
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
				SELECT MAX(attach_id)
				FROM ' . $prefix . 'attachments
			');
		}

		$attachments = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM ' . $prefix . 'attachments
				WHERE attach_id > ' . $sDb->quote($start) . '
					AND is_orphan = 0
					AND post_msg_id > 0
				ORDER BY attach_id
			', $options['limit']
		));
		if (!$attachments)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIdMap = $model->getUserIdsMapFromArray($attachments, 'poster_id');
		$postIdMap = $model->getPostIdsMapFromArray($attachments, 'post_msg_id');

		foreach ($attachments AS $attachment)
		{
			$next = $attachment['attach_id'];

			$newPostId = $this->_mapLookUp($postIdMap, $attachment['post_msg_id']);
			if (!$newPostId)
			{
				continue;
			}

			$attachFileOrig = "$options[path]/$attachment[physical_filename]";
			if (!file_exists($attachFileOrig) || !is_file($attachFileOrig))
			{
				continue;
			}

			$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			copy($attachFileOrig, $attachFile);

			$success = $model->importPostAttachment(
				$attachment['attach_id'],
				$this->_convertToUtf8($attachment['real_filename'], true),
				$attachFile,
				$this->_mapLookUp($userIdMap, $attachment['poster_id'], 0),
				$newPostId,
				$attachment['filetime'],
				array('view_count' => $attachment['download_count'])
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

	protected function _sanitizeBbCode($string, $bbCodeUid = null, $strip = false)
	{
		if ($bbCodeUid === null)
		{
			$bbCodeUidRegex = '[a-z0-9]+';
		}
		else
		{
			$bbCodeUidRegex = preg_quote($bbCodeUid, '#');
		}

		// how smilies are stored
		$string = preg_replace(
			'#<img src="[^"]*" alt="([^"]*)" title="[^"]*"\s*/?>#iU',
			'$1',
			$string
		);

		// seen some email links like this
		$string = preg_replace(
			'#<a[^>]+href="mailto:([^"]+)">(.*)</a>#siU',
			'[email=$1]$2[/email]',
			$string
		);

		// seen some links like this
		$string = preg_replace(
			'#<a[^>]+href="([^"]+)">(.*)</a>#siU',
			'[url=$1]$2[/url]',
			$string
		);

		// other comment markup
		$string = preg_replace('#<!--.*-->#siU', '', $string);

		$string = $this->_convertToUtf8($string, true);

		do
		{
			$previousString = $string;

			// don't handle converting [attachment] tags - just strip them
			$string = preg_replace(
				'#\[attachment="?.*"?:(' . $bbCodeUidRegex . ')\].*\[/attachment:\\1\]#siU',
				'',
				$string
			);

			// size tags need mapping
			$string = preg_replace_callback(
				'#\[(size)="?([^\]]*)"?:(' . $bbCodeUidRegex . ')\](.*)\[/size:\\3\]#siU',
				array($this, '_handleBbCodeSizeCallback'),
				$string
			);

			// align tags need mapping
			$string = preg_replace(
				'#\[align="?(left|center|right)"?:(' . $bbCodeUidRegex . ')\](.*)\[/align:\\2\]#siU',
				'[$1]$3[/$1]',
				$string
			);
		}
		while ($string != $previousString);

		$string = preg_replace(
			'#\[([a-z0-9_\*]+(="[^"]*"|=[^\]]*)?):(' . $bbCodeUidRegex . ')\]#siU',
			($strip ? '' : '[$1]'),
			$string
		);
		$string = preg_replace(
			'#\[/([a-z0-9_\*]+)(:[a-z])?:(' . $bbCodeUidRegex . ')\]#siU',
			($strip ? '' : '[/$1]'),
			$string
		);

		$string = str_replace('[/*]', '', $string);

		return $string;
	}

	protected function _handleBbCodeSizeCallback(array $match)
	{
		$tag = $match[1];
		$size = intval($match[2]);
		$text = $match[4];

		if ($size >= 200)
		{
			$size = 7;
		}
		else if ($size >= 170)
		{
			$size = 6;
		}
		else if ($size >= 140)
		{
			$size = 5;
		}
		else if ($size >= 110)
		{
			$size = 4;
		}
		else if ($size >= 90)
		{
			$size = 3;
		}
		else if ($size >= 60)
		{
			$size = 2;
		}
		else
		{
			$size = 1;
		}

		return '[' . $tag . '=' . $size . ']' . $text . '[/' . $tag . ']';
	}
}