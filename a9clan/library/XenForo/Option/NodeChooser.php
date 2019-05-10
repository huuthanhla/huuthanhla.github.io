<?php

/**
 * Helper for choosing a node.
 *
 * @package XenForo_Options
 */
abstract class XenForo_Option_NodeChooser
{
	/**
	 * Renders the node chooser option as a <select>.
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
		//$params['inputClass'] = 'autoSize';

		return self::_render('option_list_option_select', $view, $fieldPrefix, $preparedOption, $canEdit);
	}

	/**
	 * Renders the node chooser option as a group of <input type="radio" />.
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

	/**
	 * Fetches a list of node options.
	 *
	 * @param integer $selectedForum
	 * @param mixed Include root forum (specify a phrase to represent the root forum)
	 * @param mixed Filter the options to allow only the specified type to be selectable
	 *
	 * @return array
	 */
	public static function getNodeOptions($selectedForum, $includeRoot = false, $filter = false)
	{
		/* @var $nodeModel XenForo_Model_Node */
		$nodeModel = XenForo_Model::create('XenForo_Model_Node');

		$options = $nodeModel->getNodeOptionsArray(
			$nodeModel->getAllNodes(),
			$selectedForum,
			$includeRoot
		);

		if ($filter)
		{
			foreach ($options AS &$option)
			{
				if (!empty($option['node_type_id']) && $option['node_type_id'] != $filter)
				{
					$option['disabled'] = 'disabled';
				}

				unset($option['node_type_id']);
			}
		}

		return $options;
	}

	/**
	 * Renders the node chooser option.
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
		$filter = isset($preparedOption['nodeFilter']) ? $preparedOption['nodeFilter'] : false;

		$preparedOption['formatParams'] = self::getNodeOptions(
			$preparedOption['option_value'],
			sprintf('(%s)', new XenForo_Phrase('unspecified')),
			$filter
		);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			$templateName, $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}
}