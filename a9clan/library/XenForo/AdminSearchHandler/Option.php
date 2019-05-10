<?php

class XenForo_AdminSearchHandler_Option extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_options';
	}

	public function getPhraseKey()
	{
		return 'options';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $optionModel XenForo_Model_Option */
		$optionModel = $this->getModelFromCache('XenForo_Model_Option');

		$options = $optionModel->getOptions(array('adminQuickSearch' =>
			array('searchText' => $searchText, 'phraseMatches' => $phraseMatches)
		));

		if ($options)
		{
			return $optionModel->prepareOptions($options);
		}

		return array();
	}

	public function getPhraseConditions()
	{
		return array(
			'like' => XenForo_Db::quoteLike('option_', 'r'),
			'regex' => '/^option_(.*)(_explain|_description|)?$/U'
		);
	}

	public function getAdminPermission()
	{
		return 'option';
	}
}