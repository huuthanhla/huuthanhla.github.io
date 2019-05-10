<?php

/**
 * Helper for censoring option.
 *
 * @package XenForo_Options
 */
abstract class XenForo_Option_CensorWords
{
	/**
	 * Renders the censor words option row.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$value = $preparedOption['option_value'];

		$choices = array();
		foreach ($value AS $word)
		{
			$choices[] = array(
				'word' => $word['word'],
				'replace' => is_string($word['replace']) ? $word['replace'] : ''
			);
		}

		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

		return $view->createTemplateObject('option_template_censorWords', array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'formatParams' => $preparedOption['formatParams'],
			'editLink' => $editLink,

			'choices' => $choices,
			'nextCounter' => count($choices)
		));
	}

	/**
	 * Verifies and prepares the censor option to the correct format.
	 *
	 * @param array $words List of words to censor (from input). Keys: word, exact, replace
	 * @param XenForo_DataWriter $dw Calling DW
	 * @param string $fieldName Name of field/option
	 *
	 * @return true
	 */
	public static function verifyOption(array &$words, XenForo_DataWriter $dw, $fieldName)
	{
		$output = array();

		foreach ($words AS $word)
		{
			if (!isset($word['word']) || !isset($word['replace']))
			{
				continue;
			}

			$cache = self::buildCensorCacheValue($word['word'], $word['replace']);
			if ($cache)
			{
				$output[] = $cache;
			}
		}

		$words = $output;

		return true;
	}

	/**
	 * Builds the regex and censor cache value for a find/replace pair
	 *
	 * @param string $find
	 * @param string $replace
	 *
	 * @return array|bool
	 */
	public static function buildCensorCacheValue($find, $replace)
	{
		$find = trim(strval($find));
		if ($find === '')
		{
			return false;
		}

		$prefixWildCard = preg_match('#^\*#', $find);
		$suffixWildCard = preg_match('#\*$#', $find);

		$replace = is_int($replace) ? '' : trim(strval($replace));
		if ($replace === '')
		{
			$replace = utf8_strlen($find);
			if ($prefixWildCard)
			{
				$replace--;
			}
			if ($suffixWildCard)
			{
				$replace--;
			}
		}

		$regexFind = $find;
		if ($prefixWildCard)
		{
			$regexFind = substr($regexFind, 1);
		}
		if ($suffixWildCard)
		{
			$regexFind = substr($regexFind, 0, -1);
		}

		if (!strlen($regexFind))
		{
			return false;
		}

		$regex = '#'
			. ($prefixWildCard ? '' : '(?<=\W|^)')
			. preg_quote($regexFind, '#')
			. ($suffixWildCard ? '' : '(?=\W|$)')
			. '#iu';

		return array(
			'word' => $find,
			'regex' => $regex,
			'replace' => $replace
		);
	}
}