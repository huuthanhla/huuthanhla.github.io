<?php

class XenForo_AdminSearchHandler_StyleProperty extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_style_properties';
	}

	public function getPhraseKey()
	{
		return 'style_properties';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $propertyModel XenForo_Model_StyleProperty */
		$propertyModel = $this->getModelFromCache('XenForo_Model_StyleProperty');

		if ($properties = $propertyModel->getStylePropertyDefinitionsForAdminQuickSearch($searchText, $phraseMatches))
		{
			/* @var $styleModel XenForo_Model_Style */
			$styleModel = $this->getModelFromCache('XenForo_Model_Style');

			$styleId = $styleModel->getStyleIdFromCookie();
			$style = $styleModel->getStyleById($styleId, XenForo_Application::debugMode());

			foreach ($properties AS &$property)
			{
				$property = $propertyModel->prepareStylePropertyPhrases($property);
				$property['style'] = $style;
			}

			return $properties;
		}

		return array();
	}

	public function getPhraseConditions()
	{
		return array(
			'like' => XenForo_Db::quoteLike('style_property_', 'r'),
			'regex' => '/^style_property_(.*)(_description)?_(admin|master)$/'
		);
	}

	public function getAdminPermission()
	{
		return 'style';
	}
}