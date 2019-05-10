<?php

class XenForo_Helper_UserChangeLog
{
	protected $_fieldInfo = array(
		'username'            => 'user_name',
		'email'               => 'email_address',
		'gender'              => array('gender', '_prepareGender'),
		'custom_title'        => 'custom_title',
		'timezone'            => array('time_zone', '_prepareTimeZone'),
		'visible'             => array('show_online_status', '_prepareYesNoField'),
		'activity_visible'    => array('show_current_activity', '_prepareYesNoField'),
		'user_group_id'       => 'user_group',
		'secondary_group_ids' => 'secondary_user_groups',
		'avatar_date'         => array('avatar_date', '_prepareDateField'),
		'register_date'       => array('joined', '_prepareDateField'),
		'gravatar'            => 'gravatar',
		'user_state'          => array('user_state', '_prepareUserState'),
		'is_moderator'        => array('moderator', '_prepareYesNoField'),
		'is_admin'            => array('administrator', '_prepareYesNoField'),
		'is_staff'            => array('staff_member', '_prepareYesNoField'),
		'is_banned'           => array('banned', '_prepareYesNoField'),

		'dob_day'       => 'dob_day',
		'dob_month'     => 'dob_month',
		'dob_year'      => 'dob_year',
		'signature'     => 'signature',
		'homepage'      => 'home_page',
		'location'      => 'location',
		'occupation'    => 'occupation',
		'about'         => 'about_you',
		'custom_fields' => 'custom_user_fields',

		'show_dob_year'          => array('show_year_of_birth', '_prepareYesNoField'),
		'show_dob_date'          => array('show_day_and_month_of_birth', '_prepareYesNoField'),
		'content_show_signature' => array('show_signatures_with_messages', '_prepareYesNoField'),
		'receive_admin_email'    => array('receive_site_mailings', '_prepareYesNoField'),
		'email_on_conversation'  => array('email_conversation_notifications', '_prepareYesNoField'),
		'is_discouraged'         => array('discouraged', '_prepareYesNoField'),
		'enable_rte'             => array('enable_rte', '_prepareYesNoField'),
		'enable_flash_uploader'  => array('enable_flash_uploader', '_prepareYesNoField'),
		'default_watch_state'    => array('watch_threads_when_creating_or_replying', '_prepareWatchState'),

		'allow_view_profile'               => array('view_your_details_on_your_profile_page', '_preparePrivacyField'),
		'allow_post_profile'               => array('post_messages_on_your_profile_page', '_preparePrivacyField'),
		'allow_send_personal_conversation' => array('start_conversations_with_you', '_preparePrivacyField'),
		'allow_view_identities'            => array('view_your_identities', '_preparePrivacyField'),
		'allow_receive_news_feed'          => array('receive_your_news_feed', '_preparePrivacyField'),

		'scheme_class' => 'authentication_scheme_class',
		'data'         => 'password', // special case!
	);

	/**
	 * Cache of usergroupId => title
	 *
	 * @var array
	 */
	protected $_userGroups = null;

	/**
	 * Cache of custom user fields info
	 *
	 * @var array
	 */
	protected $_customFields = null;

	protected $_userFieldModel = null;

	public function prepareField(array $field)
	{
		$colonPosition = strpos($field['field'], ':');
		if ($colonPosition !== false)
		{
			return $this->_prepareCustomField(substr($field['field'], $colonPosition + 1), $field);
		}
		else
		{
			return $this->_prepareField($field);
		}
	}

	/**
	 * @return XenForo_Model_UserField
	 */
	protected function _getUserFieldModel()
	{
		if ($this->_userFieldModel === null)
		{
			$this->_userFieldModel = XenForo_Model::create('XenForo_Model_UserField');
		}

		return $this->_userFieldModel;
	}

	protected function _getCustomFieldInfo()
	{
		if ($this->_customFields === null)
		{
			$this->_customFields = $this->_getUserFieldModel()->getUserFields();
		}

		return $this->_customFields;
	}

	protected function _prepareCustomFieldArrayValue(array $fieldChoices, $fieldChoice)
	{
		$value = array();

		if ($fieldChoice && is_array($fieldChoice))
		{
			foreach ($fieldChoice AS $choice)
			{
				$value[] = isset($fieldChoices[$choice]) ? $fieldChoices[$choice] : $choice;
			}
		}

		return implode(', ', $value);
	}

	protected function _prepareCustomField($fieldName, array $field)
	{
		$userFieldInfo = $this->_getCustomFieldInfo();

		if (isset($userFieldInfo[$fieldName]))
		{
			$fieldInfo = $userFieldInfo[$fieldName];

			if ($fieldInfo['field_type'] == 'checkbox' || $fieldInfo['field_type'] == 'multiselect')
			{
				$fieldChoices = @unserialize($fieldInfo['field_choices']);

				$field['old_value'] = $this->_prepareCustomFieldArrayValue($fieldChoices, @unserialize($field['old_value']));
				$field['new_value'] = $this->_prepareCustomFieldArrayValue($fieldChoices, @unserialize($field['new_value']));
			}
			else if ($fieldInfo['field_type'] == 'radio' || $fieldInfo['field_type'] == 'select')
			{
				$fieldChoices = @unserialize($fieldInfo['field_choices']);

				$field['old_value'] = (empty($field['old_value']) ? '' : $fieldChoices[$field['old_value']]);
				$field['new_value'] = (empty($field['new_value']) ? '' : $fieldChoices[$field['new_value']]);
			}

			$field['name'] = new XenForo_Phrase('user_field_' . $fieldName);
		}
		else
		{
			$field['name'] = $fieldName;
		}

		return $field;
	}

	protected function _getFieldPhraseName($fieldName)
	{
		if (isset($this->_fieldInfo[$fieldName]))
		{
			if (is_array($this->_fieldInfo[$fieldName]))
			{
				return $this->_fieldInfo[$fieldName][0];
			}
			else
			{
				return $this->_fieldInfo[$fieldName];
			}
		}
		else
		{
			return $fieldName;
		}
	}

	protected function _prepareField(array $field)
	{
		if (!isset($field['name']))
		{
			$field['name'] = new XenForo_Phrase($this->_getFieldPhraseName($field['field']));
		}

		$methodName = $this->_getPrepareMethodName($field['field']);
		if (method_exists($this, $methodName))
		{
			$field['old_value'] = call_user_func(array($this, $methodName), $field['old_value']);
			$field['new_value'] = call_user_func(array($this, $methodName), $field['new_value']);
		}

		return $field;
	}

	protected function _getPrepareMethodName($fieldName)
	{
		if (isset($this->_fieldInfo[$fieldName]) && is_array($this->_fieldInfo[$fieldName]))
		{
			return $this->_fieldInfo[$fieldName][1];
		}
		else
		{
			return '_prepareField' . str_replace('_', '', $fieldName);
		}
	}

	protected function _prepareFieldUserGroupId($value)
	{
		if ($this->_userGroups === null)
		{
			$this->_userGroups = XenForo_Application::getDb()->fetchPairs('SELECT user_group_id, title FROM xf_user_group');
		}

		if (isset($this->_userGroups[$value]))
		{
			return $this->_userGroups[$value];
		}
		else
		{
			return $value;
		}
	}

	protected function _prepareFieldSecondaryGroupIds($value)
	{
		$ids = preg_split('/,/', $value, -1, PREG_SPLIT_NO_EMPTY);

		foreach ($ids AS &$id)
		{
			$id = $this->_prepareFieldUserGroupId($id);
		}

		return implode(', ', $ids);
	}

	protected function _prepareTimeZone($value)
	{
		$tzs = XenForo_Helper_TimeZone::getTimeZones();
		return isset($tzs[$value]) ? $tzs[$value] : $value;
	}

	protected function _prepareGender($value)
	{
		switch ($value)
		{
			case 'male': return new XenForo_Phrase('male');
			case 'female': return new XenForo_Phrase('female');
			case '': return new XenForo_Phrase('unspecified');

			default: return $value;
		}
	}

	protected function _prepareUserState($value)
	{
		switch ($value)
		{
			case 'valid': return new XenForo_Phrase('valid');
			case 'email_confirm': return new XenForo_Phrase('awaiting_email_confirmation');
			case 'email_confirm_edit': return new XenForo_Phrase('awaiting_email_confirmation_from_edit');
			case 'email_bounce': return new XenForo_Phrase('email_invalid_bounced');
			case 'moderated': return new XenForo_Phrase('awaiting_approval');

			default: return $value;
		}
	}

	protected function _prepareWatchState($value)
	{
		switch ($value)
		{
			case 'watch_no_email': return new XenForo_Phrase('yes');
			case 'watch_email': return new XenForo_Phrase('yes_with_email');
			case '': return new XenForo_Phrase('no');

			default: return $value;
		}
	}

	protected function _preparePrivacyField($value)
	{
		switch ($value)
		{
			case 'everyone': return new XenForo_Phrase('all_visitors');
			case 'members': return new XenForo_Phrase('members_only');
			case 'followed': return new XenForo_Phrase('followed_members_only');
			case 'none': return new XenForo_Phrase('nobody');

			default: return $value;
		}
	}

	protected function _prepareDateField($value)
	{
		if ($value)
		{
			return XenForo_Locale::date($value);
		}
		else
		{
			return '';
		}
	}

	protected function _prepareYesNoField($value)
	{
		return new XenForo_Phrase($value ? 'yes' : 'no');
	}
}