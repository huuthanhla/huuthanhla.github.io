<?php

abstract class XenForo_Option_Captcha
{
	/**
	 * Renders the captcha option.
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
		$preparedOption['extraChoices'] = array();
		XenForo_CodeEvent::fire('option_captcha_render', array(&$preparedOption['extraChoices'], $view, $preparedOption));

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_template_captcha',
			$view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
}