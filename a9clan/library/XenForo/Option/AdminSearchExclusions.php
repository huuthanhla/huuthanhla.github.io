<?php

class XenForo_Option_AdminSearchExclusions
{
	public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		/* @var $adminSearchModel XenForo_Model_AdminSearch */
		$adminSearchModel = XenForo_Model::create('XenForo_Model_AdminSearch');

		$preparedOption['formatParams'] = array();

		/* @var $handler XenForo_AdminSearchHandler_Abstract */
		foreach ($adminSearchModel->getAllSearchTypeHandlers() AS $searchType => $handler)
		{
			$preparedOption['formatParams'][] = array(
				'name' => "{$fieldPrefix}[{$preparedOption['option_id']}][$searchType]",
				'label' => new XenForo_Phrase($handler->getPhraseKey()),
				'selected' => empty($preparedOption['option_value'][$searchType])
			);
		}

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_list_option_checkbox', $view, $fieldPrefix, $preparedOption, $canEdit,
			array('class' => 'checkboxColumns')
		);
	}

	public static function verifyOption(array &$choices, XenForo_DataWriter $dw, $fieldName)
	{
		if ($dw->isInsert())
		{
			// insert - just trust the default value
			return true;
		}

		$exclusions = array();

		foreach (XenForo_Model::create('XenForo_Model_AdminSearch')->getAllSearchTypes() AS $searchType => $handlerName)
		{
			if (empty($choices[$searchType]))
			{
				$exclusions[$searchType] = true;
			}
		}

		$choices = $exclusions;

		return true;
	}
}