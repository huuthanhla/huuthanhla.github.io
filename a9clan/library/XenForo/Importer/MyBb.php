<?php

class XenForo_Importer_MyBb extends XenForo_Importer_Abstract
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
	protected $_userFieldMap = null;

	protected $_config;

	public static function getName()
	{
		return 'MyBB 1.6/1.8';
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

		return $controller->responseView('XenForo_ViewAdmin_Import_MyBb_Config', 'import_mybb_config', $viewParams);
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
					'charset' => str_replace('-', '', $config['db']['charset'])
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
				SELECT uid
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
				'charset' => str_replace('-', '', $config['db']['charset'])
			)
		);
		if (empty($config['db']['charset']))
		{
			$this->_sourceDb->query('SET character_set_results = NULL');
		}

		$this->_prefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

		$this->_charset = $config['charset'];
	}

	public function stepUserGroups($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$userGroups = $sDb->fetchAll('
			SELECT *
			FROM ' . $prefix . 'usergroups
			ORDER BY gid
		');

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($userGroups AS $userGroup)
		{
			$titlePriority = 5;

			switch ($userGroup['gid'])
			{
				case 1: // guests
					$model->logImportData('userGroup', $userGroup['gid'], XenForo_Model_User::$defaultGuestGroupId);
					break;

				case 2: // registered users
				case 5: // awaiting activation
					$model->logImportData('userGroup', $userGroup['gid'], XenForo_Model_User::$defaultRegisteredGroupId);
					break;

				case 4: // admins
					$model->logImportData('userGroup', $userGroup['gid'], XenForo_Model_User::$defaultAdminGroupId);
					break;

				case 6: // moderators
					$model->logImportData('userGroup', $userGroup['gid'], XenForo_Model_User::$defaultModeratorGroupId);
					break;

				case 3: // super mods
					$titlePriority = 910;
					// fall through intentionally

				default:
					$import = array(
						'title' => $this->_convertToUtf8($userGroup['title'], true),
						'display_style_priority' => $titlePriority,
						'permissions' => $this->_convertGlobalPermissionsForGroup($userGroup)
					);

					if ($model->importUserGroup($userGroup['gid'], $import))
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

	protected function _convertGlobalPermissionsForGroup(array $group)
	{
		$perms = array();

		if ($group['canview'])
		{
			$perms['general']['view'] = 'allow';
			$perms['general']['viewNode'] = 'allow';
			$perms['general']['editProfile'] = 'allow';
			$perms['general']['followModerationRules'] = 'allow';
		}
		if ($group['canviewthreads'])
		{
			$perms['forum']['viewOthers'] = 'allow';
			$perms['forum']['viewContent'] = 'allow';
		}
		if ($group['canviewprofiles'])
		{
			$perms['general']['viewProfile'] = 'allow';
			$perms['general']['viewMemberList'] = 'allow';
			$perms['profilePost']['view'] = 'allow';
		}
		if ($group['cansearch'])
		{
			$perms['general']['search'] = 'allow';
		}
		if ($group['canuploadavatars'])
		{
			$perms['avatar']['allowed'] = 'allow';
			$perms['avatar']['maxFileSize'] = -1;
		}
		if ($group['cancustomtitle'])
		{
			$perms['general']['editCustomTitle'] = 'allow';
		}
		if (!isset($group['canusesig']) || $group['canusesig'])
		{
			$perms['general']['editSignature'] = 'allow';
		}

		if ($group['candlattachments'])
		{
			$perms['forum']['viewAttachment'] = 'allow';
		}
		if ($group['canpostattachments'])
		{
			$perms['forum']['uploadAttachment'] = 'allow';
		}

		if ($group['canpostthreads'])
		{
			$perms['forum']['postThread'] = 'allow';
			$perms['forum']['editOwnThreadTitle'] = 'allow';
		}
		if ($group['canpostreplys'])
		{
			$perms['forum']['postReply'] = 'allow';
			$perms['forum']['like'] = 'allow';
			$perms['general']['maxTaggedUsers'] = 5;
			$perms['general']['report'] = 'allow';

			$perms['profilePost']['post'] = 'allow';
			$perms['profilePost']['comment'] = 'allow';
			$perms['profilePost']['manageOwn'] = 'allow';
			$perms['profilePost']['like'] = 'allow';
		}
		if ($group['caneditposts'])
		{
			$perms['forum']['editOwnPost'] = 'allow';
			$perms['forum']['editOwnPostTimeLimit'] = -1;
			$perms['profilePost']['editOwn'] = 'allow';
		}
		if ($group['candeleteposts'])
		{
			$perms['forum']['deleteOwnPost'] = 'allow';
			$perms['profilePost']['deleteOwn'] = 'allow';
		}
		if ($group['candeletethreads'])
		{
			$perms['forum']['deleteOwnThread'] = 'allow';
		}

		if ($group['canvotepolls'])
		{
			$perms['forum']['votePoll'] = 'allow';
		}

		if ($group['cansendpms'])
		{
			$perms['conversation']['start'] = 'allow';
			$perms['conversation']['receive'] = 'allow';

			if ($group['maxpmrecipients'])
			{
				$perms['conversation']['maxRecipients'] = $group['maxpmrecipients'];
				if ($perms['conversation']['maxRecipients'] > 2147483647)
				{
					$perms['conversation']['maxRecipients'] = -1;
				}
			}
			else
			{
				$perms['conversation']['maxRecipients'] = -1;
			}
		}

		return $perms;
	}

	public function stepUserFields($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$profileFields = $sDb->fetchAll('
			SELECT *
			FROM ' . $prefix . 'profilefields
			WHERE fid > 3
		');

		$existingFields = XenForo_Model::create('XenForo_Model_UserField')->getUserFields();

		$userFieldLookups = array();
		$total = 0;

		XenForo_Db::beginTransaction($this->_db);

		foreach ($profileFields AS $profileField)
		{
			$title = $this->_convertToUtf8($profileField['name'], true);
			$description = $this->_convertToUtf8($profileField['description'], true);

			$fieldId = $model->getUniqueFieldId(
				str_replace('-', '_', XenForo_Link::getTitleForUrl($profileField['name'], true)),
				$existingFields,
				25
			);

			$import = array(
				'field_id' => $fieldId,
				'title' => $title,
				'description' => $description,
				'display_order' => $profileField['disporder'],
				'max_length' => $profileField['maxlength'],
				'viewable_profile' => !$profileField['hidden'],
				'user_editable' => $profileField['editable'] ? 'yes' : 'never',
				'viewable_message' => 0,
				'show_registration' => 0,
				'required' => $profileField['required']
			);

			$typeParts = preg_split('/\r?\n/', $this->_convertToUtf8($profileField['type']), -1, PREG_SPLIT_NO_EMPTY);
			$type = array_shift($typeParts);
			$typeParts = array_unique($typeParts);

			switch ($type)
			{
				case 'text':
					$import['field_type'] = 'textbox';
					break;

				case 'textarea':
					$import['field_type'] = 'textarea';
					break;

				case 'select':
				case 'radio':
				case 'checkbox':
				case 'multiselect':
					$import['field_type'] = $type;
					$import['field_choices'] = $typeParts;
					$userFieldLookups[$profileField['fid']] = array_flip($typeParts);
					break;

				default:
					$import['field_type'] = 'textbox';
					break;
			}

			if ($imported = $model->importUserField($profileField['fid'], $import))
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
				SELECT MAX(uid)
				FROM ' . $prefix . 'users
			');
		}

		$users = $sDb->fetchAll(
			$sDb->limit($this->_getSelectUserSql('users.uid > ' . $sDb->quote($start)), $options['limit'])
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
			$next = $user['uid'];

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
			$merge = $sDb->fetchAll($this->_getSelectUserSql('users.uid IN (' . $sDb->quote(array_keys($manual)) . ')'));

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
					$users[$user['uid']] = array(
						'username' => $this->_convertToUtf8($user['username'], true),
						'email' => $this->_convertToUtf8($user['email'], true),
						'message_count' => $user['postnum'],
						'register_date' => $user['regdate'],
						'conflict' => $manual[$user['uid']]
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
			$users = $this->_sourceDb->fetchAll($this->_getSelectUserSql('users.uid IN (' . $sDb->quote(array_keys($manual)) . ')'));

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
					$failedUsers[$user['uid']] = array(
						'username' => $this->_convertToUtf8($user['username'], true),
						'email' => $this->_convertToUtf8($user['email'], true),
						'message_count' => $user['postnum'],
						'register_date' => $user['regdate'],
						'failure' => $manual[$user['uid']]
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
			if (empty($resolve[$user['uid']]))
			{
				continue;
			}

			$info = $resolve[$user['uid']];

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

				if ($match = $im->getUserIdByEmail($this->_convertToUtf8($user['email'], true)))
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

		if ($user['email'] && $emailMatch = $im->getUserIdByEmail($this->_convertToUtf8($user['email'], true)))
		{
			if (!empty($options['mergeEmail']))
			{
				return $this->_mergeUser($user, $emailMatch);
			}
			else
			{
				if ($im->getUserIdByUserName($this->_convertToUtf8($user['username'], true)))
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

		$name = utf8_substr($this->_convertToUtf8(trim($user['username']), true), 0, 50);

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
				SELECT value
				FROM ' . $prefix . 'settings
				WHERE name = \'regtype\'
			');
		}

		$groups = preg_split('/,\s*/', $user['additionalgroups'], -1, PREG_SPLIT_NO_EMPTY);

		$import = array(
			'username' => $this->_convertToUtf8($user['username'], true),
			'email' => $this->_convertToUtf8($user['email'], true),
			'user_group_id' => $this->_mapLookUp($this->_groupMap, $user['usergroup'], XenForo_Model_User::$defaultRegisteredGroupId),
			'secondary_group_ids' => $this->_mapLookUpList($this->_groupMap, $groups),
			'authentication' => array(
				'scheme_class' => 'XenForo_Authentication_MyBb',
				'data' => array(
					'hash' => $user['password'],
					'salt' => $user['salt']
				)
			),
			'last_activity' => $user['lastvisit'],
			'register_date' => $user['regdate'],
			'ip' => $user['regip'],
			'homepage' => $this->_convertToUtf8($user['website'], true),
			'message_count' => $user['postnum'],
			'is_admin' => ($user['admin_permissions'] ? 1 : 0),
			'is_banned' => ($user['ban_dateline'] ? 1 : 0),
			'signature' => $this->_sanitizeBbCode($user['signature']),
			'timezone' => $this->_importModel->resolveTimeZoneOffset($user['timezone'], $user['dstcorrection']),
			'visible' => !$user['invisible'],
			'content_show_signature' => $user['showsigs'], // view sigs
			'receive_admin_email' => $user['allownotices'],
			'default_watch_state' => ($user['subscriptionmethod'] == 2 ? 'watch_email' : ($user['subscriptionmethod'] == 1 ? 'watch_no_email' : '')),
			'allow_send_personal_conversation' => ($user['receivepms'] ? 'everyone' : 'none'),
			'email_on_conversation' => $user['pmnotify']
		);

		if ($user['usergroup'] == 5)
		{
			$import['user_state'] = ($this->_userActivationSetting == 'admin' ? 'moderated' : 'email_confirm');
		}
		else
		{
			$import['user_state'] = 'valid';
		}

		if ($user['birthday'])
		{
			$parts = explode('-', $user['birthday']);
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
			if ($user['avatartype'] != 'uploaded' && $user['email'] && $user['lastpost']
				&& XenForo_Model_Avatar::gravatarExists($user['email'])
			)
			{
				$import['gravatar'] = $import['email'];
			}
		}

		$import['about'] = isset($user['fid2']) ? $this->_convertToUtf8($user['fid2'], true) : '';
		$import['location'] = isset($user['fid1']) ? $this->_convertToUtf8($user['fid1'], true) : '';
		if (!empty($user['fid3']))
		{
			if ($user['fid3'] == 'Male')
			{
				$import['gender'] = 'male';
			}
			else if ($user['fid3'] == 'Female')
			{
				$import['gender'] = 'female';
			}
		}

		// custom user fields
		$userFieldDefinitions = $this->_importModel->getUserFieldDefinitions();

		$identityMap = array(
			'icq' => 'icq',
			'aim' => 'aim',
			'yahoo' => 'yahoo',
			'msn' => 'msn'
		);

		foreach ($identityMap AS $identityType => $field)
		{
			if (isset($userFieldDefinitions[$identityType]))
			{
				$import[XenForo_Model_Import::USER_FIELD_KEY][$identityType] = $this->_convertToUtf8($user[$field], true);
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

			if (isset($user["fid$oldFieldId"]) && $user["fid$oldFieldId"] !== '')
			{
				if (isset($userFieldLookups[$oldFieldId]))
				{
					$fieldInfo = $userFieldLookups[$oldFieldId];

					$userFieldValue = array();
					foreach (preg_split('/\r?\n/', $user["fid$oldFieldId"], -1, PREG_SPLIT_NO_EMPTY) AS $fieldChoiceId)
					{
						if (isset($fieldInfo[$fieldChoiceId]))
						{
							$userFieldValue[$fieldInfo[$fieldChoiceId]] = $fieldInfo[$fieldChoiceId];
						}
					}
					$userFieldValue = array_unique($userFieldValue);
				}
				else
				{
					// set the field value directly
					$userFieldValue = $this->_convertToUtf8($user["fid$oldFieldId"], true);
				}
			}

			$import[XenForo_Model_Import::USER_FIELD_KEY][$newFieldId] = $userFieldValue;
		}

		if ($import['is_admin'] && $user['admin_permissions'])
		{
			$myBbAdminPerms = @unserialize($user['admin_permissions']);
			if (is_array($myBbAdminPerms))
			{
				$aPerms = array();
				if (!empty($myBbAdminPerms['config']['settings'])) { $aPerms[] = 'option'; }
				if (!empty($myBbAdminPerms['config']['smilies'])) { $aPerms[] = 'bbCodeSmilie'; }
				if (!empty($myBbAdminPerms['config']['plugins'])) { $aPerms[] = 'addOn'; }
				if (!empty($myBbAdminPerms['forum']['management'])) { $aPerms[] = 'node'; }
				if (!empty($myBbAdminPerms['forum']['management'])) { $aPerms[] = 'thread'; }
				if (!empty($myBbAdminPerms['forum']['attachments'])) { $aPerms[] = 'attachment'; }
				if (!empty($myBbAdminPerms['forum']['announcements'])) { $aPerms[] = 'notice'; }
				if (!empty($myBbAdminPerms['user']['users'])) { $aPerms[] = 'user'; }
				if (!empty($myBbAdminPerms['user']['users'])) { $aPerms[] = 'userField'; }
				if (!empty($myBbAdminPerms['user']['users'])) { $aPerms[] = 'trophy'; }
				if (!empty($myBbAdminPerms['user']['users'])) { $aPerms[] = 'warning'; }
				if (!empty($myBbAdminPerms['user']['banning'])) { $aPerms[] = 'ban'; }
				if (!empty($myBbAdminPerms['user']['group'])) { $aPerms[] = 'userGroup'; }
				if (!empty($myBbAdminPerms['user']['users'])) { $aPerms[] = 'userUpgrade'; }
				if (!empty($myBbAdminPerms['style']['templates'])) { $aPerms[] = 'style'; }
				if (!empty($myBbAdminPerms['style']['templates'])) { $aPerms[] = 'language'; }
				if (!empty($myBbAdminPerms['tools']['tasks'])) { $aPerms[] = 'cron'; }
				if (!empty($myBbAdminPerms['tools']['cache'])) { $aPerms[] = 'rebuildCache'; }
				if (!empty($myBbAdminPerms['tools']['statistics'])) { $aPerms[] = 'viewStatistics'; }
				if (!empty($myBbAdminPerms['tools']['modlog'])) { $aPerms[] = 'viewLogs'; }
				if (!empty($myBbAdminPerms['forum']['management'])) { $aPerms[] = 'import'; }

				$aPerms[] = 'upgradeXenForo';

				$import['admin_permissions'] = $aPerms;
			}
		}

		$importedUserId = $this->_importModel->importUser($user['uid'], $import, $failedKey);
		if ($importedUserId)
		{
			if ($user['ban_dateline'])
			{
				$this->_importModel->importBan(array(
					'user_id' => $importedUserId,
					'ban_user_id' => 0,
					'ban_date' => $user['ban_dateline'],
					'end_date' => $user['ban_lifted'],
					'user_reason' => $this->_convertToUtf8($user['ban_reason'], true)
				));
			}
		}
		else if ($failedKey)
		{
			$this->_session->setExtraData('userFailed', $user['uid'], $failedKey);
		}

		return $importedUserId;
	}

	protected function _getSelectUserSql($where)
	{
		return '
			SELECT users.*, userfields.*,
				banned.dateline AS ban_dateline, banned.lifted AS ban_lifted, banned.reason AS ban_reason,
				adminoptions.permissions AS admin_permissions
			FROM ' . $this->_prefix . 'users AS users
			LEFT JOIN ' . $this->_prefix . 'adminoptions AS adminoptions ON
				(adminoptions.uid = users.uid)
			LEFT JOIN ' . $this->_prefix . 'userfields AS userfields ON
				(userfields.ufid = users.uid)
			LEFT JOIN ' . $this->_prefix . 'banned AS banned ON
				(banned.uid = users.uid AND (banned.lifted = 0 OR banned.lifted > ' . XenForo_Application::$time . '))
			WHERE ' . $where . '
			ORDER BY users.uid
		';
	}

	protected function _mergeUser(array $user, $targetUserId)
	{
		$this->_db->query('
			UPDATE xf_user SET
				message_count = message_count + ?,
				register_date = IF(register_date > ?, ?, register_date)
			WHERE user_id = ?
		', array($user['postnum'], $user['regdate'], $user['regdate'], $targetUserId));

		$this->_importModel->logImportData('user', $user['uid'], $targetUserId);

		return $targetUserId;
	}

	public function stepAvatars($start, array $options)
	{
		$options = array_merge(array(
			'path' => $this->_config['attachmentPath'] . '/avatars',
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
				SELECT MAX(uid)
				FROM ' . $prefix . 'users
				WHERE avatartype = \'upload\'
			');
		}

		$avatars = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM ' . $prefix . 'users
				WHERE uid > ' . $sDb->quote($start) . '
					AND avatartype = \'upload\'
				ORDER BY uid
			', $options['limit']
		));
		if (!$avatars)
		{
			return true;
		}

		$userIdMap = $model->getUserIdsMapFromArray($avatars, 'uid');

		$next = 0;
		$total = 0;

		foreach ($avatars AS $avatar)
		{
			$next = $avatar['uid'];

			$newUserId = $this->_mapLookUp($userIdMap, $avatar['uid']);
			if (!$newUserId)
			{
				continue;
			}

			if (!preg_match('/avatar_\d+\.[a-z0-9_]+/i', $avatar['avatar'], $match))
			{
				continue;
			}
			$basename = $match[0];

			$avatarFileOrig = "$options[path]/$basename";
			if (!file_exists($avatarFileOrig))
			{
				continue;
			}

			$avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			copy($avatarFileOrig, $avatarFile);

			if ($this->_importModel->importAvatar($avatar['uid'], $newUserId, $avatarFile))
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
				SELECT MAX(pmid)
				FROM ' . $prefix . 'privatemessages
			');
		}

		$pms = $sDb->fetchAll($sDb->limit(
			'
				SELECT pms.*, users.username
				FROM ' . $prefix . 'privatemessages AS pms
				LEFT JOIN ' . $prefix . 'users AS users ON (pms.uid = users.uid)
				WHERE pms.pmid > ' . $sDb->quote($start) . '
					AND pms.toid > 0
					AND pms.fromid > 0
				ORDER BY pms.pmid
			', $options['limit']
		));
		if (!$pms)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIds = array();
		foreach ($pms AS $pm)
		{
			$userIds[$pm['uid']] = $pm['uid'];
			$userIds[$pm['toid']] = $pm['toid'];
			$userIds[$pm['fromid']] = $pm['fromid'];
		}

		$mapUserIds = $model->getImportContentMap('user', $userIds);

		XenForo_Db::beginTransaction();

		foreach ($pms AS $pm)
		{
			$next = $pm['pmid'];

			if ($pm['uid'] != $pm['toid'])
			{
				continue;
			}

			$users = $sDb->fetchPairs('
				SELECT uid, username
				FROM ' . $prefix . 'users
				WHERE uid IN (' . $sDb->quote($pm['toid']) . ',' . $sDb->quote($pm['fromid']) . ')
			');
			if (count($users) != 2)
			{
				continue;
			}

			$newFromUserId = $this->_mapLookUp($mapUserIds, $pm['fromid']);
			if (!$newFromUserId)
			{
				continue;
			}

			$recipients = array();
			foreach ($users AS $userId => $username)
			{
				$newUserId = $this->_mapLookUp($mapUserIds, $userId);
				if (!$newUserId)
				{
					continue;
				}

				if ($pm['uid'] == $userId)
				{
					$lastReadDate = $pm['readtime'];
				}
				else
				{
					$lastReadDate = $pm['dateline'];
				}

				$recipients[$newUserId] = array(
					'username' => $this->_convertToUtf8($username, true),
					'last_read_date' => $lastReadDate,
					'recipient_state' => 'active'
				);
			}

			$fromUserName = $this->_convertToUtf8($users[$pm['fromid']], true);

			$conversation = array(
				'title' => $this->_convertToUtf8($pm['subject'], true),
				'user_id' => $newFromUserId,
				'username' => $fromUserName,
				'start_date' => $pm['dateline'],
				'open_invite' => 0,
				'conversation_open' => 1
			);

			$messages = array(
				array(
					'message_date' => $pm['dateline'],
					'user_id' => $newFromUserId,
					'username' => $fromUserName,
					'message' => $this->_sanitizeBbCode($pm['message'])
				)
			);

			if ($model->importConversation($pm['pmid'], $conversation, $recipients, $messages))
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
			$forumTree[$forum['pid']][$forum['fid']] = $forum;
		}

		$forumPermissions = array();
		$forumPermissionSql = $sDb->query('
			SELECT *
			FROM ' . $prefix . 'forumpermissions
		');
		while ($forumPermission = $forumPermissionSql->fetch())
		{
			$forumPermissions[$forumPermission['fid']][$forumPermission['gid']] = $forumPermission;
		}

		XenForo_Db::beginTransaction();

		$total = $this->_importForumTree(0, $forumTree, $forumPermissions);

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array(1, array(), '');
	}

	protected function _importForumTree($parentId, array $forumTree, array $forumPermissions, array $forumIdMap = array())
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
				'title' => $this->_convertToUtf8($forum['name'], true),
				'description' => $this->_sanitizeBbCode($forum['description'], null, true),
				'display_order' => $forum['disporder'],
				'parent_node_id' => $this->_mapLookUp($forumIdMap, $forum['pid'], 0),
				'display_in_list' => 1
			);

			 if ($forum['type'] == 'f')
			{
				$import['node_type_id'] = 'Forum';
				$import['discussion_count'] = $forum['threads'];
				$import['message_count'] = $forum['posts'];
				$import['last_post_date'] = $forum['lastpost'];
				$import['last_post_username'] = $this->_convertToUtf8($forum['lastposter'], true);
				$import['last_thread_title'] = $this->_convertToUtf8($forum['lastpostsubject'], true);

				$nodeId = $this->_importModel->importForum($forum['fid'], $import);
			}
			else
			{
				$import['node_type_id'] = 'Category';

				$nodeId = $this->_importModel->importCategory($forum['fid'], $import);
			}

			if ($nodeId)
			{
				if (!empty($forumPermissions[$forum['fid']]))
				{
					$this->_importForumPermissions($nodeId, $forumPermissions[$forum['fid']]);
				}

				$forumIdMap[$forum['fid']] = $nodeId;

				$total++;
				$total += $this->_importForumTree($forum['fid'], $forumTree, $forumPermissions, $forumIdMap);
			}
		}

		XenForo_Db::commit();

		return $total;
	}

	protected function _importForumPermissions($nodeId, array $groupPerms)
	{
		if ($this->_groupMap === null)
		{
			$this->_groupMap = $this->_importModel->getImportContentMap('userGroup');
		}

		XenForo_Db::beginTransaction();

		foreach ($groupPerms AS $oldGroupId => $perms)
		{
			if ($oldGroupId == 5)
			{
				continue; // skip these. they'll be treated as guests
			}

			$newGroupId = $this->_mapLookUp($this->_groupMap, $oldGroupId);
			if (!$newGroupId)
			{
				continue;
			}

			$newPerms = $this->_calculateForumPermissions($perms);
			if ($newPerms)
			{
				$this->_importModel->insertNodePermissionEntries($nodeId, $newGroupId, 0, $newPerms);
			}
		}

		XenForo_Db::commit();
	}

	protected $_nodePermissionsGrouped = null;

	protected function _calculateForumPermissions(array $perms)
	{
		$output = array();

		if ($this->_nodePermissionsGrouped === null)
		{
			$this->_nodePermissionsGrouped = $this->_importModel->getNodePermissionsGrouped();
		}

		if ($perms['canview'])
		{
			// viewable
			$output['general']['viewNode'] = 'content_allow';

			$output['forum']['viewContent'] = ($perms['canviewthreads'] ? 'content_allow' : 'reset');
			$output['forum']['viewOthers'] = (!$perms['canonlyviewownthreads'] ? 'content_allow' : 'reset');
			$output['forum']['postThread'] = ($perms['canpostthreads'] ? 'content_allow' : 'reset');
			$output['forum']['postReply'] = ($perms['canpostreplys'] ? 'content_allow' : 'reset');
			$output['forum']['editOwnPost'] = ($perms['caneditposts'] ? 'content_allow' : 'reset');
			$output['forum']['deleteOwnPost'] = ($perms['candeleteposts'] ? 'content_allow' : 'reset');
			$output['forum']['deleteOwnThread'] = ($perms['candeletethreads'] ? 'content_allow' : 'reset');
			$output['forum']['viewAttachment'] = ($perms['candlattachments'] ? 'content_allow' : 'reset');
			$output['forum']['uploadAttachment'] = ($perms['canpostattachments'] ? 'content_allow' : 'reset');
			$output['forum']['votePoll'] = ($perms['canvotepolls'] ? 'content_allow' : 'reset');
		}
		else
		{
			// not viewable, reset all permissions
			$output['general']['viewNode'] = 'reset';

			foreach ($this->_nodePermissionsGrouped AS $groupId => $permissions)
			{
				foreach ($permissions AS $permissionId => $perm)
				{
					if ($perm['permission_type'] == 'flag')
					{
						$output[$groupId][$permissionId] = 'reset';
					}
				}
			}
		}

		return $output;
	}

	public function stepModerators($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$moderators = $sDb->fetchAll('
			SELECT mods.*
			FROM ' . $prefix . 'moderators AS mods
			INNER JOIN ' . $prefix . 'users AS users ON (mods.id = users.uid)
			WHERE mods.isgroup = 0
		');
		if (!$moderators)
		{
			return true;
		}

		$modGrouped = array();
		foreach ($moderators AS $moderator)
		{
			$modGrouped[$moderator['id']][$moderator['fid']] = $moderator;
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
					'moderator_permissions' => $this->_convertForumPermissionsForUser($userId, $forumId, $moderator)
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

	protected function _convertForumPermissionsForUser($userId, $forumId, array $moderator)
	{
		$perms = array();

		if ($moderator['caneditposts'])
		{
			$perms['forum']['editAnyPost'] = 'content_allow';
			$perms['forum']['approveUnapprove'] = 'content_allow';
			$perms['forum']['viewModerated'] = 'content_allow';
		}
		if ($moderator['candeleteposts'])
		{
			$perms['forum']['deleteAnyPost'] = 'content_allow';
			$perms['forum']['deleteAnyThread'] = 'content_allow';
			$perms['forum']['viewDeleted'] = 'content_allow';
			$perms['forum']['undelete'] = 'content_allow';
		}
		if ($moderator['canopenclosethreads'])
		{
			$perms['forum']['lockUnlockThread'] = 'content_allow';
			$perms['forum']['stickUnstickThread'] = 'content_allow';
		}
		if ($moderator['canmanagethreads'])
		{
			$perms['forum']['manageAnyThread'] = 'content_allow';
		}

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
				SELECT MAX(tid)
				FROM ' . $prefix . 'threads
			');
		}

		// pull threads from things we actually imported as forums
		$threads = $sDb->fetchAll($sDb->limit(
			'
				SELECT threads.*,
					IF(users.username IS NOT NULL, users.username, threads.username) AS username
				FROM ' . $prefix . 'threads AS threads FORCE INDEX (PRIMARY)
				LEFT JOIN ' . $prefix . 'users AS users ON (threads.uid = users.uid)
				INNER JOIN ' . $prefix . 'forums AS forums ON (threads.fid = forums.fid)
				WHERE threads.tid >= ' . $sDb->quote($start) . '
				ORDER BY threads.tid
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

			$next = $thread['tid'] + 1; // uses >=, will be moved back down if need to continue
			$options['postDateStart'] = 0;

			$maxPosts = $options['postLimit'] - $totalPosts;
			$posts = $sDb->fetchAll($sDb->limit(
				'
					SELECT posts.*,
						IF(users.username IS NOT NULL, users.username, posts.username) AS username
					FROM ' . $prefix . 'posts AS posts
					LEFT JOIN ' . $prefix . 'users AS users ON (posts.uid = users.uid)
					WHERE posts.tid = ' . $sDb->quote($thread['tid']) . '
						AND posts.dateline > ' . $sDb->quote($postDateStart) . '
					ORDER BY posts.dateline
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
				$threadId = $model->mapThreadId($thread['tid']);

				$position = $this->_db->fetchOne('
					SELECT MAX(position)
					FROM xf_post
					WHERE thread_id = ?
				', $threadId);
			}
			else
			{
				$forumId = $this->_mapLookUp($nodeMap, $thread['fid']);
				if (!$forumId)
				{
					continue;
				}

				if (trim($thread['username']) === '')
				{
					$thread['username'] = 'Guest';
				}

				$import = array(
					'title' => $this->_convertToUtf8($thread['subject'], true),
					'node_id' => $forumId,
					'user_id' => $model->mapUserId($thread['uid'], 0),
					'username' => $this->_convertToUtf8($thread['username'], true),
					'discussion_open' => ($thread['closed'] ? 0 : 1),
					'post_date' => $thread['dateline'],
					'reply_count' => $thread['replies'],
					'view_count' => $thread['views'],
					'sticky' => $thread['sticky'],
					'last_post_date' => $thread['lastpost'],
					'last_post_username' => $this->_convertToUtf8($thread['lastposter'], true),
				);
				if ($thread['visible'])
				{
					$import['discussion_state'] = 'visible';
				}
				else
				{
					$import['discussion_state'] = 'moderated';
				}

				$threadId = $model->importThread($thread['tid'], $import);
				if (!$threadId)
				{
					continue;
				}

				$position = -1;

				$subs = $sDb->fetchPairs('
					SELECT uid, notification
					FROM ' . $prefix . 'threadsubscriptions
					WHERE tid = ' . $sDb->quote($thread['tid'])
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

				$userIdMap = $model->getUserIdsMapFromArray($posts, 'uid');

				foreach ($posts AS $post)
				{
					if ($post['subject'] !== '' && !preg_match($threadTitleRegex, $post['subject']))
					{
						$post['message'] = '[b]' . $post['subject'] . "[/b]\n\n" . ltrim($post['message']);
					}

					if (trim($post['username']) === '')
					{
						$post['username'] = 'Guest';
					}

					$post['message'] = $this->_sanitizeBbCode($post['message']);

					$import = array(
						'thread_id' => $threadId,
						'user_id' => $this->_mapLookUp($userIdMap, $post['uid'], 0),
						'username' => $this->_convertToUtf8($post['username'], true),
						'post_date' => $post['dateline'],
						'message' => $post['message'],
						'attach_count' => 0,
						'ip' => $post['ipaddress']
					);
					if ($post['visible'])
					{
						$import['message_state'] = 'visible';
						$import['position'] = ++$position;
					}
					else
					{
						$import['message_state'] = 'moderated';
						$import['position'] = $position;
					}

					$model->importPost($post['pid'], $import);

					$options['postDateStart'] = $post['dateline'];
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
				SELECT MAX(pid)
				FROM ' . $prefix . 'polls
			');
		}

		$polls = $sDb->fetchAll($sDb->limit(
			'
				SELECT polls.*
				FROM ' . $prefix . 'polls AS polls
				INNER JOIN ' . $prefix . 'threads AS threads ON (threads.tid = polls.tid)
				WHERE polls.pid > ' . $sDb->quote($start) . '
				ORDER BY polls.pid
			', $options['limit']
		));
		if (!$polls)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$threadIdMap = $model->getThreadIdsMapFromArray($polls, 'tid');

		XenForo_Db::beginTransaction();

		foreach ($polls AS $poll)
		{
			$next = $poll['pid'];

			$newThreadId = $this->_mapLookUp($threadIdMap, $poll['tid']);
			if (!$newThreadId)
			{
				continue;
			}

			if ($this->_db->fetchOne("
				SELECT poll_id
				FROM xf_poll
				WHERE content_type = 'thread'
					AND content_id = ?
			", $newThreadId))
			{
				// some times the pollid in the thread table isn't unique so it can try to import dupes
				continue;
			}

			$import = array(
				'question' => $this->_convertToUtf8($poll['question'], true),
				'public_votes' => $poll['public'],
				'max_votes' => $poll['multiple'] ? 0 : 1,
				'close_date' => ($poll['timeout'] ? $poll['dateline'] + 86400 * $poll['timeout'] : 0)
			);
			$responses = explode('||~|~||', $this->_convertToUtf8($poll['options'], true));
			if (end($responses) === '')
			{
				array_pop($responses);
			}
			if (!$responses)
			{
				continue;
			}

			$newPollId = $model->importThreadPoll($poll['pid'], $newThreadId, $import, $responses, $responseIds);
			if ($newPollId)
			{
				$votes = $sDb->fetchAll('
					SELECT uid, dateline, voteoption
					FROM ' . $prefix . 'pollvotes
					WHERE pid = ' . $sDb->quote($poll['pid'])
				);

				$userIdMap = $model->getUserIdsMapFromArray($votes, 'uid');
				foreach ($votes AS $vote)
				{
					$userId = $this->_mapLookUp($userIdMap, $vote['uid']);
					if (!$userId)
					{
						continue;
					}

					$voteOption = max(0, $vote['voteoption'] - 1);

					if (!isset($responseIds[$voteOption]))
					{
						continue;
					}

					$model->importPollVote($newPollId, $userId, $responseIds[$voteOption], $vote['dateline']);
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
				SELECT MAX(aid)
				FROM ' . $prefix . 'attachments
			');
		}

		$attachments = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM ' . $prefix . 'attachments
				WHERE aid > ' . $sDb->quote($start) . '
					AND visible = 1
					AND pid > 0
				ORDER BY aid
			', $options['limit']
		));
		if (!$attachments)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIdMap = $model->getUserIdsMapFromArray($attachments, 'uid');
		$postIdMap = $model->getPostIdsMapFromArray($attachments, 'pid');

		foreach ($attachments AS $attachment)
		{
			$next = $attachment['aid'];

			$newPostId = $this->_mapLookUp($postIdMap, $attachment['pid']);
			if (!$newPostId)
			{
				continue;
			}

			$attachFileOrig = "$options[path]/$attachment[attachname]";
			if (!file_exists($attachFileOrig))
			{
				continue;
			}

			$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			copy($attachFileOrig, $attachFile);

			$success = $model->importPostAttachment(
				$attachment['aid'],
				$this->_convertToUtf8($attachment['filename'], true),
				$attachFile,
				$this->_mapLookUp($userIdMap, $attachment['uid'], 0),
				$newPostId,
				$attachment['dateuploaded'],
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

	protected function _sanitizeBbCode($string, $bbCodeUid = null, $strip = false)
	{
		$string = $this->_convertToUtf8($string, true);

		$string = preg_replace('#\[align=left\](.*)\[/align\]#siU', '[LEFT]$1[/LEFT]', $string);
		$string = preg_replace('#\[align=center\](.*)\[/align\]#siU', '[CENTER]$1[/CENTER]', $string);
		$string = preg_replace('#\[align=right\](.*)\[/align\]#siU', '[RIGHT]$1[/RIGHT]', $string);
		$string = preg_replace('#\[align=justify\](.*)\[/align\]#siU', '[LEFT]$1[/LEFT]', $string);

		$string = preg_replace('#(\[quote=\'[^\']*\')[^\]]*]#iU', '$1]', $string);

		$string = preg_replace('#\[size=xx-small\](.*)\[/size\]#siU', '[SIZE=1]$1[/SIZE]', $string);
		$string = preg_replace('#\[size=x-small\](.*)\[/size\]#siU', '[SIZE=2]$1[/SIZE]', $string);
		$string = preg_replace('#\[size=small\](.*)\[/size\]#siU', '[SIZE=3]$1[/SIZE]', $string);
		$string = preg_replace('#\[size=medium\](.*)\[/size\]#siU', '[SIZE=4]$1[/SIZE]', $string);
		$string = preg_replace('#\[size=large\](.*)\[/size\]#siU', '[SIZE=5]$1[/SIZE]', $string);
		$string = preg_replace('#\[size=x-large\](.*)\[/size\]#siU', '[SIZE=6]$1[/SIZE]', $string);
		$string = preg_replace('#\[size=xx-large\](.*)\[/size\]#siU', '[SIZE=7]$1[/SIZE]', $string);


		return $string;
	}
}