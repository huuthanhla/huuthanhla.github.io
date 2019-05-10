<?php

/**
 * Controller for handling the users section and actions on users in the
 * admin control panel.
 *
 * @package XenForo_Users
 */
class XenForo_ControllerAdmin_User extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		switch (strtolower($action))
		{
			case 'index':
			case 'searchname':
				break;

			default:
				$this->assertAdminPermission('user');
		}
	}

	/**
	 * Section splash page.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		/*
		 * Fetch:
		 *
		 * Total members
		 * Members awaiting approval
		 * Administrators
		 * Moderators
		 * Banned members
		 *
		 * Banned IPs
		 * Banned emails
		 */

		$userModel = $this->_getUserModel();
		$banningModel = $this->getModelFromCache('XenForo_Model_Banning');

		$visitor = XenForo_Visitor::getInstance();

		$boardTotals = $this->getModelFromCache('XenForo_Model_DataRegistry')->get('boardTotals');
		if (!$boardTotals)
		{
			$boardTotals = $this->getModelFromCache('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
		}

		$viewParams = array(
			'canManageUsers' => $visitor->hasAdminPermission('user'),
			'canManageBans' => $visitor->hasAdminPermission('ban'),
			'canManageUserGroups' => $visitor->hasAdminPermission('userGroup'),
			'canManageTrophies' => $visitor->hasAdminPermission('trophy'),
			'canManageUserUpgrades' => $visitor->hasAdminPermission('userUpgrade'),

			'users' => array(
				'total' => $boardTotals['users'],
				'awaitingApproval' => $userModel->countUsers(array('user_state' => 'moderated')),
				'admins' => $this->getModelFromCache('XenForo_Model_Admin')->countAdmins(),
				'moderators' => $this->getModelFromCache('XenForo_Model_Moderator')->countModerators(),
				'banned' => $banningModel->countBannedUsers()
			),
			'bannedIps' => $banningModel->countBannedIps(),
			'bannedEmails' => $banningModel->countBannedEmails(),
		);

		return $this->responseView('XenForo_ViewAdmin_User_Splash', 'user_splash', $viewParams);
	}

	protected function _filterUserSearchCriteria(array $criteria)
	{
		return $this->_getCriteriaHelper()->filterUserSearchCriteria($criteria);
	}

	protected function _prepareUserSearchCriteria(array $criteria)
	{
		return $this->_getCriteriaHelper()->prepareUserSearchCriteria($criteria);
	}

	/**
	 * Shows a list of users.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionList()
	{
		$criteria = $this->_input->filterSingle('criteria', XenForo_Input::JSON_ARRAY);
		$criteria = $this->_filterUserSearchCriteria($criteria);

		$filter = $this->_input->filterSingle('_filter', XenForo_Input::ARRAY_SIMPLE);
		if ($filter && isset($filter['value']))
		{
			$criteria['username2'] = array($filter['value'], empty($filter['prefix']) ? 'lr' : 'r');
			$filterView = true;
		}
		else
		{
			$filterView = false;
		}

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING);
		$direction = $this->_input->filterSingle('direction', XenForo_Input::STRING);

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$usersPerPage = 20;

		$showingAll = $this->_input->filterSingle('all', XenForo_Input::UINT);
		if ($showingAll)
		{
			$page = 1;
			$usersPerPage = 5000;
		}

		$fetchOptions = array(
			'perPage' => $usersPerPage,
			'page' => $page,

			'order' => $order,
			'direction' => $direction
		);

		$userModel = $this->_getUserModel();

		$criteriaPrepared = $this->_prepareUserSearchCriteria($criteria);

		$totalUsers = $userModel->countUsers($criteriaPrepared);
		if (!$totalUsers)
		{
			return $this->responseError(new XenForo_Phrase('no_users_matched_specified_criteria'));
		}

		$users = $userModel->getUsers($criteriaPrepared, $fetchOptions);

		if ($totalUsers == 1 && ($user = reset($users)) && !$filterView)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('users/edit', $user)
			);
		}

		// TODO: show more structured info: username, email, last activity, messages?

		$viewParams = array(
			'users' => $users,
			'totalUsers' => $totalUsers,
			'showingAll' => $showingAll,
			'showAll' => (!$showingAll && $totalUsers <= 5000),

			'linkParams' => array('criteria' => $criteria, 'order' => $order, 'direction' => $direction),
			'page' => $page,
			'usersPerPage' => $usersPerPage,

			'filterView' => $filterView,
			'filterMore' => ($filterView && $totalUsers > $usersPerPage)
		);

		return $this->responseView('XenForo_ViewAdmin_User_List', 'user_list', $viewParams);
	}

	/**
	 * Search for users form.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSearch()
	{
		$viewParams = array(
			'lastUser' => $this->_getUserModel()->getUserById($this->_input->filterSingle('last_user_id', XenForo_Input::UINT)),
		);

		$criteriaHelper = $this->_getCriteriaHelper();
		$viewParams += $criteriaHelper->getDataForUserSearchForm();
		$viewParams['criteria'] = $criteriaHelper->getDefaultUserSearchCriteria();

		return $this->responseView('XenForo_ViewAdmin_User_Search', 'user_search', $viewParams);
	}

	public function actionBatchUpdate()
	{
		if ($this->isConfirmedPost())
		{
			$criteria = $this->_input->filterSingle('criteria', XenForo_Input::JSON_ARRAY);
			$criteria = $this->_filterUserSearchCriteria($criteria);
			$criteriaPrepared = $this->_prepareUserSearchCriteria($criteria);

			$userModel = $this->_getUserModel();

			$userIds = $this->_input->filterSingle('user_ids', XenForo_Input::JSON_ARRAY);

			$totalUsers = $userIds ? count($userIds) : $userModel->countUsers($criteriaPrepared);
			if (!$totalUsers)
			{
				return $this->responseError(new XenForo_Phrase('no_users_matched_specified_criteria'));
			}

			$actions = $this->_input->filterSingle('actions', XenForo_Input::ARRAY_SIMPLE);

			if ($this->_input->filterSingle('confirmUpdate', XenForo_Input::UINT) && $actions)
			{
				$defer = array(
					'actions' => $actions,
					'total' => $totalUsers
				);

				if ($userIds)
				{
					$defer['userIds'] = $userIds;
				}
				else if ($totalUsers > 10000)
				{
					$defer['criteria'] = $criteriaPrepared;
				}
				else
				{
					$defer['userIds'] = $userModel->getUserIds($criteriaPrepared);
				}

				XenForo_Application::defer('UserAction', $defer, null, true);

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('tools/run-deferred', null, array(
						'redirect' => XenForo_Link::buildAdminLink('users/batch-update', null, array('success' => 1))
					))
				);
			}
			else
			{
				return $this->responseView('XenForo_ViewAdmin_User_BatchUpdateConfirm', 'user_batch_update_confirm', array(
					'criteria' => $criteria,
					'userIds' => $userIds,
					'totalUsers' => $totalUsers,
					'linkParams' => array('criteria' => $criteria, 'order' => 'username', 'direction' => 'asc'),
					'userGroups' => $this->_getUserGroupModel()->getAllUserGroupTitles(),
				));
			}
		}
		else
		{
			$viewParams = array(
				'success' => $this->_input->filterSingle('success', XenForo_Input::UINT)
			);

			$criteriaHelper = $this->_getCriteriaHelper();
			$viewParams += $criteriaHelper->getDataForUserSearchForm();
			$viewParams['criteria'] = $criteriaHelper->getDefaultUserSearchCriteria();

			return $this->responseView('XenForo_ViewAdmin_User_BatchUpdateSearch', 'user_batch_update_search', $viewParams);
		}
	}

	/**
	 * Searches for a user by the left-most prefix of a name (for auto-complete(.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSearchName()
	{
		$q = ltrim($this->_input->filterSingle('q', XenForo_Input::STRING, array('noTrim' => true)));

		if ($q !== '')
		{
			$users = $this->_getUserModel()->getUsers(
				array('username' => array($q , 'r')),
				array('limit' => 10)
			);
		}
		else
		{
			$users = array();
		}

		$viewParams = array(
			'users' => $users
		);

		return $this->responseView(
			'XenForo_ViewAdmin_User_SearchName',
			'',
			$viewParams
		);
	}

	protected function _getUserAddEditResponse(array $user)
	{
		$userModel = $this->_getUserModel();

		if ($user['user_id'])
		{
			$user['is_super_admin'] = $this->_getUserModel()->isUserSuperAdmin($user);
		}
		else
		{
			$user['is_super_admin'] = false;
		}

		$fieldModel = $this->_getFieldModel();
		$customFields = $fieldModel->prepareUserFields($fieldModel->getUserFields(
			array(),
			array('valueUserId' => $user['user_id'])
		), true);

		$viewParams = array(
			'user' => $user,
			'timeZones'	=> XenForo_Helper_TimeZone::getTimeZones(),
			'userGroups' => $this->_getUserGroupModel()->getAllUserGroupTitles(),
			'customFieldsGrouped' => $fieldModel->groupUserFields($customFields),
			'styles' => $this->getModelFromCache('XenForo_Model_Style')->getStylesForOptionsTag($user['style_id']),
			'languages' => $this->getModelFromCache('XenForo_Model_Language')->getLanguagesForOptionsTag($user['language_id']),
			'lastHash' => $this->getLastHash($user['user_id'])
		);

		return $this->responseView('XenForo_ViewAdmin_User_Edit', 'user_edit', $viewParams);
	}

	/**
	 * Form to add a user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$user = array(
			'user_id' => 0,
			'timezone' => XenForo_Application::get('options')->guestTimeZone,
			'user_group_id' => XenForo_Model_User::$defaultRegisteredGroupId,
			'style_id' => 0,
			'language_id' => XenForo_Application::get('options')->defaultLanguageId,
			'user_state' => 'valid',
			'enable_rte' => 1,
			'enable_flash_uploader' => 1,
			'message_count' => 0,
			'like_count' => 0,
			'trophy_points' => 0
		);
		$user = array_merge($user, XenForo_Application::get('options')->registrationDefaults);

		return $this->_getUserAddEditResponse($user);
	}

	/**
	 * Form to edit an existing user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		$this->getHelper('Admin')->checkSuperAdminEdit($user);

		return $this->_getUserAddEditResponse($user);
	}

	/**
	 * Validate a single field
	 *
	 * @return XenForo_ControllerResponse_View|XenForo_ControllerResponse_Error
	 */
	public function actionValidateField()
	{
		$this->_assertPostOnly();

		$field = $this->_getFieldValidationInputParams();
		$existingKey = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		if (preg_match('/^custom_field_([a-zA-Z0-9_]+)$/', $field['name'], $match))
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
			if ($existingKey)
			{
				$writer->setExistingData($existingKey);
			}
			$writer->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, true);

			$writer->setCustomFields(array($match[1] => $field['value']));

			if ($errors = $writer->getErrors())
			{
				return $this->responseError($errors);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				'',
				new XenForo_Phrase('redirect_field_validated', array('name' => $field['name'], 'value' => $field['value']))
			);
		}
		else
		{
			// handle normal fields
			return $this->_validateField(
				'XenForo_DataWriter_User',
				array('existingDataKey' => $existingKey),
				array(XenForo_DataWriter_User::OPTION_ADMIN_EDIT => true)
			);
		}
	}

	/**
	 * Inserts a new user or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		if ($userId)
		{
			$user = $this->_getUserOrError($userId);
			$this->getHelper('Admin')->checkSuperAdminEdit($user);

			if ($this->_getUserModel()->isUserSuperAdmin($user))
			{
				$visitorPassword = $this->_input->filterSingle('visitor_password', XenForo_Input::STRING);
				$this->getHelper('Admin')->assertVisitorPasswordCorrect($visitorPassword);
			}
		}

		$userInput = $this->_input->filter(array(

			// essentials
			'username'               => XenForo_Input::STRING,
			'email'                  => XenForo_Input::STRING,

			'user_group_id'          => XenForo_Input::UINT,
			'user_state'             => XenForo_Input::STRING,
			'is_discouraged'         => XenForo_Input::UINT,
			'is_staff'               => XenForo_Input::UINT,

			// personal details
			'gender'                 => XenForo_Input::STRING,
			'dob_day'                => XenForo_Input::UINT,
			'dob_month'              => XenForo_Input::UINT,
			'dob_year'               => XenForo_Input::UINT,
			'location'               => XenForo_Input::STRING,
			'occupation'             => XenForo_Input::STRING,

			// profile info
			'custom_title'           => XenForo_Input::STRING,
			'homepage'               => XenForo_Input::STRING,
			'about'                  => XenForo_Input::STRING,
			'signature'              => XenForo_Input::STRING,

			'message_count'          => XenForo_Input::UINT,
			'like_count'             => XenForo_Input::UINT,
			'trophy_points'          => XenForo_Input::UINT,

			// preferences
			'style_id'               => XenForo_Input::UINT,
			'language_id'            => XenForo_Input::UINT,
			'timezone'               => XenForo_Input::STRING,
			'content_show_signature' => XenForo_Input::UINT,
			'enable_rte'             => XenForo_Input::UINT,
			'enable_flash_uploader'  => XenForo_Input::UINT,
			'email_on_conversation'  => XenForo_Input::UINT,
			'default_watch_state'    => XenForo_Input::STRING,

			// privacy
			'visible'                 => XenForo_Input::UINT,
			'activity_visible'        => XenForo_Input::UINT,
			'receive_admin_email'     => XenForo_Input::UINT,
			'show_dob_date'           => XenForo_Input::UINT,
			'show_dob_year'           => XenForo_Input::UINT,
			'allow_view_profile'      => XenForo_Input::STRING,
			'allow_post_profile'      => XenForo_Input::STRING,
			'allow_send_personal_conversation' => XenForo_Input::STRING,
			'allow_view_identities'   => XenForo_Input::STRING,
			'allow_receive_news_feed' => XenForo_Input::STRING,
		));

		$secondaryGroupIds = $this->_input->filterSingle('secondary_group_ids', XenForo_Input::UINT, array('array' => true));

		$userInput['about'] = XenForo_Helper_String::autoLinkBbCode($userInput['about']);

		if ($this->_input->filterSingle('clear_status', XenForo_Input::UINT))
		{
			//TODO: clear status
		}

		/* @var $writer XenForo_DataWriter_User */
		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		if ($userId)
		{
			$writer->setExistingData($userId);
		}
		$writer->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, true);

		$writer->bulkSet($userInput);
		$writer->setSecondaryGroups($secondaryGroupIds);

		$password = $this->_input->filterSingle('password', XenForo_Input::STRING);
		if ($password !== '')
		{
			$writer->setPassword($password, false, null, true);
		}

		$customFields = $this->_input->filterSingle('custom_fields', XenForo_Input::ARRAY_SIMPLE);
		$customFieldsShown = $this->_input->filterSingle('custom_fields_shown', XenForo_Input::STRING, array('array' => true));
		$writer->setCustomFields($customFields, $customFieldsShown);

		$writer->save();

		$userId = $writer->get('user_id');

		// TODO: redirect to previous search if possible?

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('users/search', null, array('last_user_id' => $userId)) . $this->getLastHash($userId)
		);
	}

	/**
	 * Displays a form to edit a user's avatar.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAvatar()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		$this->getHelper('Admin')->checkSuperAdminEdit($user);

		$viewParams = array(
			'user' => $user,
		);

		return $this->responseView('XenForo_ViewAdmin_User_Avatar', 'user_avatar', $viewParams);
	}

	/**
	 * Updates a user's avatar.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAvatarUpload()
	{
		$this->_assertPostOnly();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		$this->getHelper('Admin')->checkSuperAdminEdit($user);

		$avatars = XenForo_Upload::getUploadedFiles('avatar');
		$avatar = reset($avatars);

		/* @var $avatarModel XenForo_Model_Avatar */
		$avatarModel = $this->getModelFromCache('XenForo_Model_Avatar');

		if ($avatar)
		{
			$avatarModel->uploadAvatar($avatar, $user['user_id'], false);
		}
		else if ($this->_input->filterSingle('delete', XenForo_Input::UINT))
		{
			$avatarModel->deleteAvatar($user['user_id']);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('users/edit', $user)
		);
	}

	/**
	 * Deletes the specified user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_EXCEPTION);
		$writer->setExistingData($user);
		$writer->preDelete();

		$this->getHelper('Admin')->checkSuperAdminEdit($user);

		if ($this->isConfirmedPost())
		{
			if ($this->_getUserModel()->isUserSuperAdmin($user))
			{
				$visitorPassword = $this->_input->filterSingle('visitor_password', XenForo_Input::STRING);
				$this->getHelper('Admin')->assertVisitorPasswordCorrect($visitorPassword);
			}

			return $this->_deleteData(
				'XenForo_DataWriter_User', 'user_id',
				XenForo_Link::buildAdminLink('users')
			);
		}
		else // show confirmation dialog
		{
			$user['is_super_admin'] = $this->_getUserModel()->isUserSuperAdmin($user);

			$viewParams = array(
				'user' => $user
			);

			return $this->responseView('XenForo_ViewAdmin_User_Delete',
				'user_delete', $viewParams
			);
		}
	}

	public function actionMerge()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		if ($user['is_admin'] || $user['is_moderator'])
		{
			return $this->responseNoPermission();
		}

		if ($this->isConfirmedPost())
		{
			$mergeUserName = $this->_input->filterSingle('username', XenForo_Input::STRING);
			$mergeUser = $this->_getUserModel()->getUserByName($mergeUserName);
			if ($mergeUser)
			{
				$this->_getUserModel()->mergeUsers($mergeUser, $user);

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('users/edit', $mergeUser)
				);
			}
		}

		return $this->responseView('XenForo_ViewAdmin_User_Merge', 'user_merge', array(
			'user' => $user
		));
	}

	public function actionDeleteConversations()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		if ($user['is_admin'] || $user['is_moderator'])
		{
			return $this->responseNoPermission();
		}

		if ($this->isConfirmedPost())
		{
			/** @var $conversationModel XenForo_Model_Conversation */
			$conversationModel = $this->getModelFromCache('XenForo_Model_Conversation');
			$conversationModel->deleteConversationsStartedByUser($user['user_id']);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('users/edit', $user)
			);
		}

		return $this->responseView('XenForo_ViewAdmin_User_ConversationDelete', 'user_conversation_delete', array(
			'user' => $user
		));
	}

	public function actionRevertMessageEdit()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		$options = XenForo_Application::getOptions();

		if ($this->_getUserModel()->isUserSuperAdmin($user) || !$options->editHistory['enabled'])
		{
			return $this->responseNoPermission();
		}

		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'cutoff_value' => XenForo_Input::UINT,
				'cutoff_unit' => XenForo_Input::STRING
			));
			$cutOff = strtotime('-' . $input['cutoff_value'] . ' ' . $input['cutoff_unit']);
			if ($options->editHistory['length'])
			{
				$cutOff = max($cutOff, XenForo_Application::$time - $options->editHistory['length'] * 86400);
			}

			$cutOff = max(0, $cutOff);

			$defer = array('userId' => $user['user_id'], 'cutOff' => $cutOff);
			XenForo_Application::defer('UserRevertMessageEdit', $defer, null, true);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('users/edit', $user)
			);
		}

		return $this->responseView('XenForo_ViewAdmin_User_RevertMessageEdit', 'user_revert_message_edit', array(
			'user' => $user
		));
	}

	public function actionRemoveLikes()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		if ($this->_getUserModel()->isUserSuperAdmin($user))
		{
			return $this->responseNoPermission();
		}

		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'cutoff_value' => XenForo_Input::UINT,
				'cutoff_unit' => XenForo_Input::STRING
			));
			$cutOff = strtotime('-' . $input['cutoff_value'] . ' ' . $input['cutoff_unit']);

			$cutOff = max(0, $cutOff);

			$defer = array('userId' => $user['user_id'], 'cutOff' => $cutOff);
			XenForo_Application::defer('UserRemoveLikes', $defer, null, true);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('users/edit', $user)
			);
		}

		return $this->responseView('XenForo_ViewAdmin_User_RemoveLikes', 'user_remove_likes', array(
			'user' => $user
		));
	}

	public function actionManageWatchedThreads()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		if ($this->isConfirmedPost())
		{
			$action = $this->_input->filterSingle('act', XenForo_Input::STRING);

			/** @var XenForo_Model_ThreadWatch $threadWatchModel */
			$threadWatchModel = $this->getModelFromCache('XenForo_Model_ThreadWatch');
			$threadWatchModel->setThreadWatchStateForAll($user['user_id'], $action);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('users/edit', $user)
			);
		}
		else
		{
			return $this->responseView('XenForo_ViewAdmin_User_ManageWatchedThreads', 'user_manage_watched_threads', array(
				'user' => $user
			));
		}
	}

	public function actionExtra()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		/** @var XenForo_Model_UserUpgrade $upgradeModel */
		$upgradeModel = $this->getModelFromCache('XenForo_Model_UserUpgrade');
		$upgradeRecords = $upgradeModel->getActiveUserUpgradeRecordsForUser($user['user_id']);

		/** @var XenForo_Model_UserExternal $externalAuthModel */
		$externalAuthModel = $this->getModelFromCache('XenForo_Model_UserExternal');
		$external = $externalAuthModel->getExternalAuthAssociationsForUser($user['user_id']);

		$fbUser = false;
		if (!empty($external['facebook']))
		{
			$extra = @unserialize($external['facebook']['extra_data']);
			if (!empty($extra['token']))
			{
				$fbUser = XenForo_Helper_Facebook::getUserInfo($extra['token'], $external['facebook']['provider_key']);
			}
		}

		$twitterUser = false;
		if (!empty($external['twitter']))
		{
			$extra = @unserialize($external['twitter']['extra_data']);
			if (!empty($extra['token']))
			{
				$twitterUser = XenForo_Helper_Twitter::getUserFromToken($extra['token'], $extra['secret']);
			}
		}

		return $this->responseView('XenForo_ViewAdmin_User_Extra', 'user_extra', array(
			'user' => $user,
			'upgradeRecords' => $upgradeRecords,
			'external' => $external,
			'fbUser' => $fbUser,
			'twitterUser' => $twitterUser
		));
	}

	/**
	 * Shows a list of moderated users and allows them to be managed.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionModerated()
	{
		$users = $this->_getUserModel()->getUsers(array(
			'user_state' => 'moderated'
		), array('limit' => 30));

		$class = XenForo_Application::resolveDynamicClass('XenForo_Session');
		/** @var $publicSession XenForo_Session */
		$publicSession = new $class();
		$publicSession->start();
		if ($publicSession->get('user_id') == XenForo_Visitor::getUserId())
		{
			$sessionCounts = $publicSession->get('userModerationCounts');
			if (!is_array($sessionCounts) || $sessionCounts['total'] != count($users))
			{
				$publicSession->remove('userModerationCounts');
				$publicSession->save();

				$this->getModelFromCache('XenForo_Model_User')->rebuildUserModerationQueueCache();
			}
		}

		if (!$users)
		{
			return $this->responseMessage(new XenForo_Phrase('no_users_awaiting_approval'));
		}

		/** @var XenForo_Model_SpamPrevention $spamPreventionModel */
		$spamPreventionModel = $this->getModelFromCache('XenForo_Model_SpamPrevention');

		$spamLogs = $spamPreventionModel->getSpamTriggerLogsByContentIds('user', array_keys($users));
		$spamLogs = $spamPreventionModel->prepareSpamTriggerLogs($spamLogs);

		foreach ($users AS &$user)
		{
			$ips = $this->_getUserModel()->getRegistrationIps($user['user_id']);
			$user['ip'] = $ips ? reset($ips) : false;
			//$user['ipHost'] = $user['ip'] ? XenForo_Model_Ip::getHost($user['ip']) : false;
			$user['ipHost'] = false;

			if (isset($spamLogs[$user['user_id']]))
			{
				$user['spamDetails'] = $spamLogs[$user['user_id']]['detailsPrintable'];
			}
			else
			{
				$user['spamDetails'] = false;
			}
		}

		$viewParams = array(
			'users' => $users
		);

		return $this->responseView('XenForo_ViewAdmin_User_Moderated', 'user_moderated', $viewParams);
	}

	/**
	 * Processes moderated users.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionModeratedUpdate()
	{
		$this->_assertPostOnly();

		$usersInput = $this->_input->filterSingle('users', XenForo_Input::ARRAY_SIMPLE);
		$users = $this->_getUserModel()->getUsersByIds(array_keys($usersInput));

		foreach ($users AS $user)
		{
			if (!isset($usersInput[$user['user_id']]))
			{
				continue;
			}

			$userControl = $usersInput[$user['user_id']];
			if (empty($userControl['action']) || $userControl['action'] == 'none')
			{
				continue;
			}

			$notify = (!empty($userControl['notify']) ? true : false);
			$rejectionReason = (!empty($userControl['reject_reason']) ? $userControl['reject_reason'] : '');

			$this->getModelFromCache('XenForo_Model_UserConfirmation')->processUserModeration(
				$user, $userControl['action'], $notify, $rejectionReason
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('users/moderated')
		);
	}

	/**
	 * Displays the form to setup the email, or to confirm sending of it.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEmail()
	{
		if ($this->isConfirmedPost())
		{
			$criteria = $this->_input->filterSingle('criteria', XenForo_Input::JSON_ARRAY);
			$criteria = $this->_filterUserSearchCriteria($criteria);
			$criteriaPrepared = $this->_prepareUserSearchCriteria($criteria);

			if ($this->_input->filterSingle('list_only', XenForo_Input::UINT))
			{
				$users = $this->_getUserModel()->getUsers($criteriaPrepared);
				if (!$users)
				{
					return $this->responseError(new XenForo_Phrase('no_users_matched_specified_criteria'));
				}

				$viewParams = array(
					'users' => $users
				);

				return $this->responseView('XenForo_ViewAdmin_User_EmailList', 'user_email_list', $viewParams);
			}

			$email = $this->_input->filter(array(
				'from_name' => XenForo_Input::STRING,
				'from_email' => XenForo_Input::STRING,

				'email_title' => XenForo_Input::STRING,
				'email_format' => XenForo_Input::STRING,
				'email_body' => XenForo_Input::STRING
			));

			if (!$email['from_name'] || !$email['from_email'] || !$email['email_title'] || !$email['email_body'])
			{
				return $this->responseError(new XenForo_Phrase('please_complete_required_fields'));
			}

			$total = $this->_getUserModel()->countUsers($criteriaPrepared);
			if (!$total)
			{
				return $this->responseError(new XenForo_Phrase('no_users_matched_specified_criteria'));
			}

			$viewParams = array(
				'test' => $this->_input->filterSingle('test', XenForo_Input::STRING),
				'total' => $total,
				'criteria' => $criteria,
				'email' => $email
			);

			return $this->responseView('XenForo_ViewAdmin_User_EmailConfirm', 'user_email_confirm', $viewParams);
		}
		else
		{
			$viewParams = array(
				'sent' => $this->_input->filterSingle('sent', XenForo_Input::UINT),
				'failed' => $this->_input->filterSingle('failed', XenForo_Input::UINT)
			);

			$criteriaHelper = $this->_getCriteriaHelper();
			$viewParams += $criteriaHelper->getDataForUserSearchForm();
			$viewParams['criteria'] = $criteriaHelper->getDefaultUserSearchCriteria();
			$viewParams['criteria']['user_state'] = array('valid' => true);
			$viewParams['criteria']['is_banned'] = array(0 => true);


			return $this->responseView('XenForo_ViewAdmin_User_Email', 'user_email', $viewParams);
		}
	}

	/**
	 * Sends the specified email.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEmailSend()
	{
		$this->_assertPostOnly();

		$criteria = $this->_input->filterSingle('criteria', XenForo_Input::JSON_ARRAY);
		$criteria = $this->_filterUserSearchCriteria($criteria);
		$criteriaPrepared = $this->_prepareUserSearchCriteria($criteria);

		$email = $this->_input->filter(array(
			'from_name' => XenForo_Input::STRING,
			'from_email' => XenForo_Input::STRING,

			'email_title' => XenForo_Input::STRING,
			'email_format' => XenForo_Input::STRING,
			'email_body' => XenForo_Input::STRING
		));

		$total = $this->_input->filterSingle('total', XenForo_Input::UINT);
		$failed = $this->_input->filterSingle('failed', XenForo_Input::UINT);

		$transport = XenForo_Mail::getTransport();

		if ($this->_input->filterSingle('test', XenForo_Input::STRING))
		{
			$this->_sendEmail(XenForo_Visitor::getInstance()->toArray(), $email, $transport);

			return $this->responseReroute(__CLASS__, 'email');
		}

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$perPage = 30;

		$users = $this->_getUserModel()->getUsers($criteriaPrepared, array(
			'page' => $page,
			'perPage' => $perPage
		));
		if (!$users)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('users/email', false, array(
					'sent' => $total,
					'failed' => $failed
				))
			);
		}
		else
		{
			foreach ($users AS $user)
			{
				if (!$this->_sendEmail($user, $email, $transport))
				{
					$failed++;
				}
			}

			$viewParams = array(
				'total' => $total,
				'failed' => $failed,
				'completed' => ($page - 1) * $perPage + count($users),
				'nextPage' => $page + 1,

				'criteria' => $criteria,
				'email' => $email
			);
			return $this->responseView('XenForo_ViewAdmin_User_Email_Send', 'user_email_send', $viewParams);
		}
	}

	protected function _sendEmail(array $user, array $email, Zend_Mail_Transport_Abstract $transport)
	{
		if (!$user['email'])
		{
			return false;
		}

		if (!XenForo_Application::get('config')->enableMail)
		{
			return true;
		}

		$options = XenForo_Application::getOptions();

		XenForo_Db::ping();

		$mailObj = new Zend_Mail('utf-8');
		$mailObj->setSubject($email['email_title'])
			->addTo($user['email'], $user['username'])
			->setFrom($email['from_email'], $email['from_name']);

		$bounceEmailAddress = $options->bounceEmailAddress;
		if (!$bounceEmailAddress)
		{
			$bounceEmailAddress = $options->defaultEmailAddress;
		}

		$toEmail = $user['email'];
		$bounceHmac = substr(hash_hmac('md5', $toEmail, XenForo_Application::getConfig()->globalSalt), 0, 8);

		$mailObj->addHeader('X-To-Validate', "$bounceHmac+$toEmail");

		if ($options->enableVerp)
		{
			$verpValue = str_replace('@', '=', $toEmail);
			$bounceEmailAddress = str_replace('@', "+$bounceHmac+$verpValue@", $bounceEmailAddress);
		}
		$mailObj->setReturnPath($bounceEmailAddress);

		if ($email['email_format'] == 'html')
		{
			$replacements = array(
				'{name}' => htmlspecialchars($user['username']),
				'{email}' => htmlspecialchars($user['email']),
				'{id}' => $user['user_id']
			);
			$email['email_body'] = strtr($email['email_body'], $replacements);

			$text = trim(
				htmlspecialchars_decode(strip_tags($email['email_body']))
			);

			$mailObj->setBodyHtml($email['email_body'])
				->setBodyText($text);
		}
		else
		{
			$replacements = array(
				'{name}' => $user['username'],
				'{email}' => $user['email'],
				'{id}' => $user['user_id']
			);
			$email['email_body'] = strtr($email['email_body'], $replacements);

			$mailObj->setBodyText($email['email_body']);
		}

		if (!$mailObj->getMessageId())
		{
			$mailObj->setMessageId();
		}

		$thisTransport = XenForo_Mail::getFinalTransportForMail($mailObj, $transport);

		try
		{
			$mailObj->send($thisTransport);
		}
		catch (Exception $e)
		{
			XenForo_Error::logException($e, false, "Email to $user[email] failed: ");
			return false;
		}

		return true;
	}

	/**
	 * Lists all IPs logged for the specified user
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUserIps()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		if ($ips = $this->_getIpModel()->getIpsByUserId($userId))
		{
			$viewParams = array(
				'user' => $user,
				'ips' => $ips
			);

			return $this->responseView('XenForo_ViewAdmin_User_UserIps', 'user_ips', $viewParams);
		}
		else
		{
			return $this->responseMessage(new XenForo_Phrase('no_ip_logs_for_requested_user'));
		}
	}

	public function actionChangeLog()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserOrError($userId);

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;

		$conditions = array('user_id' => $userId);

		/** @var XenForo_Model_UserChangeLog $userChangeModel */
		$userChangeModel = $this->getModelFromCache('XenForo_Model_UserChangeLog');

		$logs = $userChangeModel->getChangeLogs($conditions, array(
			'page' => $page,
			'perPage' => $perPage
		));

		$viewParams = array(
			'logs' => $logs,
			'user' => $user,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $userChangeModel->countChangeLogs($conditions),
		);

		return $this->responseView('XenForo_ViewAdmin_User_ChangeLog_User', 'user_change_log_user', $viewParams);
	}

	/**
	 * Lists all users logged from the specified IP
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIpUsers()
	{
		$ip = $this->_input->filterSingle('ip', XenForo_Input::STRING);

		$fetchOptions = array(
			'join' => XenForo_Model_User::FETCH_USER_PROFILE
		);

		$ipDetails = XenForo_Helper_Ip::parseIpRangeString($ip);
		if (!$ipDetails)
		{
			return $this->responseMessage(new XenForo_Phrase('please_enter_valid_ip_or_ip_range'));
		}
		else if ($ipDetails['isRange'])
		{
			$users = $this->_getUserModel()->getUsersByIpRange(
				$ipDetails['startRange'], $ipDetails['endRange'], $fetchOptions
			);
		}
		else
		{
			$users = $this->_getUserModel()->getUsersByIp(
				$ip, $fetchOptions
			);
		}

		if ($users)
		{
			$viewParams = array(
				'users' => $users,
				'ip' => $ip,
				'ipPrintable' => $ipDetails['printable']
			);

			return $this->responseView('XenForo_ViewAdmin_Users_IpUsers', 'ip_users', $viewParams);
		}
		else
		{
			return $this->responseMessage(new XenForo_Phrase('no_users_logged_at_ip'));
		}
	}

	/**
	 * Gets the specified user or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getUserOrError($id)
	{
		$userModel = $this->_getUserModel();

		return $this->getRecordOrError(
			$id, $userModel, 'getFullUserById',
			'requested_user_not_found'
		);
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenForo_Model_UserField
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserField');
	}

	/**
	 * @return XenForo_Model_UserGroup
	 */
	protected function _getUserGroupModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroup');
	}

	/**
	 * @return XenForo_Model_Ip
	 */
	protected function _getIpModel()
	{
		return $this->getModelFromCache('XenForo_Model_Ip');
	}

	/**
	 * @return XenForo_ControllerHelper_UserCriteria
	 */
	protected function _getCriteriaHelper()
	{
		return $this->getHelper('UserCriteria');
	}
}