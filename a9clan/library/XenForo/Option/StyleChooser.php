<?php

/**
 * Helper for choosing a style.
 *
 * @package XenForo_Options
 */
abstract class XenForo_Option_StyleChooser
{
	/**
	 * Renders the style chooser option as a <select>.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	public static function renderSelect(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		//$preparedOption['inputClass'] = 'autoSize';

		return self::_render('option_list_option_select', $view, $fieldPrefix, $preparedOption, $canEdit);
	}

	/**
	 * Renders the style chooser option as a group of <input type="radio" />.
	 *
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	public static function renderRadio(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		return self::_render('option_list_option_radio', $view, $fieldPrefix, $preparedOption, $canEdit);
	}

	public static function renderRadioSelectable(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		/** @var XenForo_Model_Style $styleModel */
		$styleModel = XenForo_Model::create('XenForo_Model_Style');

		$styles = $styleModel->getAllStylesAsFlattenedTree();
		foreach ($styles AS $id => $style)
		{
			if (!$style['user_selectable'])
			{
				unset($styles[$id]);
			}
		}
		$preparedOption['formatParams'] = $styleModel->getStylesForOptionsTag($preparedOption['option_value'], $styles);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_list_option_radio', $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}

	/**
	 * Renders the style chooser option.
	 *
	 * @param string Name of template to render
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	protected static function _render($templateName, XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = XenForo_Model::create('XenForo_Model_Style')->getStylesForOptionsTag($preparedOption['option_value']);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			$templateName, $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
}