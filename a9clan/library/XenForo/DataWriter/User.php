<?php

/**
* Data writer for users.
*
* @package XenForo_User
*/
class XenForo_DataWriter_User extends XenForo_DataWriter
{
	const OPTION_USERNAME_LENGTH_MIN = 'usernameLengthMin';
	const OPTION_USERNAME_LENGTH_MAX = 'usernameLengthMax';
	const OPTION_USERNAME_DISALLOWED_NAMES = 'usernameIllegalNames';
	const OPTION_USERNAME_REGEX = 'usernameRegex';
	const OPTION_CUSTOM_TITLE_DISALLOWED = 'customTitleDisallowed';
	const OPTION_ADMIN_EDIT = 'adminEdit';
	const OPTION_ALLOW_DELETE_SELF = 'allowDeleteSelf';
	const OPTION_REQUIRE_LOCATION = 'requireLocation';
	const OPTION_MESSAGE_MAX_LENGTH = 'messageMaxLength';
	const OPTION_LOG_CHANGES = 'logChanges';

	const DATA_USER_FIELD_DEFINITIONS = 'userFields';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_user_not_found';

	/**
	 * If this is set, it represents a set of seconardy group relations to *replace*.
	 * When it is null, no relations will be updated.
	 * @var null|array
	 */
	protected $_secondaryGroups = null;

	/**
	 * The custom fields to be updated. Use setCustomFields to manage this.
	 *
	 * @var array
	 */
	protected $_updateCustomFields = array();

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_user' => array(
				'user_id'
					=> array('type' => self::TYPE_UINT, 'autoIncrement' => true, 'verification' => array('XenForo_DataWriter_Helper_User', 'verifyUserId')),
				'username'
					=> array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50, 'verification' => array('$this', '_verifyUsername'), 'requiredError' => 'please_enter_valid_name'),
				'email'
					=> array('type' => self::TYPE_STRING, 'maxLength' => 120, 'verification' => array('$this', '_verifyEmail'), 'requiredError' => 'please_enter_valid_email'),
				'gender'
					=> array('type' => self::TYPE_STRING, 'default' => '', 'allowedValues' => array('male', 'female', '')),
				'style_id'
					=> array('type' => self::TYPE_UINT, 'default' => 0, 'verification' => array('$this', '_verifyStyleId')),
				'language_id'
					=> array('type' => self::TYPE_UINT, 'default' => 0, 'verification' => array('$this', '_verifyLanguageId')),
				'timezone'
					=> array('type' => self::TYPE_STRING, 'default' => 'Europe/London', 'maxLength' => 50, 'verification' => array('$this', '_verifyTimezone')),
				'visible'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'activity_visible'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'user_group_id'
					=> array('type' => self::TYPE_UINT, 'required' => true),
				'secondary_group_ids'
					=> array('type' => self::TYPE_BINARY, 'default' => ''),
				'display_style_group_id'
					=> array('type' => self::TYPE_UINT, 'default' => 0),
				'permission_combination_id'
					=> array('type' => self::TYPE_UINT, 'default' => 0),
				'message_count'
					=> array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'alerts_unread'
					=> array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'conversations_unread'
					=> array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'register_date'
					=> array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'last_activity'
					=> array('type' => self::TYPE_UINT, 'default' => XenForo_Application::$time),
				'trophy_points'
					=> array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'avatar_date'
					=> array('type' => self::TYPE_UINT, 'default' => 0),
				'avatar_width'
					=> array('type' => self::TYPE_UINT, 'max' => 65535, 'default' => 0),
				'avatar_height'
					=> array('type' => self::TYPE_UINT, 'max' => 65535, 'default' => 0),
				'gravatar'
					=> array('type' => self::TYPE_STRING, 'maxLength' => 120, 'default' => '', 'verification' => array('$this', '_verifyGravatar')),
				'user_state'
					=> array('type' => self::TYPE_STRING, 'allowedValues' => array('valid', 'email_confirm', 'email_confirm_edit', 'moderated', 'email_bounce'), 'default' => 'valid'),
				'is_moderator'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'is_admin'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'is_staff'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'is_banned'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'like_count'
					=> array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
				'custom_title'
					=> array('type' => self::TYPE_STRING, 'maxLength' => 50, 'default' => '', 'verification' => array('$this', '_verifyCustomTitle')),
				'warning_points'
					=> array('type' => self::TYPE_UINT_FORCED, 'default' => 0)
			),
			'xf_user_profile' => array(
				'user_id'    => array('type' => self::TYPE_UINT,   'default' => array('xf_user', 'user_id'), 'required' => true),
				'dob_day'    => array('type' => self::TYPE_UINT,   'default' => 0, 'max' => 31),
				'dob_month'  => array('type' => self::TYPE_UINT,   'default' => 0, 'max' => 12),
				'dob_year'   => array('type' => self::TYPE_UINT,   'default' => 0, 'max' => 2100),
				'status'     => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 140),
				'status_date' => array('type' => self::TYPE_UINT, 'default' => 0),
				'status_profile_post_id' => array('type' => self::TYPE_UINT, 'default' => 0),
				'signature'  => array('type' => self::TYPE_STRING, 'default' => '', 'verification' => array('$this', '_verifyMessageLikeContent'), 'maxLength' => 20000),
				'homepage'   => array('type' => self::TYPE_STRING, 'default' => '', 'verification' => array('XenForo_DataWriter_Helper_Uri', 'verifyUriOrEmpty')),
				'location'   => array('type' => self::TYPE_STRING, 'default' => '', 'verification' => array('$this', '_verifyLocation'), 'maxLength' => 50),
				'occupation' => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 50),
				'following'  => array('type' => self::TYPE_STRING, 'default' => '', 'verification' => array('XenForo_DataWriter_Helper_Denormalization', 'verifyIntCommaList')),
				'ignored'    => array('type' => self::TYPE_SERIALIZED, 'default' => ''),
				'csrf_token' => array('type' => self::TYPE_STRING, 'default' => ''),
				'avatar_crop_x' => array('type' => self::TYPE_UINT, 'default' => 0),
				'avatar_crop_y' => array('type' => self::TYPE_UINT, 'default' => 0),
				'about'      => array('type' => self::TYPE_STRING, 'default' => '', 'verification' => array('$this', '_verifyMessageLikeContent'), 'maxLength' => 20000),
				'custom_fields' => array('type' => self::TYPE_SERIALIZED, 'default' => ''),
				'external_auth' => array('type' => self::TYPE_SERIALIZED, 'default' => ''),
				'password_date' => array('type' => self::TYPE_UINT, 'default' => 0),
			),
			'xf_user_option' => array(
				'user_id'
					=> array('type' => self::TYPE_UINT, 'default' => array('xf_user', 'user_id'), 'required' => true),
				'show_dob_year'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'show_dob_date'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'content_show_signature'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'receive_admin_email'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'email_on_conversation'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'is_discouraged'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'default_watch_state'
					=> array('type' => self::TYPE_STRING, 'default' => '', 'allowedValues' => array('', 'watch_no_email', 'watch_email')),
				'alert_optout'
					=> array('type' => self::TYPE_STRING, 'default' => ''),
				'enable_rte'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'enable_flash_uploader'
					=> array('type' => self::TYPE_BOOLEAN, 'default' => 1),
			),
			'xf_user_privacy' => array(
				'user_id'
					=> array('type' => self::TYPE_UINT, 'default' => array('xf_user', 'user_id'), 'required' => true),
				'allow_view_profile'
					=> array('type' => self::TYPE_STRING, 'default' => 'everyone', 'verification' => array('$this', '_verifyPrivacyChoice')),
				'allow_post_profile'
					=> array('type' => self::TYPE_STRING, 'default' => 'members', 'verification' => array('$this', '_verifyPrivacyChoice')),
				'allow_send_personal_conversation'
					=> array('type' => self::TYPE_STRING, 'default' => 'members', 'verification' => array('$this', '_verifyPrivacyChoice')),
				'allow_view_identities'
					=> array('type' => self::TYPE_STRING, 'default' => 'everyone', 'verification' => array('$this', '_verifyPrivacyChoice')),
				'allow_receive_news_feed'
					=> array('type' => self::TYPE_STRING, 'default' => 'everyone', 'verification' => array('$this', '_verifyPrivacyChoice')),
			),
			'xf_user_authenticate' => array(
				'user_id'      => array('type' => self::TYPE_UINT,   'default' => array('xf_user', 'user_id'), 'required' => true),
				'scheme_class' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 75),
				'data'         => array('type' => self::TYPE_BINARY, 'required' => true),
				'remember_key' => array('type' => self::TYPE_STRING, 'default' => '')
			)
		);
	}

	/**
	 * Changes to the following fields will not be logged by the user change log system
	 *
	 * @var array
	 */
	static $changeLogIgnoreFields = array
	(
		'user_id',
		'password',
		'style_id',
		'language_id',
		'display_style_group_id',
		'permission_combination_id',
		'message_count',
		'alerts_unread',
		'conversations_unread',
		'last_activity',
		'trophy_points',
		'avatar_width',
		'avatar_height',
		'like_count',
		'warning_points',

		'status',
		'status_date',
		'status_profile_post_id',
		'following',
		'ignored',
		'csrf_token',
		'avatar_crop_x',
		'avatar_crop_y',
		'external_auth',
		'password_date',

		'alert_optout',

		'scheme_class',
		'remember_key',
	);

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$userId = $this->_getExistingPrimaryKey($data, 'user_id'))
		{
			return false;
		}

		if (!$user = $this->_getUserModel()->getFullUserById($userId))
		{
			return false;
		}

		$returnData = $this->getTablesDataFromArray($user);
		$returnData['xf_user_authenticate'] = $this->_getUserModel()->getUserAuthenticationRecordByUserId($userId);

		return $returnData;
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'user_id = ' . $this->_db->quote($this->getExisting('user_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		$options = XenForo_Application::get('options');

		return array(
			self::OPTION_USERNAME_LENGTH_MIN => intval($options->get('usernameLength', 'min')),
			self::OPTION_USERNAME_LENGTH_MAX => intval($options->get('usernameLength', 'max')),
			self::OPTION_USERNAME_DISALLOWED_NAMES => preg_split('/\r?\n/', $options->get('usernameValidation', 'disallowedNames')),
			self::OPTION_USERNAME_REGEX => $options->get('usernameValidation', 'matchRegex'),
			self::OPTION_CUSTOM_TITLE_DISALLOWED => preg_split('/\r?\n/', $options->get('disallowedCustomTitles')),
			self::OPTION_ADMIN_EDIT => false,
			self::OPTION_ALLOW_DELETE_SELF => false,
			self::OPTION_REQUIRE_LOCATION => !empty($options->registrationSetup['requireLocation']),
			self::OPTION_MESSAGE_MAX_LENGTH => $options->messageMaxLength,
			self::OPTION_LOG_CHANGES => true,
		);
	}

	/**
	 * Set the full list of secondary user groups (IDs) the user belongs to.
	 *
	 * @param array $groups
	 */
	public function setSecondaryGroups(array $groups)
	{
		$groups = array_map('intval', $groups);
		$groups = array_unique($groups);
		sort($groups, SORT_NUMERIC);

		$zeroKey = array_search(0, $groups);
		if ($zeroKey !== false)
		{
			unset($groups[$zeroKey]);
		}

		$this->_secondaryGroups = $groups;
	}

	/**
	 * Set the full list of alerts from which the user opts out.
	 *
	 * @param array $optOuts [alert1 => true, alert2 => true]
	 */
	public function setAlertOptOuts(array $optOuts)
	{
		ksort($optOuts);
		$optOuts = array_keys($optOuts);

		$userId = $this->get('user_id');

		if ($optOuts !== array_keys(preg_split('/\s*,\s*/', $this->getExisting('alert_optout'), -1, PREG_SPLIT_NO_EMPTY)))
		{
			$this->_db->delete('xf_user_alert_optout', 'user_id = ' . $userId);

			foreach ($optOuts AS $alert)
			{
				$this->_db->insert('xf_user_alert_optout', array(
					'user_id' => $userId,
					'alert' => $alert
				));
			}

			$this->set('alert_optout', implode(',', $optOuts));
		}
	}

	public function setCustomFields(array $fieldValues, array $fieldsShown = null)
	{
		if ($fieldsShown === null)
		{
			// not passed - assume keys are all there
			$fieldsShown = array_keys($fieldValues);
		}

		$fieldModel = $this->_getFieldModel();
		$fields = $this->_getUserFieldDefinitions();

		if ($this->get('user_id') && !$this->_importMode)
		{
			$existingValues = $fieldModel->getUserFieldValues($this->get('user_id'));
		}
		else
		{
			$existingValues = array();
		}

		$finalValues = array();

		foreach ($fieldsShown AS $fieldId)
		{
			if (!isset($fields[$fieldId]))
			{
				continue;
			}

			$field = $fields[$fieldId];
			$multiChoice = ($field['field_type'] == 'checkbox' || $field['field_type'] == 'multiselect');

			if ($multiChoice)
			{
				// multi selection - array
				$value = array();
				if (isset($fieldValues[$fieldId]))
				{
					if (is_string($fieldValues[$fieldId]))
					{
						$value = array($fieldValues[$fieldId]);
					}
					else if (is_array($fieldValues[$fieldId]))
					{
						$value = $fieldValues[$fieldId];
					}
				}
			}
			else
			{
				// single selection - string
				if (isset($fieldValues[$fieldId]))
				{
					if (is_array($fieldValues[$fieldId]))
					{
						$value = count($fieldValues[$fieldId]) ? strval(reset($fieldValues[$fieldId])) : '';
					}
					else
					{
						$value = strval($fieldValues[$fieldId]);
					}
				}
				else
				{
					$value = '';
				}
			}

			$existingValue = (isset($existingValues[$fieldId]) ? $existingValues[$fieldId] : null);

			if (!$this->_importMode)
			{
				if (!$this->getOption(self::OPTION_ADMIN_EDIT))
				{
					if ($field['user_editable'] == 'never')
					{
						continue;
					}
					else if ($field['user_editable'] == 'once')
					{
						if ($existingValue !== null && $existingValue !== '' && $existingValue !== array())
						{
							continue;
						}
					}
				}

				$valid = $fieldModel->verifyUserFieldValue($field, $value, $error);
				if (!$valid)
				{
					$this->error($error, "custom_field_$fieldId");
					continue;
				}

				if (!$this->getOption(self::OPTION_ADMIN_EDIT) && $field['required'] && ($value === '' || $value === array()))
				{
					$this->error(new XenForo_Phrase('please_enter_value_for_all_required_fields'), "required");
					continue;
				}
			}

			if ($value !== $existingValue)
			{
				$finalValues[$fieldId] = $value;
			}
		}

		$this->_updateCustomFields = $finalValues + $this->_updateCustomFields;
		$this->set('custom_fields', $finalValues + $existingValues);
	}

	/**
	 * Sets the user's password.
	 *
	 * @param string $password
	 * @param string|false $passwordConfirm If a string, ensures that the password and the confirm are the same
	 * @param XenForo_Authentication_Abstract|null $auth Auth object to generate the password (or null to use default)
	 * @param boolean If true, do not accept an empty password
	 *
	 * @return boolean
	 */
	public function setPassword($password, $passwordConfirm = false, XenForo_Authentication_Abstract $auth = null, $requirePassword = false)
	{
		if ($requirePassword && $password === '')
		{
			$this->error(new XenForo_Phrase('please_enter_valid_password'), 'password');
			return false;
		}

		if ($passwordConfirm !== false && $password !== $passwordConfirm)
		{
			$this->error(new XenForo_Phrase('passwords_did_not_match'), 'password');
			return false;
		}

		if (!$auth)
		{
			$auth = XenForo_Authentication_Abstract::createDefault();
		}

		$authData = $auth->generate($password);
		if (!$authData)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_password'), 'password');
			return false;
		}

		$this->set('scheme_class', $auth->getClassName());
		$this->set('data', $authData, 'xf_user_authenticate');
		return true;
	}

	/**
	 * Advances the user state forward one step, following the registration rules.
	 *
	 * @param boolean $allowEmailConfirm
	 */
	public function advanceRegistrationUserState($allowEmailConfirm = true)
	{
		$options = XenForo_Application::get('options');

		if ($this->isInsert())
		{
			if (!$this->get('user_state'))
			{
				if ($options->get('registrationSetup', 'emailConfirmation') && $allowEmailConfirm)
				{
					$this->set('user_state', 'email_confirm');
				}
				else if ($options->get('registrationSetup', 'moderation'))
				{
					$this->set('user_state', 'moderated');
				}
				else
				{
					$this->set('user_state', 'valid');
				}
			}
		}
		else
		{
			switch ($this->get('user_state'))
			{
				case 'email_confirm':
					if ($options->get('registrationSetup', 'moderation'))
					{
						$this->set('user_state', 'moderated');
						break;
					}
					// otherwise, fall through

				case 'email_confirm_edit': // this is a user editing email, never send back to moderation
				case 'moderated':
					$this->set('user_state', 'valid');
					break;
			}
		}
	}

	/**
	* Verification callback to check that a username is valid
	*
	* @param string Username
	*
	* @return bool
	*/
	protected function _verifyUsername(&$username)
	{
		if ($this->isUpdate() && $username === $this->getExisting('username'))
		{
			return true; // unchanged, always pass
		}

		// standardize white space in names
		$username = preg_replace('/\s+/u', ' ', $username);
		try
		{
			// if this matches, then \v isn't known (appears to be PCRE < 7.2) so don't strip
			if (!preg_match('/\v/', 'v'))
			{
				$newName = preg_replace('/\v+/u', ' ', $username);
				if (is_string($newName))
				{
					$username = $newName;
				}
			}
		}
		catch (Exception $e) {}

		$username = trim($username);

		$usernameLength = utf8_strlen($username);
		$minLength = $this->getOption(self::OPTION_USERNAME_LENGTH_MIN);
		$maxLength = $this->getOption(self::OPTION_USERNAME_LENGTH_MAX);

		if (!$this->getOption(self::OPTION_ADMIN_EDIT))
		{
			if ($minLength > 0 && $usernameLength < $minLength)
			{
				$this->error(new XenForo_Phrase('please_enter_name_that_is_at_least_x_characters_long', array('count' => $minLength)), 'username');
				return false;
			}
			if ($maxLength > 0 && $usernameLength > $maxLength)
			{
				$this->error(new XenForo_Phrase('please_enter_name_that_is_at_most_x_characters_long', array('count' => $maxLength)), 'username');
				return false;
			}

			$disallowedNames = $this->getOption(self::OPTION_USERNAME_DISALLOWED_NAMES);
			if ($disallowedNames)
			{
				foreach ($disallowedNames AS $name)
				{
					$name = trim($name);
					if ($name === '')
					{
						continue;
					}
					if (stripos($username, $name) !== false)
					{
						$this->error(new XenForo_Phrase('please_enter_another_name_disallowed_words'), 'username');
						return false;
					}
				}
			}

			$matchRegex = $this->getOption(self::OPTION_USERNAME_REGEX);
			if ($matchRegex)
			{
				$matchRegex = str_replace('#', '\\#', $matchRegex); // escape delim only
				if (!preg_match('#' . $matchRegex . '#i', $username))
				{
					$this->error(new XenForo_Phrase('please_enter_another_name_required_format'), 'username');
					return false;
				}
			}

			$censoredUserName = XenForo_Helper_String::censorString($username);
			if ($censoredUserName !== $username)
			{
				$this->error(new XenForo_Phrase('please_enter_name_that_does_not_contain_any_censored_words'), 'username');
				return false;
			}
		}

		// ignore check if unicode properties aren't compiled
		try
		{
			if (@preg_match("/\p{C}/u", $username))
			{
				$this->error(new XenForo_Phrase('please_enter_name_without_using_control_characters'), 'username');
				return false;
			}
		}
		catch (Exception $e) {}

		if (strpos($username, ',') !== false)
		{
			$this->error(new XenForo_Phrase('please_enter_name_that_does_not_contain_comma'), 'username');
			return false;
		}

		if (XenForo_Helper_Email::isEmailValid($username))
		{
			$this->error(new XenForo_Phrase('please_enter_name_that_does_not_resemble_an_email_address'), 'username');
			return false;
		}

		$existingUser = $this->_getUserModel()->getUserByName($username);
		if ($existingUser && $existingUser['user_id'] != $this->get('user_id'))
		{
			$this->error(new XenForo_Phrase('usernames_must_be_unique'), 'username');
			return false;
		}

		// compare against romanized name to help reduce confusable issues
		$romanized = utf8_deaccent(utf8_romanize($username));
		if ($romanized != $username)
		{
			$existingUser = $this->_getUserModel()->getUserByName($romanized);
			if ($existingUser && $existingUser['user_id'] != $this->get('user_id'))
			{
				$this->error(new XenForo_Phrase('usernames_must_be_unique'), 'username');
				return false;
			}
		}

		return true;
	}

	/**
	* Verification callback to check the email address is in a valid form
	*
	* @param string Email Address
	*
	* @return bool
	*/
	protected function _verifyEmail(&$email)
	{
		if ($this->isUpdate() && $email === $this->getExisting('email'))
		{
			return true;
		}

		if ($this->getOption(self::OPTION_ADMIN_EDIT) && $email === '')
		{
			return true;
		}

		if (!XenForo_Helper_Email::isEmailValid($email))
		{
			$this->error(new XenForo_Phrase('please_enter_valid_email'), 'email');
			return false;
		}

		$existingUser = $this->_getUserModel()->getUserByEmail($email);
		if ($existingUser && $existingUser['user_id'] != $this->get('user_id'))
		{
			$this->error(new XenForo_Phrase('email_addresses_must_be_unique'), 'email');
			return false;
		}

		if (XenForo_Helper_Email::isEmailBanned($email))
		{
			$this->error(new XenForo_Phrase('email_address_you_entered_has_been_banned_by_administrator'), 'email');
			return false;
		}

		return true;
	}

	protected function _verifyLocation(&$location)
	{
		if ($this->getOption(self::OPTION_ADMIN_EDIT))
		{
			return true;
		}

		if ($this->isUpdate() && $location === $this->getExisting('location'))
		{
			return true;
		}

		if ($this->getOption(self::OPTION_REQUIRE_LOCATION) && $location === '')
		{
			$this->error(new XenForo_Phrase('please_enter_valid_location'), 'location');
			return false;
		}

		return true;
	}

	protected function _verifyMessageLikeContent(&$message, $null, $fieldName)
	{
		$maxLength = $this->getOption(self::OPTION_MESSAGE_MAX_LENGTH);
		if ($maxLength && utf8_strlen($message) > $maxLength)
		{
			$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_characters', array('count' => $maxLength)), $fieldName);
			return false;
		}

		return true;
	}

	protected function _verifyTimezone(&$timezone)
	{
		try
		{
			new DateTimeZone($timezone);
		}
		catch (Exception $e)
		{
			$this->error(new XenForo_Phrase('please_select_valid_time_zone'), 'timezone');
			return false;
		}

		return true;
	}

	protected function _verifyLanguageId(&$languageId)
	{
		if ($languageId === 0 || $this->getModelFromCache('XenForo_Model_Language')->getLanguageById($languageId))
		{
			return true;
		}

		$languageId = 0;
		return true;
	}

	protected function _verifyStyleId(&$styleId)
	{
		if ($styleId === 0 || $this->getModelFromCache('XenForo_Model_Style')->getStyleById($styleId))
		{
			return true;
		}

		$styleId = 0;
		return true;
	}

	/**
	 * Verifies that a gravatar email address is valid, or empty
	 *
	 * @param string $gravatarEmail
	 *
	 * @return boolean
	 */
	protected function _verifyGravatar(&$gravatarEmail)
	{
		if ($gravatarEmail !== '' && !XenForo_Helper_Email::isEmailValid($gravatarEmail))
		{
			$this->error(new XenForo_Phrase('please_enter_valid_email'), 'gravatar');
			return false;
		}

		return true;
	}

	/**
	 * Validates a custom user title, checking for blocked terms, banned markup etc.
	 *
	 * @param string $title
	 *
	 * @return boolean
	 */
	protected function _verifyCustomTitle(&$title)
	{
		if (!$this->getOption(self::OPTION_ADMIN_EDIT))
		{
			if ($title === $this->getExisting('custom_title'))
			{
				return true; // can always keep the existing value
			}

			if ($title !== XenForo_Helper_String::censorString($title))
			{
				$this->error(new XenForo_Phrase('please_enter_custom_title_that_does_not_contain_any_censored_words'), 'custom_title');
				return false;
			}

			$disallowed = $this->getOption(self::OPTION_CUSTOM_TITLE_DISALLOWED);
			if ($disallowed && !$this->get('is_moderator') && !$this->get('is_admin'))
			{
				foreach ($disallowed AS $value)
				{
					$value = trim($value);
					if ($value === '')
					{
						continue;
					}
					if (stripos($title, $value) !== false)
					{
						$this->error(new XenForo_Phrase('please_enter_another_custom_title_disallowed_words'), 'custom_title');
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Verification callback for privacy choice field
	 * Valid:
	 * * everyone
	 * * members
	 * * followed
	 * * none
	 *
	 * @param string Choice
	 *
	 * @return boolean
	 */
	protected function _verifyPrivacyChoice(&$choice, $dw, $fieldName)
	{
		if (!in_array(strtolower($choice), array('everyone', 'members', 'followed', 'none')))
		{
			$choice = 'none';
		}

		return true;
	}

	/**
	 * Pre-save default setting.
	 */
	protected function _preSaveDefaults()
	{
		if (is_array($this->_secondaryGroups))
		{
			$primaryGroupKey = array_search($this->get('user_group_id'), $this->_secondaryGroups);
			if ($primaryGroupKey !== false)
			{
				unset($this->_secondaryGroups[$primaryGroupKey]);
			}

			$this->set('secondary_group_ids', implode(',', $this->_secondaryGroups));
		}

		if ($this->isChanged('scheme_class', 'xf_user_authenticate') || $this->isChanged('data', 'xf_user_authenticate'))
		{
			$this->set('remember_key', XenForo_Application::generateRandomString(40));
			$this->set('password_date', XenForo_Application::$time);
		}

		if (!$this->get('csrf_token'))
		{
			$this->set('csrf_token', XenForo_Application::generateRandomString(40));
		}
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		$this->checkDob();

		if (!$this->get('scheme_class', 'xf_user_authenticate') || !$this->get('data', 'xf_user_authenticate'))
		{
			$this->error(new XenForo_Phrase('please_enter_valid_password'), 'password', false);

			// prevent required errors
			$this->set('scheme_class', 'invalid');
			$this->set('data', 'invalid', 'xf_user_authenticate');
		}

		if (!$this->get('language_id'))
		{
			$this->set('language_id', XenForo_Application::get('options')->defaultLanguageId);
		}

		if ($this->isUpdate() && $this->isChanged('email')
			&& $this->get('user_state') == 'email_bounce' && !$this->isChanged('user_state')
		)
		{
			if (XenForo_Application::get('options')->get('registrationSetup', 'emailConfirmation'))
			{
				$this->set('user_state', 'email_confirm_edit');
			}
			else
			{
				$this->set('user_state', 'valid');
			}
		}
	}

	/**
	 * Checks that the date of birth entered is valid. If not entered or not changed,
	 * it's valid.
	 *
	 * @return boolean
	 */
	public function checkDob()
	{
		if ($this->isChanged('dob_day') || $this->isChanged('dob_month') || $this->isChanged('dob_year'))
		{
			if (!$this->get('dob_day') || !$this->get('dob_month'))
			{
				$this->set('dob_day', 0);
				$this->set('dob_month', 0);
				$this->set('dob_year', 0);
			}
			else
			{
				$year = $this->get('dob_year');
				if (!$year)
				{
					$year = 2008; // pick a leap year to be sure
				}
				else if ($year < 100)
				{
					$year += ($year < 30 ? 2000 : 1900);
					$this->set('dob_year', $year);
				}

				if ($year > intval(date('Y'))
					|| $year < 1900
					|| !checkdate($this->get('dob_month'), $this->get('dob_day'), $year)
					|| gmmktime(0, 0, 0, $this->get('dob_month'), $this->get('dob_day'), $year) > XenForo_Application::$time + 86400 // +1 day to be careful with TZs ahead of GMT
				)
				{
					if ($this->_importMode)
					{
						// don't error, wipe it out
						$this->set('dob_day', 0);
						$this->set('dob_month', 0);
						$this->set('dob_year', 0);
					}
					else
					{
						$this->error(new XenForo_Phrase('please_enter_valid_date_of_birth'), 'dob');
					}

					return false;
				}
			}
		}

		return true;
	}

	/**
	 * List of denormalised username fields in tables that should
	 * be updated in the event that a username is changed.
	 *
	 * @var array [query-key => [tablename, username-field, user-id-field]]
	 */
	public static $usernameChangeUpdates = array();

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$db = $this->_db;
		$userId = $this->get('user_id');

		if ($this->isChanged('secondary_group_ids') || $this->isChanged('user_group_id'))
		{
			// TODO: update user group change list to remove any group IDs that have been removed
			$this->rebuildUserGroupRelations();
		}

		if ($this->isChanged('user_group_id') || $this->isChanged('secondary_group_ids'))
		{
			$this->rebuildPermissionCombinationId();

			if (!$this->isChanged('display_style_group_id') || !$this->get('display_style_group_id'))
			{
				$this->rebuildDisplayStyleGroupId();
			}
		}

		$this->updateCustomFields();

		if ($this->isUpdate())
		{
			if ($this->isChanged('warning_points'))
			{
				$this->getModelFromCache('XenForo_Model_Warning')->userWarningPointsChanged(
					$this->get('user_id'), $this->get('warning_points'), $this->getExisting('warning_points')
				);
			}

			// publish events to news feed
			$this->_publishIfChanged('location');
			$this->_publishIfChanged('occupation');
			$this->_publishIfChanged('homepage');

			$this->_publishIfAvatarChanged();

			//TODO: handle publishing custom fields

			// handle username change updates
			if ($this->isChanged('username') && XenForo_Application::get('options')->updateContentOnUsernameChange)
			{
				$this->_getUserModel()->changeContentUser($userId, $this->get('username'), $this->getExisting('username'));
			}

			if ($this->isChanged('signature'))
			{
				$this->getModelFromCache('XenForo_Model_BbCode')->deleteBbCodeParseCacheForContent(
					'signature', $this->get('user_id')
				);
			}
		}

		if ($this->isChanged('user_state') && ($this->get('user_state') == 'moderated' || $this->getExisting('user_state') == 'moderated')) {
			$this->getModelFromCache('XenForo_Model_User')->rebuildUserModerationQueueCache();
		}

		// user change logging, only on regular updates
		if ($this->isUpdate() && !$this->_importMode && $this->getOption(self::OPTION_LOG_CHANGES))
		{
			if ($changes = $this->getNewData())
			{
				$changedFields = array();

				foreach ($changes AS $table => $fields)
				{
					foreach ($fields AS $field => $newValue)
					{
						if (!in_array($field, self::$changeLogIgnoreFields))
						{
							$oldValue = $this->getExisting($field, $table);

							if ($oldValue != $newValue)
							{
								if ($table == 'xf_user_authenticate' && $field == 'data')
								{
									$oldValue = '*****';
									$newValue = '******';
								}

								$changedFields[$field] = array($oldValue, $newValue);
							}
						}
					}
				}

				if (!empty($changedFields))
				{
					$this->_getUserModel()->logChanges($userId, $changedFields);
				}
			}
		}
	}

	public function rebuildUserGroupRelations()
	{
		$db = $this->_db;
		$userId = intval($this->get('user_id'));
		$userIdQuoted = $db->quote($userId);

		$inserts = array();
		$inserts[] = '(' . $userIdQuoted . ', ' . $db->quote($this->get('user_group_id')) . ', 1)';

		$secondaryGroups = ($this->get('secondary_group_ids') ? explode(',', $this->get('secondary_group_ids')) : array());
		foreach ($secondaryGroups AS $groupId)
		{
			$inserts[] = '(' . $userIdQuoted . ', ' . $db->quote($groupId) . ', 0)';
		}

		$db->delete('xf_user_group_relation', 'user_id = ' . $userIdQuoted);
		$db->query('
			INSERT INTO xf_user_group_relation
				(user_id, user_group_id, is_primary)
			VALUES
				' . implode(',', $inserts) . '
			ON DUPLICATE KEY UPDATE
				is_primary = VALUES(is_primary)
		');
	}

	public function rebuildPermissionCombinationId($checkForUserPerms = true)
	{
		$combinationId = $this->_getPermissionModel()->updateUserPermissionCombination(
			$this->getMergedData(), true, $checkForUserPerms
		);
		$this->_setPostSave('permission_combination_id', $combinationId);
	}

	public function rebuildDisplayStyleGroupId()
	{
		$db = $this->_db;

		$groups = ($this->get('secondary_group_ids') ? explode(',', $this->get('secondary_group_ids')) : array());
		$groups[] = $this->get('user_group_id');

		$displayStyleGroupId = $this->getModelFromCache('XenForo_Model_UserGroup')->getDisplayStyleGroupIdForCombination($groups);

		if ($displayStyleGroupId !== $this->get('display_style_group_id'))
		{
			$this->_setPostSave('display_style_group_id', $displayStyleGroupId);
			$db->update('xf_user',
				array('display_style_group_id' => $displayStyleGroupId),
				'user_id = ' . $db->quote($this->get('user_id'))
			);
		}
	}

	public function rebuildCustomFields()
	{
		$this->_getUserModel()->rebuildCustomFieldCache($this->get('user_id'));
	}

	public function rebuildIgnoreCache()
	{
		$users = $this->_getUserIgnoreModel()->getUserIgnoreCache($this->get('user_id'));
		$this->_setPostSave('ignored', $users);

		$db = $this->_db;
		$db->update('xf_user_profile',
			array('ignored' => serialize($users)),
			'user_id = ' . $db->quote($this->get('user_id'))
		);
	}

	public function updateCustomFields()
	{
		if ($this->_updateCustomFields)
		{
			$userId = $this->get('user_id');

			foreach ($this->_updateCustomFields AS $fieldId => $value)
			{
				if (is_array($value))
				{
					$value = serialize($value);
				}
				$this->_db->query('
					INSERT INTO xf_user_field_value
						(user_id, field_id, field_value)
					VALUES
						(?, ?, ?)
					ON DUPLICATE KEY UPDATE
						field_value = VALUES(field_value)
				', array($userId, $fieldId, $value));
			}
		}
	}

	protected function _publishIfAvatarChanged()
	{
		// avatar
		$publishAvatarChange = false;

		/*
		 * Logic: publish if:
		 * gravatar has changed and gravatar is not empty
		 * or
		 * gravatar has changed and become empty but avatar_date is non-zero
		 * or
		 * avatar_date has changed and last change was > 24 hours ago
		 */

		// if gravatar has changed value
		if ($this->isChanged('gravatar'))
		{
			// if gravatar is not empty or avatar_date is non-zero
			if ($this->getNew('gravatar') != '' || $this->get('avatar_date'))
			{
				$publishAvatarChange = true;
			}
		}
		else if ($this->isChanged('avatar_date'))
		{
			if ($this->get('avatar_date') - $this->getExisting('avatar_date') > 86400)
			{
				$publishAvatarChange = true;
			}
		}

		if ($publishAvatarChange)
		{
			$this->_publish('avatar_change');
		}
	}

	/**
	 * Wrapper around _publish. Will publish 'old' and 'new' versions of the field
	 * specified if they are different, provided that 'new' is not empty.
	 *
	 * @param string $fieldName
	 */
	protected function _publishIfChanged($fieldName)
	{
		if ($this->isChanged($fieldName) && $newValue = $this->get($fieldName))
		{
			$this->_publish($fieldName, array(
				'old' => $this->getExisting($fieldName),
				'new' => $newValue
			));
		}
	}

	/**
	 * Publish an item to the news feed
	 *
	 * @param string action
	 * @param mixed extra data
	 */
	protected function _publish($action, $extraData = null)
	{
		if ($this->get('user_id') != XenForo_Visitor::getUserId())
		{
			return;
		}

		$this->_getNewsFeedModel()->publish(
			$this->get('user_id'),
			$this->get('username'),
			'user',
			0,
			$action,
			$extraData
		);
	}

	protected function _preDelete()
	{
		if (!$this->getOption(self::OPTION_ALLOW_DELETE_SELF))
		{
			if ($this->get('user_id') == XenForo_Visitor::getUserId())
			{
				$this->error(new XenForo_Phrase('you_cannot_delete_your_own_account'));
			}
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$db = $this->_db;
		$userId = $this->get('user_id');
		$userIdQuoted = $db->quote($userId);

		$db->delete('xf_user_alert', "alerted_user_id = $userIdQuoted");
		$db->delete('xf_user_alert_optout', "user_id = $userIdQuoted");
		$db->delete('xf_user_change_log', "user_id = $userIdQuoted");
		$db->delete('xf_user_change_temp', "user_id = $userIdQuoted");
		$db->delete('xf_user_confirmation', "user_id = $userIdQuoted");
		$db->delete('xf_user_external_auth', "user_id = $userIdQuoted");
		$db->delete('xf_user_field_value', "user_id = $userIdQuoted");
		$db->delete('xf_user_follow', "follow_user_id = $userIdQuoted");
        $db->delete('xf_user_follow', "user_id = $userIdQuoted");
		$db->delete('xf_user_group_change', "user_id = $userIdQuoted");
		$db->delete('xf_user_group_promotion_log', "user_id = $userIdQuoted");
		$db->delete('xf_user_group_relation', "user_id = $userIdQuoted");
		$db->delete('xf_user_ignored', "ignored_user_id = $userIdQuoted");
        $db->delete('xf_user_ignored', "user_id = $userIdQuoted");
		$db->delete('xf_user_news_feed_cache', "user_id = $userIdQuoted");
		$db->delete('xf_user_trophy', "user_id = $userIdQuoted");
		$db->delete('xf_user_upgrade_active', "user_id = $userIdQuoted");
		$db->delete('xf_user_upgrade_expired', "user_id = $userIdQuoted");
		$db->delete('xf_warning', "user_id = $userIdQuoted");

		$db->delete('xf_permission_combination', "user_id = $userIdQuoted");
		$db->delete('xf_permission_entry', "user_id = $userIdQuoted");
		$db->delete('xf_permission_entry_content', "user_id = $userIdQuoted");

		if ($this->get('is_moderator'))
		{
			$db->delete('xf_moderator',  "user_id = $userIdQuoted");
			$db->delete('xf_moderator_content',  "user_id = $userIdQuoted");
		}
		if ($this->get('is_admin'))
		{
			$db->delete('xf_admin',  "user_id = $userIdQuoted");
			$db->delete('xf_admin_permission_entry',  "user_id = $userIdQuoted");
		}
		if ($this->get('is_banned'))
		{
			$db->delete('xf_user_ban',  "user_id = $userIdQuoted");
		}

		$db->delete('xf_profile_post', "profile_user_id = $userIdQuoted");

		$db->delete('xf_news_feed', "user_id = $userIdQuoted");

		$db->delete('xf_conversation_user', "owner_user_id = $userIdQuoted");
		// note: leaving records in conversation recipient to keep data somewhat intact for others

		$db->delete('xf_ip', "content_type = 'user' AND user_id = $userIdQuoted");
		// note: leaving content-associated IPs

		$db->delete('xf_forum_read', "user_id = $userIdQuoted");
		$db->delete('xf_thread_read', "user_id = $userIdQuoted");
		$db->delete('xf_thread_reply_ban', "user_id = $userIdQuoted");
		$db->delete('xf_thread_user_post', "user_id = $userIdQuoted");
		$db->delete('xf_forum_watch', "user_id = $userIdQuoted");
		$db->delete('xf_thread_watch', "user_id = $userIdQuoted");

		$this->getModelFromCache('XenForo_Model_Avatar')->deleteAvatar($userId, false);

		if ($this->get('user_state') == 'moderated')
		{
			$this->getModelFromCache('XenForo_Model_User')->rebuildUserModerationQueueCache();
		}

		$this->getModelFromCache('XenForo_Model_BbCode')->deleteBbCodeParseCacheForContent(
			'signature', $this->get('user_id')
		);

		$this->_getUserModel()->changeContentUser($userId, $this->get('username'), $this->get('username'), 0);
	}

	/**
	 * @return XenForo_Model_Permission
	 */
	protected function _getPermissionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Permission');
	}

	/**
	 * Fetch (and cache) user field definitions
	 *
	 * @return array
	 */
	protected function _getUserFieldDefinitions()
	{
		$fields = $this->getExtraData(self::DATA_USER_FIELD_DEFINITIONS);

		if (is_null($fields))
		{
			$fields = $this->_getFieldModel()->getUserFields();

			$this->setExtraData(self::DATA_USER_FIELD_DEFINITIONS, $fields);
		}

		return $fields;
	}

	/**
	 * @return XenForo_Model_UserField
	 */
	protected function _getFieldModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserField');
	}
}
