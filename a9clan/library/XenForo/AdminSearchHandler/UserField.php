<?php

class XenForo_AdminSearchHandler_UserField extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_user_fields';
	}

	public function getPhraseKey()
	{
		return 'custom_user_fields';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $fieldModel XenForo_Model_UserField */
		$fieldModel = $this->getModelFromCache('XenForo_Model_UserField');

		$fields = $fieldModel->getUserFields(array('adminQuickSearch' => array(
			'searchText' => $searchText,
			'phraseMatches' => $phraseMatches
		)));

		if ($fields)
		{
			return $fieldModel->prepareUserFields($fields);
		}

		return array();
	}

	public function getPhraseConditions()
	{
		return array(
			'like' => XenForo_Db::quoteLike('user_field_', 'r'),
			'regex' => '/^user_field_(.*)(_desc)?$/U'
		);
	}

	public function getAdminPermission()
	{
		return 'userField';
	}
}