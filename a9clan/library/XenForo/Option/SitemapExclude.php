<?php

abstract class XenForo_Option_SitemapExclude
{
	/**
	 * @param XenForo_View $view View object
	 * @param string $fieldPrefix Prefix for the HTML form field name
	 * @param array $preparedOption Prepared option info
	 * @param boolean $canEdit True if an "edit" link should appear
	 *
	 * @return XenForo_Template_Abstract Template object
	 */
	public static function renderCheckbox(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		/** @var XenForo_Model_Sitemap $sitemapModel */
		$sitemapModel = XenForo_Model::create('XenForo_Model_Sitemap');

		$types = $sitemapModel->getSitemapContentTypes(true);
		unset($types['core']); // always enabled

		$preparedOption['formatParams'] = array();

		foreach ($types AS $type => $handlerClass)
		{
			$handlerClass = XenForo_Application::resolveDynamicClass($handlerClass);
			$handler = new $handlerClass();

			$preparedOption['formatParams'][] = array(
				'name' => "{$fieldPrefix}[{$preparedOption['option_id']}][$type]",
				'label' => new XenForo_Phrase($handler->getPhraseKey($type)),
				'selected' => empty($preparedOption['option_value'][$type])
			);
		}

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_list_option_checkbox',
			$view, $fieldPrefix, $preparedOption, $canEdit
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

		/** @var XenForo_Model_Sitemap $sitemapModel */
		$sitemapModel = XenForo_Model::create('XenForo_Model_Sitemap');

		$types = $sitemapModel->getSitemapContentTypes(true);
		unset($types['core']); // always enabled

		foreach ($types AS $type => $handlerName)
		{
			if (empty($choices[$type]))
			{
				$exclusions[$type] = true;
			}
		}

		$choices = $exclusions;

		return true;
	}
}