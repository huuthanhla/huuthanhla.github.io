<?php

class XenForo_Importer_vBulletin extends XenForo_Importer_Abstract
{
	/**
	 * Source database connection.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_sourceDb;

	protected $_prefix;

	protected $_charset = 'windows-1252';

	protected $_config;

	protected $_groupMap = null;

	protected $_userFieldMap = null;

	public static function getName()
	{
		return 'vBulletin 3.7/3.8';
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

			if (isset($config['attachmentPath']) || isset($config['avatarPath']))
			{
				// already configured
				return true;
			}

			$this->_bootstrap($config);

			$settings = $this->_sourceDb->fetchPairs('
				SELECT varname, value
				FROM ' . $this->_prefix . 'setting
				WHERE varname IN (\'attachpath\', \'attachfile\', \'avatarpath\', \'usefileavatar\')
			');
			if (($settings['attachfile'] && $settings['attachpath'])
				|| ($settings['usefileavatar'] && $settings['avatarpath']))
			{
				return $controller->responseView('XenForo_ViewAdmin_Import_vBulletin_Config', 'import_vbulletin_config', array(
					'config' => $config,
					'attachmentPath' => ($settings['attachfile'] ? $settings['attachpath'] : ''),
					'avatarPath' => ($settings['usefileavatar'] ? $settings['avatarpath'] : ''),
					'retainKeys' => $config['retain_keys'],
				));
			}

			return true;
		}
		else
		{

			$configPath = getcwd() . '/includes/config.php';
			if (file_exists($configPath) && is_readable($configPath))
			{
				$config = array();
				include($configPath);

				$viewParams = array('input' => $config);
			}
			else
			{
				$viewParams = array('input' => array
				(
					'MasterServer' => array
					(
						'servername' => 'localhost',
						'port' => 3306,
						'username' => '',
						'password' => '',
					),
					'Database' => array
					(
						'dbname' => '',
						'tableprefix' => ''
					),
					'Mysqli' => array
					(
						'charset' => ''
					),
				));
			}

			return $controller->responseView('XenForo_ViewAdmin_Import_vBulletin_Config', 'import_vbulletin_config', $viewParams);
		}
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
					'charset' => $config['db']['charset']
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
				SELECT userid
				FROM ' . $config['db']['prefix'] . 'user
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

		if (!empty($config['attachmentPath']))
		{
			if (!file_exists($config['attachmentPath']) || !is_dir($config['attachmentPath']))
			{
				$errors[] = new XenForo_Phrase('attachments_directory_not_found');
			}
		}

		if (!empty($config['avatarPath']))
		{
			if (!file_exists($config['avatarPath']) || !is_dir($config['avatarPath']))
			{
				$errors[] = new XenForo_Phrase('avatars_directory_not_found');
			}
		}

		if (!$errors)
		{
			$defaultLanguageId = $db->fetchOne('
				SELECT value
				FROM ' . $config['db']['prefix'] . 'setting
				WHERE varname = \'languageid\'
			');
			$defaultCharset = $db->fetchOne('
				SELECT charset
				FROM ' . $config['db']['prefix'] . 'language
				WHERE languageid = ?
			', $defaultLanguageId);
			if (!$defaultCharset || str_replace('-', '', strtolower($defaultCharset)) == 'iso88591')
			{
				$config['charset'] = 'windows-1252';
			}
			else
			{
				$config['charset'] = strtolower($defaultCharset);
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
			'avatars' => array(
				'title' => new XenForo_Phrase('import_custom_avatars'),
				'depends' => array('users')
			),
			'buddyIgnore' => array(
				'title' => new XenForo_Phrase('import_buddy_ignore_lists'),
				'depends' => array('users')
			),
			'paidSubscriptions' => array(
				'title' => new XenForo_Phrase('import_paid_subscriptions'),
				'depends' => array('users')
			),
			'privateMessages' => array(
				'title' => new XenForo_Phrase('import_private_messages'),
				'depends' => array('users')
			),
			'visitorMessages' => array(
				'title' => new XenForo_Phrase('import_profile_comments'),
				'depends' => array('users')
			),
			'forums' => array(
				'title' => new XenForo_Phrase('import_forums'),
				'depends' => array('userGroups')
			),
			'moderators' => array(
				'title' => new XenForo_Phrase('import_moderators'),
				'depends' => array('forums', 'users')
			),
			'threadPrefixes' => array(
				'title' => new XenForo_Phrase('import_thread_prefixes'),
				'depends' => array('forums')
			),
			'threads' => array(
				'title' => new XenForo_Phrase('import_threads_and_posts'),
				'depends' => array('forums', 'users', 'threadPrefixes')
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
				'depends' => array('threads')
			),
			'reputation' => array(
				'title' => new XenForo_Phrase('import_positive_reputation'),
				'depends' => array('threads')
			),
			'infractions' => array(
				'title' => new XenForo_Phrase('import_infractions'),
				'depends' => array('threads')
			),
		);

		// deferred: albums/comments, announcements, custom bb code, calendars/events, social groups, tags
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
				'charset' => $config['db']['charset']
			)
		);
		if (empty($config['db']['charset']))
		{
			$this->_sourceDb->query('SET character_set_results = NULL');
		}

		$this->_prefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

		if (!empty($config['charset']))
		{
			$this->_charset = $config['charset'];
		}
	}

	public function stepUserGroups($start, array $options)
	{
		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;
		$model->retainableKeys[] = 'user_group_id';

		$userGroups = $sDb->fetchAll('
			SELECT *
			FROM ' . $prefix . 'usergroup
			ORDER BY usergroupid
		');

		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($userGroups AS $userGroup)
		{
			$titlePriority = 5;
			switch ($userGroup['usergroupid'])
			{
				case 1: // guests
					$model->logImportData('userGroup', $userGroup['usergroupid'], XenForo_Model_User::$defaultGuestGroupId);
					break;

				case 2: // registered users
				case 3: // email confirm
				case 4: // moderation
					$model->logImportData('userGroup', $userGroup['usergroupid'], XenForo_Model_User::$defaultRegisteredGroupId);
					break;

				case 6: // admins
					$model->logImportData('userGroup', $userGroup['usergroupid'], XenForo_Model_User::$defaultAdminGroupId);
					continue;

				case 7: // mods
					$model->logImportData('userGroup', $userGroup['usergroupid'], XenForo_Model_User::$defaultModeratorGroupId);
					continue;

				case 5: // super mods
					$titlePriority = 910;

				default:
					$import = array(
						'title' => $this->_convertToUtf8($userGroup['title']),
						'user_title' => $this->_convertToUtf8($userGroup['usertitle']),
						'display_style_priority' => $titlePriority,
						'permissions' => $this->_calculateUserGroupPermissions($userGroup)
					);

					if ($model->importUserGroup($userGroup['usergroupid'], $import))
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

	protected function _calculateUserGroupPermissions(array $userGroup)
	{
		$perms = array();

		$userGroup['forumpermissions'] = intval($userGroup['forumpermissions']);
		$userGroup['genericpermissions'] = intval($userGroup['genericpermissions']);
		$userGroup['adminpermissions'] = intval($userGroup['adminpermissions']);

		if ($userGroup['forumpermissions'] & 1)
		{
			$perms['general']['view'] = 'allow';
			$perms['general']['viewNode'] = 'allow';
			$perms['forum']['like'] = 'allow';
		}

		if ($userGroup['forumpermissions'] & 524288)
		{
			$perms['forum']['viewContent'] = 'allow';
		}
		if ($userGroup['forumpermissions'] & 2)
		{
			$perms['forum']['viewOthers'] = 'allow';
		}

		if ($userGroup['genericpermissions'] & 1)
		{
			$perms['general']['viewProfile'] = 'allow';
			$perms['general']['viewMemberList'] = 'allow';
		}

		if (($userGroup['adminpermissions'] & 1) || ($userGroup['adminpermissions'] & 2))
		{
			$perms['general']['bypassFloodCheck'] = 'allow';
		}

		if ($userGroup['forumpermissions'] & 4)
		{
			$perms['general']['search'] = 'allow';
		}

		if ($userGroup['forumpermissions'] & 131072)
		{
			$perms['general']['followModerationRules'] = 'allow';
		}

		if ($userGroup['forumpermissions'] & 16)
		{
			$perms['forum']['postThread'] = 'allow';
		}
		if ($userGroup['forumpermissions'] & 32)
		{
			$perms['forum']['postReply'] = 'allow';
		}
		if ($userGroup['forumpermissions'] & 256)
		{
			$perms['forum']['deleteOwnPost'] = 'allow';
		}
		if ($userGroup['forumpermissions'] & 512)
		{
			$perms['forum']['deleteOwnThread'] = 'allow';
		}
		if ($userGroup['forumpermissions'] & 128)
		{
			$perms['forum']['editOwnPost'] = 'allow';
		}
		if ($userGroup['forumpermissions'] & 4096)
		{
			$perms['forum']['viewAttachment'] = 'allow';
		}
		if ($userGroup['forumpermissions'] & 8192)
		{
			$perms['forum']['uploadAttachment'] = 'allow';
		}
		if ($userGroup['forumpermissions'] & 32768)
		{
			$perms['forum']['votePoll'] = 'allow';
		}

		if ($userGroup['pmsendmax'])
		{
			$perms['conversation']['start'] = 'allow';
			$perms['conversation']['receive'] = 'allow';
			$perms['conversation']['maxRecipients'] = $userGroup['pmsendmax'];
			if ($perms['conversation']['maxRecipients'] > 2147483647)
			{
				$perms['conversation']['maxRecipients'] = -1;
			}
		}

		if ($userGroup['genericpermissions'] & 512)
		{
			$perms['avatar']['allowed'] = 'allow';
			$perms['avatar']['maxFileSize'] = ($userGroup['avatarmaxsize'] > 0 ? $userGroup['avatarmaxsize'] : -1);
			if ($perms['avatar']['maxFileSize'] > 2147483647)
			{
				$perms['avatar']['maxFileSize'] = -1;
			}
		}

		if (isset($userGroup['visitormessagepermissions']))
		{
			$userGroup['visitormessagepermissions'] = intval($userGroup['visitormessagepermissions']);

			if ($userGroup['visitormessagepermissions'] & 1)
			{
				$perms['profilePost']['view'] = 'allow'; // this checks against "can message own", which isn't perfect
				$perms['profilePost']['like'] = 'allow';
			}
			if ($userGroup['visitormessagepermissions'] & 2)
			{
				$perms['profilePost']['post'] = 'allow'; // this checks against "can message others"
				$perms['profilePost']['comment'] = 'allow';
			}
			if ($userGroup['visitormessagepermissions'] & 4)
			{
				$perms['profilePost']['editOwn'] = 'allow';
			}
			if ($userGroup['visitormessagepermissions'] & 8)
			{
				$perms['profilePost']['deleteOwn'] = 'allow';
			}
			if ($userGroup['visitormessagepermissions'] & 32)
			{
				$perms['profilePost']['manageOwn'] = 'allow';
			}
		}
		else
		{
			// vBulletin 3.6
			if (isset($perms['general']['view']))
			{
				$perms['profilepost']['view'] = $perms['general']['view'];
			}
			if (isset($perms['forum']['like']))
			{
				$perms['profilepost']['like'] = $perms['forum']['like'];
			}
			if (isset($perms['forum']['postReply']))
			{
				$perms['profilePost']['post'] = $perms['forum']['postReply'];
			}
			if (isset($perms['forum']['editOwnPost']))
			{
				$perms['profilePost']['editOwn'] = $perms['forum']['editOwnPost'];
			}
			if (isset($perms['forum']['deleteOwnPost']))
			{
				$perms['profilePost']['deleteOwn'] = $perms['forum']['deleteOwnPost'];
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

		// fetch all profile fields except 1-4, for which we already have analogues
		$profileFields = $sDb->fetchAll('
			SELECT *
			FROM ' . $prefix . 'profilefield
			WHERE profilefieldid > 4
		');

		$phrases = $sDb->fetchPairs('
			SELECT varname, text
			FROM ' . $prefix . 'phrase
			WHERE languageid = 0
			AND fieldname = ?
		', 'cprofilefield');

		$existingFields = XenForo_Model::create('XenForo_Model_UserField')->getUserFields();

		$userFieldLookups = array();
		$total = 0;

		XenForo_Db::beginTransaction($this->_db);

		foreach ($profileFields AS $profileField)
		{
			$title = $this->_convertToUtf8($phrases["field$profileField[profilefieldid]_title"]);
			$description = $this->_convertToUtf8($phrases["field$profileField[profilefieldid]_desc"]);

			$fieldId = $model->getUniqueFieldId(
				str_replace('-', '_', XenForo_Link::getTitleForUrl($title, true)),
				$existingFields,
				25);

			$import = array(
				'field_id' => $fieldId,
				'title' => $title,
				'description' => $description,
				'display_order' => $profileField['displayorder'],
				'max_length' => $profileField['maxlength'],
				'viewable_profile' => !$profileField['hidden'],
			);

			switch ($profileField['type'])
			{
				case 'select':
				case 'radio':
				case 'checkbox':
					$import['field_type'] = $profileField['type'];
					$import['field_choices'] = $this->_convertUserFieldChoices($profileField, $userFieldLookups);
					if (!$import['field_choices'])
					{
						continue;
					}
					break;

				case 'select_multiple':
					$import['field_type'] = 'multiselect';
					$import['field_choices'] = $this->_convertUserFieldChoices($profileField, $userFieldLookups);
					if (!$import['field_choices'])
					{
						continue;
					}
					break;

				case 'textarea':
					$import['field_type'] = 'textarea';
					break;

				case 'input':
				default:
					$import['field_type'] = 'textbox';
					break;
			}

			switch ($profileField['required'])
			{
				case 3: // yes, always
				case 1: // yes, at registration and profile update
					$import['required'] = 1;
					$import['show_registration'] = 1;
					break;

				case 2: // no, but display at registration
					$import['required'] = 0;
					$import['show_registration'] = 1;
					break;

				case 0: // no
				default:
					$import['required'] = 0;
					$import['show_registration'] = 0;
					break;
			}

			switch ($profileField['editable'])
			{
				case 0: // no
					$import['user_editable'] = 'never';
					break;

				case 2: // only at registration
					$import['user_editable'] = 'once';
					break;

				case 1: // yes
				default:
					$import['user_editable'] = 'yes';
					break;
			}

			switch ($profileField['form'])
			{
				case 0: // edit your details
					$import['display_group'] = 'personal';
					break;

				case 1: // options: login / privacy
				case 2: // options: messaging
				case 3: // options: thread viewing
				case 4: // options: date / time
				case 5: // options: other
				default:
					$import['display_group'] = 'preferences';
					break;
			}

			if ($profileField['regex'])
			{
				$import['match_type'] = 'regex';
				$import['match_regex'] = $this->_convertToUtf8($profileField['regex']);
			}
			else
			{
				$import['match_type'] = 'none';
			}

			if ($imported = $model->importUserField($profileField['profilefieldid'], $import))
			{
				$total++;
			}
		}

		XenForo_Db::commit($this->_db);

		$this->_session->setExtraData('userFieldLookups', $userFieldLookups);

		$this->_session->incrementStepImportTotal($total);

		return true;
	}

	protected function _convertUserFieldChoices(array $profileField, array &$fieldChoiceLookups)
	{
		try
		{
			$choiceData = @unserialize($profileField['data']);
		}
		catch (Exception $e)
		{
			// this is either corrupted data or an invalid char set. The latter isn't something we can automatically detect
			return array();
		}

		if (!is_array($choiceData))
		{
			return array();
		}

		$choices = array();

		foreach ($choiceData AS $key => $choice)
		{
			$choice = $this->_convertToUtf8($choice);

			$choiceId = XenForo_Helper_String::wholeWordTrim($choice, 23, 0, '');

			if ($choiceId != '')
			{
				$choiceId = str_replace('-', '_', XenForo_Link::getTitleForUrl($choiceId, true));

				$i = 1;
				$choiceIdBase = $choiceId;
				while (isset($choices[$choiceId]))
				{
					$choiceId = $choiceIdBase . '_' . ++$i;
				}
			}
			else
			{
				$choiceId = $key;
			}

			$choices[$choiceId] = $choice;
		}

		$lookUps = array();

		switch ($profileField['type'])
		{
			case 'checkbox':
			case 'select_multiple':
				$multiple = true;
				$i = 1;
				foreach ($choices AS $key => $value)
				{
					$lookUps[$i] = $key;
					$i = $i * 2;
				}
				break;

			case 'select':
			case 'radio':
			default:
				$multiple = false;
				foreach ($choices AS $key => $value)
				{
					$lookUps[$value] = $key;
				}
				break;
		}

		$fieldChoiceLookups[$profileField['profilefieldid']] = array(
			'multiple' => $multiple,
			'choices' => $lookUps
		);

		return $choices;
	}

	public function configStepUsers(array $options)
	{
		if ($options)
		{
			return false;
		}

		return $this->_controller->responseView('XenForo_ViewAdmin_Import_vBulletin_ConfigUsers', 'import_config_users');
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

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(userid)
				FROM ' . $prefix . 'user
			');
		}

		$users = $sDb->fetchAll(
			$sDb->limit($this->_getSelectUserSql('user.userid > ' . $sDb->quote($start)), $options['limit'])
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
			$next = $user['userid'];

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

	public function stepBuddyIgnore($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 100,
			'max' => false,
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(userid)
				FROM ' . $prefix . 'user
			');
		}

		$users = $sDb->fetchAll(
			$sDb->limit($this->_getSelectUserSql('user.userid > ' . $sDb->quote($start)
				. " AND (usertextfield.buddylist <> '' OR usertextfield.ignorelist <> '')"), $options['limit'])
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
			$next = $user['userid'];

			if ($this->_importBuddyIgnore($user, $options))
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	protected function _importBuddyIgnore(array $user, array $options)
	{
		$userIds = $user['userid'] . ' ' . $user['buddylist'] . ' ' . $user['ignorelist'];
		$userIds = preg_split('/\s+/', $userIds, -1, PREG_SPLIT_NO_EMPTY);
		$userMap = $this->_importModel->getImportContentMap('user', $userIds);

		$importedUserId = $this->_mapLookUp($userMap, $user['userid']);
		if (!$importedUserId)
		{
			return false;
		}

		if ($user['buddylist'])
		{
			$buddyIds = preg_split('/\s/', $user['buddylist'], -1, PREG_SPLIT_NO_EMPTY);
			$buddyIds = $this->_mapLookUpList($userMap, $buddyIds);
			$this->_importModel->importFollowing($importedUserId, $buddyIds);
		}

		if ($user['ignorelist'])
		{
			$ignoreIds = preg_split('/\s/', $user['ignorelist'], -1, PREG_SPLIT_NO_EMPTY);
			$ignoreIds = $this->_mapLookUpList($userMap, $ignoreIds);
			$this->_importModel->importIgnored($importedUserId, $ignoreIds);
		}

		return $importedUserId;
	}

	public function stepUsersMerge($start, array $options)
	{
		$sDb = $this->_sourceDb;

		$manual = $this->_session->getExtraData('userMerge');

		if ($manual)
		{
			$merge = $sDb->fetchAll($this->_getSelectUserSql('user.userid IN (' . $sDb->quote(array_keys($manual)) . ')'));

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
					$users[$user['userid']] = array(
						'username' => $this->_convertToUtf8($user['username'], true),
						'email' => $this->_convertToUtf8($user['email']),
						'message_count' => $user['posts'],
						'register_date' => $user['joindate'],
						'conflict' => $manual[$user['userid']]
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
			$users = $this->_sourceDb->fetchAll($this->_getSelectUserSql('user.userid IN (' . $sDb->quote(array_keys($manual)) . ')'));

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
					$failedUsers[$user['userid']] = array(
						'username' => $this->_convertToUtf8($user['username'], true),
						'email' => $this->_convertToUtf8($user['email']),
						'message_count' => $user['posts'],
						'register_date' => $user['joindate'],
						'failure' => $manual[$user['userid']]
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
			if (empty($resolve[$user['userid']]))
			{
				continue;
			}

			$info = $resolve[$user['userid']];

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

	protected function _importOrMergeUser(array $user, array $options = array())
	{
		$im = $this->_importModel;

		if ($user['email'] && $emailMatch = $im->getUserIdByEmail($this->_convertToUtf8($user['email'])))
		{
			if (!empty($options['mergeEmail']))
			{
				return $this->_mergeUser($user, $emailMatch);
			}
			else
			{
				if ($im->getUserIdByUserName($this->_convertToUtf8($user['username'], true)))
				{
					$this->_session->setExtraData('userMerge', $user['userid'], 'both');
				}
				else
				{
					$this->_session->setExtraData('userMerge', $user['userid'], 'email');
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
				$this->_session->setExtraData('userMerge', $user['userid'], 'name');
				return false;
			}
		}

		return $this->_importUser($user, $options);
	}

	protected function _importUser(array $user, array $options)
	{
		if ($this->_groupMap === null)
		{
			$this->_groupMap = $this->_importModel->getImportContentMap('userGroup');
		}

		if ($this->_userFieldMap === null)
		{
			$this->_userFieldMap = $this->_importModel->getImportContentMap('userField');
		}

		$user['options'] = intval($user['options']);

		$import = array(
			'username' => $this->_convertToUtf8($user['username'], true),
			'email' => $this->_convertToUtf8($user['email']),
			'user_group_id' => $this->_mapLookUp($this->_groupMap, $user['usergroupid'], XenForo_Model_User::$defaultRegisteredGroupId),
			'secondary_group_ids' => $this->_mapLookUpList($this->_groupMap, explode(',', $user['membergroupids'])),
			'authentication' => array(
				'scheme_class' => 'XenForo_Authentication_vBulletin',
				'data' => array(
					'hash' => $user['password'],
					'salt' => $user['salt']
				)
			),
			'homepage' => $this->_convertToUtf8($user['homepage']),
			'last_activity' => $user['lastactivity'],
			'register_date' => $user['joindate'],
			'ip' => $user['ipaddress'],
			'message_count' => $user['posts'],
			'is_admin' => $user['is_admin'],
			'is_banned' => $user['is_banned'],
			'warning_points' => isset($user['ipoints']) ? $user['ipoints'] : 0,
			'signature' => $this->_convertToUtf8($user['signature']),
			'timezone' => $this->_importModel->resolveTimeZoneOffset($user['timezoneoffset'], $user['options'] & 64), // 64 = dstauto
			'content_show_signature' => (($user['options'] & 1) ? 1 : 0), // 1 = showsignatures
			'receive_admin_email' => (($user['options'] & 16) ? 1 : 0), // 16 = adminemail
		);

		if ($user['customtitle'])
		{
			$import['custom_title'] = $this->_convertToUtf8($user['usertitle']);
			if ($user['customtitle'] == 2) // admin set
			{
				$import['custom_title'] = htmlspecialchars_decode($import['custom_title']);
				$import['custom_title'] = preg_replace('#<br\s*/?>#i', ', ', $import['custom_title']);
				$import['custom_title'] = strip_tags($import['custom_title']);
			}
		}

		if (!($user['options'] & 2048)) // 2048 = receivepm
		{
			$import['allow_send_personal_conversation'] = 'none';
		}
		else if ($user['options'] & 131072) // 131072 = receivepmbuddies
		{
			$import['allow_send_personal_conversation'] = 'followed';
		}

		if (!($user['options'] & 8388608)) // 8388608 = vm_enable
		{
			$import['allow_post_profile'] = 'none';
		}
		else if ($user['options'] & 16777216) // 16777216 = vm_contactonly
		{
			$import['allow_post_profile'] = 'followed';
		}

		if ($user['birthday'])
		{
			$parts = explode('-', $user['birthday']);
			if (count($parts) == 3)
			{
				$import['dob_day'] = $parts[1];
				$import['dob_month'] = $parts[0];
				$import['dob_year'] = ($parts[2] === '0000' ? 0 : $parts[2]);
			}
		}

		// try to give users without an avatar that have actually posted a gravatar
		if (!empty($options['gravatar']))
		{
			if (!$user['has_custom_avatar'] && $user['email'] && $user['lastpost'] && XenForo_Model_Avatar::gravatarExists($user['email']))
			{
				$import['gravatar'] = $import['email'];
			}
		}

		$import['about'] = '';
		if (isset($user['field1']))
		{
			$import['about'] .= $this->_convertToUtf8($user['field1'], true) . "\n\n";
		}
		if (isset($user['field3']))
		{
			$import['about'] .= $this->_convertToUtf8($user['field3'], true) . "\n\n";
		}
		$import['about'] = trim($import['about']);

		if (isset($user['field2']))
		{
			$import['location'] = $this->_convertToUtf8($user['field2'], true);
		}
		if (isset($user['field4']))
		{
			$import['occupation'] = $this->_convertToUtf8($user['field4'], true);
		}

		switch ($user['usergroupid'])
		{
			case 3: $import['user_state'] = 'email_confirm'; break;
			case 4: $import['user_state'] = 'moderated'; break;
			default: $import['user_state'] = 'valid';
		}

		switch ($user['autosubscribe'])
		{
			case -1: $import['default_watch_state'] = ''; break;
			case 0: $import['default_watch_state'] = 'watch_no_email'; break;
			default: $import['default_watch_state'] = 'watch_email';
		}

		switch ($user['showbirthday'])
		{
			case 0: $import['show_dob_year'] = 0; $import['show_dob_date'] = 0; break;
			case 1: $import['show_dob_year'] = 1; $import['show_dob_date'] = 0; break;
			case 2: $import['show_dob_year'] = 1; $import['show_dob_date'] = 1; break;
			case 3: $import['show_dob_year'] = 0; $import['show_dob_date'] = 1; break;
		}

		// custom user fields
		$userFieldDefinitions = $this->_importModel->getUserFieldDefinitions();

		foreach (array('icq', 'aim', 'yahoo', 'msn', 'skype') AS $identityType)
		{
			if (isset($userFieldDefinitions[$identityType]))
			{
				$import[XenForo_Model_Import::USER_FIELD_KEY][$identityType] = $this->_convertToUtf8($user[$identityType]);
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

			if ($user["field$oldFieldId"] !== '')
			{
				if (isset($userFieldLookups[$oldFieldId]))
				{
					$fieldInfo = $userFieldLookups[$oldFieldId];

					// use the lookup info to resolve the value
					if ($fieldInfo['multiple'])
					{
						$userFieldValue = array();

						foreach ($fieldInfo['choices'] AS $bitValue => $stringValue)
						{
							if ($user["field$oldFieldId"] & $bitValue)
							{
								$userFieldValue[$stringValue] = $stringValue;
							}
						}
					}
					else
					{
						$fieldChoiceId = $this->_convertToUtf8($user["field$oldFieldId"]);

						if (isset($fieldInfo['choices'][$fieldChoiceId]))
						{
							$userFieldValue = $fieldInfo['choices'][$fieldChoiceId];
						}
					}
				}
				else
				{
					// set the field value directly
					$userFieldValue = $this->_convertToUtf8($user["field$oldFieldId"], true);
				}
			}

			$import[XenForo_Model_Import::USER_FIELD_KEY][$newFieldId] = $userFieldValue;
		}

		if ($user['is_admin'] && $user['admin_permissions'])
		{
			$user['admin_permissions'] = intval($user['admin_permissions']);

			$aPerms = array();
			if ($user['admin_permissions'] & 4) { $aPerms[] = 'option'; }
			if ($user['admin_permissions'] & 8) { $aPerms[] = 'style'; }
			if ($user['admin_permissions'] & 16) { $aPerms[] = 'language'; }
			if ($user['admin_permissions'] & 32) { $aPerms[] = 'node'; }
			if ($user['admin_permissions'] & 256)
			{
				$aPerms[] = 'user';
				$aPerms[] = 'ban';
				$aPerms[] = 'identityService';
				$aPerms[] = 'trophy';
				$aPerms[] = 'userUpgrade';
			}
			if ($user['admin_permissions'] & 512) { $aPerms[] = 'userGroup'; } // actually, user permissions
			if ($user['admin_permissions'] & 4096) { $aPerms[] = 'bbCodeSmilie'; }
			if ($user['admin_permissions'] & 8192) { $aPerms[] = 'cron'; }
			if ($user['admin_permissions'] & 16384)
			{
				$aPerms[] = 'import';
				$aPerms[] = 'upgradeXenForo';
			}
			if ($user['admin_permissions'] & 65536) { $aPerms[] = 'addOn'; }

			$import['admin_permissions'] = $aPerms;
		}

		$importedUserId = $this->_importModel->importUser($user['userid'], $import, $failedKey);
		if ($importedUserId)
		{
			if ($user['is_banned'])
			{
				$this->_importModel->importBan(array(
					'user_id' => $importedUserId,
					'ban_user_id' => $this->_importModel->mapUserId($user['ban_user_id'], 0),
					'ban_date' => $user['ban_date'],
					'end_date' => $user['ban_end_date'],
					'user_reason' => $this->_convertToUtf8($user['ban_reason'])
				));
			}

			if ($user['is_super_moderator'])
			{
				$this->_session->setExtraData('superMods', $user['userid'], $importedUserId);
			}
		}
		else if ($failedKey)
		{
			$this->_session->setExtraData('userFailed', $user['userid'], $failedKey);
		}

		return $importedUserId;
	}

	protected function _getSelectUserSql($where)
	{
		return '
			SELECT user.*, userfield.*, usertextfield.*,
				IF(admin.userid IS NULL, 0, 1) AS is_admin,
				admin.adminpermissions AS admin_permissions,
				IF(userban.userid IS NULL, 0, 1) AS is_banned,
				userban.bandate AS ban_date,
				userban.liftdate AS ban_end_date,
				userban.reason AS ban_reason,
				userban.adminid AS ban_user_id,
				IF(usergroup.adminpermissions & 1, 1, 0) AS is_super_moderator,
				IF(customavatar.userid, 1, 0) AS has_custom_avatar
			FROM ' . $this->_prefix . 'user AS user
			STRAIGHT_JOIN ' . $this->_prefix . 'userfield AS userfield ON (user.userid = userfield.userid)
			STRAIGHT_JOIN ' . $this->_prefix . 'usertextfield AS usertextfield ON (user.userid = usertextfield.userid)
			LEFT JOIN ' . $this->_prefix . 'administrator AS admin ON (user.userid = admin.userid)
			LEFT JOIN ' . $this->_prefix . 'userban AS userban ON (user.userid = userban.userid)
			LEFT JOIN ' . $this->_prefix . 'usergroup AS usergroup ON (user.usergroupid = usergroup.usergroupid)
			LEFT JOIN ' . $this->_prefix . 'customavatar AS customavatar ON (user.userid = customavatar.userid)
			WHERE ' . $where . '
			ORDER BY user.userid
		';
	}

	protected function _mergeUser(array $user, $targetUserId)
	{
		$this->_db->query('
			UPDATE xf_user SET
				message_count = message_count + ?,
				register_date = IF(register_date > ?, ?, register_date)
			WHERE user_id = ?
		', array($user['posts'], $user['joindate'], $user['joindate'], $targetUserId));

		$this->_importModel->logImportData('user', $user['userid'], $targetUserId);

		return $targetUserId;
	}

	public function stepPaidSubscriptions($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 200,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(subscriptionlogid)
				FROM ' . $prefix . 'subscriptionlog
			');

			XenForo_Db::beginTransaction();

			$userGroupMap = $model->getImportContentMap('userGroup');

			$subs = $sDb->fetchAll('
				SELECT sub.*, title.text AS title, description.text AS description
				FROM ' . $prefix . 'subscription AS sub
				INNER JOIN ' . $prefix . 'phrase AS title ON (title.languageid = 0
					AND title.varname = CONCAT(\'sub\', sub.subscriptionid, \'_title\'))
				INNER JOIN ' . $prefix . 'phrase AS description ON (description.languageid = 0
					AND description.varname = CONCAT(\'sub\', sub.subscriptionid, \'_desc\'))
			');
			foreach ($subs AS $sub)
			{
				$import = array(
					'title' => $this->_convertToUtf8($sub['title']),
					'description' => $this->_convertToUtf8($sub['description']),
					'display_order' => $sub['displayorder'],
					'extra_group_ids' => '',
					'can_purchase' => 0, // never be active as the admin probably needs to verify/setup their configs
					'recurring' => 0,
					'cost_amount' => 1,
					'cost_currency' => 'usd',
					'length_amount' => 1,
					'length_unit' => 'year'
				);

				if ($sub['membergroupids'])
				{
					$groupIds = array();
					foreach (explode(',', $sub['membergroupids']) AS $groupId)
					{
						if (isset($userGroupMap[$groupId]))
						{
							$groupIds[] = $userGroupMap[$groupId];
						}
					}
					$import['extra_group_ids'] = implode(',', $groupIds);
				}

				$def = @unserialize($sub['cost']);
				if (is_array($def))
				{
					if (isset($def[0]))
					{
						$def = $def[0];
					}
					if (!empty($def['cost']) && is_array($def['cost']))
					{
						foreach ($def['cost'] AS $currency => $cost)
						{
							if (floatval($cost) > 0)
							{
								$import['cost_amount'] = floatval($cost);
								$import['cost_currency'] = $currency;
								break;
							}
						}
					}
					if (!empty($def['units']))
					{
						$import['length_amount'] = $def['length'];
						switch ($def['units'])
						{
							case 'Y': $import['length_unit'] = 'year'; break;
							case 'M': $import['length_unit'] = 'month'; break;
							case 'D': $import['length_unit'] = 'day'; break;
							default:
								$import['length_amount'] = 0;
								$import['length_unit'] = '';
						}
					}
					else
					{
						$import['length_amount'] = 0;
						$import['length_unit'] = '';
					}
					$import['recurring'] = !empty($def['recurring']) ? 1 : 0;
				}

				$model->importUserUpgradeDefinition($sub['subscriptionid'], $import);
			}

			XenForo_Db::commit();
		}

		$logs = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM ' . $prefix . 'subscriptionlog
				WHERE subscriptionlogid > ' . $sDb->quote($start) . '
					AND status = 1
				ORDER BY subscriptionlogid
			', $options['limit']
		));
		if (!$logs)
		{
			return true;
		}

		$userIdMap = $model->getUserIdsMapFromArray($logs, 'userid');
		$upgradeMap = $model->getImportContentMap('userUpgrade');
		$upgrades = $model->getModelFromCache('XenForo_Model_UserUpgrade')->getAllUserUpgrades();

		$next = 0;
		$total = 0;

		XenForo_Db::beginTransaction();

		foreach ($logs AS $log)
		{
			$next = $log['subscriptionlogid'];

			$newUserId = $this->_mapLookUp($userIdMap, $log['userid']);
			if (!$newUserId)
			{
				continue;
			}

			$newUpgradeId = $this->_mapLookUp($upgradeMap, $log['subscriptionid']);
			if (!$newUpgradeId)
			{
				continue;
			}

			$import = array(
				'user_id' => $newUserId,
				'user_upgrade_id' => $newUpgradeId,
				'extra' => 'a:0:{}',
				'start_date' => $log['regdate'],
				'end_date' => $log['expirydate']
			);
			$extraGroupIds = isset($upgrades[$newUpgradeId]) ? $upgrades[$newUpgradeId]['extra_group_ids'] : '';

			if ($model->importActiveUserUpgrade($import, $extraGroupIds))
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
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
				SELECT MAX(userid)
				FROM ' . $prefix . 'customavatar
			');
		}

		$avatars = $sDb->fetchAll($sDb->limit(
			'
				SELECT customavatar.userid, user.avatarrevision
				FROM ' . $prefix . 'customavatar AS customavatar
				INNER JOIN ' . $prefix . 'user AS user ON (user.userid = customavatar.userid)
				WHERE customavatar.userid > ' . $sDb->quote($start) . '
				ORDER BY customavatar.userid
			', $options['limit']
		));
		if (!$avatars)
		{
			return true;
		}

		$userIdMap = $model->getUserIdsMapFromArray($avatars, 'userid');

		$next = 0;
		$total = 0;

		foreach ($avatars AS $avatar)
		{
			$next = $avatar['userid'];

			$newUserId = $this->_mapLookUp($userIdMap, $avatar['userid']);
			if (!$newUserId)
			{
				continue;
			}

			if (!$options['path'])
			{
				$fData = $sDb->fetchOne('
					SELECT filedata
					FROM ' . $prefix . 'customavatar
					WHERE userid = ' . $sDb->quote($avatar['userid'])
				);
				if ($fData === '')
				{
					continue;
				}

				$avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				if (!$avatarFile || !@file_put_contents($avatarFile, $fData))
				{
					continue;
				}

				$isTemp = true;
			}
			else
			{
				$avatarFileOrig = "$options[path]/avatar$avatar[userid]_$avatar[avatarrevision].gif";
				if (!file_exists($avatarFileOrig))
				{
					continue;
				}

				$avatarFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				copy($avatarFileOrig, $avatarFile);

				$isTemp = true;
			}

			if ($this->_importModel->importAvatar($avatar['userid'], $newUserId, $avatarFile))
			{
				$total++;
			}

			if ($isTemp)
			{
				@unlink($avatarFile);
			}
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
				SELECT MAX(pmtextid)
				FROM ' . $prefix . 'pmtext
			');
		}

		$pmTexts = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM ' . $prefix . 'pmtext
				WHERE pmtextid > ' . $sDb->quote($start) . '
				ORDER BY pmtextid
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
			$next = $pmText['pmtextid'];

			$readState = $sDb->fetchPairs('
				SELECT userid, IF(folderid >= 0, messageread, 1)
				FROM ' . $prefix . 'pm
				WHERE pmtextid = ' . $sDb->quote($pmText['pmtextid'])
			);

			try
			{
				$toUser = @unserialize($pmText['touserarray']);
			}
			catch (Exception $e)
			{
				// this is either corrupted data or an invalid char set. The latter isn't something we can automatically detect
				continue;
			}

			if (!is_array($toUser))
			{
				continue;
			}

			$users = array(
				$pmText['fromuserid'] => $pmText['fromusername']
			);
			foreach ($toUser AS $key => $toUser)
			{
				if (is_array($toUser))
				{
					foreach ($toUser AS $subKey => $username)
					{
						$users[$subKey] = $username;
					}
				}
				else
				{
					$users[$key] = $toUser;
				}
			}

			$mapUserIds = $model->getImportContentMap('user', array_keys($users));

			$newFromUserId = $this->_mapLookUp($mapUserIds, $pmText['fromuserid']);
			if (!$newFromUserId)
			{
				continue;
			}

			$fromUserName = $this->_convertToUtf8($pmText['fromusername'], true);

			$recipients = array();
			foreach ($users AS $userId => $username)
			{
				$newUserId = $this->_mapLookUp($mapUserIds, $userId);
				if (!$newUserId)
				{
					continue;
				}

				if (isset($readState[$userId]))
				{
					$lastReadDate = ($readState[$userId] ? $pmText['dateline'] : 0);
					$deleted = false;
				}
				else
				{
					$lastReadDate = $pmText['dateline'];
					$deleted = true;
				}

				$recipients[$newUserId] = array(
					'username' => $this->_convertToUtf8($username, true),
					'last_read_date' => $lastReadDate,
					'recipient_state' => ($deleted ? 'deleted' : 'active')
				);
			}

			$conversation = array(
				'title' => $this->_convertToUtf8($pmText['title'], true),
				'user_id' => $newFromUserId,
				'username' => $fromUserName,
				'start_date' => $pmText['dateline'],
				'open_invite' => 0,
				'conversation_open' => 1
			);

			$messages = array(
				array(
					'message_date' => $pmText['dateline'],
					'user_id' => $newFromUserId,
					'username' => $fromUserName,
					'message' => $this->_convertToUtf8($pmText['message'])
				)
			);

			if ($model->importConversation($pmText['pmtextid'], $conversation, $recipients, $messages))
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepVisitorMessages($start, array $options)
	{
		$options = array_merge(array(
			'limit' => 200,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(vmid)
				FROM ' . $prefix . 'visitormessage
			');
		}

		$vms = $sDb->fetchAll($sDb->limit(
			'
				SELECT vm.*,
						IF(user.username IS NULL, vm.postusername, user.username) AS username
				FROM ' . $prefix . 'visitormessage AS vm
				LEFT JOIN ' . $prefix . 'user AS user ON (vm.postuserid = user.userid)
				WHERE vm.vmid > ' . $sDb->quote($start) . '
				ORDER BY vm.vmid
			', $options['limit']
		));
		if (!$vms)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIds = array();
		foreach ($vms AS $vm)
		{
			$userIds[] = $vm['userid'];
			$userIds[] = $vm['postuserid'];
		}
		$userIdMap = $model->getImportContentMap('user', $userIds);

		XenForo_Db::beginTransaction();

		$formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Text');
		$parser = XenForo_BbCode_Parser::create($formatter);

		foreach ($vms AS $vm)
		{
			if (trim($vm['postusername']) === '')
			{
				continue;
			}

			$next = $vm['vmid'];

			$profileUserId = $this->_mapLookUp($userIdMap, $vm['userid']);
			if (!$profileUserId)
			{
				continue;
			}

			$postUserId = $this->_mapLookUp($userIdMap, $vm['postuserid'], 0);

			$import = array(
				'profile_user_id' => $profileUserId,
				'user_id' => $postUserId,
				'username' => $this->_convertToUtf8($vm['postusername'], true),
				'post_date' => $vm['dateline'],
				'message' => $parser->render($this->_convertToUtf8($vm['pagetext'])),
				'ip' => XenForo_Helper_Ip::convertIpBinaryToString($vm['ipaddress'])
			);

			switch ($vm['state'])
			{
				case 'deleted': $import['message_state'] = 'deleted'; break;
				case 'moderation': $import['message_state'] = 'moderated'; break;
				default: $import['message_state'] = 'visible';
			}

			if ($model->importProfilePost($vm['vmid'], $import))
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
			FROM ' . $prefix . 'forum
		');
		if (!$forums)
		{
			return true;
		}

		$forumTree = array();
		foreach ($forums AS $forum)
		{
			$forumTree[$forum['parentid']][$forum['forumid']] = $forum;
		}

		$forumPermissions = array();
		$forumPermissionSql = $sDb->query('
			SELECT *
			FROM ' . $prefix . 'forumpermission
		');
		while ($forumPermission = $forumPermissionSql->fetch())
		{
			$forumPermissions[$forumPermission['forumid']][$forumPermission['usergroupid']] = $forumPermission['forumpermissions'];
		}

		XenForo_Db::beginTransaction();

		$total = $this->_importForumTree(-1, $forumTree, $forumPermissions);

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
			$forum['options'] = intval($forum['options']);

			$import = array(
				'title' => $this->_convertToUtf8($forum['title'], true),
				'description' => $this->_convertToUtf8($forum['description'], true),
				'display_order' => $forum['displayorder'],
				'parent_node_id' => $this->_mapLookUp($forumIdMap, $forum['parentid'], 0),
				'display_in_list' => (($forum['options'] & 1) && $forum['displayorder'] > 0) // active
			);

			if ($forum['link'])
			{
				$import['node_type_id'] = 'LinkForum';
				$import['link_url'] = $forum['link'];

				$nodeId = $this->_importModel->importLinkForum($forum['forumid'], $import);
			}
			else if ($forum['options'] & 4) // cancontainthreads
			{
				$import['node_type_id'] = 'Forum';
				$import['discussion_count'] = $forum['threadcount'];
				$import['message_count'] = $forum['replycount'] + $forum['threadcount'];
				$import['last_post_date'] = $forum['lastpost'];
				$import['last_post_username'] = $this->_convertToUtf8($forum['lastposter'], true);
				$import['last_thread_title'] = $this->_convertToUtf8($forum['lastthread'], true);

				$nodeId = $this->_importModel->importForum($forum['forumid'], $import);
			}
			else
			{
				$import['node_type_id'] = 'Category';

				$nodeId = $this->_importModel->importCategory($forum['forumid'], $import);
			}

			if ($nodeId)
			{
				if (!empty($forumPermissions[$forum['forumid']]))
				{
					$this->_importForumPermissions($nodeId, $forumPermissions[$forum['forumid']]);
				}

				$forumIdMap[$forum['forumid']] = $nodeId;

				$total++;
				$total += $this->_importForumTree($forum['forumid'], $forumTree, $forumPermissions, $forumIdMap);
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
			if ($oldGroupId == 3 || $oldGroupId == 4)
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

	protected function _calculateForumPermissions($perms)
	{
		$output = array();

		if ($this->_nodePermissionsGrouped === null)
		{
			$this->_nodePermissionsGrouped = $this->_importModel->getNodePermissionsGrouped();
		}

		$perms = intval($perms);

		if ($perms & 1)
		{
			// viewable
			$output['general']['viewNode'] = 'content_allow';

			$output['forum']['viewContent'] = ($perms & 524288 ? 'content_allow' : 'reset');
			$output['forum']['viewOthers'] = ($perms & 2 ? 'content_allow' : 'reset');
			$output['forum']['postThread'] = ($perms & 16 ? 'content_allow' : 'reset');
			$output['forum']['postReply'] = ($perms & 32 ? 'content_allow' : 'reset');
			$output['forum']['editOwnPost'] = ($perms & 128 ? 'content_allow' : 'reset');
			$output['forum']['deleteOwnPost'] = ($perms & 256 ? 'content_allow' : 'reset');
			$output['forum']['deleteOwnThread'] = ($perms & 512 ? 'content_allow' : 'reset');
			$output['forum']['viewAttachment'] = ($perms & 4096 ? 'content_allow' : 'reset');
			$output['forum']['uploadAttachment'] = ($perms & 8192 ? 'content_allow' : 'reset');
			$output['forum']['votePoll'] = ($perms & 32768 ? 'content_allow' : 'reset');
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
			SELECT moderator.*,
				IF(usergroup.adminpermissions & 1, 1, 0) AS is_super_moderator
			FROM ' . $prefix . 'moderator AS moderator
			INNER JOIN ' . $prefix . 'user AS user ON (moderator.userid = user.userid)
			LEFT JOIN ' . $this->_prefix . 'usergroup AS usergroup ON (user.usergroupid = usergroup.usergroupid)
		');
		if (!$moderators)
		{
			return true;
		}

		$modGrouped = array();
		foreach ($moderators AS $moderator)
		{
			if (!array_key_exists('permissions2', $moderator))
			{
				$moderator['permissions2'] = 0;
			}

			$modGrouped[$moderator['userid']][$moderator['forumid']] = $moderator;
		}

		$superMods = $this->_session->getExtraData('superMods');
		if ($superMods)
		{
			foreach ($superMods AS $oldUserId => $newUserId)
			{
				if (!isset($modGrouped[$oldUserId]))
				{
					// no record in mod table, but imported as super mod - has everything
					$modGrouped[$oldUserId][-1] = array(
						'userid' => $oldUserId,
						'is_super_moderator' => true,
						'forumid' => -1,
						'permissions' => -1, // bitwise, all 1s
						'permissions2' => -1 // bitwise, all 1s
					);
				}
			}
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

			$globalModPermissions = array();
			$inserted = false;

			if (!empty($forums[-1]['is_super_moderator']))
			{
				$perms = $this->_calculateModeratorPermissions($forums[-1]['permissions'], $forums[-1]['permissions2']);
				$globalModPermissions += $perms['global'] + $perms['forum'];

				$isSuperMod = true;

				$total++;
				$inserted = true;
			}
			else
			{
				$isSuperMod = false;
			}

			unset($forums[-1]);

			foreach ($forums AS $forumId => $moderator)
			{
				$newNodeId = $this->_mapLookUp($nodeMap, $forumId);
				if (!$newNodeId)
				{
					continue;
				}

				$perms = $this->_calculateModeratorPermissions($moderator['permissions'], $moderator['permissions2']);
				$globalModPermissions += $perms['global'];

				$mod = array(
					'content_id' => $newNodeId,
					'user_id' => $newUserId,
					'moderator_permissions' => $perms['forum']
				);
				$model->importNodeModerator($forumId, $userId, $mod);

				$total++;
				$inserted = true;
			}

			if ($inserted)
			{
				$mod = array(
					'user_id' => $newUserId,
					'is_super_moderator' => $isSuperMod,
					'moderator_permissions' => $globalModPermissions
				);
				$model->importGlobalModerator($userId, $mod);
			}
		}

		$this->_session->incrementStepImportTotal($total);

		XenForo_Db::commit();

		return true;
	}

	protected function _calculateModeratorPermissions($perms1, $perms2)
	{
		$global = array();
		$forum = array();

		$perms1 = intval($perms1);
		$perms2 = intval($perms2);

		if ($perms2 & 1)
		{
			$global['profilePost']['editAny'] = true;
		}
		if ($perms2 & 2)
		{
			$global['profilePost']['deleteAny'] = true;
			$global['profilePost']['undelete'] = true;

			if ($perms2 & 4)
			{
				$global['profilePost']['hardDeleteAny'] = true;
			}
		}
		if ($perms2 & 8)
		{
			$global['profilePost']['approveUnapprove'] = true;
			$global['profilePost']['viewDeleted'] = true;
			$global['profilePost']['viewModerated'] = true;
		}

		if ($perms1 & 1)
		{
			$forum['forum']['editAnyPost'] = true;
		}
		if ($perms1 & 2)
		{
			$forum['forum']['deleteAnyPost'] = true;
			$forum['forum']['deleteAnyThread'] = true;
			$forum['forum']['undelete'] = true;

			if ($perms1 & 131072)
			{
				$forum['forum']['hardDeleteAnyPost'] = true;
				$forum['forum']['hardDeleteAnyThread'] = true;
			}

			// these don't really fit. give them to mods that can delete stuff
			$forum['general']['viewIps'] = true;
			$forum['general']['cleanSpam'] = true;
			$forum['general']['bypassUserPrivacy'] = true;
		}
		if ($perms1 & 4)
		{
			$forum['forum']['lockUnlockThread'] = true;
		}
		if ($perms1 & 16)
		{
			$forum['forum']['manageAnyThread'] = true;
			$forum['forum']['stickUnstickThread'] = true;
		}
		if ($perms1 & 64)
		{
			$forum['forum']['approveUnapprove'] = true;
			$forum['forum']['viewModerated'] = true;
		}

		$forum['forum']['viewDeleted'] = true; // this was given automatically to mods

		return array(
			'global' => $global,
			'forum' => $forum
		);
	}

	public function stepThreadPrefixes($start, array $options)
	{
		$options = array_merge(array(), $options);

		$sDb = $this->_sourceDb;
		$tablePrefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		$nodeMap = $model->getImportContentMap('node');
		$userGroupMap = $model->getImportContentMap('userGroup');

		$prefixSets = $sDb->fetchAll("
			SELECT prefixset.*, phrase.text AS title
			FROM {$tablePrefix}prefixset AS prefixset
			LEFT JOIN {$tablePrefix}phrase AS phrase ON
				(phrase.languageid = 0 AND phrase.varname = CONCAT('prefixset_', prefixset.prefixsetid, '_title'))
			ORDER BY prefixset.displayorder
		");

		$prefixUserGroups = array();
		try // vB 3.7 has no prefix permissions, so catch the error that will occur when we try to query them
		{
			foreach ($sDb->fetchAll('SELECT * FROM ' . $tablePrefix . 'prefixpermission') AS $pug)
			{
				$prefixUserGroups[$pug['prefixid']][$pug['usergroupid']] = true;
			}
		}
		catch (Exception $e) {}

		$prefixSetForums = array();
		foreach ($sDb->fetchAll('SELECT * FROM ' . $tablePrefix . 'forumprefixset') AS $psf)
		{
			$prefixSetForums[$psf['prefixsetid']][$psf['forumid']] = $psf['forumid'];
		}

		$prefixes = $sDb->fetchAll("
			SELECT prefix.*, phrase.text AS title
			FROM {$tablePrefix}prefix AS prefix
			INNER JOIN {$tablePrefix}prefixset AS prefixset ON
				(prefixset.prefixsetid = prefix.prefixsetid)
			LEFT JOIN {$tablePrefix}phrase AS phrase ON
				(phrase.languageid = 0 AND phrase.varname = CONCAT('prefix_', prefix.prefixid, '_title_plain'))
			ORDER BY prefixset.displayorder, prefix.displayorder
		");

		// get the list of nodes to which each prefix set applies
		$prefixNodes = array();
		foreach ($prefixes AS $prefix)
		{
			$prefixSetId = $prefix['prefixsetid'];

			if (empty($prefixNodes[$prefixSetId]))
			{
				$prefixNodes[$prefixSetId] = array();

				if (isset($prefixSetForums[$prefixSetId]))
				{
					foreach ($prefixSetForums[$prefixSetId] AS $prefixSetForumId)
					{
						$nodeId = $this->_mapLookUp($nodeMap, $prefixSetForumId);
						if ($nodeId)
						{
							$prefixNodes[$prefixSetId][] = $nodeId;
						}
					}
				}
			}
		}

		$total = 0;

		XenForo_Db::beginTransaction($this->_db);

		$prefixGroupMap = array();
		foreach ($prefixSets AS $prefixSet)
		{
			$prefixSetId = strtolower($prefixSet['prefixsetid']);

			$import = array(
				'display_order' => $prefixSet['displayorder'],
				XenForo_Model_Import::EXTRA_DATA_KEY => array(
					XenForo_DataWriter_ThreadPrefixGroup::DATA_TITLE => $this->_convertToUtf8($prefixSet['title'])
				)
			);

			$prefixGroupMap[$prefixSetId] = $model->importThreadPrefixGroup($prefixSetId, $import);
		}

		foreach ($prefixes AS $order => $prefix)
		{
			$prefixId = $prefix['prefixid'];
			$prefixSetId = $prefix['prefixsetid'];

			if (empty($prefixUserGroups[$prefixId]))
			{
				$allowedUserGroupIds = '-1';
			}
			else
			{
				$allowedUserGroupIds = array();

				foreach ($userGroupMap AS $oldUgId => $newUgId)
				{
					if (!isset($prefixUserGroups[$prefixId][$oldUgId]))
					{
						$allowedUserGroupIds[] = $newUgId;
					}
				}

				$allowedUserGroupIds = implode(',', $allowedUserGroupIds);
			}

			$import = array(
				'display_order' => $order * 10,
				'css_class' => 'prefix prefixPrimary',
				'allowed_user_group_ids' => $allowedUserGroupIds,
				'prefix_group_id' => $this->_mapLookUp($prefixGroupMap, $prefixSetId, 0),
			);

			$model->importThreadPrefix(strtolower($prefixId), $import,
				$this->_convertToUtf8($prefix['title']),
				$prefixNodes[$prefixSetId]);

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
				SELECT MAX(threadid)
				FROM ' . $prefix . 'thread
			');
		}

		// pull threads from things we actually imported as forums
		$threads = $sDb->fetchAll($sDb->limit(
			'
				SELECT thread.*,
					IF(user.username IS NULL, thread.postusername, user.username) AS postusername
				FROM ' . $prefix . 'thread AS thread FORCE INDEX (PRIMARY)
				LEFT JOIN ' . $prefix . 'user AS user ON (thread.postuserid = user.userid)
				INNER JOIN ' . $prefix . 'forum AS forum ON
					(thread.forumid = forum.forumid AND forum.link = \'\' AND forum.options & 4)
				WHERE thread.threadid >= ' . $sDb->quote($start) . '
					AND thread.open <> 10
				ORDER BY thread.threadid
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
		$threadPrefixMap = $model->getImportContentMap('threadPrefix');

		XenForo_Db::beginTransaction();

		foreach ($threads AS $thread)
		{
			if (trim($thread['title']) === '')
			{
				continue;
			}

			$postDateStart = $options['postDateStart'];

			$next = $thread['threadid'] + 1; // uses >=, will be moved back down if need to continue
			$options['postDateStart'] = 0;

			$maxPosts = $options['postLimit'] - $totalPosts;
			$posts = $sDb->fetchAll($sDb->limit(
				'
					SELECT post.*,
						IF(user.username IS NULL, post.username, user.username) AS username,
						editlog.dateline AS editdate, editlog.userid AS edituserid
					FROM ' . $prefix . 'post AS post
					LEFT JOIN ' . $prefix . 'user AS user ON (post.userid = user.userid)
					LEFT JOIN ' . $prefix . 'editlog AS editlog ON (post.postid = editlog.postid)
					WHERE post.threadid = ' . $sDb->quote($thread['threadid']) . '
						AND post.dateline > ' . $sDb->quote($postDateStart) . '
					ORDER BY post.dateline
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
				$threadId = $model->mapThreadId($thread['threadid']);

				$position = $this->_db->fetchOne('
					SELECT MAX(position)
					FROM xf_post
					WHERE thread_id = ?
				', $threadId);
			}
			else
			{
				$forumId = $this->_mapLookUp($nodeMap, $thread['forumid']);
				if (!$forumId)
				{
					continue;
				}

				if (trim($thread['postusername']) === '')
				{
					$thread['postusername'] = 'Guest';
				}

				$import = array(
					'title' => $this->_convertToUtf8($thread['title'], true),
					'node_id' => $forumId,
					'user_id' => $model->mapUserId($thread['postuserid'], 0),
					'username' => $this->_convertToUtf8($thread['postusername'], true),
					'discussion_open' => $thread['open'],
					'post_date' => $thread['dateline'],
					'reply_count' => $thread['replycount'],
					'view_count' => $thread['views'],
					'sticky' => $thread['sticky'],
					'last_post_date' => $thread['lastpost'],
					'last_post_username' => $this->_convertToUtf8($thread['lastposter'], true),
				);
				if (isset($thread['prefixid']))
				{
					$import['prefix_id'] = $this->_mapLookUp($threadPrefixMap, $thread['prefixid'], 0);
				}
				switch ($thread['visible'])
				{
					case 0: $import['discussion_state'] = 'moderated'; break;
					case 2: $import['discussion_state'] = 'deleted'; break;
					default: $import['discussion_state'] = 'visible'; break;
				}

				$threadId = $model->importThread($thread['threadid'], $import);
				if (!$threadId)
				{
					continue;
				}

				$position = -1;

				$subs = $sDb->fetchPairs('
					SELECT userid, emailupdate
					FROM ' . $prefix . 'subscribethread
					WHERE threadid = ' . $sDb->quote($thread['threadid'])
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

				$threadTitleRegex = '#^(re:\s*)?' . preg_quote($thread['title'], '#') . '$#i';

				$userIdMap = $model->getUserIdsMapFromArray($posts, 'userid');

				foreach ($posts AS $i => $post)
				{
					if ($post['title'] !== '' && !preg_match($threadTitleRegex, $post['title']))
					{
						$post['pagetext'] = '[b]' . htmlspecialchars_decode($post['title']) . "[/b]\n\n" . ltrim($post['pagetext']);
					}

					if (trim($post['username']) === '')
					{
						$post['username'] = 'Guest';
					}

					$post['pagetext'] = $this->_convertToUtf8($post['pagetext']);

					$import = array(
						'thread_id' => $threadId,
						'user_id' => $this->_mapLookUp($userIdMap, $post['userid'], 0),
						'username' => $this->_convertToUtf8($post['username'], true),
						'post_date' => $post['dateline'],
						'message' => $post['pagetext'],
						'attach_count' => 0,
						'ip' => $post['ipaddress'],
						'last_edit_date' => !empty($post['editdate']) ? $post['editdate'] : 0,
						'last_edit_user_id' => !empty($post['editdate']) ? $post['edituserid'] : 0,
						'edit_count' => !empty($post['editdate']) ? 1 : 0
					);
					switch ($post['visible'])
					{
						case 0: $import['message_state'] = 'moderated'; $import['position'] = $position; break;
						case 2: $import['message_state'] = 'deleted'; $import['position'] = $position; break;
						default: $import['message_state'] = 'visible'; $import['position'] = ++$position; break;
					}

					$post['xf_post_id'] = $model->importPost($post['postid'], $import);

					$options['postDateStart'] = $post['dateline'];
					$totalPosts++;

					if (stripos($post['pagetext'], '[quote=') !== false)
					{
						if (preg_match_all('/\[quote=("|\'|)(?P<username>[^;\n\]]*);\s*(?P<postid>\d+)\s*\1\]/siU', $post['pagetext'], $quotes, PREG_SET_ORDER))
						{
							$post['quotes'] = array();

							foreach ($quotes AS $quote)
							{
								$quotedPostId = intval($quote['postid']);

								$quotedPostIds[] = $quotedPostId;

								$post['quotes'][$quote[0]] = array($quote['username'], $quotedPostId);
							}
						}
					}

					$posts[$i] = $post;
				}

				$postIdMap = (empty($quotedPostIds) ? array() : $model->getImportContentMap('post', $quotedPostIds));

				$db = XenForo_Application::getDb();

				foreach ($posts AS $post)
				{
					if (!empty($post['quotes']))
					{
						$postQuotesRewrite = $this->_rewriteQuotes($post['pagetext'], $post['quotes'], $postIdMap);

						if ($post['pagetext'] != $postQuotesRewrite)
						{
							$db->update('xf_post', array('message' => $postQuotesRewrite), 'post_id = ' . $db->quote($post['xf_post_id']));
						}
					}
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

	protected function _rewriteQuotes($message, array $quotes, array $postIdMap)
	{
		foreach ($quotes AS $quote => &$replace)
		{
			list($username, $postId) = $replace;

			$replace = sprintf('[quote="%s, post: %d"]', $username, $this->_mapLookUp($postIdMap, $postId));
		}

		return str_replace(array_keys($quotes), $quotes, $message);
	}

	public function stepPostEditHistory($start, array $options)
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
				SELECT MAX(postid)
				FROM ' . $prefix . 'postedithistory
			');
		}

		// fetch the next 100 posts
		$postIds = $sDb->fetchCol($sDb->limit(
			'
				SELECT DISTINCT postid
				FROM ' . $prefix . 'postedithistory
				WHERE postid > ' . $sDb->quote($start) . '
				ORDER BY postid
			', $options['limit']
		));
		if (!$postIds)
		{
			return true;
		}

		$edits = $sDb->fetchAll(
			'
				SELECT *
				FROM ' . $prefix . 'postedithistory
				WHERE postid IN (' . $sDb->quote($postIds) . ')
				ORDER BY postedithistoryid
			'
		);
		if (!$edits)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$postIdMap = $model->getPostIdsMapFromArray($edits, 'postid');
		$userIdMap = $model->getUserIdsMapFromArray($edits, 'userid');

		// assemble an array
		$orderedEdits = array();
		foreach ($edits AS $edit)
		{
			$orderedEdits[$edit['postid']][$edit['postedithistoryid']] = $edit;
		}
		unset($edits);

		XenForo_Db::beginTransaction();

		foreach ($orderedEdits AS $postId => $edits)
		{
			$pageText = false;
			$next = max($next, $postId);

			foreach ($edits AS $edit)
			{
				if ($pageText)
				{
					$newPostId = $this->_mapLookUp($postIdMap, $edit['postid']);
					if (!$newPostId)
					{
						continue;
					}

					if ($pageText !== false)
					{
						$import = array(
							'content_type' => 'post',
							'content_id' => $newPostId,
							'edit_user_id' => $this->_mapLookUp($userIdMap, $edit['userid'], 0),
							'edit_date' => $edit['dateline'],
							'old_text' => $this->_convertToUtf8($pageText),
						);

						$model->importEditHistory($edit['postedithistoryid'], $import);

						$total++;
					}
				}

				$pageText = $edit['pagetext'];
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
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
				SELECT MAX(pollid)
				FROM ' . $prefix . 'poll
			');
		}

		$polls = $sDb->fetchAll($sDb->limit(
			'
				SELECT poll.*, thread.threadid
				FROM ' . $prefix . 'poll AS poll
				INNER JOIN ' . $prefix . 'thread AS thread ON (thread.pollid = poll.pollid AND thread.open <> 10)
				WHERE poll.pollid > ' . $sDb->quote($start) . '
				ORDER BY poll.pollid
			', $options['limit']
		));
		if (!$polls)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$threadIdMap = $model->getThreadIdsMapFromArray($polls, 'threadid');
		$donePolls = array();

		XenForo_Db::beginTransaction();

		foreach ($polls AS $poll)
		{
			$next = $poll['pollid'];

			$newThreadId = $this->_mapLookUp($threadIdMap, $poll['threadid']);
			if (!$newThreadId)
			{
				continue;
			}

			if (isset($donePolls[$poll['pollid']]))
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
			$responses = explode('|||', $this->_convertToUtf8($poll['options'], true));

			$newPollId = $model->importThreadPoll($poll['pollid'], $newThreadId, $import, $responses, $responseIds);
			if ($newPollId)
			{
				$donePolls[$poll['pollid']] = $newPollId;

				$votes = $sDb->fetchAll('
					SELECT userid, votedate, voteoption
					FROM ' . $prefix . 'pollvote
					WHERE pollid = ' . $sDb->quote($poll['pollid'])
				);

				$userIdMap = $model->getUserIdsMapFromArray($votes, 'userid');
				foreach ($votes AS $vote)
				{
					$userId = $this->_mapLookUp($userIdMap, $vote['userid']);
					if (!$userId)
					{
						continue;
					}

					$voteOption = max(0, $vote['voteoption'] - 1);

					if (!isset($responseIds[$voteOption]))
					{
						continue;
					}

					$model->importPollVote($newPollId, $userId, $responseIds[$voteOption], $vote['votedate']);
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
				SELECT MAX(attachmentid)
				FROM ' . $prefix . 'attachment
			');
		}

		$attachments = $sDb->fetchAll($sDb->limit(
			'
				SELECT attachmentid, userid, dateline, filename, counter, postid
				FROM ' . $prefix . 'attachment
				WHERE attachmentid > ' . $sDb->quote($start) . '
					AND visible = 1
				ORDER BY attachmentid
			', $options['limit']
		));
		if (!$attachments)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIdMap = $model->getUserIdsMapFromArray($attachments, 'userid');

		$postIdMap = $model->getPostIdsMapFromArray($attachments, 'postid');
		$posts = $model->getModelFromCache('XenForo_Model_Post')->getPostsByIds($postIdMap);

		foreach ($attachments AS $attachment)
		{
			$next = $attachment['attachmentid'];

			$newPostId = $this->_mapLookUp($postIdMap, $attachment['postid']);
			if (!$newPostId)
			{
				continue;
			}

			if (!$options['path'])
			{
				$fData = $sDb->fetchOne('
					SELECT filedata
					FROM ' . $prefix . 'attachment
					WHERE attachmentid = ' . $sDb->quote($attachment['attachmentid'])
				);
				if ($fData === '')
				{
					continue;
				}

				$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				if (!$attachFile || !@file_put_contents($attachFile, $fData))
				{
					continue;
				}

				$isTemp = true;
			}
			else
			{
				$attachFileOrig = "$options[path]/" . implode('/', str_split($attachment['userid'])) . "/$attachment[attachmentid].attach";
				if (!file_exists($attachFileOrig))
				{
					continue;
				}

				$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				copy($attachFileOrig, $attachFile);

				$isTemp = true;
			}

			$success = $model->importPostAttachment(
				$attachment['attachmentid'],
				$this->_convertToUtf8($attachment['filename']),
				$attachFile,
				$this->_mapLookUp($userIdMap, $attachment['userid'], 0),
				$newPostId,
				$attachment['dateline'],
				array('view_count' => $attachment['counter']),
				array($this, 'processAttachmentTags'),
				$posts[$newPostId]['message']
			);
			if ($success)
			{
				$total++;
			}

			if ($isTemp)
			{
				@unlink($attachFile);
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public static function processAttachmentTags($oldAttachmentId, $newAttachmentId, $messageText)
	{
		if (stripos($messageText, '[ATTACH') !== false)
		{
			/**
			 * Note: We use '$newAttachmentId.vB' as the attachment id in the XenForo replacement
			 * to avoid it being replaced again when we come across an attachment whose source id
			 * is the same as this one's imported id.
			 *
			 * @var string
			 */
			$newTag = sprintf('[ATTACH]%d.vB[/ATTACH]', $newAttachmentId);

			$messageText = preg_replace("#\[ATTACH[^\]]*\]{$oldAttachmentId}\[/ATTACH\]#siU", $newTag, $messageText);
		}

		return $messageText;
	}

	public function configStepReputation(array $options)
	{
		if ($options)
		{
			return false;
		}

		return $this->_controller->responseView('XenForo_ViewAdmin_Import_vBulletin_ConfigReputation', 'import_config_likes');
	}

	public function stepReputation($start, array $options)
	{
		$options = array_merge(array(
			'fetchLikeUsers' => false,
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
				SELECT MAX(reputationid)
				FROM ' . $prefix . 'reputation
				WHERE reputation > 0
			');
		}

		$reputations = $sDb->fetchAll($sDb->limit(
			'
				SELECT *
				FROM ' . $prefix . 'reputation
				WHERE reputationid > ' . $sDb->quote($start) . '
					AND reputation > 0
				ORDER BY reputationid
			', $options['limit']
		));
		if (!$reputations)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIds = array();
		foreach ($reputations AS $rep)
		{
			$userIds[] = $rep['userid'];
			$userIds[] = $rep['whoadded'];
		}

		$postIdMap = $model->getPostIdsMapFromArray($reputations, 'postid');
		$userIdMap = $model->getImportContentMap('user', $userIds);

		XenForo_Db::beginTransaction();

		foreach ($reputations AS $rep)
		{
			$next = $rep['reputationid'];

			$newPostId = $this->_mapLookUp($postIdMap, $rep['postid']);
			if (!$newPostId)
			{
				continue;
			}

			$model->importLike(
				'post',
				$newPostId,
				$this->_mapLookUp($userIdMap, $rep['userid']),
				$this->_mapLookUp($userIdMap, $rep['whoadded']),
				$rep['dateline'],
				$options['fetchLikeUsers']
			);

			$total++;
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}

	public function stepInfractions($start, array $options)
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
				SELECT MAX(infractionid)
				FROM ' . $prefix . 'infraction
				WHERE infractionid > 0
			');
		}

		$infractions = $sDb->fetchAll($sDb->limit(
			'
				SELECT infraction.*,
					user.username AS username,
					COALESCE(thread.title, \'\') AS thread_title,
					COALESCE(phrase.text, \'\') AS title
				FROM ' . $prefix . 'infraction AS infraction
				INNER JOIN ' . $prefix . 'user AS user ON (user.userid = infraction.userid)
				LEFT JOIN ' . $prefix . 'post AS post ON (infraction.postid = post.postid)
				LEFT JOIN ' . $prefix . 'thread AS thread ON (post.threadid = thread.threadid)
				LEFT JOIN ' . $prefix . 'phrase AS phrase ON (phrase.varname = CONCAT(\'infractionlevel\', infraction.infractionlevelid, \'_title\') AND phrase.languageid = 0)
				WHERE infractionid > ' . $sDb->quote($start) . '
				ORDER BY infractionid
			', $options['limit']
		));
		if (!$infractions)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIds = array();
		foreach ($infractions AS $infraction)
		{
			$userIds[] = $infraction['userid'];
			$userIds[] = $infraction['whoadded'];
		}

		$postIdMap = $model->getPostIdsMapFromArray($infractions, 'postid');
		$userIdMap = $model->getImportContentMap('user', $userIds);

		XenForo_Db::beginTransaction();

		foreach ($infractions AS $infraction)
		{
			$next = $infraction['infractionid'];

			if ($infraction['postid'])
			{
				$newPostId = $this->_mapLookUp($postIdMap, $infraction['postid']);
				if (!$newPostId)
				{
					continue;
				}
			}
			else
			{
				$newPostId = 0;
			}

			$newUserId = $this->_mapLookUp($userIdMap, $infraction['userid']);
			if (!$newUserId)
			{
				continue;
			}

			$newWarnUserId = $this->_mapLookUp($userIdMap, $infraction['whoadded']);
			if (!$newWarnUserId)
			{
				$newWarnUserId = 0;
			}

			$import = array(
				'content_type' => $infraction['postid'] ? 'post' : 'user',
				'content_id' => $infraction['postid'] ? $newPostId : $newUserId,
				'content_title' => $this->_convertToUtf8($infraction['postid'] ? $infraction['thread_title'] : $infraction['username'], true),
				'user_id' => $newUserId,
				'warning_date' => $infraction['dateline'],
				'warning_user_id' => $newWarnUserId,
				'warning_definition_id' => 0,
				'title' => $this->_convertToUtf8($infraction['title']),
				'notes' => $this->_convertToUtf8($infraction['note']),
				'points' => $infraction['points'],
				'expiry_date' => $infraction['action'] == 2 ? $infraction['dateline'] : $infraction['expires'],
				'is_expired' => !empty($infraction['action']) ? 1 : 0,
				'extra_user_group_ids' => ''
			);

			if ($model->importWarning($infraction['infractionid'], $import))
			{
				$total++;
			}
		}

		XenForo_Db::commit();

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}
}