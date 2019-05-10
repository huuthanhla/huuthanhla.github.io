<?php

class XenForo_ControllerPublic_Member extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		if ($userId)
		{
			return $this->responseReroute(__CLASS__, 'member');
		}
		else if ($this->_input->inRequest('user_id'))
		{
			return $this->responseError(new XenForo_Phrase('posted_by_guest_no_profile'));
		}

		if (!$this->_getUserModel()->canViewMemberList())
		{
			return $this->responseNoPermission();
		}

		$userModel = $this->_getUserModel();

		$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
		if ($username !== '')
		{
			$user = $userModel->getUserByName($username);
			if ($user)
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildPublicLink('members', $user)
				);
			}
			else
			{
				$userNotFound = true;
			}
		}
		else
		{
			$userNotFound = false;
		}

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('members')
		);

		$limit = XenForo_Application::get('options')->membersPerPage;
		$type = $this->_input->filterSingle('type', XenForo_Input::STRING);

		$bigKey = '';

		if ($type == 'staff')
		{
			$users = $userModel->getUsers(array('is_staff' => true), array(
				'join' => XenForo_Model_User::FETCH_USER_FULL,
				'order' => 'username'
			));
		}
		else
		{
			$result = $this->_getNotableMembers($type, $limit);
			if (!$result)
			{
				$type = 'messages';
				$result = $this->_getNotableMembers($type, $limit);
			}

			list($users, $bigKey) = $result;
		}

		$visitor = XenForo_Visitor::getInstance();
		$dt = new DateTime('now', new DateTimeZone($visitor['timezone']));

		list($month, $day) = explode('/', $dt->format('n/j'));

		$memberCriteria = array(
			'user_state' => 'valid',
			'is_banned' => 0,
			'active_recently' => true
		);

		$birthdays = $userModel->getBirthdayUsers($month, $day, $memberCriteria, array('limit' => 12));
		$latestUsers = $userModel->getLatestUsers($memberCriteria, array('limit' => 12));

		$viewParams = array(
			'userNotFound' => $userNotFound,
			'users' => $users,
			'type' => $type,
			'bigKey' => $bigKey,
			'birthdays' => $birthdays,
			'latestUsers' => $latestUsers
		);

		return $this->responseView('XenForo_ViewPublic_Member_Notable', 'member_notable', $viewParams);
	}

	protected function _getNotableMembers($type, $limit)
	{
		$userModel = $this->_getUserModel();

		$notableCriteria = array(
			'is_banned' => 0
		);
		$typeMap = array(
			'messages' => 'message_count',
			'likes' => 'like_count',
			'points' => 'trophy_points'
		);

		if (!isset($typeMap[$type]))
		{
			return false;
		}

		$field = $typeMap[$type];

		$notableCriteria[$field] = array('>', 0);

		return array($userModel->getUsers($notableCriteria, array(
			'join' => XenForo_Model_User::FETCH_USER_FULL,
			'limit' => $limit,
			'order' => $field,
			'direction' => 'desc'
		)), $typeMap[$type]);
	}

	/**
	 * Member list
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionList()
	{
		if (!XenForo_Application::getOptions()->enableMemberList)
		{
			return $this->responseNoPermission();
		}
		if (!$this->_getUserModel()->canViewMemberList())
		{
			return $this->responseNoPermission();
		}

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('members/list')
		);

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$usersPerPage = XenForo_Application::get('options')->membersPerPage;

		$criteria = array(
			'user_state' => 'valid',
			'is_banned' => 0
		);

		$userModel = $this->_getUserModel();

		$totalUsers = $userModel->countUsers($criteria);

		$this->canonicalizePageNumber($page, $usersPerPage, $totalUsers, 'members/list');

		// users for the member list
		$users = $userModel->getUsers($criteria, array(
			'join' => XenForo_Model_User::FETCH_USER_FULL,
			'perPage' => $usersPerPage,
			'page' => $page
		));

		// most recent registrations
		$latestUsers = $userModel->getLatestUsers($criteria, array('limit' => 8));

		// most active users (highest post count)
		$activeUsers = $userModel->getMostActiveUsers($criteria, array('limit' => 12));

		$viewParams = array(
			'users' => $users,

			'totalUsers' => $totalUsers,
			'page' => $page,
			'usersPerPage' => $usersPerPage,

			'latestUsers' => $latestUsers,
			'activeUsers' => $activeUsers
		);

		return $this->responseView('XenForo_ViewPublic_Member_List', 'member_list', $viewParams);
	}

	/**
	 * Member profile page
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionMember()
	{
		if ($this->_input->filterSingle('card', XenForo_Input::UINT))
		{
			return $this->responseReroute(__CLASS__, 'card');
		}

		$visitor = XenForo_Visitor::getInstance();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$userFetchOptions = array(
			'join' => XenForo_Model_User::FETCH_LAST_ACTIVITY | XenForo_Model_User::FETCH_USER_PERMISSIONS
		);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId, $userFetchOptions);

		// get last activity details
		$user['activity'] = ($user['view_date'] ? $this->getModelFromCache('XenForo_Model_Session')->getSessionActivityDetails($user) : false);

		$userModel = $this->_getUserModel();
		$userProfileModel = $this->_getUserProfileModel();

		// profile posts
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$profilePostsPerPage = XenForo_Application::get('options')->messagesPerPage;

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('members', $user, array('page' => $page))
		);

		$profilePostModel = $this->_getProfilePostModel();

		if ($userProfileModel->canViewProfilePosts($user))
		{
			$profilePostConditions = $profilePostModel->getPermissionBasedProfilePostConditions($user);
			$profilePostFetchOptions = array(
				'join' => XenForo_Model_ProfilePost::FETCH_USER_POSTER,
				'likeUserId' => XenForo_Visitor::getUserId(),
				'perPage' => $profilePostsPerPage,
				'page' => $page
			);
			if (!empty($profilePostConditions['deleted']))
			{
				$profilePostFetchOptions['join'] |= XenForo_Model_ProfilePost::FETCH_DELETION_LOG;
			}

			$totalProfilePosts = $profilePostModel->countProfilePostsForUserId($userId, $profilePostConditions);

			$profilePosts = $profilePostModel->getProfilePostsForUserId($userId, $profilePostConditions, $profilePostFetchOptions);
			$profilePosts = $profilePostModel->prepareProfilePosts($profilePosts, $user);
			$inlineModOptions = $profilePostModel->addInlineModOptionToProfilePosts($profilePosts, $user);

			$ignoredNames = $this->_getIgnoredContentUserNames($profilePosts);

			$profilePosts = $profilePostModel->addProfilePostCommentsToProfilePosts($profilePosts, array(
				'join' => XenForo_Model_ProfilePost::FETCH_COMMENT_USER
			));
			foreach ($profilePosts AS &$profilePost)
			{
				if (empty($profilePost['comments']))
				{
					continue;
				}

				foreach ($profilePost['comments'] AS &$comment)
				{
					$comment = $profilePostModel->prepareProfilePostComment($comment, $profilePost, $user);
				}
				$ignoredNames += $this->_getIgnoredContentUserNames($profilePost['comments']);
			}

			$canViewProfilePosts = true;
			if ($user['user_id'] == $visitor['user_id'])
			{
				$canPostOnProfile = $visitor->canUpdateStatus();
			}
			else
			{
				$canPostOnProfile = $userProfileModel->canPostOnProfile($user);
			}
		}
		else
		{
			$totalProfilePosts = 0;
			$profilePosts = array();
			$inlineModOptions = array();

			$ignoredNames = array();

			$canViewProfilePosts = false;
			$canPostOnProfile = false;
		}

		// custom fields
		$fieldModel = $this->_getFieldModel();
		$customFields = $fieldModel->prepareUserFields($fieldModel->getUserFields(
			array('profileView' => true),
			array('valueUserId' => $user['user_id'])
		));
		foreach ($customFields AS $key => $field)
		{
			if (!$field['viewableProfile'] || !$field['hasValue'])
			{
				unset($customFields[$key]);
			}
		}

		$customFieldsGrouped = $fieldModel->groupUserFields($customFields);
		if (!$userProfileModel->canViewIdentities($user))
		{
			$customFieldsGrouped['contact'] = array();
		}

		// misc
		if ($user['following'])
		{
			$followingToShowCount = 6;
			$followingCount = substr_count($user['following'], ',') + 1;

			$following = $userModel->getFollowedUserProfiles($userId, $followingToShowCount, 'RAND()');

			if (($followingCount >= $followingToShowCount && count($following) < $followingToShowCount)
				|| ($followingCount < $followingToShowCount && $followingCount != count($following)))
			{
				// following count is off, rebuild it
				$user['following'] = $userModel->getFollowingDenormalizedValue($user['user_id']);
				$userModel->updateFollowingDenormalizedValue($user['user_id'], $user['following']);

				$followingCount = substr_count($user['following'], ',') + 1;
			}
		}
		else
		{
			$followingCount = 0;

			$following = array();
		}

		$followersCount = $userModel->countUsersFollowingUserId($userId);
		$followers = $userModel->getUsersFollowingUserId($userId, 6, 'RAND()');

		$birthday = $userProfileModel->getUserBirthdayDetails($user);
		$user['age'] = $birthday['age'];

		$user['isFollowingVisitor'] = $userModel->isFollowing($visitor['user_id'], $user);

		if ($userModel->canViewWarnings())
		{
			$canViewWarnings = true;
			$warningCount = $this->getModelFromCache('XenForo_Model_Warning')->countWarningsByUser($user['user_id']);
		}
		else
		{
			$canViewWarnings = false;
			$warningCount = 0;
		}

		$viewParams = array_merge($profilePostModel->getProfilePostViewParams($profilePosts, $user), array(
			'user' => $user,
			'canViewOnlineStatus' => $userModel->canViewUserOnlineStatus($user),
			'canViewCurrentActivity' => $userModel->canViewUserCurrentActivity($user),
			'canIgnore' => $this->_getIgnoreModel()->canIgnoreUser($visitor['user_id'], $user),
			'canCleanSpam' => (XenForo_Permission::hasPermission($visitor['permissions'], 'general', 'cleanSpam') && $userModel->couldBeSpammer($user)),
			'canBanUsers' => ($visitor['is_admin'] && $visitor->hasAdminPermission('ban') && $user['user_id'] != $visitor->getUserId() && !$user['is_admin'] && !$user['is_moderator']),
			'canEditUser' => $userModel->canEditUser($user),
			'canViewIps' => $userModel->canViewIps(),
			'canReport' => $this->_getUserModel()->canReportUser($user),

			'warningCount' => $warningCount,
			'canViewWarnings' => $canViewWarnings,
			'canWarn' => $userModel->canWarnUser($user),

			'followingCount' => $followingCount,
			'followersCount' => $followersCount,

			'following' => $following,
			'followers' => $followers,

			'birthday' => $birthday,

			'customFieldsGrouped' => $customFieldsGrouped,

			'canStartConversation' => $userModel->canStartConversationWithUser($user),

			'canViewProfilePosts' => $canViewProfilePosts,
			'canPostOnProfile' => $canPostOnProfile,
			'inlineModOptions' => $inlineModOptions,
			'page' => $page,
			'profilePostsPerPage' => $profilePostsPerPage,
			'totalProfilePosts' => $totalProfilePosts,

			'ignoredNames' => $ignoredNames,

			'showRecentActivity' => $userProfileModel->canViewRecentActivity($user),
		));

		return $this->responseView('XenForo_ViewPublic_Member_View', 'member_view', $viewParams);
	}

	public function actionFollowing()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$perPage = XenForo_Application::getOptions()->membersPerPage;

		$userModel = $this->_getUserModel();

		$following = $userModel->getFollowedUsers($user['user_id'], array(
			'page' => $page,
			'perPage' => $perPage
		));

		$viewParams = array(
			'user' => $user,
			'following' => $following,
			'hasMore' => count($following) == $perPage,
			'page' => $page,
			'perPage' => $perPage
		);

		return $this->responseView('XenForo_ViewPublic_Member_Following', 'member_following', $viewParams);
	}

	public function actionFollowers()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$perPage = XenForo_Application::getOptions()->membersPerPage;

		$userModel = $this->_getUserModel();

		$followers = $userModel->getUsersFollowing($user['user_id'], array(
			'page' => $page,
			'perPage' => $perPage
		));

		$viewParams = array(
			'user' => $user,
			'followers' => $followers,
			'hasMore' => count($followers) == $perPage,
			'page' => $page,
			'perPage' => $perPage
		);

		return $this->responseView('XenForo_ViewPublic_Member_Followers', 'member_followers', $viewParams);
	}

	public function actionFollow()
	{
		$this->_assertRegistrationRequired();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		if (!$user = $this->_getUserModel()->getUserById($userId, array('join' => XenForo_Model_User::FETCH_USER_OPTION)))
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$visitor = XenForo_Visitor::getInstance();

		if (!$visitor->canFollow())
		{
			return $this->responseError(new XenForo_Phrase('your_account_must_be_confirmed_before_follow'));
		}

		if ($visitor['user_id'] == $user['user_id'])
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('members', $user)
			);
		}

		if ($this->isConfirmedPost())
		{
			$this->_getUserModel()->follow($user);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('members', $user),
				null,
				array(
					'linkPhrase' => new XenForo_Phrase('unfollow'),
					'linkUrl' => XenForo_Link::buildPublicLink('members/unfollow', $user, array('_xfToken' => $visitor['csrf_token_page']))
				)
			);
		}
		else // show confirmation dialog
		{
			$viewParams = array('user' => $user);

			return $this->responseView('XenForo_ViewPublic_Member_Follow', 'member_follow', $viewParams);
		}
	}

	public function actionUnfollow()
	{
		$this->_assertRegistrationRequired();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		if (!$user = $this->_getUserModel()->getUserById($userId))
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$visitor = XenForo_Visitor::getInstance();

		if ($visitor['user_id'] == $user['user_id'])
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('members', $user)
			);
		}

		if ($this->isConfirmedPost())
		{
			$this->_getUserModel()->unfollow($userId);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('members', $user),
				null,
				array(
					'linkPhrase' => new XenForo_Phrase('follow'),
					'linkUrl' => XenForo_Link::buildPublicLink('members/follow', $user, array('_xfToken' => $visitor['csrf_token_page']))
				)
			);
		}
		else // show confirmation dialog
		{
			$viewParams = array('user' => $user);

			return $this->responseView('XenForo_ViewPublic_Member_Unfollow', 'member_unfollow', $viewParams);
		}
	}

	public function actionIgnore()
	{
		$this->_assertRegistrationRequired();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->getUserOrError($userId);

		$visitor = XenForo_Visitor::getInstance();

		$ignoreModel = $this->_getIgnoreModel();

		if (!$ignoreModel->canIgnoreUser($visitor['user_id'], $user, $error))
		{
			return $this->responseError($error);
		}

		if ($this->isConfirmedPost())
		{
			$ignoreModel->ignoreUsers($visitor['user_id'], $user['user_id']);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('members', $user),
				null,
				array(
					'linkPhrase' => new XenForo_Phrase('unignore'),
					'linkUrl' => XenForo_Link::buildPublicLink('members/unignore', $user, array('_xfToken' => $visitor['csrf_token_page']))
				)
			);
		}
		else // show confirmation dialog
		{
			$viewParams = array('user' => $user);

			return $this->responseView('XenForo_ViewPublic_Member_Ignore', 'member_ignore', $viewParams);
		}
	}

	public function actionUnignore()
	{
		$this->_assertRegistrationRequired();

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->getUserOrError($userId);

		$visitor = XenForo_Visitor::getInstance();

		$ignoreModel = $this->_getIgnoreModel();

		if ($visitor['user_id'] == $user['user_id'])
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('members', $user)
			);
		}

		if ($this->isConfirmedPost())
		{
			$ignoreModel->unignoreUser($visitor['user_id'], $user['user_id']);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('members', $user),
				null,
				array(
					'linkPhrase' => new XenForo_Phrase('ignore'),
					'linkUrl' => XenForo_Link::buildPublicLink('members/ignore', $user, array('_xfToken' => $visitor['csrf_token_page']))
				)
			);
		}
		else // show confirmation dialog
		{
			$viewParams = array('user' => $user);

			return $this->responseView('XenForo_ViewPublic_Member_Unignore', 'member_unignore', $viewParams);
		}
	}

	public function actionTrophies()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$trophyModel = $this->_getTrophyModel();
		$trophies = $trophyModel->prepareTrophies($trophyModel->getTrophiesForUserId($userId));

		$viewParams = array(
			'user' => $user,
			'trophies' => $trophies
		);

		return $this->responseView('XenForo_ViewPublic_Member_Trophies', 'member_trophies', $viewParams);
	}

	public function actionMiniStats()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$user = XenForo_Application::arrayFilterKeys($user, array(
			'user_id',
			'username',
			'message_count',
			'like_count',
			'trophy_points',
			'register_date',
		));

		$viewParams = array('user' => $user);

		return $this->responseView('XenForo_ViewPublic_Member_MiniStats', '', $viewParams);
	}

	/**
	 * Gets recent content for the specified member
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionRecentContent()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$results = XenForo_Search_SourceHandler_Abstract::getDefaultSourceHandler()->executeSearchByUserId(
			$userId, 0, 15
		);
		$results = $this->getModelFromCache('XenForo_Model_Search')->getSearchResultsForDisplay($results);
		if (!$results)
		{
			return $this->responseMessage(new XenForo_Phrase('this_member_does_not_have_any_recent_content'));
		}

		$viewParams = array(
			'user' => $user,
			'results' => $results
		);

		return $this->responseView('XenForo_ViewPublic_Member_RecentContent', 'member_recent_content', $viewParams);
	}

	public function actionRecentActivity()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		if (!$this->_getUserProfileModel()->canViewRecentActivity($user))
		{
			return $this->responseView(
				'XenForo_ViewPublic_Member_RecentActivity_Restricted',
				'member_recent_activity',
				array('user' => $user, 'restricted' => true)
			);
		}

		$newsFeedId = $this->_input->filterSingle('news_feed_id', XenForo_Input::UINT);
		$conditions = array('user_id' => $userId);

		$feed = $this->getModelFromCache('XenForo_Model_NewsFeed')->getNewsFeed($conditions, $newsFeedId);
		$feed['user'] = $user;
		$feed['startNewsFeedId'] = $newsFeedId;

		return $this->responseView(
			'XenForo_ViewPublic_Member_RecentActivity',
			'member_recent_activity',
			$feed
		);
	}

	public function actionWarn()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->getUserOrError($userId);
		$visitor = XenForo_Visitor::getInstance();

		$contentInput = $this->_input->filter(array(
			'content_type' => XenForo_Input::STRING,
			'content_id' => XenForo_Input::UINT
		));

		if (!$contentInput['content_type'])
		{
			$contentInput['content_type'] = 'user';
			$contentInput['content_id'] = $user['user_id'];
		}

		/* @var $warningModel XenForo_Model_Warning */
		$warningModel = $this->getModelFromCache('XenForo_Model_Warning');

		$warningHandler = $warningModel->getWarningHandler($contentInput['content_type']);
		if (!$warningHandler)
		{
			return $this->responseNoPermission();
		}

		$content = $warningHandler->getContent($contentInput['content_id']);

		if (!$content || !$warningHandler->canView($content) || !$warningHandler->canWarn($user['user_id'], $content))
		{
			return $this->responseNoPermission();
		}

		$contentTitle = $warningHandler->getContentTitle($content);
		$contentDetails = $warningHandler->getContentDetails($content);

		if ($this->_input->filterSingle('fill', XenForo_Input::UINT))
		{
			// filler result
			$choice = $this->_input->filterSingle('choice', XenForo_Input::UINT);
			$warning = $warningModel->getWarningDefinitionById($choice);
			if ($warning)
			{
				$warning = $warningModel->prepareWarningDefinition($warning, true);

				$replace = array(
					'{title}' => $contentTitle,
					'{content}' => $contentDetails,
					'{url}' => $warningHandler->getContentUrl($content, true),
					'{name}' => $user['username'],
					'{staff}' => $visitor['username']
				);
				$warning['conversationTitle'] = strtr((string)$warning['conversationTitle'], $replace);
				$warning['conversationMessage'] = strtr((string)$warning['conversationMessage'], $replace);
			}
			else
			{
				$warning = array(
					'warning_definition_id' => 0,
					'points_default' => 1,
					'expiry_type' => 'months',
					'expiry_default' => 1,
					'extra_user_group_ids' => '',
					'is_editable' => 1,
					'title' => '',
					'conversationTitle' => '',
					'conversationMessage' => ''
				);
			}

			return $this->responseView('XenForo_ViewPublic_Member_WarnFill', '', array('warning' => $warning));
		}

		$warnings = $warningModel->prepareWarningDefinitions($warningModel->getWarningDefinitions());

		if ($this->_request->isPost())
		{
			$dwInput = $this->_input->filter(array(
				'warning_definition_id' => XenForo_Input::UINT,
				'title' => XenForo_Input::STRING,
				'points' => XenForo_Input::UINT,
				'notes' => XenForo_Input::STRING,
			));

			// TODO: permission over customizing warnings?

			if (!$dwInput['warning_definition_id'] || empty($warnings[$dwInput['warning_definition_id']]))
			{
				// custom warning
				$warning = false;
				$extraGroupIds = '';
				$dwInput['warning_definition_id'] = 0;
			}
			else
			{
				$warning = $warningModel->prepareWarningDefinition($warnings[$dwInput['warning_definition_id']]);
				$dwInput['title'] = (string)$warning['title'];
				$extraGroupIds = $warning['extra_user_group_ids'];
			}

			if ($warning && !$warning['is_editable'])
			{
				$dwInput['points'] = $warning['points_default'];
				$dwInput['expiry_date'] = (
					$warning['expiry_type'] == 'never' ? 0
					: min(
						pow(2,32) - 1,
						strtotime('+' . $warning['expiry_default'] . ' ' . $warning['expiry_type'])
					)
				);
			}
			else
			{
				if (!$this->_input->filterSingle('points_enable', XenForo_Input::UINT))
				{
					$dwInput['points'] = 0;
				}

				if (!$this->_input->filterSingle('expiry_enable', XenForo_Input::UINT))
				{
					$dwInput['expiry_date'] = 0;
				}
				else
				{
					$expireInput = $this->_input->filter(array(
						'expiry_value' => XenForo_Input::UINT,
						'expiry_unit' => XenForo_Input::STRING
					));
					$dwInput['expiry_date'] = min(
						pow(2,32) - 1,
						strtotime('+' . $expireInput['expiry_value'] . ' ' . $expireInput['expiry_unit'])
					);
				}
			}

			$dwInput += array(
				'content_type' => $contentInput['content_type'],
				'content_id' => $contentInput['content_id'],
				'content_title' => $contentTitle,
				'user_id' => $user['user_id'],
				'warning_user_id' => XenForo_Visitor::getUserId(),
				'extra_user_group_ids' => $extraGroupIds
			);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Warning');
			$dw->bulkSet($dwInput);
			$dw->setExtraData(XenForo_DataWriter_Warning::DATA_CONTENT, $content);
			switch ($this->_input->filterSingle('content_action', XenForo_Input::STRING))
			{
				case 'public_warning':
					if ($warningHandler->canPubliclyDisplayWarning())
					{
						$dw->setExtraData(XenForo_DataWriter_Warning::DATA_PUBLIC_WARNING,
							$this->_input->filterSingle('public_warning', XenForo_Input::STRING)
						);
					}
					break;

				case 'delete_content':
					if ($warningHandler->canDeleteContent($content))
					{
						$dw->setExtraData(XenForo_DataWriter_Warning::DATA_DELETION_REASON,
							$this->_input->filterSingle('delete_reason', XenForo_Input::STRING)
						);
					}
					break;
			}
			$dw->save();

			$warning = $dw->getMergedData();

			$conversationInput = $this->_input->filter(array(
				'conversation_title' => XenForo_Input::STRING,
				'conversation_message' => XenForo_Input::STRING,
				'conversation_locked' => XenForo_Input::UINT,
				'open_invite' => XenForo_Input::UINT,
			));

			if ($conversationInput['conversation_title'] && $conversationInput['conversation_message'])
			{
				$visitor = XenForo_Visitor::getInstance();

				$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster', XenForo_DataWriter::ERROR_SILENT);
				$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $visitor->toArray());
				$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_MESSAGE, $conversationInput['conversation_message']);
				$conversationDw->bulkSet(array(
					'user_id' => $visitor['user_id'],
					'username' => $visitor['username'],
					'title' => $conversationInput['conversation_title'],
					'open_invite' => $conversationInput['open_invite'],
					'conversation_open' => ($conversationInput['conversation_locked'] ? 0 : 1),
				));
				$conversationDw->addRecipientUserIds(array($user['user_id']));

				$messageDw = $conversationDw->getFirstMessageDw();
				$messageDw->set('message', $conversationInput['conversation_message']);
				$conversationDw->save();

				$this->getModelFromCache('XenForo_Model_Conversation')->markConversationAsRead(
					$conversationDw->get('conversation_id'), XenForo_Visitor::getUserId(), XenForo_Application::$time
				);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect()
			);
		}
		else
		{
			if ($this->_getUserModel()->canViewWarnings())
			{
				$canViewWarnings = true;
				$warningCount = $warningModel->countWarningsByUser($user['user_id']);
			}
			else
			{
				$canViewWarnings = false;
				$warningCount = 0;
			}

			$viewParams = array(
				'contentTitle' => $warningHandler->getContentTitleForDisplay($contentTitle),
				'contentUrl' => $warningHandler->getContentUrl($content),
				'contentType' => $contentInput['content_type'],
				'contentId' => $contentInput['content_id'],
				'canWarnPublicly' => $warningHandler->canPubliclyDisplayWarning(),
				'canDeleteContent' => $warningHandler->canDeleteContent($content),
				'user' => $user,
				'warnings' => $warnings,

				'canViewWarnings' => $canViewWarnings,
				'warningCount' => $warningCount,

				'redirect' => $this->getDynamicRedirect()
			);
			return $this->responseView('XenForo_ViewPublic_Member_Warn', 'member_warn', $viewParams);
		}
	}

	public function actionWarnings()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		/* @var $warningModel XenForo_Model_Warning */
		$warningModel = $this->getModelFromCache('XenForo_Model_Warning');

		if (!$this->_getUserModel()->canViewWarnings())
		{
			return $this->responseNoPermission();
		}

		$warnings = $warningModel->getWarningsByUser($user['user_id']);
		if (!$warnings)
		{
			return $this->responseMessage(new XenForo_Phrase('this_member_has_not_been_warned'));
		}

		$warnings = $warningModel->prepareWarnings($warnings);

		$viewParams = array(
			'user' => $user,
			'warnings' => $warnings
		);
		return $this->responseView('XenForo_ViewPublic_Member_Warnings', 'member_warnings', $viewParams);
	}

	public function actionEdit()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$userFetchOptions = array(
			'join' => XenForo_Model_User::FETCH_USER_PERMISSIONS
		);
		$user = $this->getHelper('UserProfile')->getUserOrError($userId, $userFetchOptions);

		$visitor = XenForo_Visitor::getInstance();
		$userModel = $this->_getUserModel();

		if (!$userModel->canEditUser($user))
		{
			return $this->responseNoPermission();
		}

		if ($visitor->hasAdminPermission('user'))
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildAdminLink('users/edit', $user)
			);
		}

		$user['permissions'] = XenForo_Permission::unserializePermissions($user['global_permission_cache']);
		$userCanSetCustomTitle = XenForo_Permission::hasPermission($user['permissions'], 'general', 'editCustomTitle');
		$userCanEditProfile = XenForo_Permission::hasPermission($user['permissions'], 'general', 'editProfile');
		$userCanEditSignature = (
			XenForo_Permission::hasPermission($user['permissions'], 'general', 'editSignature')
			&& XenForo_Permission::hasPermission($user['permissions'], 'signature', 'maxLines') != 0
		);

		$customFields = $this->_getFieldModel()->getUserFields(
			array('moderator_editable' => true),
			array('valueUserId' => $user['user_id'])
		);

		if (!$userCanEditProfile)
		{
			// the user can't edit their profile, so don't let moderator change empty values
			foreach ($customFields AS $fieldId => $customField)
			{
				if (!$customField['field_value'])
				{
					unset($customFields[$fieldId]);
				}
			}
		}

		if ($this->isConfirmedPost())
		{
			$dwInput = $this->_input->filter(array(
				'custom_title' => XenForo_Input::STRING,
				'location' => XenForo_Input::STRING,
				'occupation' => XenForo_Input::STRING,
				'homepage' => XenForo_Input::STRING,
			));
			$dwInput['about'] = $this->getHelper('Editor')->getMessageText('about', $this->_input);
			$dwInput['about'] = XenForo_Helper_String::autoLinkBbCode($dwInput['about']);

			$dwInput['signature'] = $this->getHelper('Editor')->getMessageText('signature', $this->_input);
			$dwInput['signature'] = XenForo_Helper_String::autoLinkBbCode($dwInput['signature']);

			// moderators can only edit things that users can change or have set themselves,
			// so check to see if the user can edit OR if there's an existing value
			if (!$userCanSetCustomTitle && !$user['custom_title'])
			{
				unset($dwInput['custom_title']);
			}

			if (!$userCanEditProfile)
			{
				if (!$user['location'])
				{
					unset($dwInput['location']);
				}

				if (!$user['occupation'])
				{
					unset($dwInput['occupation']);
				}

				if (!$user['homepage'])
				{
					unset($dwInput['homepage']);
				}

				if (!$user['about'])
				{
					unset($dwInput['about']);
				}
			}

			if (!$userCanEditSignature && !$user['signature'])
			{
				unset($dwInput['signature']);
			}

			$customFieldsValues = $this->_input->filterSingle('custom_fields', XenForo_Input::ARRAY_SIMPLE);
			$customFieldsShown = $this->_input->filterSingle('custom_fields_shown', XenForo_Input::STRING, array('array' => true));

			// only edit the ones that can be edited and are shown
			$customFieldsEditable = array_intersect($customFieldsShown, array_keys($customFields));

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
			$dw->setExistingData($user);
			$dw->bulkSet($dwInput);
			$dw->setCustomFields($customFieldsValues, $customFieldsEditable);
			$dw->save();

			if ($this->_input->filterSingle('remove_avatar', XenForo_Input::BOOLEAN))
			{
				/** @var XenForo_Model_Avatar $avatarModel */
				$avatarModel = $this->getModelFromCache('XenForo_Model_Avatar');
				$avatarModel->deleteAvatar($user['user_id']);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('members', $user)
			);
		}
		else
		{
			$viewParams = array(
				'user' => $user,
				'customFields' => $this->_getFieldModel()->prepareUserFields($customFields, true),

				'userCanSetCustomTitle' => $userCanSetCustomTitle,
				'userCanEditProfile' => $userCanEditProfile,
				'userCanEditSignature' => $userCanEditSignature
			);
			return $this->responseView('XenForo_ViewPublic_Member_Edit', 'member_edit', $viewParams);
		}
	}

	public function actionSharedIps()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->getUserOrError($userId);

		if (!$this->_getUserModel()->canViewIps())
		{
			return $this->responseNoPermission();
		}

		/** @var XenForo_Model_Ip $ipModel */
		$ipModel = $this->getModelFromCache('XenForo_Model_Ip');
		$users = $ipModel->getSharedIpUsers($user['user_id'], 14);

		$viewParams = array(
			'profileUser' => $user,
			'users' => $users,
		);

		return $this->responseView(
			'XenForo_ViewPublic_Member_SharedIps',
			'member_shared_ips',
			$viewParams
		);
	}

	/**
	 * Member mini-profile (for popup)
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionCard()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$userFetchOptions = array(
			'join' => XenForo_Model_User::FETCH_LAST_ACTIVITY | XenForo_Model_User::FETCH_USER_PERMISSIONS
		);
		$user = $this->getHelper('UserProfile')->getUserOrError($userId, $userFetchOptions);

		$visitor = XenForo_Visitor::getInstance();
		$userModel = $this->_getUserModel();

		$user = $userModel->prepareUserCard($user);

		// get last activity details
		$user['activity'] = ($user['view_date'] ? $this->getModelFromCache('XenForo_Model_Session')->getSessionActivityDetails($user) : false);

		$user['isFollowingVisitor'] = $userModel->isFollowing($visitor['user_id'], $user);

		$canCleanSpam = (XenForo_Permission::hasPermission($visitor['permissions'], 'general', 'cleanSpam') && $userModel->couldBeSpammer($user));

		$viewParams = array(
			'user' => $user,

			'canBanUsers' => ($visitor['is_admin'] && $visitor->hasAdminPermission('ban') && $user['user_id'] != $visitor->getUserId() && !$user['is_admin'] && !$user['is_moderator']),
			'canEditUser' => $userModel->canEditUser($user),
			'canViewIps' => $userModel->canViewIps(),
			'canCleanSpam' => $canCleanSpam,
			'canViewOnlineStatus' => $userModel->canViewUserOnlineStatus($user),
			'canViewCurrentActivity' => $userModel->canViewUserCurrentActivity($user),
			'canStartConversation' => $userModel->canStartConversationWithUser($user),
			'canViewWarnings' => $userModel->canViewWarnings(),
			'canWarn' => $userModel->canWarnUser($user),
			'canIgnore' => $this->_getIgnoreModel()->canIgnoreUser($visitor['user_id'], $user)
		);

		return $this->responseView('XenForo_ViewPublic_Member_Card', 'member_card', $viewParams);
	}

	/**
	 * Reports this user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionReport()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->getUserOrError($userId);

		if (!$this->_getUserModel()->canReportUser($user, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($this->isConfirmedPost())
		{
			$message = $this->_input->filterSingle('message', XenForo_Input::STRING);
			if (!$message)
			{
				return $this->responseError(new XenForo_Phrase('please_enter_reason_for_reporting_this_member'));
			}

			$this->assertNotFlooding('report');

			/* @var $reportModel XenForo_Model_Report */
			$reportModel = XenForo_Model::create('XenForo_Model_Report');
			$reportModel->reportContent('user', $user, $message);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('members', $user),
				new XenForo_Phrase('thank_you_for_reporting_this_member')
			);
		}
		else
		{
			$viewParams = array(
				'user' => $user
			);

			return $this->responseView('XenForo_ViewPublic_Member_Report', 'member_report', $viewParams);
		}
	}

	public function actionPost()
	{
		$this->_assertPostOnly();

		$data = $this->_input->filter(array(
			'message' => XenForo_Input::STRING,
		));

		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->getHelper('UserProfile')->assertUserProfileValidAndViewable($userId);

		$visitor = XenForo_Visitor::getInstance();

		if ($visitor['user_id'] == $user['user_id'])
		{
			if (!$visitor->canUpdateStatus())
			{
				return $this->responseNoPermission();
			}

			if ($data['message'] !== '')
			{
				$this->assertNotFlooding('post');
			}

			$profilePostId = $this->_getUserProfileModel()->updateStatus($data['message']);

			if ($this->_input->filterSingle('return', XenForo_Input::UINT))
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$this->getDynamicRedirect(),
					new XenForo_Phrase('your_status_has_been_updated')
				);
			}

			$hash = '';
		}
		else
		{
			if (!$this->_getUserProfileModel()->canPostOnProfile($user, $errorPhraseKey))
			{
				throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
			}

			$writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');

			$writer->set('user_id', $visitor['user_id']);
			$writer->set('username', $visitor['username']);
			$writer->set('message', $data['message']);
			$writer->set('profile_user_id', $user['user_id']);
			$writer->set('message_state', $this->_getProfilePostModel()->getProfilePostInsertMessageState($user));
			$writer->setExtraData(XenForo_DataWriter_DiscussionMessage_ProfilePost::DATA_PROFILE_USER, $user);
			$writer->setOption(XenForo_DataWriter_DiscussionMessage_ProfilePost::OPTION_MAX_TAGGED_USERS, $visitor->hasPermission('general', 'maxTaggedUsers'));

			/** @var $spamModel XenForo_Model_SpamPrevention */
			$spamModel = $this->getModelFromCache('XenForo_Model_SpamPrevention');

			if (!$writer->hasErrors()
				&& $writer->get('message_state') == 'visible'
				&& $spamModel->visitorRequiresSpamCheck()
			)
			{
				switch ($spamModel->checkMessageSpam($data['message'], array(), $this->_request))
				{
					case XenForo_Model_SpamPrevention::RESULT_MODERATED:
						$writer->set('message_state', 'moderated');
						break;

					case XenForo_Model_SpamPrevention::RESULT_DENIED;
						$spamModel->logSpamTrigger('profile_post', null);
						$writer->error(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'));
						break;
				}
			}

			$writer->preSave();

			if (!$writer->hasErrors())
			{
				$this->assertNotFlooding('post');
			}

			$writer->save();
			$profilePostId = $writer->get('profile_post_id');

			$spamModel->logSpamTrigger('profile_post', $profilePostId);

			$hash = '#profile-post-' . $profilePostId;
		}

		if ($this->_noRedirect())
		{
			$profilePostModel = $this->_getProfilePostModel();

			$profilePost = $profilePostModel->getProfilePostById($profilePostId, array(
				'join' => XenForo_Model_ProfilePost::FETCH_USER_POSTER
			));
			$profilePost = $profilePostModel->prepareProfilePost($profilePost, $user);
			$profilePostModel->addInlineModOptionToProfilePost($profilePost, $user);

			$viewParams = array(
				'profilePost' => $profilePost,
				'isStatus' =>  ($visitor['user_id'] == $user['user_id']),
			);

			return $this->responseView(
				'XenForo_ViewPublic_Member_Post',
				$this->_input->filterSingle('simple', XenForo_Input::BOOLEAN) ? 'profile_post_list_item_simple' : 'profile_post',
				$viewParams
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('members', $user) . $hash
			);
		}
	}

	/**
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionNewsFeed()
	{
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildPublicLink('recent-activity')
		);
	}

	/**
	 * Finds valid members matching the specified username prefix.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionFind()
	{
		$q = ltrim($this->_input->filterSingle('q', XenForo_Input::STRING, array('noTrim' => true)));

		if ($q !== '' && utf8_strlen($q) >= 2)
		{
			$users = $this->_getUserModel()->getUsers(
				array(
					'username' => array($q , 'r'),
					'user_state' => 'valid',
					'is_banned' => 0,
					'active_recently' => true
				),
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
			'XenForo_ViewPublic_Member_Find',
			'member_autocomplete',
			$viewParams
		);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		if (!XenForo_Visitor::getInstance()->hasPermission('general', 'viewProfile'))
		{
			return new XenForo_Phrase('viewing_members');
		}

		$userIds = array();
		foreach ($activities AS $activity)
		{
			if (!empty($activity['params']['user_id']))
			{
				$userIds[$activity['params']['user_id']] = intval($activity['params']['user_id']);
			}
		}

		$userData = array();

		if ($userIds)
		{
			/* @var $userModel XenForo_Model_User */
			$userModel = XenForo_Model::create('XenForo_Model_User');

			$users = $userModel->getUsersByIds($userIds, array(
				'join' => XenForo_Model_User::FETCH_USER_PRIVACY
			));
			foreach ($users AS $user)
			{
				$userData[$user['user_id']] = array(
					'username' => $user['username'],
					'url' => XenForo_Link::buildPublicLink('members', $user),
				);
			}
		}

		$output = array();
		foreach ($activities AS $key => $activity)
		{
			$user = false;
			if (!empty($activity['params']['user_id']))
			{
				$userId = $activity['params']['user_id'];
				if (isset($userData[$userId]))
				{
					$user = $userData[$userId];
				}
			}

			if ($user)
			{
				$output[$key] = array(
					new XenForo_Phrase('viewing_member_profile'),
					$user['username'],
					$user['url'],
					false
				);
			}
			else
			{
				$output[$key] = new XenForo_Phrase('viewing_members');
			}
		}

		return $output;
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenForo_Model_UserProfile
	 */
	protected function _getUserProfileModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserProfile');
	}

	/**
	 * @return XenForo_Model_UserIgnore
	 */
	protected function _getIgnoreModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserIgnore');
	}

	/**
	 * @return XenForo_Model_UserField
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserField');
	}

	/**
	 * @return XenForo_Model_ProfilePost
	 */
	protected function _getProfilePostModel()
	{
		return $this->getModelFromCache('XenForo_Model_ProfilePost');
	}

	/**
	 * @return XenForo_Model_Trophy
	 */
	protected function _getTrophyModel()
	{
		return $this->getModelFromCache('XenForo_Model_Trophy');
	}
}
