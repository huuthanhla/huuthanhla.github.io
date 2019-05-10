<?php

class XenForo_ViewPublic_Helper_User
{
	/**
	 * Generates left and top CSS positions for a user avatar to be cropped within .avatarCropper
	 *
	 * @param array $user Must contain avatar_width, avatar_height, avatar_crop_x and avatar_crop_y keys
	 *
	 * @return array (top: Xpx; top: Ypx)
	 */
	public static function getAvatarCropCss(array $user)
	{
		$largeSize = 192;

		if ($user['avatar_width'])
		{
			if ($user['avatar_width'] >= $largeSize || $user['avatar_height'] >= $largeSize)
			{
				if ($user['avatar_width'] > $user['avatar_height'])
				{
					// landscape
					$ratio = -1 * $user['avatar_height'] / 96;
					$left = max($user['avatar_crop_x'] * $ratio, $largeSize - $user['avatar_width']);
					return array(
						'left' => $left . 'px',
						'top' => max(0, ($largeSize - $user['avatar_height']) / 2) . 'px'
					);
				}
				else if ($user['avatar_width'] < $user['avatar_height'])
				{
					// portrait
					$ratio = -1 * $user['avatar_width'] / 96;
					$top = max($user['avatar_crop_y'] * $ratio, $largeSize - $user['avatar_height']);
					return array(
						'left' => max(0, ($largeSize - $user['avatar_width']) / 2) . 'px',
						'top' => $top . 'px'
					);
				}
			}
			else
			{
				// center small image
				return array(
					'left' => ($largeSize - $user['avatar_width']) / 2 . 'px',
					'top' => ($largeSize - $user['avatar_height']) / 2 . 'px'
				);
			}
		}

		return array('left' => '0px', 'top' => '0px');
	}

	/**
	 * Gets the HTML value of the user field.
	 *
	 * @param array $field
	 * @param mixed $value Value of the field; if null, pulls from field_value in field
	 */
	public static function getUserFieldValueHtml(array $field, $value = null)
	{
		if ($value === null && isset($field['field_value']))
		{
			$value = $field['field_value'];
		}

		if ($value === '' || $value === null)
		{
			return '';
		}

		$multiChoice = false;
		$choice = '';

		switch ($field['field_type'])
		{
			case 'radio':
			case 'select':
				$choice = $value;
				$value = new XenForo_Phrase("user_field_$field[field_id]_choice_$value");
				$value->setPhraseNameOnInvalid(false);
				break;

			case 'checkbox':
			case 'multiselect':
				$multiChoice = true;
				if (!is_array($value) || count($value) == 0)
				{
					return '';
				}

				$newValues = array();
				foreach ($value AS $id => $choice)
				{
					$phrase = new XenForo_Phrase("user_field_$field[field_id]_choice_$choice");
					$phrase->setPhraseNameOnInvalid(false);

					$newValues[$choice] = $phrase;
				}
				$value = $newValues;
				break;

			case 'textbox':
			case 'textarea':
			default:
				$value = nl2br(htmlspecialchars(XenForo_Helper_String::censorString($value)));
		}

		if (!empty($field['display_template']))
		{
			if ($multiChoice && is_array($value))
			{
				foreach ($value AS $choice => &$thisValue)
				{
					$thisValue = strtr($field['display_template'], array(
						'{$fieldId}' => $field['field_id'],
						'{$value}' => $thisValue,
						'{$valueUrl}' => urlencode($thisValue),
						'{$choice}' => $choice,
					));
				}
			}
			else
			{
				$value = strtr($field['display_template'], array(
					'{$fieldId}' => $field['field_id'],
					'{$value}' => $value,
					'{$valueUrl}' => urlencode($value),
					'{$choice}' => $choice,
				));
			}
		}

		return $value;
	}

	/**
	 * Add user field HTML keys to the given list of fields.
	 *
	 * @param XenForo_View $view
	 * @param array $fields
	 * @param array $values Field values; pulls from field_value in fields if not specified here
	 */
	public static function addUserFieldsValueHtml(XenForo_View $view, array $fields, array $values = array())
	{
		foreach ($fields AS &$field)
		{
			$field['fieldValueHtml'] = self::getUserFieldValueHtml(
				$field,
				isset($values[$field['field_id']]) ? $values[$field['field_id']] : null
			);
		}

		return $fields;
	}
}