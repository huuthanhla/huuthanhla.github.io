<?php

class XenForo_ControllerPublic_Editor extends XenForo_ControllerPublic_Abstract
{
	public function actionDialog()
	{
		$styleId = $this->_input->filterSingle('style', XenForo_Input::UINT);
		if ($styleId)
		{
			$this->setViewStateChange('styleId', $styleId);
		}

		$dialog = $this->_input->filterSingle('dialog', XenForo_Input::STRING);
		$viewParams = array();

		if ($dialog == 'media')
		{
			$viewParams['sites'] = $this->_getBbCodeModel()->getAllBbCodeMediaSites();
		}

		$viewParams['jQuerySource'] = XenForo_Dependencies_Public::getJquerySource();
		$viewParams['jQuerySourceLocal'] = XenForo_Dependencies_Public::getJquerySource(true);
		$viewParams['javaScriptSource'] = XenForo_Application::$javaScriptUrl;

		return $this->responseView('XenForo_ViewPublic_Editor_Dialog', 'editor_dialog_' . $dialog, $viewParams);
	}

	public function actionMedia()
	{
		$url = $this->_input->filterSingle('url', XenForo_Input::STRING);

		$matchBbCode = XenForo_Helper_Media::convertMediaLinkToEmbedHtml($url);

		$viewParams = array('matchBbCode' => nl2br($matchBbCode));
		if (!$matchBbCode)
		{
			$viewParams['noMatch'] = new XenForo_Phrase('specified_url_cannot_be_embedded_as_media');
		}

		return $this->responseView('XenForo_ViewPublic_Editor_Media', '', $viewParams);
	}

	public function actionSmilies()
	{
		/* @var $smilieModel XenForo_Model_Smilie */
		$smilieModel = XenForo_Model::create('XenForo_Model_Smilie');

		$smilieCategories = $smilieModel->getAllSmiliesCategorized(false);

		$viewParams = array(
			'smilieCategories' => $smilieModel->prepareCategorizedSmiliesForEditor($smilieCategories),
			'showCategories' => (count($smilieCategories) > 1 ? true : false),
		);

		return $this->responseView('XenForo_ViewPublic_Editor_Smilies', 'editor_smilies', $viewParams);
	}

	public function actionToBbCode()
	{
		$html = $this->_input->filterSingle('html', XenForo_Input::STRING);
		$bbCode = $this->getHelper('Editor')->convertEditorHtmlToBbCode($html, $this->_input);

		return $this->responseView('XenForo_ViewPublic_Editor_ToBbCode', '', array(
			'bbCode' => $bbCode
		));
	}

	public function actionToHtml()
	{
		return $this->responseView('XenForo_ViewPublic_Editor_ToHtml', '', array(
			'bbCode' => $this->_input->filterSingle('bbCode', XenForo_Input::STRING)
		));
	}

	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}

	/**
	 * @return XenForo_Model_BbCode
	 */
	protected function _getBbCodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_BbCode');
	}
}