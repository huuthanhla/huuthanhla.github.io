<?php

/**
 * Model for custom user fields.
 */
class XenForo_Model_UserField extends XenForo_Model
{
	/**
	 * Gets a custom user field by ID.
	 *
	 * @param string $fieldId
	 *
	 * @return array|false
	 */
	public function getUserFieldById($fieldId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_user_field
			WHERE field_id = ?
		', $fieldId);
	}

	/**
	 * Gets custom user fields that match the specified criteria.
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array [field id] => info
	 */
	public function getUserFields(array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->prepareUserFieldConditions($conditions, $fetchOptions);
		$joinOptions = $this->prepareUserFieldFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT user_field.*
				' . $joinOptions['selectFields'] . '
			FROM xf_user_field AS user_field
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause . '
			ORDER BY user_field.display_group, user_field.display_order
		', 'field_id');
	}

	/**
	 * Prepares a set of conditions to select fields against.
	 *
	 * @param array $conditions List of conditions.
	 * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	 *
	 * @return string Criteria as SQL for where clause
	 */
	public function prepareUserFieldConditions(array $conditions, array &$fetchOptions)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['display_group']))
		{
			$sqlConditions[] = 'user_field.display_group = ' . $db->quote($conditions['display_group']);
		}

		if (!empty($conditions['profileView']))
		{
			$sqlConditions[] = 'user_field.display_group <> \'preferences\' AND user_field.viewable_profile = 1';
		}

		if (!empty($conditions['messageView']))
		{
			$sqlConditions[] = 'user_field.display_group <> \'preferences\' AND user_field.viewable_message = 1';
		}

		if (!empty($conditions['registration']))
		{
			$sqlConditions[] = 'user_field.required = 1 OR user_field.show_registration = 1';
		}

		if (isset($conditions['moderator_editable']))
		{
			$sqlConditions[] = 'user_field.moderator_editable = ' . ($conditions['moderator_editable'] ? 1 : 0);
		}

		if (!empty($conditions['adminQuickSearch']))
		{
			$searchStringSql = 'CONVERT(user_field.field_id USING utf8) LIKE ' . XenForo_Db::quoteLike($conditions['adminQuickSearch']['searchText'], 'lr');

			if (!empty($conditions['adminQuickSearch']['phraseMatches']))
			{
				$sqlConditions[] = '(' . $searchStringSql . ' OR CONVERT(user_field.field_id USING utf8) IN (' . $db->quote($conditions['adminQuickSearch']['phraseMatches']) . '))';
			}
			else
			{
				$sqlConditions[] = $searchStringSql;
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Prepares join-related fetch options.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys.
	 */
	public function prepareUserFieldFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		$db = $this->_getDb();

		if (!empty($fetchOptions['valueUserId']))
		{
			$selectFields .= ',
				field_value.field_value';
			$joinTables .= '
				LEFT JOIN xf_user_field_value AS field_value ON
					(field_value.field_id = user_field.field_id AND field_value.user_id = ' . $db->quote($fetchOptions['valueUserId']) . ')';
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	/**
	 * Groups user fields by their display group.
	 *
	 * @param array $fields
	 *
	 * @return array [display group][key] => info
	 */
	public function groupUserFields(array $fields)
	{
		$return = array();

		foreach ($fields AS $fieldId => $field)
		{
			$return[$field['display_group']][$fieldId] = $field;
		}

		return $return;
	}

	/**
	 * Prepares a user field for display.
	 *
	 * @param array $field
	 * @param boolean $getFieldChoices If true, gets the choice options for this field (as phrases)
	 * @param mixed $fieldValue If not null, the value for the field; if null, pulled from field_value
	 * @param boolean $valueSaved If true, considers the value passed to be saved; should be false on registration
	 *
	 * @return array Prepared field
	 */
	public function prepareUserField(array $field, $getFieldChoices = false, $fieldValue = null, $valueSaved = true)
	{
		$field['isMultiChoice'] = ($field['field_type'] == 'checkbox' || $field['field_type'] == 'multiselect');
		$field['isChoice'] = ($field['isMultiChoice'] || $field['field_type'] == 'radio' || $field['field_type'] == 'select');

		if ($fieldValue === null && isset($field['field_value']))
		{
			$fieldValue = $field['field_value'];
		}
		if ($field['isMultiChoice'])
		{
			if (is_string($fieldValue))
			{
				$fieldValue = @unserialize($fieldValue);
			}
			else if (!is_array($fieldValue))
			{
				$fieldValue = array();
			}
		}
		$field['field_value'] = $fieldValue;

		$field['title'] = new XenForo_Phrase($this->getUserFieldTitlePhraseName($field['field_id']));
		$field['description'] = new XenForo_Phrase($this->getUserFieldDescriptionPhraseName($field['field_id']));

		$field['viewableProfile'] = ($field['viewable_profile'] && $field['display_group'] != 'preferences');
		$field['showRegistration'] = (($field['show_registration'] || $field['required']) && $field['user_editable'] != 'never');

		$field['hasValue'] = $valueSaved && ((is_string($fieldValue) && $fieldValue !== '') || (!is_string($fieldValue) && $fieldValue));
		$field['isEditable'] = ($field['user_editable'] == 'yes' || (!$field['hasValue'] && $field['user_editable'] == 'once'));

		if ($getFieldChoices)
		{
			$field['fieldChoices'] = $this->getUserFieldChoices($field['field_id'], $field['field_choices']);
		}

		return $field;
	}

	/**
	 * Prepares a list of user fields for display.
	 *
	 * @param array $fields
	 * @param boolean $getFieldChoices If true, gets the choice options for these fields (as phrases)
	 * @param array $fieldValues List of values for the specified fields; if skipped, pulled from field_value in array
	 * @param boolean $valueSaved If true, considers the value passed to be saved; should be false on registration
	 *
	 * @return array
	 */
	public function prepareUserFields(array $fields, $getFieldChoices = false, array $fieldValues = array(), $valueSaved = true)
	{
		foreach ($fields AS &$field)
		{
			$value = isset($fieldValues[$field['field_id']]) ? $fieldValues[$field['field_id']] : null;
			$field = $this->prepareUserField($field, $getFieldChoices, $value, $valueSaved);
		}

		return $fields;
	}

	/**
	 * Gets the field choices for the given field.
	 *
	 * @param string $fieldId
	 * @param string|array $choices Serialized string or array of choices; key is choide ID
	 * @param boolean $master If true, gets the master phrase values; otherwise, phrases
	 *
	 * @return array Choices
	 */
	public function getUserFieldChoices($fieldId, $choices, $master = false)
	{
		if (!is_array($choices))
		{
			$choices = ($choices ? @unserialize($choices) : array());
		}

		if (!$master)
		{
			foreach ($choices AS $value => &$text)
			{
				$text = new XenForo_Phrase($this->getUserFieldChoicePhraseName($fieldId, $value));
			}
		}

		return $choices;
	}

	/**
	 * Verifies that the value for the specified field is valid.
	 *
	 * @param array $field
	 * @param mixed $value
	 * @param mixed $error Returned error message
	 *
	 * @return boolean
	 */
	public function verifyUserFieldValue(array $field, &$value, &$error = '')
	{
		$error = false;

		switch ($field['field_type'])
		{
			case 'textbox':
				$value = preg_replace('/\r?\n/', ' ', strval($value));
				// break missing intentionally

			case 'textarea':
				$value = trim(strval($value));

				if ($field['max_length'] && utf8_strlen($value) > $field['max_length'])
				{
					$error = new XenForo_Phrase('please_enter_value_using_x_characters_or_fewer', array('count' => $field['max_length']));
					return false;
				}

				$matched = true;

				if ($value !== '')
				{
					switch ($field['match_type'])
					{
						case 'number':
							$matched = preg_match('/^[0-9]+(\.[0-9]+)?$/', $value);
							break;

						case 'alphanumeric':
							$matched = preg_match('/^[a-z0-9_]+$/i', $value);
							break;

						case 'email':
							$matched = XenForo_Helper_Email::isEmailValid($value);
							break;

						case 'url':
							if ($value === 'http://')
							{
								$value = '';
								break;
							}
							if (substr(strtolower($value), 0, 4) == 'www.')
							{
								$value = 'http://' . $value;
							}
							$matched = Zend_Uri::check($value);
							break;

						case 'regex':
							$matched = preg_match('#' . str_replace('#', '\#', $field['match_regex']) . '#sU', $value);
							break;

						case 'callback':
							$matched = call_user_func_array(
								array($field['match_callback_class'], $field['match_callback_method']),
								array($field, &$value, &$error)
							);

						default:
							// no matching
					}
				}

				if (!$matched)
				{
					if (!$error)
					{
						$error = new XenForo_Phrase('please_enter_value_that_matches_required_format');
					}
					return false;
				}
				break;

			case 'radio':
			case 'select':
				$choices = unserialize($field['field_choices']);
				$value = strval($value);

				if (!isset($choices[$value]))
				{
					$value = '';
				}
				break;

			case 'checkbox':
			case 'multiselect':
				$choices = unserialize($field['field_choices']);
				if (!is_array($value))
				{
					$value = array();
				}

				$newValue = array();

				foreach ($value AS $key => $choice)
				{
					$choice = strval($choice);
					if (isset($choices[$choice]))
					{
						$newValue[$choice] = $choice;
					}
				}

				$value = $newValue;
				break;
		}

		return true;
	}

	/**
	 * Gets the possible user field groups. Used to display in form in ACP.
	 *
	 * @return array [group] => keys: value, label, hint (optional)
	 */
	public function getUserFieldGroups()
	{
		return array(
			'personal' => array(
				'value' => 'personal',
				'label' => new XenForo_Phrase('personal_details')
			),
			'contact' => array(
				'value' => 'contact',
				'label' => new XenForo_Phrase('contact_details')
			),
			'preferences' => array(
				'value' => 'preferences',
				'label' => new XenForo_Phrase('preferences'),
				'hint' => new XenForo_Phrase('these_fields_will_never_be_displayed_on_users_profile')
			)
		);
	}

	/**
	 * Gets the possible user field types.
	 *
	 * @return array [type] => keys: value, label, hint (optional)
	 */
	public function getUserFieldTypes()
	{
		return array(
			'textbox' => array(
				'value' => 'textbox',
				'label' => new XenForo_Phrase('single_line_text_box')
			),
			'textarea' => array(
				'value' => 'textarea',
				'label' => new XenForo_Phrase('multi_line_text_box')
			),
			'select' => array(
				'value' => 'select',
				'label' => new XenForo_Phrase('drop_down_selection')
			),
			'radio' => array(
				'value' => 'radio',
				'label' => new XenForo_Phrase('radio_buttons')
			),
			'checkbox' => array(
				'value' => 'checkbox',
				'label' => new XenForo_Phrase('check_boxes')
			),
			'multiselect' => array(
				'value' => 'multiselect',
				'label' => new XenForo_Phrase('multiple_choice_drop_down_selection')
			)
		);
	}

	/**
	 * Maps user fields to their high level type "group". Field types can be changed only
	 * within the group.
	 *
	 * @return array [field type] => type group
	 */
	public function getUserFieldTypeMap()
	{
		return array(
			'textbox' => 'text',
			'textarea' => 'text',
			'radio' => 'single',
			'select' => 'single',
			'checkbox' => 'multiple',
			'multiselect' => 'multiple'
		);
	}

	/**
	 * Gets the field's title phrase name.
	 *
	 * @param string $fieldId
	 *
	 * @return string
	 */
	public function getUserFieldTitlePhraseName($fieldId)
	{
		return 'user_field_' . $fieldId;
	}

	/**
	 * Gets the field's description phrase name.
	 *
	 * @param string $fieldId
	 *
	 * @return string
	 */
	public function getUserFieldDescriptionPhraseName($fieldId)
	{
		return 'user_field_' . $fieldId . '_desc';
	}

	/**
	 * Gets a field choices's phrase name.
	 *
	 * @param string $fieldId
	 * @param string $choice
	 *
	 * @return string
	 */
	public function getUserFieldChoicePhraseName($fieldId, $choice)
	{
		return 'user_field_' . $fieldId . '_choice_' . $choice;
	}

	/**
	 * Gets a field's master title phrase text.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	public function getUserFieldMasterTitlePhraseValue($id)
	{
		$phraseName = $this->getUserFieldTitlePhraseName($id);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets a field's master description phrase text.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	public function getUserFieldMasterDescriptionPhraseValue($id)
	{
		$phraseName = $this->getUserFieldDescriptionPhraseName($id);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the user field values for the given user.
	 *
	 * @param integer $userId
	 *
	 * @return array [field id] => value (may be string or array)
	 */
	public function getUserFieldValues($userId)
	{
		$fields = $this->_getDb()->fetchAll('
			SELECT value.*, field.field_type
			FROM xf_user_field_value AS value
			INNER JOIN xf_user_field AS field ON (field.field_id = value.field_id)
			WHERE value.user_id = ?
		', $userId);

		$values = array();
		foreach ($fields AS $field)
		{
			if ($field['field_type'] == 'checkbox' || $field['field_type'] == 'multiselect')
			{
				$values[$field['field_id']] = @unserialize($field['field_value']);
			}
			else
			{
				$values[$field['field_id']] = $field['field_value'];
			}
		}

		return $values;
	}

	/**
	 * Rebuilds the cache of user field info for front-end display
	 *
	 * @return array
	 */
	public function rebuildUserFieldCache()
	{
		$cache = array();
		foreach ($this->getUserFields() AS $fieldId => $field)
		{
			$cache[$fieldId] = XenForo_Application::arrayFilterKeys($field, array(
				'field_id',
				'field_type',
				'display_group',
			));

			foreach (array('display_template', 'viewable_profile', 'viewable_message') AS $optionalField)
			{
				if (!empty($field[$optionalField]))
				{
					$cache[$fieldId][$optionalField] = $field[$optionalField];
				}
			}
		}

		$this->_getDataRegistryModel()->set('userFieldsInfo', $cache);
		return $cache;
	}

	/**
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}
}