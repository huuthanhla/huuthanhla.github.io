<?php

/**
 * Model for user profile related elements. This can be seen as an extension of
 * the user model, containing less-commonly-used elements.
 *
 * @package XenForo_User
 */
class XenForo_Model_UserProfile extends XenForo_Model
{
	/**
	 * Gets information about the user's birthday, including age if displayable.
	 *
	 * @param array $user
	 * @param boolean $force If true, ignores user privacy options
	 *
	 * @return array|false False or array: [age] => integer|false, [timeStamp] => DateTime, and [format] => named format to use to display birthday
	 */
	public function getUserBirthdayDetails(array $user, $force = false)
	{
		if ($user['dob_day'] && ($force || $user['show_dob_date']))
		{
			if ($user['dob_year'] && ($force || $user['show_dob_year']))
			{
				return array(
					'age' => $this->getUserAge($user, $force),
					'timeStamp' => new DateTime("$user[dob_year]-$user[dob_month]-$user[dob_day]"),
					'format' => 'absolute'
				);
			}
			else
			{
				return array(
					'age' => false,
					'timeStamp' => new DateTime("2000-$user[dob_month]-$user[dob_day]"),
					'format' => 'monthDay'
				);
			}
		}
		else
		{
			return false;
		}
	}

	/**
	 * Calculates the age of a user.
	 *
	 * @param array $user
	 * @param boolean $force If true, ignores user privacy options
	 *
	 * @return integer|false
	 */
	public function getUserAge(array $user, $force = false)
	{
		if (empty($user['dob_year']) || empty($user['dob_month']) || empty($user['dob_day']))
		{
			return false;
		}

		if ($user['dob_year'] && ($force || ($user['show_dob_date'] && $user['show_dob_year'])))
		{
			return $this->calculateAge($user['dob_year'], $user['dob_month'], $user['dob_day']);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Calculates the age of the person with the specified birth date.
	 *
	 * @param integer $year
	 * @param integer $month
	 * @param integer $day
	 *
	 * @return integer
	 */
	public function calculateAge($year, $month, $day)
	{
		list($cYear, $cMonth, $cDay) = explode('-', XenForo_Locale::getFormattedDate(XenForo_Application::$time, 'Y-m-d'));
		$age = $cYear - $year;
		if ($cMonth < $month || ($cMonth == $month && $cDay < $day))
		{
			$age--;
		}

		return max(0, $age);
	}

	/**
	 * Determines if the specified full user profile is viewable.
	 *
	 * @param array $user User being viewed
	 * @param string $errorPhraseKey Returned by ref. Phrase key of more specific error
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return boolean
	 */
	public function canViewFullUserProfile(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if ($viewingUser['user_id'] == $user['user_id'])
		{
			return true; // always let a user view their own profile
		}

		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'viewProfile'))
		{
			return false;
		}

		$userModel = $this->_getUserModel();

		if (!$userModel->passesPrivacyCheck($user['allow_view_profile'], $user, $viewingUser))
		{
			$errorPhraseKey = 'member_limits_viewing_profile';
			return false;
			// TODO: we should do a limited profile at some point
		}

		// don't display the profiles of unconfirmed users
		if (($user['user_state'] == 'moderated' || $user['user_state'] == 'email_confirm') && !$userModel->canByPassUserPrivacy($user['user_id'], $viewingUser))
		{
			$errorPhraseKey = 'this_users_profile_is_not_available';
			return false;
		}

		// user profiles of permanently-banned users should be unavailable to all but privacy-bypassing users
		if ($user['is_banned'])
		{
			$ban = $this->getModelFromCache('XenForo_Model_Banning')->getBannedUserById($user['user_id']);

			if ($ban && $ban['end_date'] == 0 && !$userModel->canBypassUserPrivacy($null, $viewingUser))
			{
				$errorPhraseKey = 'this_users_profile_is_not_available';
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines if permissions are sufficient to view the specified
	 * user's profile posts. This does not check container (profile) permissions.
	 *
	 * @param array $user User being viewed
	 * @param string $errorPhraseKey Returned by ref. Phrase key of more specific error
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return boolean
	 */
	public function canViewProfilePosts(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'view');
	}

	/**
	 * Determines if permissions are sufficient to post on the specified
	 * user's profile. This does not check container (profile) permissions.
	 *
	 * @param array $user User being viewed
	 * @param string $errorPhraseKey Returned by ref. Phrase key of more specific error
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return boolean
	 */
	public function canPostOnProfile(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return (
			$viewingUser['user_id']
			&& XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'view')
			&& XenForo_Permission::hasPermission($viewingUser['permissions'], 'profilePost', 'post')
			&& $this->_getUserModel()->passesPrivacyCheck($user['allow_post_profile'], $user, $viewingUser)
		);
	}

	/**
	 * Determines if permissions are sufficient toget the specified user's news
	 * feed/recent activity. This does not check container (profile) permissions.
	 *
	 * @param array $user User being viewed
	 * @param string $errorPhraseKey Returned by ref. Phrase key of more specific error
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return boolean
	 */
	public function canViewRecentActivity(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$userModel = $this->_getUserModel();

		if (!$userModel->canBypassUserPrivacy($null, $viewingUser))
		{
			$options = XenForo_Application::getOptions();

			if (!$options->enableNewsFeed)
			{
				return false;
			}
		}

		return $userModel->passesPrivacyCheck($user['allow_receive_news_feed'], $user, $viewingUser);
	}

	/**
	 * Determines if permissions are sufficient to view on the specified
	 * user's identities. This does not check container (profile) permissions.
	 *
	 * @param array $user User being viewed
	 * @param string $errorPhraseKey Returned by ref. Phrase key of more specific error
	 * @param array|null $viewingUser Viewing user ref
	 *
	 * @return boolean
	 */
	public function canViewIdentities(array $user, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return $this->_getUserModel()->passesPrivacyCheck($user['allow_view_identities'], $user, $viewingUser);
	}

	/**
	 * Updates the status of the viewing user.
	 *
	 * @param string $status
	 * @param integer|null $date
	 * @param array|null $viewingUser
	 *
	 * @return integer|false False on failure, profile post ID on success
	 */
	public function updateStatus($status, $date = null, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($date === null)
		{
			$date = XenForo_Application::$time;
		}

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
		$writer->set('user_id', $viewingUser['user_id']);
		$writer->set('username', $viewingUser['username']);
		$writer->set('message', $status);
		$writer->set('profile_user_id', $viewingUser['user_id']);
		$writer->set('post_date', $date);
		$writer->set('message_state', $this->getModelFromCache('XenForo_Model_ProfilePost')->getProfilePostInsertMessageState($viewingUser, $viewingUser));
		$writer->setOption(XenForo_DataWriter_DiscussionMessage_ProfilePost::OPTION_MAX_TAGGED_USERS,
			XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'maxTaggedUsers')
		);

		/** @var $spamModel XenForo_Model_SpamPrevention */
		$spamModel = $this->getModelFromCache('XenForo_Model_SpamPrevention');

		if (!$writer->hasErrors()
			&& $writer->get('message_state') == 'visible'
			&& $spamModel->visitorRequiresSpamCheck($viewingUser)
		)
		{
			switch ($spamModel->checkMessageSpam($status))
			{
				case XenForo_Model_SpamPrevention::RESULT_MODERATED:
					$writer->set('message_state', 'moderated');
					break;

				case XenForo_Model_SpamPrevention::RESULT_DENIED;
					$writer->error(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'));
					break;
			}
		}

		$writer->save();

		return $writer->get('profile_post_id');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}