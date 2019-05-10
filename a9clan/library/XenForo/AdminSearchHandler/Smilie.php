<?php

class XenForo_AdminSearchHandler_Smilie extends XenForo_AdminSearchHandler_Abstract
{
	protected function _getTemplateName()
	{
		return 'quicksearch_smilies';
	}

	public function getPhraseKey()
	{
		return 'smilies';
	}

	public function search($searchText, array $phraseMatches = null)
	{
		/* @var $smilieModel XenForo_Model_Smilie */
		$smilieModel = $this->getModelFromCache('XenForo_Model_Smilie');

		if ($smilies = $smilieModel->getSmiliesForAdminQuickSearch($searchText))
		{
			foreach ($smilies AS &$smilie)
			{
				$smilie = $smilieModel->prepareSmilie($smilie, true);
			}

			return $smilies;
		}

		return array();
	}

	public function getAdminPermission()
	{
		return 'bbCodeSmilie';
	}
}