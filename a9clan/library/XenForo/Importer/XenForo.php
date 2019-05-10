<?php

class XenForo_Importer_XenForo extends XenForo_Importer_Abstract
{
	/**
	 * Source database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_sourceDb;

	protected $_config;

	protected $_groupMap = null;

	protected $_userFieldMap = null;

	protected $_nodeTypeIds = array('Category', 'Forum', 'LinkForum', 'Page');
	protected $_pollContentTypes = array('thread');

	public static function getName()
	{
		return 'XenForo 1.2/1.3/1.4';
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

			$this->_bootstrap($config);

			return true;
		}
		else
		{
			$viewParams = array('input' => array(
				'db' => array(
					'host' => 'localhost',
					'port' => '3306',
				)
			));

			return $controller->responseView('XenForo_ViewAdmin_Import_XenForo_Config', 'import_xenforo_config', $viewParams);
		}
	}

	public function validateConfiguration(array &$config)
	{
		$errors = array();

		try
		{
			$db = Zend_Db::factory('mysqli',
				array(
					'host' => $config['db']['host'],
					'port' => $config['db']['port'],
					'username' => $config['db']['username'],
					'password' => $config['db']['password'],
					'dbname' => $config['db']['dbname'],
					'charset' => 'utf8',
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
			$db->query('SELECT user_id FROM xf_user LIMIT 1');
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

		if (!empty($config['dir']['data']))
		{
			if (!file_exists($config['dir']['data']) || !is_dir($config['dir']['data']))
			{
				$errors[] = new XenForo_Phrase('data_directory_not_found');
			}
		}

		if (!empty($config['dir']['internal_data']))
		{
			if (!file_exists($config['dir']['internal_data']) || !is_dir($config['dir']['internal_data']))
			{
				$errors[] = new XenForo_Phrase('internal_data_directory_not_found');
			}
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
			'followIgnore' => array(
				'title' => new XenForo_Phrase('import_follow_ignore_lists'),
				'depends' => array('users')
			),
			'conversations' => array(
				'title' => new XenForo_Phrase('import_conversations'),
				'depends' => array('users')
			),
			'profilePosts' => array(
				'title' => new XenForo_Phrase('import_profile_posts'),
				'depends' => array('users')
			),
			'nodes' => array(
				'title' => new XenForo_Phrase('import_nodes'),
				'depends' => array('userGroups')
			),
			'moderators' => array(
				'title' => new XenForo_Phrase('import_moderators'),
				'depends' => array('nodes', 'users')
			),
			'threadPrefixes' => array(
				'title' => new XenForo_Phrase('import_thread_prefixes'),
				'depends' => array('nodes')
			),
			'threads' => array(
				'title' => new XenForo_Phrase('import_threads_and_posts'),
				'depends' => array('nodes', 'users', 'threadPrefixes')
			),
			'postEditHistory' => array(
				'title' => new XenForo_Phrase('import_post_edit_history'),
				'depends' => array('threads')
			),
			'polls' => array(
				'title' => new XenForo_Phrase('import_polls'),
				'depends' => array('threads')
			),
			'attachments' => array(
				'title' => new XenForo_Phrase('import_attached_files'),
				'depends' => array('conversations', 'threads')
			),
			'likes' => array(
				'title' => new XenForo_Phrase('import_likes'),
				'depends' => array('threads', 'profilePosts')
			),
			'warnings' => array(
				'title' => new XenForo_Phrase('import_warnings'),
				'depends' => array('threads', 'profilePosts')
			),
			'userUpgrades' => array(
				'title' => new XenForo_Phrase('import_user_upgrades'),
				'depends' => array('users')
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
				'charset' => 'utf8',
			)
		);
	}

	public function stepUserGroups($start, array $options)
	{
		$groups = $this->_sourceDb->fetchAll('SELECT * FROM xf_user_group ORDER BY user_group_id');

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($groups AS $group)
		{
			if ($group['user_group_id'] <= 4)
			{
				// Group IDs 1-4 will not be imported, just logged, as we know they are default groups
				$this->_importModel->logImportData('userGroup', $group['user_group_id'], $group['user_group_id']);
			}
			else
			{
				// Group IDs > 4 will be imported as usual with new keys attached.
				$import = $this->_quickAssembleData($group, array(
					'title',
					'display_style_priority',
					'username_css',
					'user_title',
					'banner_css_class',
					'banner_text'
				));
				$this->_importModel->importUserGroup($group['user_group_id'], $import);
			}

			$total++;
		}

		XenForo_Model::create('XenForo_Model_UserGroup')->rebuildDisplayStyleCache();

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return true;
	}

	public function stepUserFields($start, array $options)
	{
		$model = $this->_importModel;

		$existingFields = $model->getUserFieldDefinitions();

		/*
		 * See XenForo_Model_UserField::getUserFieldTitlePhraseName(),
		 * 	getUserFieldDescriptionPhraseName(),
		 * 	getUserFieldChoicePhraseName()
		 */

		$userFields = $this->_sourceDb->fetchAll("
			SELECT field.*,
				ptitle.phrase_text AS title,
				pdesc.phrase_text AS description
			FROM xf_user_field AS field
			INNER JOIN xf_phrase AS ptitle ON
			(
				ptitle.language_id = 0 AND
				ptitle.title = CONCAT('user_field_', field.field_id)
			)
			INNER JOIN xf_phrase AS pdesc ON
			(
				pdesc.language_id = 0 AND
				pdesc.title = CONCAT('user_field_', field.field_id, '_desc')
			)
		");

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($userFields AS $userField)
		{
			if (!empty($existingFields[$userField['field_id']]))
			{
				// don't import a field if we already have one called that... seems a reasonable decision?
				$model->logImportData('userField', $userField['field_id'], $userField['field_id']);
			}
			else
			{
				$fieldChoices = @unserialize($userField['field_choices']);
				if (!is_array($fieldChoices))
				{
					$fieldChoices = array();
				}

				$import = $this->_quickAssembleData($userField, array(
					'field_id',
					'display_group',
					'display_order',
					'field_type',
					'field_choices' => $fieldChoices,
					'match_type',
					'match_regex',
					'match_callback_class',
					'match_callback_method',
					'max_length',
					'required',
					'show_registration',
					'user_editable',
					'moderator_editable',
					'viewable_profile',
					'viewable_message',
					'display_template',

					// pseudo-fields
					'title',
					'description',
				));

				$model->importUserField($userField['field_id'], $import);
			}

			$total++;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return true;
	}

	public function configStepUsers(array $options)
	{
		if ($options)
		{
			return false;
		}

		return $this->_controller->responseView('XenForo_ViewAdmin_Import_XenForo_ConfigUsers', 'import_config_users');
	}

	public function stepUsers($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false,
			// all checkbox options must default to false as they may not be submitted
			'mergeEmail' => false,
			'mergeName' => false,
		), $options);

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(user_id) FROM xf_user');
		}

		$users = $sDb->fetchAll(
			$sDb->limit($this->_getSelectUserSql('xf_user.user_id > ' . $sDb->quote($start)), $options['limit'])
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
			$merge = $sDb->fetchAll($this->_getSelectUserSql('xf_user.user_id IN (' . $sDb->quote(array_keys($manual)) . ')'));

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
					$users[$user['user_id']] = $this->_quickAssembleData($user, array(
						'username',
						'email',
						'message_count',
						'register_date',
						'conflict' => $manual[$user['user_id']]
					));
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
			$users = $this->_sourceDb->fetchAll($this->_getSelectUserSql('xf_user.user_id IN (' . $sDb->quote(array_keys($manual)) . ')'));

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
					$failedUsers[$user['userid']] = $this->_quickAssembleData($user, array(
						'username',
						'email',
						'message_count',
						'register_date',
						'failure' => $manual[$user['user_id']]
					));
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
					$user['email'] = $info['email'];
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

				if ($match = $im->getUserIdByEmail($this->_convertToUtf8($user['email'])))
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

	/**
	 * Returns SQL to fetch a complete user record
	 *
	 * @param string $where
	 *
	 * @return string
	 */
	protected function _getSelectUserSql($where)
	{
		return '
			SELECT
				xf_user.*,
				xf_user_option.*,
				xf_user_privacy.*,
				xf_user_profile.*,
				xf_user_authenticate.*
			FROM xf_user
			INNER JOIN  xf_user_option ON
				(xf_user_option.user_id = xf_user.user_id)
			INNER JOIN  xf_user_privacy ON
				(xf_user_privacy.user_id = xf_user.user_id)
			INNER JOIN  xf_user_profile ON
				(xf_user_profile.user_id = xf_user.user_id)
			INNER JOIN xf_user_authenticate ON
				(xf_user_authenticate.user_id = xf_user.user_id)
			WHERE '  . $where . '
			ORDER BY xf_user.user_id
		';
	}

	/**
	 * Determines whether to import or merge the specified user
	 *
	 * @param array $user
	 * @param array $options
	 * @return boolean
	 */
	protected function _importOrMergeUser(array $user, array $options = array())
	{
		$im = $this->_importModel;

		if ($user['email'] && $emailMatch = $im->getUserIdByEmail($user['email']))
		{
			if (!empty($options['mergeEmail']))
			{
				return $this->_mergeUser($user, $emailMatch);
			}
			else
			{
				if ($im->getUserIdByUserName($user['username']))
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

		if ($nameMatch = $im->getUserIdByUserName($user['username']))
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

	protected function _mergeUser(array $user, $targetUserId)
	{
		$this->_db->query('
			UPDATE xf_user SET
				message_count = message_count + ?,
				register_date = IF(register_date > ?, ?, register_date)
			WHERE user_id = ?
		', array($user['message_count'], $user['register_date'], $user['register_date'], $targetUserId));

		$this->_importModel->logImportData('user', $user['user_id'], $targetUserId);

		return $targetUserId;
	}

	protected function _importUser(array $user, array $options = array())
	{

		if ($this->_groupMap === null)
		{
			$this->_groupMap = $this->_importModel->getImportContentMap('userGroup');
		}

		$import = $this->_quickAssembleData($user, array(

		// xf_user
			#'user_id',
			'username',
			'email',
			'gender',
			'custom_title',
			#'language_id',
			#'style_id',
			'timezone',
			'visible',
			'user_group_id' => $this->_mapLookUp($this->_groupMap, $user['user_group_id'], XenForo_Model_User::$defaultRegisteredGroupId),
			'secondary_group_ids' => $this->_translateUserGroupIdList($user['secondary_group_ids'], true),
			'display_style_group_id' => $this->_mapLookUp($this->_groupMap, $user['display_style_group_id'], XenForo_Model_User::$defaultRegisteredGroupId),
			#'permission_combination_id',
			'message_count',
			'conversations_unread',
			'register_date',
			'last_activity',
			#'trophy_points',
			'avatar_date',
			'avatar_width',
			'avatar_height',
			'gravatar',
			'user_state',
			'is_moderator',
			'is_admin',
			'is_staff',
			'is_banned',
			'like_count',
			'warning_points',

		// xf_user_profile
			'dob_day',
			'dob_month',
			'dob_year',
			'status',
			'status_date',
			#'status_profile_post_id',
			'signature',
			'homepage',
			'location',
			'occupation',
			#'following',
			#'csrf_token',
			'avatar_crop_x',
			'avatar_crop_y',
			'about',
			#'custom_fields',
			#'ignored',

		// xf_user_option
			'show_dob_year',
			'show_dob_date',
			'content_show_signature',
			'receive_admin_email',
			'email_on_conversation',
			'is_discouraged',
			'default_watch_state',
			'alert_optout',
			'enable_rte',
			'enable_flash_uploader',

		// xf_user_privacy
			'allow_view_profile',
			'allow_post_profile',
			'allow_send_personal_conversation',
			'allow_view_identities',
			'allow_receive_news_feed',

		// xf_user_authenticate
			'scheme_class',
			'data',
			#'remember_key',
		));

		// custom user fields
		if ($user['custom_fields'] = unserialize($user['custom_fields']))
		{
			if ($this->_userFieldMap === null)
			{
				$this->_userFieldMap = $this->_importModel->getImportContentMap('userField');
			}

			$userFieldDefinitions = $this->_importModel->getUserFieldDefinitions();

			foreach ($user['custom_fields'] AS $oldFieldId => $customField)
			{
				$newFieldId = $this->_mapLookUp($this->_userFieldMap, $oldFieldId);
				if (!$newFieldId)
				{
					continue;
				}

				$import[XenForo_Model_Import::USER_FIELD_KEY][$newFieldId] = $user['custom_fields'][$oldFieldId];
			}
		}

		$importedUserId = $this->_importModel->importUser($user['user_id'], $import, $failedKey, false);

		if ($importedUserId)
		{
			// banned users
			if ($user['is_banned'])
			{
				if ($ban = $this->_sourceDb->fetchRow('SELECT * FROM xf_user_ban WHERE user_id = ?', $user['user_id']))
				{
					$this->_importModel->importBan($this->_quickAssembleData($ban, array(
						'user_id' => $importedUserId,
						'ban_user_id' => $this->_importModel->mapUserId($ban['ban_user_id'], 0),
						'ban_date',
						'end_date',
						'user_reason',
						'triggered',
					)));
				}
			}

			// handle admins in a different way from normal imports so that we get a complete data import
			if ($user['is_admin'] && (!$this->_importModel->keysRetained() || $user['user_id'] != 1))
			{
				if ($admin = $this->_sourceDb->fetchRow('SELECT * FROM xf_admin WHERE user_id = ?', $user['user_id']))
				{
					$this->_importModel->importAdmin($this->_quickAssembleData($admin, array(
						'user_id' => $importedUserId,
						'extra_user_group_ids' => $this->_translateUserGroupIdList($admin['extra_user_group_ids']),
						'last_login',
						'permission_cache'
					)));
				}
			}

			// avatar
			if ($user['avatar_date'])
			{
				/* @var $avatarModel XenForo_Model_Avatar */
				$avatarModel = XenForo_Model::create('XenForo_Model_Avatar');

				foreach (array('l', 'm', 's') AS $size)
				{
					$oldAvatar = $avatarModel->getAvatarFilePath($user['user_id'], $size, $this->_config['dir']['data']);
					if (!file_exists($oldAvatar) || !is_readable($oldAvatar))
					{
						continue;
					}
					$newAvatar = $avatarModel->getAvatarFilePath($importedUserId, $size);

					$directory = dirname($newAvatar);
					if (XenForo_Helper_File::createDirectory($directory, true) && is_writable($directory))
					{
						copy($oldAvatar, $newAvatar);
					}
				}
			}
		}
		else if ($failedKey)
		{
			$this->_session->setExtraData('userFailed', $user['user_id'], $failedKey);
		}

		return $importedUserId;
	}

	/**
	 * Translates xf_user.secondary_group_ids to new group IDs
	 *
	 * @param string $idString
	 * @param boolean If true, return an array instead of a string
	 *
	 * @return string|array
	 */
	protected function _translateUserGroupIdList($idString, $returnArray = false)
	{
		$groupIds = array();

		if (!empty($idString))
		{
			if ($this->_groupMap === null)
			{
				$this->_groupMap = $this->_importModel->getImportContentMap('userGroup');
			}

			$oldGroupIds = preg_split('/,/', $idString, -1, PREG_SPLIT_NO_EMPTY);
			foreach ($oldGroupIds AS $oldGroupId)
			{
				$gId = $this->_mapLookUp($this->_groupMap, $oldGroupId);
				if ($gId)
				{
					$groupIds[] = $gId;
				}
			}
		}

		return ($returnArray ? $groupIds : implode(',', $groupIds));
	}

	public function stepFollowIgnore($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false,
		), $options);

		// fetch 100 users and insert following and ignoring records for each

		$sDb = $this->_sourceDb;
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('SELECT MAX(user_id) FROM xf_user');
		}

		$userIds = $sDb->fetchCol($sDb->limit("
			SELECT user_id
			FROM xf_user_profile
			WHERE user_id > ? AND
				(following <> '' OR (ignored <> '' AND ignored <> 'a:0:{}'))
			ORDER BY user_id
			", $options['limit']
		), $start);
		if (!$userIds)
		{
			return true;
		}

		$searchUserIds = $userIds;
		$users = array();

		// collect user ids from follow and ignore tables

		$follows = $sDb->fetchAll('SELECT * FROM xf_user_follow WHERE user_id IN(' . $sDb->quote($searchUserIds) . ')');
		foreach ($follows AS $follow)
		{
			$userIds[] = $follow['follow_user_id'];
			$users[$follow['user_id']]['following'][] = $follow['follow_user_id'];
		}

		$ignores = $sDb->fetchAll('SELECT * FROM xf_user_ignored WHERE user_id IN(' . $sDb->quote($searchUserIds) . ')');
		foreach ($ignores AS $ignore)
		{
			$userIds[] = $ignore['ignored_user_id'];
			$users[$ignore['user_id']]['ignoring'][] = $ignore['ignored_user_id'];
		}

		ksort($users);

		// get a map for all referenced user ids
		$userIdMap = $model->getImportContentMap('user', array_unique($userIds));

		XenForo_Db::beginTransaction();

		$next = 0;
		$total = 0;

		foreach ($users AS $userId => $user)
		{
			$next = $userId;

			$importUserId = $this->_mapLookUp($userIdMap, $userId);
			if (!$importUserId)
			{
				continue;
			}

			if (!empty($user['following']))
			{
				$followUserIds = array();
				foreach ($user['following'] AS $followUserId)
				{
					if ($importFollowUserId = $this->_mapLookUp($userIdMap, $followUserId, 0))
					{
						$followUserIds[$followUserId] = $importFollowUserId;
					}
				}

				if ($followUserIds && $model->importFollowing($importUserId, $followUserIds))
				{
					$total++;
				}
			}

			if (!empty($user['ignoring']))
			{
				$ignoreUserIds = array();
				foreach ($user['ignoring'] AS $ignoreUserId)
				{
					if ($importIgnoreUserId = $this->_mapLookUp($userIdMap, $ignoreUserId, 0))
					{
						$ignoreUserIds[$ignoreUserId] = $importIgnoreUserId;
					}
				}

				if ($ignoreUserIds && $model->importIgnored($importUserId, $ignoreUserIds))
				{
					$total++;
				}
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));

	}

	public function stepConversations($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(conversation_id)
				FROM xf_conversation_master
			');
		}

		$conversations = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM xf_conversation_master
				WHERE conversation_id > ' . $sDb->quote($start) . '
				ORDER BY conversation_id
			', $options['limit']
		));
		if (!$conversations)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($conversations AS $conversation)
		{
			$next = $conversation['conversation_id'];

			$recipientRecords = $sDb->fetchAll('
				SELECT *
				FROM xf_conversation_recipient
				WHERE conversation_id = ?
				', $conversation['conversation_id']);

			// build a user map
			$userIds = array($conversation['user_id']);
			foreach ($recipientRecords AS $recipient)
			{
				$userIds[] = $recipient['user_id'];
			}

			// get a map for all referenced user ids
			$userIdMap = $model->getImportContentMap('user', $userIds);

			$recipients = array();
			foreach ($recipientRecords AS $recipient)
			{
				$recipientUserId = $this->_mapLookUp($userIdMap, $recipient['user_id'], 0);

				$recipients[$recipientUserId] = $this->_quickAssembleData($recipient, array(
					'recipient_state',
					'last_read_date'
				));
			}

			$messageRecords = $sDb->fetchAll('
				SELECT *
				FROM xf_conversation_message
				WHERE conversation_id = ?
				', $conversation['conversation_id']);

			$messages = array();
			foreach ($messageRecords AS $message)
			{
				$messages[$message['message_id']] = $this->_quickAssembleData($message, array(
					'message_date',
					'user_id' => $this->_mapLookUp($userIdMap, $message['user_id'], 0),
					'username',
					'message',
					'attach_count'
				));
			}

			$importConversation = $this->_quickAssembleData($conversation, array(
				'title',
				'user_id' => $this->_mapLookUp($userIdMap, $conversation['user_id'], 0),
				'username',
				'start_date',
				'reply_count',
				'recipient_count',
				'open_invite',
				'conversation_open',
				'last_message_date',
				'last_message_user_id' => $this->_mapLookUp($userIdMap, $conversation['last_message_user_id'], 0),
				'last_message_username',
			));

			if ($model->importConversation($conversation['conversation_id'], $importConversation, $recipients, $messages))
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepProfilePosts($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 200,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(profile_post_id)
				FROM xf_profile_post
			');
		}

		$profilePosts = $sDb->fetchAll($sDb->limit(
			'
				SELECT xf_profile_post.*, xf_ip.ip
				FROM xf_profile_post
				LEFT JOIN xf_ip ON (xf_ip.ip_id = xf_profile_post.ip_id)
				WHERE xf_profile_post.profile_post_id > ' . $sDb->quote($start) . '
				ORDER BY xf_profile_post.profile_post_id
			', $options['limit']
		));
		if (!$profilePosts)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		XenForo_Db::beginTransaction();
		$db = XenForo_Application::getDb();

		foreach ($profilePosts AS $profilePost)
		{
			$next = $profilePost['profile_post_id'];

			$userIds = array($profilePost['user_id'], $profilePost['profile_user_id']);

			if ($profilePost['comment_count'])
			{
				// fetch comments and insert
				$comments = $sDb->fetchAll('
					SELECT xf_profile_post_comment.*, xf_ip.ip
					FROM xf_profile_post_comment
					LEFT JOIN xf_ip ON (xf_ip.ip_id = xf_profile_post_comment.ip_id)
					WHERE xf_profile_post_comment.profile_post_id = ?
					', $profilePost['profile_post_id']);

				foreach ($comments AS $comment)
				{
					$userIds[] = $comment['user_id'];
				}
			}
			else
			{
				$comments = false;
			}

			$userIdMap = $model->getImportContentMap('user', $userIds);

			$profileUserId = $this->_mapLookUp($userIdMap, $profilePost['profile_user_id']);
			if (!$profileUserId)
			{
				continue;
			}

			$importProfilePost = $this->_quickAssembleData($profilePost, array(
				'profile_user_id' => $profileUserId,
				'user_id' => $this->_mapLookUp($userIdMap, $profilePost['user_id'], 0),
				'username',
				'post_date',
				'message',
				'ip' => XenForo_Helper_Ip::convertIpBinaryToString($profilePost['ip']),
				'message_state',
				#'attach_count',
				#'likes',
				#'like_users',
				'comment_count',
				'first_comment_date',
				'last_comment_date',
				#'latest_comment_ids',
				#'warning_id',
				#'warning_message',
			));

			if ($profilePostId = $model->importProfilePost($profilePost['profile_post_id'], $importProfilePost))
			{
				if (!empty($comments))
				{
					$lastCommentIds = array();
					foreach ($comments AS $comment)
					{
						$importComment = $this->_quickAssembleData($comment, array(
							'profile_post_id' => $profilePostId,
							'user_id' => $this->_mapLookUp($userIdMap, $comment['user_id'], 0),
							'username',
							'comment_date',
							'message',
							//'ip' => XenForo_Helper_Ip::convertIpBinaryToString($comment['ip']),
						));

						$lastCommentId = $model->importProfilePostComment($comment['profile_post_comment_id'], $importComment);
						if ($lastCommentId)
						{
							$lastCommentIds[] = $lastCommentId;
						}
					}

					$db->update('xf_profile_post', array(
						'latest_comment_ids' => implode(',', array_slice($lastCommentIds, -3))
					), 'profile_post_id = ' . $sDb->quote($profilePostId));
				}

				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	/**
	 * Currently handles categories, forums, link forums and pages
	 *
	 * @param integer $start
	 * @param array $options
	 */
	public function stepNodes($start, array $options)
	{
		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($start > 0)
		{
			// after importing everything, rebuild the full permission cache so forums appear
			XenForo_Model::create('XenForo_Model_Node')->updateNestedSetInfo();
			XenForo_Model::create('XenForo_Model_Permission')->rebuildPermissionCache();
			return true;
		}

		$nodes = $sDb->fetchAll('
			SELECT *
			FROM xf_node
			WHERE node_type_id IN (' . $sDb->quote($this->_nodeTypeIds) . ')
		');
		if (!$nodes)
		{
			return true;
		}

		// get data for all forums, link forums and pages
		$nodeData = array();
		foreach ($sDb->fetchAll('SELECT * FROM xf_forum') AS $forum)
		{
			$nodeData[$forum['node_id']] = $forum;
		}
		foreach ($sDb->fetchAll('SELECT * FROM xf_page') AS $page)
		{
			$nodeData[$page['node_id']] = $page;
		}
		foreach ($sDb->fetchAll('SELECT * FROM xf_link_forum') AS $linkForum)
		{
			$nodeData[$linkForum['node_id']] = $linkForum;
		}

		// build a tree of node data
		$nodeTree = array();
		foreach ($nodes AS $node)
		{
			if (isset($nodeData[$node['node_id']]))
			{
				$node = array_merge($node, $nodeData[$node['node_id']]);
			}

			$nodeTree[$node['parent_node_id']][$node['node_id']] = $node;
		}

		// fetch node permissions
		$nodePermissions = array();
		foreach ($sDb->fetchAll("SELECT * FROM xf_permission_entry_content WHERE content_type = 'node'") AS $nodePermission)
		{
			$nodePermissions[$nodePermission['content_id']][] = $nodePermission;
		}

		XenForo_Db::beginTransaction();

		$total = $this->_importNodeTree(0, $nodeTree, $nodePermissions);

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array(1, array(), '');
	}

	/**
	 * Works with a tree of node data to recursively import nodes
	 *
	 * @param integer $parentId
	 * @param array $nodeTree
	 * @param array $nodePermissions
	 * @param array $nodeIdMap
	 *
	 * @return number
	 */
	protected function _importNodeTree($parentId, array $nodeTree, array $nodePermissions = array(), array $nodeIdMap = array())
	{
		if (!isset($nodeTree[$parentId]))
		{
			return 0;
		}

		XenForo_Db::beginTransaction();

		$total = 0;

		foreach ($nodeTree[$parentId] AS $node)
		{
			$import = $this->_quickAssembleData($node, array(
				'title',
				'description',
				'node_type_id',
				'display_order',
				'display_in_list',
				'parent_node_id' => $this->_mapLookUp($nodeIdMap, $node['parent_node_id'], 0),
			));

			// don't even set node_name if it's not specified in the source record
			if ($node['node_name'])
			{
				$import['node_name'] = $node['node_name'];
			}

			switch ($node['node_type_id'])
			{
				case 'Forum':
				{
					$import = $this->_quickAssembleData($node, array(
						'discussion_count',
						'message_count',
						'allow_posting',
						'count_messages',
						'find_new',
						'default_sort_order',
						'default_sort_direction',
						'require_prefix',
						'allowed_watch_notifications',
						'allow_poll',
						'list_date_limit_days'
					), $import);

					if (isset($node['moderate_messages']))
					{
						$import['moderate_threads'] = $import['moderate_replies'] = $node['moderate_messages'];
					}
					else if (isset($node['moderate_replies']))
					{
						$import['moderate_threads'] = $node['moderate_threads'];
						$import['moderate_replies'] = $node['moderate_replies'];
					}

					$nodeId = $this->_importModel->importForum($node['node_id'], $import);
				}
				break;

				case 'LinkForum':
				{
					$import = $this->_quickAssembleData($node, array(
						'link_url',
						'redirect_count',
					), $import);

					$nodeId = $this->_importModel->importLinkForum($node['node_id'], $import);
				}
				break;

				case 'Page':
				{
					$import = $this->_quickAssembleData($node, array(
						'publish_date',
						'modified_date',
						'view_count',
						'list_siblings',
						'list_children',
						'log_visits',
						'callback_class',
						'callback_method',
					), $import);

					/* @var $pageModel XenForo_Model_Page */
					$pageModel = XenForo_Model::create('XenForo_Model_Page');

					$template = $this->_sourceDb->fetchOne('
						SELECT template
						FROM xf_template
						WHERE style_id = 0
						AND title = ?', $pageModel->getTemplateTitle($node));

					$nodeId = $this->_importModel->importPage($node['node_id'], $import, $template);
				}
				break;

				default: // Category
				{
					// no additional data to import, so just grab the node info

					$nodeId = $this->_importModel->importCategory($node['node_id'], $import);
				}
			}

			if ($nodeId)
			{
				if (!empty($nodePermissions[$node['node_id']]))
				{
					$this->_importNodePermissions($nodeId, $nodePermissions[$node['node_id']]);
				}

				$nodeIdMap[$node['node_id']] = $nodeId;

				$total++;

				$total += $this->_importNodeTree($node['node_id'], $nodeTree, $nodePermissions, $nodeIdMap);
			}
		}

		XenForo_Db::commit();

		return $total;
	}

	protected function _importNodePermissions($nodeId, array $nodePerms)
	{
		if ($this->_groupMap === null)
		{
			$this->_groupMap = $this->_importModel->getImportContentMap('userGroup');
		}

		XenForo_Db::beginTransaction();

		foreach ($nodePerms AS $nodeId => $nodePerm)
		{
			if (!empty($nodePerm['user_id']))
			{
				$userId = $this->_importModel->mapUserId($nodePerm['user_id'], 0);
				$userGroupId = 0;

				if (!$userId)
				{
					continue;
				}
			}
			else
			{
				$userId = 0;
				$userGroupId = $this->_mapLookUp($this->_groupMap, $nodePerm['user_group_id']);

				if (!$userGroupId)
				{
					continue;
				}
			}

			if ($nodePerm['permission_value'] == 'use_int')
			{
				$permValue = $nodePerm['permission_value_int'];
			}
			else
			{
				$permValue = $nodePerm['permission_value'];
			}

			$perms = array();

			$perms[$nodePerm['permission_group_id']][$nodePerm['permission_id']] = $permValue;

			$this->_importModel->insertNodePermissionEntries($nodeId, $userGroupId, $userId, $perms);
		}

		XenForo_Db::commit();
	}

	/**
	 * Currenty handles global and node moderators only
	 *
	 * @param integer $start
	 * @param array $options
	 */
	public function stepModerators($start, array $options)
	{
		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$moderators = $sDb->fetchAll('SELECT * FROM xf_moderator');
		if (!$moderators)
		{
			return true;
		}

		$nodeModerators = $sDb->fetchAll("SELECT * FROM xf_moderator_content WHERE content_type = 'node'");

		$modsGrouped = array();

		foreach ($moderators AS $moderator)
		{
			$modsGrouped[$moderator['user_id']] = $moderator;
		}

		foreach ($nodeModerators AS $cm)
		{
			$modsGrouped[$cm['user_id']]['nodes'][$cm['content_id']] = $cm['moderator_permissions'];
		}

		$nodeMap = $model->getImportContentMap('node');
		$userIdMap = $model->getImportContentMap('user', array_keys($modsGrouped));

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($modsGrouped AS $userId => $user)
		{
			$newUserId = $this->_mapLookUp($userIdMap, $userId);
			if (!$newUserId)
			{
				continue;
			}

			if (isset($user['nodes']))
			{
				foreach ($user['nodes'] AS $nodeId => $permissions)
				{
					$newNodeId = $this->_mapLookUp($nodeMap, $nodeId);
					if (!$newNodeId)
					{
						continue;
					}

					$mod = array(
						'user_id' => $newUserId,
						'content_id' => $newNodeId,
						'moderator_permissions' => unserialize($permissions)
					);

					$model->importNodeModerator($nodeId, $userId, $mod);

					$total++;
				}
			}

			if ($user['extra_user_group_ids'])
			{
				$extraGroupIds = $this->_translateUserGroupIdList($user['extra_user_group_ids']);
			}
			else
			{
				$extraGroupIds = '';
			}

			$mod = array(
				'user_id' => $newUserId,
				'is_super_moderator' => $user['is_super_moderator'],
				'extra_user_group_ids' => $extraGroupIds,
				'moderator_permissions' => unserialize($user['moderator_permissions'])
			);

			$model->importGlobalModerator($userId, $mod);

			$total++;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return true;
	}

	/**
	 * Assumes phrase names of thread_prefix_% and thread_prefix_group_%
	 *
	 * @param integer $start
	 * @param array $options
	 */
	public function stepThreadPrefixes($start, array $options)
	{
		$options = array_merge(array(), $options);

		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$nodeMap = $model->getImportContentMap('node');
		$userGroupMap = $model->getImportContentMap('userGroup');

		$prefixes = $sDb->fetchAll("
			SELECT tp.*,
				p.phrase_text
			FROM xf_thread_prefix AS tp
			INNER JOIN xf_phrase AS p
			ON (
				p.language_id = 0 AND
				p.title = CONCAT('thread_prefix_', tp.prefix_id)
			)
		");
		if (!$prefixes)
		{
			return true;
		}

		$prefixGroups = $sDb->fetchAll("
			SELECT tpg.*,
				p.phrase_text
			FROM xf_thread_prefix_group AS tpg
			INNER JOIN xf_phrase AS p
			ON (
				p.language_id = 0 AND
				p.title = CONCAT('thread_prefix_group_', tpg.prefix_group_id)
			)
		");

		$prefixGroupMap = array();
		foreach ($prefixGroups AS $prefixGroup)
		{
			$importPrefixGroup = array(
				'display_order' => $prefixGroup['display_order'],
				XenForo_Model_Import::EXTRA_DATA_KEY => array(
					XenForo_DataWriter_ThreadPrefixGroup::DATA_TITLE => $prefixGroup['phrase_text']
				)
			);

			$prefixGroupMap[$prefixGroup['prefix_group_id']] = $model->importThreadPrefixGroup($prefixGroup['prefix_group_id'], $importPrefixGroup);
		}

		$forumPrefixes = array();
		foreach ($sDb->fetchAll('SELECT * FROM xf_forum_prefix') AS $forumPrefix)
		{
			$nodeId = $this->_mapLookUp($nodeMap, $forumPrefix['node_id']);
			if ($nodeId)
			{
				$forumPrefixes[$forumPrefix['prefix_id']][] = $nodeId;
			}
		}

		$total = 0;

		foreach ($prefixes AS $prefix)
		{
			$prefixGroupId = $this->_mapLookUp($prefixGroupMap, $prefix['prefix_group_id'], 0);

			if ($prefix['allowed_user_group_ids'] == '-1')
			{
				$allowedUserGroupIds = '-1';
			}
			else
			{
				$allowedUserGroupIds = $this->_translateUserGroupIdList($prefix['allowed_user_group_ids']);
			}

			$importPrefix = $this->_quickAssembleData($prefix, array(
				'prefix_group_id' => $prefixGroupId,
				'display_order',
				'css_class',
				'allowed_user_group_ids' => $allowedUserGroupIds,
			));

			if (isset($forumPrefixes[$prefix['prefix_id']]))
			{
				$nodeIds = $forumPrefixes[$prefix['prefix_id']];
			}
			else
			{
				$nodeIds = array();
			}

			$model->importThreadPrefix($prefix['prefix_id'], $importPrefix, $prefix['phrase_text'], $nodeIds);

			$total++;
		}

		// build the prefix caches
		$prefixModel = XenForo_Model::create('XenForo_Model_ThreadPrefix');
		$prefixModel->rebuildPrefixMaterializedOrder();
		$prefixModel->rebuildPrefixCache();

		XenForo_Db::commit($this->_db);

		$this->_session->incrementStepImportTotal($total);

		return true;
	}

	/**
	 * Does not currently handle redirects
	 *
	 * @param integer $start
	 * @param array $options
	 */
	public function stepThreads($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'postDateStart' => 0,
			'postLimit' => 800,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(thread_id)
				FROM xf_thread
			');
		}

		$threads = $sDb->fetchAll($sDb->limit(
			"
				SELECT thread.*,
					IF (user.username IS NULL, thread.username, user.username) AS username
				FROM xf_thread AS thread
				LEFT JOIN xf_user AS user ON (user.user_id = thread.user_id)
				WHERE thread.thread_id >= " . $sDb->quote($start) . "
					AND thread.discussion_type <> 'redirect'
			", $options['limit']
		));
		if (!$threads)
		{
			return true;
		}

		$next = 0;
		$total = 0;
		$totalPosts = 0;

		$nodeMap = $model->getImportContentMap('node');
		$threadPrefixMap = $model->getImportContentMap('threadPrefix');

		XenForo_Db::beginTransaction();

		foreach ($threads AS $thread)
		{
			$postDateStart = $options['postDateStart'];

			$next = $thread['thread_id'] + 1;
			$options['postDateStart'] = 0;

			$maxPosts = $options['postLimit'] - $totalPosts;
			$posts = $sDb->fetchAll($sDb->limit(
				"
					SELECT post.*,
						IF (user.username IS NULL, post.username, user.username) AS username,
						xf_ip.ip
					FROM xf_post AS post
					LEFT JOIN xf_user AS user ON (user.user_id = post.user_id)
					LEFT JOIN xf_ip ON (xf_ip.ip_id = post.ip_id)
					WHERE post.thread_id = " . $sDb->quote($thread['thread_id']) . "
						AND post.post_date > " . $sDb->quote($postDateStart) . "
					ORDER BY post.post_date
				", $maxPosts
			));
			if (!$posts)
			{
				if ($postDateStart)
				{
					// continuing thread but no remaining threads
					$total++;
				}
				continue;
			}

			if ($postDateStart)
			{
				// continuing already-imported thread
				$threadId = $model->mapThreadId($thread['thread_id']);

				$position = $this->_db->fetchOne('
					SELECT MAX(position)
					FROM xf_post
					WHERE thread_id = ?
				', $threadId);
			}
			else
			{
				$forumId = $this->_mapLookUp($nodeMap, $thread['node_id']);
				if (!$forumId)
				{
					continue;
				}

				$import = $this->_quickAssembleData($thread, array(
					'node_id' => $forumId,
					'title',
					'reply_count',
					'view_count',
					'user_id' => $model->mapUserId($thread['user_id'], 0),
					'username',
					'post_date',
					'sticky',
					'discussion_state',
					'discussion_open',
					'discussion_type',
					'prefix_id' => $this->_mapLookUp($threadPrefixMap, $thread['prefix_id'], 0)
				));

				$threadId = $model->importThread($thread['thread_id'], $import);
				if (!$threadId)
				{
					continue;
				}

				$subscriptions = $sDb->fetchPairs('
					SELECT user_id, email_subscribe
					FROM xf_thread_watch
					WHERE thread_id = ?
					', $thread['thread_id']
				);
				if ($subscriptions)
				{
					$userIdMap = $model->getImportContentMap('user', array_keys($subscriptions));
					foreach ($subscriptions AS $userId => $emailSubscribe)
					{
						if ($newUserId = $this->_mapLookUp($userIdMap, $userId))
						{
							$model->importThreadWatch($newUserId, $threadId, $emailSubscribe);
						}
					}
				}
			}

			if ($threadId)
			{
				$quotedPostIds = array();
				$quotedUserIds = array();

				$userIdMap = $model->getUserIdsMapFromArray($posts, 'user_id');

				foreach ($posts AS $i => $post)
				{
					$import = $this->_quickAssembleData($post, array(
						'thread_id' => $threadId,
						'user_id' => $this->_mapLookUp($userIdMap, $post['user_id'], 0),
						'username',
						'post_date',
						'message',
						'ip' => XenForo_Helper_Ip::convertIpBinaryToString($post['ip']),
						'message_state',
						'attach_count',
						'position',
						'likes',
						'last_edit_date',
						'edit_count',
					));

					$post['new_post_id'] = $model->importPost($post['post_id'], $import);

					$options['postDateStart'] = $post['post_date'];
					$totalPosts++;

					// quotes
					if (stripos($post['message'], '[quote=') !== false)
					{
						if (preg_match_all('/\[quote=("|\'|)(?P<username>[^,]*),post:\s*(?P<post_id>\d+)\s*,\s*member:\s*(?P<user_id>\d+)\s*\1\]/siU', $post['message'], $quotes, PREG_SET_ORDER))
						{
							$post['quotes'] = array();

							foreach ($quotes AS $quote)
							{
								$quotedPostId = intval($quote['post_id']);
								$quotedPostIds[] = $quotedPostId;

								$quotedUserId = intval($quote['user_id']);
								$quotedUserIds[] = $quotedUserId;

								$post['quotes'][$quote[0]] = array($quote['username'], $quotedPostId, $quotedUserId);
							}
						}
					}

					$posts[$i] = $post;
				}

				$postIdMap = (empty($quotedPostIds) ? array() : $model->getImportContentMap('post', $quotedPostIds));
				$userIdMap = array_merge($userIdMap, (empty($quotedUserIds) ? array() : $model->getImportContentMap('user', $quotedUserIds)));

				$db = XenForo_Application::getDb();

				foreach ($posts AS $post)
				{
					if (!empty($post['quotes']))
					{
						$postQuotesRewrite = $this->_rewritePostQuotes($post['message'], $post['quotes'], $postIdMap, $userIdMap);

						if ($post['message'] != $postQuotesRewrite)
						{
							$db->update('xf_post', array('message' => $postQuotesRewrite), 'post_id = ' . $db->quote($post['new_post_id']));
						}
					}
				}
			}

			if (count($posts) < $maxPosts)
			{
				// this thread completed
				$total++;
				$options['postDateStart'] = 0;
			}
			else
			{
				// pick up the thread on the next go-around
				break;
			}
		}

		if ($options['postDateStart'])
		{
			// thread not yet completed
			$next--;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next - 1, $options['max']));
	}

	/**
	 * Rewrites post quotes with imported user and post ids
	 *
	 * @param string $message
	 * @param array $quotes
	 * @param array $postMap
	 * @param array $userMap
	 *
	 * @return array
	 */
	protected function _rewritePostQuotes($message, array $quotes, array $postMap, array $userMap)
	{
		foreach ($quotes AS $quote => &$replace)
		{
			list($username, $postId, $userId) = $replace;

			$replace = sprintf('[quote="%s, post: %d, member: %d"]',
				$username,
				$this->_mapLookUp($postMap, $postId, 0),
				$this->_mapLookUp($userMap, $userId, 0)
			);
		}

		return str_replace(array_keys($quotes), $quotes, $message);
	}

	/**
	 * Quick method to allow array data with specific keys to be added to an output array
	 *
	 * @param array Input data
	 * @param array Keys to copy
	 * @param array Existing output array, if it exists
	 *
	 * @return array
	 */
	protected function _quickAssembleData(array $info, array $keys, array $output = array())
	{
		foreach ($keys AS $key => $value)
		{
			if (is_string($key))
			{
				$output[$key] = $value;
			}
			else if (array_key_exists($value, $info))
			{
				$output[$value] = $info[$value];
			}
		}

		return $output;
	}

	/**
	 * Currently handles edit history for posts only
	 *
	 * @param integer $start
	 * @param array $options
	 */
	public function stepPostEditHistory($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne("
				SELECT MAX(content_id)
				FROM xf_edit_history
				WHERE content_type = 'post'
			");
		}

		// fetch the next 100 posts
		$postIds = $sDb->fetchCol($sDb->limit(
			"
				SELECT DISTINCT content_id
				FROM xf_edit_history
				WHERE content_id > " . $sDb->quote($start) . "
					AND content_type = 'post'
				ORDER BY content_id
			", $options['limit']
		));
		if (!$postIds)
		{
			return true;
		}

		$edits = $sDb->fetchAll("
			SELECT *
			FROM xf_edit_history
			WHERE content_id IN (" . $sDb->quote($postIds) . ")
				AND content_type = 'post'
			ORDER BY edit_date
		");
		if (!$edits)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$postIdMap = $model->getPostIdsMapFromArray($edits, 'content_id');
		$userIdMap = $model->getUserIdsMapFromArray($edits, 'edit_user_id');

		foreach ($edits AS $edit)
		{
			$next = $edit['content_id'];

			$contentId = $this->_mapLookUp($postIdMap, $edit['content_id']);
			if (!$contentId)
			{
				continue;
			}

			$import = $this->_quickAssembleData($edit, array(
				'content_type',
				'content_id' => $contentId,
				'edit_user_id' => $this->_mapLookUp($userIdMap, $edit['edit_user_id'], 0),
				'edit_date',
				'old_text'
			));

			$model->importEditHistory($edit['edit_history_id'], $import);

			$total++;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	/**
	 * Currently handles polls for threads only
	 *
	 * @param integer $start
	 * @param array $options
	 */
	public function stepPolls($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(poll_id)
				FROM xf_poll
				WHERE content_type IN (' . $sDb->quote($this->_pollContentTypes) . ')
			');
		}

		$polls = $sDb->fetchAll($sDb->limit(
			'
				SELECT poll.*
				FROM xf_poll AS poll
				WHERE content_type IN (' . $sDb->quote($this->_pollContentTypes) . ')
					AND poll.poll_id > ' . $sDb->quote($start) . '
				ORDER BY poll.poll_id
			', $options['limit']
		));
		if (!$polls)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$threadIdMap = $model->getThreadIdsMapFromArray($polls, 'content_id');

		XenForo_Db::beginTransaction();

		foreach ($polls AS $poll)
		{
			$next = $poll['poll_id'];

			$newThreadId = $this->_mapLookUp($threadIdMap, $poll['content_id']);
			if (!$newThreadId)
			{
				continue;
			}

			$import = $this->_quickAssembleData($poll, array(
				'content_type',
				'content_id' => $newThreadId,
				'question',
				'voter_count',
				'public_votes',
				'change_votes',
				'view_results_unvoted',
				'close_date'
			));

			if (isset($poll['multiple']))
			{
				$import['max_votes'] = $poll['multiple'] ? 0 : 1;
			}
			else if (isset($poll['max_votes']))
			{
				$import['max_votes'] = $poll['max_votes'];
			}

			$responses = array();
			foreach (unserialize($poll['responses']) AS $responseId => $response)
			{
				$responses[$responseId] = $response['response'];
			}

			$newPollId = $model->importThreadPoll($poll['poll_id'], $newThreadId, $import, $responses, $responseIds);

			$votes = $sDb->fetchAll('
				SELECT user_id, poll_response_id, vote_date
				FROM xf_poll_vote
				WHERE poll_id = ?
				', $poll['poll_id']);

			if ($votes)
			{
				$userIdMap = $model->getUserIdsMapFromArray($votes, 'user_id');

				foreach ($votes AS $vote)
				{
					$userId = $this->_mapLookUp($userIdMap, $vote['user_id']);
					if (!$userId)
					{
						continue;
					}

					$model->importPollVote($newPollId,
						$userId,
						$this->_mapLookUp($responseIds, $vote['poll_response_id']),
						$vote['vote_date']);
				}
			}

			$total++;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	/**
	 * Currently handles attachments for posts and conversation messages only
	 *
	 * @param integer $start
	 * @param array $options
	 */
	public function stepAttachments($start, array $options)
	{
		$options = array_merge(array(
			'data' => isset($this->_config['dir']['data']) ? $this->_config['dir']['data'] : '',
			'internal_data' => isset($this->_config['dir']['internal_data']) ? $this->_config['dir']['internal_data'] : '',
			'limit' => 50,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$attachmentSqlContentInfo = $model->getAttachmentContentSqlInfo();

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(attachment_id)
				FROM xf_attachment
				WHERE content_type IN (' . $sDb->quote(array_keys($attachmentSqlContentInfo)) . ')
			');
		}

		$attachments = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM xf_attachment AS a
				LEFT JOIN xf_attachment_data AS ad ON (ad.data_id = a.data_id)
				WHERE a.content_type IN (' . $sDb->quote(array_keys($attachmentSqlContentInfo)) . ')
					AND a.attachment_id > ' . $sDb->quote($start) . '
				ORDER BY a.attachment_id
			', $options['limit']
		));
		if (!$attachments)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIdMap = $model->getUserIdsMapFromArray($attachments, 'user_id');

		$groupedAttachments = array();
		foreach ($attachments AS $attachment)
		{
			$groupedAttachments[$attachment['content_type']][$attachment['attachment_id']] = $attachment;
		}

		$db = XenForo_Application::getDb();

		/* @var $attachModel XenForo_Model_Attachment */
		$attachModel = XenForo_Model::create('XenForo_Model_Attachment');

		foreach ($groupedAttachments AS $contentType => $attachments)
		{
			list($sqlTableName, $sqlIdName) = $attachmentSqlContentInfo[$contentType];

			$contentIdMap = $model->getImportContentMap($contentType, XenForo_Application::arrayColumn($attachments, 'content_id'));

			if ($contentIdMap)
			{
				$contentItems = $db->fetchPairs("
					SELECT $sqlIdName, message
					FROM $sqlTableName
					WHERE $sqlIdName IN (" . $db->quote($contentIdMap) . ")
				");
			}
			else
			{
				$contentItems = array();
			}

			foreach ($attachments AS $attachment)
			{
				$next = max($next, $attachment['attachment_id']);

				$contentId = $this->_mapLookUp($contentIdMap, $attachment['content_id']);
				if (!$contentId || !isset($contentItems[$contentId]))
				{
					continue;
				}

				$attachFileOrig = $attachModel->getAttachmentDataFilePath($attachment, $options['internal_data']);
				if (!file_exists($attachFileOrig))
				{
					continue;
				}

				$userId = $this->_mapLookUp($userIdMap, $attachment['user_id'], 0);

				$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				copy($attachFileOrig, $attachFile);

				$success = $model->importAttachment(
					$attachment['attachment_id'],
					$attachment['filename'],
					$attachFile,
					$userId,
					$contentType,
					$contentId,
					$attachment['upload_date'],
					array('view_count' => $attachment['view_count']),
					array($this, 'processAttachmentTags'),
					$contentItems[$contentId]
				);
				if ($success)
				{
					$total++;
				}

				@unlink($attachFile);
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	/**
	 * Limited to likes for post and profile posts at present
	 *
	 * @param integer $start
	 * @param array $options
	 */
	public function stepLikes($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$likeTypes = $model->getSupportedLikeTypes();

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(like_id)
				FROM xf_liked_content
				WHERE content_type IN (' . $sDb->quote(array_keys($likeTypes)) . ')
			');
		}

		$likes = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM xf_liked_content
				WHERE content_type IN (' . $sDb->quote(array_keys($likeTypes)) . ')
					AND like_id > ' . $sDb->quote($start) . '
				ORDER BY like_id
			', $options['limit']
		));
		if (!$likes)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$groupedLikes = array();
		$userIds = array();
		foreach ($likes AS $like)
		{
			$groupedLikes[$like['content_type']][$like['content_id']] = $like;
			$userIds[] = $like['like_user_id'];
			$userIds[] = $like['content_user_id'];
		}

		$userIdMap = $model->getImportContentMap('user', $userIds);

		XenForo_Db::beginTransaction();

		foreach ($groupedLikes AS $contentType => $likes)
		{
			$contentIdMap = $model->getImportContentMap($contentType, array_keys($likes));

			foreach ($likes AS $contentId => $like)
			{
				$next = $like['like_id'];

				$newContentId = $this->_mapLookUp($contentIdMap, $contentId);
				if (!$newContentId)
				{
					continue;
				}

				$model->importLike(
					$contentType,
					$newContentId,
					$this->_mapLookUp($userIdMap, $like['content_user_id'], 0),
					$this->_mapLookUp($userIdMap, $like['like_user_id'], 0),
					$like['like_date']
				);

				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	/**
	 * Handles user, post and profile post warnings
	 *
	 * @param integer $start
	 * @param array $options
	 */
	public function stepWarnings($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$warningTypes = $model->getSupportedWarningTypes();

		/*if ($start == 0)
		{
			// first time around, import warning definitions

			// note: phrase relates to XenForo_Model_Warning::getWarningDefinitionTitlePhraseName()

			$warningDefinitions = $sDb->fetchAll("
				SELECT wd.*,
					phrase.phrase_text
				FROM xf_warning_definition AS wd
				INNER JOIN xf_phrase AS phrase ON
				(
					phrase.language_id = 0 AND
					phrase.title = CONCAT('warning_definition_', wd.warning_definition_id, '_title')
				)
			");
			foreach ($warningDefinitions AS $warningDefinition)
			{
				$import = $this->_quickAssembleData($warningDefinition, array(
					'points_default',
					'expiry_type',
					'expiry_default',
					'extra_user_group_ids' => $this->_translateUserGroupIdList($warningDefinition['extra_user_group_ids']),
					'is_editable',
					XenForo_Model_Import::EXTRA_DATA_KEY => array(
						XenForo_DataWriter_WarningDefinition::DATA_TITLE => $warning['title']
					)
				));
			}
		}*/

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne("
				SELECT MAX(warning_id)
				FROM xf_warning
				WHERE content_type IN (" . $sDb->quote(array_keys($warningTypes)) . ", 'user')
			");
		}

		$warnings = $sDb->fetchAll($sDb->limit(
			"
				SELECT *
				FROM xf_warning
				WHERE content_type IN (" . $sDb->quote(array_keys($warningTypes)) . ", 'user')
					AND warning_id > " . $sDb->quote($start) . "
			", $options['limit']
		));
		if (!$warnings)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$groupedWarnings = array();
		foreach ($warnings AS $warning)
		{
			$groupedWarnings[$warning['content_type']][$warning['warning_id']] = $warning;
		}

		$userIdMap = $model->getUserIdsMapFromArray($warnings, array('user_id', 'warning_user_id'));

		//$warningDefinitionMap = $model->getImportContentMap('warning_definition');

		XenForo_Db::beginTransaction();

		foreach ($groupedWarnings AS $contentType => $warnings)
		{

			if ($contentType == 'user')
			{
				// if we encounter a user warning, it will actually use the user id map
				$contentMap = $userIdMap;
			}
			else
			{
				$contentMap = $model->getImportContentMapFromArray($contentType, $warnings, 'content_id');
			}

			foreach ($warnings AS $warningId => $warning)
			{
				$next = $warningId;

				$userId = $this->_mapLookUp($userIdMap, $warning['user_id']);
				if (!$userId)
				{
					continue;
				}

				$contentId = $this->_mapLookUp($contentMap, $warning['content_id']);
				if (!$contentId)
				{
					continue;
				}

				/*$warningDefinitionId = $this->_mapLookUp($warningDefinitionMap, $warning['warning_definition_id']);
				if (!$warningDefinitionId)
				{
					continue;
				}*/
				$warningDefinitionId = 0;

				$import = $this->_quickAssembleData($warning, array(
					'content_type',
					'content_id' => $contentId,
					'content_title',
					'user_id' => $userId,
					'warning_date',
					'warning_user_id' => $this->_mapLookUp($userIdMap, $warning['warning_user_id'], 0),
					'warning_definition_id' => $warningDefinitionId,
					'title',
					'notes',
					'points',
					'expiry_date',
					'is_expired',
					'extra_user_group_ids' => $this->_translateUserGroupIdList($warning['extra_user_group_ids'])
				));

				if ($model->importWarning($warningId, $import))
				{
					$total++;
				}
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepUserUpgrades($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 200,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$total = 0;
		$next = 0;

		// import user upgrade definitions
		if ($start == 0)
		{
			$upgrades = $sDb->fetchAll('
				SELECT *
				FROM xf_user_upgrade
				ORDER BY user_upgrade_id
			');
			if (!$upgrades)
			{
				return true;
			}

			$upgradeMap = array();

			XenForo_Db::beginTransaction();

			foreach ($upgrades AS $upgrade)
			{
				$import = $this->_quickAssembleData($upgrade, array(
					'title',
					'description',
					'display_order',
					'extra_group_ids' => $this->_translateUserGroupIdList($upgrade['extra_group_ids']),
					'recurring',
					'cost_amount',
					'cost_currency',
					'length_amount',
					'length_unit',
					'disabled_upgrade_ids' => $this->_mapDisabledUpgradeIds($upgradeMap, $upgrade['disabled_upgrade_ids']),
					'can_purchase'
				));

				$newUpgradeId = $model->importUserUpgradeDefinition($upgrade['user_upgrade_id'], $import);
				if ($newUpgradeId)
				{
					$upgradeMap[$upgrade['user_upgrade_id']] = $newUpgradeId;

					$total++;
				}
			}

			XenForo_Db::commit();

			$this->_session->incrementStepImportTotal($total);
		}

		$total = 0;

		// import user upgrades
		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(user_upgrade_record_id)
				FROM xf_user_upgrade_active
			');
		}

		$activeUpgrades = $sDb->fetchAll($sDb->limit(
			'
				SELECT uua.*,
					uu.extra_group_ids
				FROM xf_user_upgrade_active AS uua
				INNER JOIN xf_user_upgrade AS uu ON
					(uu.user_upgrade_id = uua.user_upgrade_id)
				WHERE uua.user_upgrade_record_id > ' . $sDb->quote($start) . '
			', $options['limit']
		));
		if (!$activeUpgrades)
		{
			return true;
		}

		$userIdMap = $model->getUserIdsMapFromArray($activeUpgrades, 'user_id');

		if (empty($upgradeMap))
		{
			$upgradeMap = $model->getImportContentMap('user_upgrade');
		}

		XenForo_Db::beginTransaction();

		$extraGroupIdsCache = array();

		foreach ($activeUpgrades AS $activeUpgrade)
		{
			$next = $activeUpgrade['user_upgrade_record_id'];

			$userId = $this->_mapLookUp($userIdMap, $activeUpgrade['user_id']);
			if (!$userId)
			{
				continue;
			}

			$upgradeId = $this->_mapLookUp($upgradeMap, $activeUpgrade['user_upgrade_id']);
			if (!$upgradeId)
			{
				continue;
			}

			$import = $this->_quickAssembleData($activeUpgrade, array(
				'user_id' => $userId,
				'user_upgrade_id' => $upgradeId,
				'extra',
				'start_date',
				'end_date'
			));

			if (!isset($extraGroupIdsCache[$activeUpgrade['extra_group_ids']]))
			{
				$extraGroupIdsCache[$activeUpgrade['extra_group_ids']] = $this->_translateUserGroupIdList($activeUpgrade['extra_group_ids']);
			}

			$model->importActiveUserUpgrade($import, $extraGroupIdsCache[$activeUpgrade['extra_group_ids']]);

			$total++;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _mapDisabledUpgradeIds($map, $idString)
	{
		$output = array();

		foreach (preg_split('/\s*,\s*/si', $idString, -1, PREG_SPLIT_NO_EMPTY) AS $id)
		{
			if ($newId = $this->_mapLookUp($map, $id))
			{
				$output[] = $id;
			}
		}

		return implode(',', $output);
	}

	/*
	 * drafts
	 * feeds
	 * news feed
	 * notices
	 * smilies
	 * trophies
	 * user alerts
	 * user group promotions
	 */
}