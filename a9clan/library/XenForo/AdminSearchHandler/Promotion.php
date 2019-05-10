<?php

class XenForo_AdminSearchHandler_Promotion extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_promotions';
	}

	public function getPhraseKey()
	{
		return 'user_group_promotions';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		$promotionModel = $this->getModelFromCache('XenForo_Model_UserGroupPromotion');

		return $promotionModel->getPromotions(array('adminQuickSearch' => $searchText));
	}

	public function getAdminPermission()
	{
		return 'userGroup';
	}
}