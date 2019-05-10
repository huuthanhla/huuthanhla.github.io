<?php

/**
 * View that combines the requested CSS and output them in one request.
 *
 * @package XenForo_CssInternal
 */
class XenForo_ViewAdmin_CssInternal extends XenForo_ViewAdmin_Base
{
	/**
	 * Render the CSS version of the... CSS!
	 *
	 * @return string
	 */
	public function renderCss()
	{
		XenForo_Template_Abstract::setLanguageId(0);

		$bbCodeCache = $this->_params['bbCodeCache'];

		$templateParams = array(
			'displayStyles' => array(),
			'smilieSprites' => $this->_params['smilieSprites'],
			'xenOptions' => XenForo_Application::get('options')->getOptions(),
			'customBbCodes' => !empty($bbCodeCache['bbCodes']) ? $bbCodeCache['bbCodes'] : array(),
			'dir' => $this->_params['dir'],
			'pageIsRtl' => ($this->_params['dir'] == 'RTL')
		);

		$templates = array();
		foreach ($this->_params['css'] AS $cssTemplate)
		{
			if (strpos($cssTemplate, 'public:') === 0)
			{
				$templates[$cssTemplate] = new XenForo_Template_Public(substr($cssTemplate, strlen('public:')), $templateParams);
			}
			else
			{
				$templates[$cssTemplate] = $this->createTemplateObject($cssTemplate, $templateParams);
			}
		}

		if (XenForo_Application::isRegistered('adminStyleModifiedDate'))
		{
			$modifyDate = XenForo_Application::get('adminStyleModifiedDate');
		}
		else
		{
			$modifyDate = XenForo_Application::$time;
		}

		$this->_response->setHeader('Expires', 'Wed, 01 Jan 2020 00:00:00 GMT', true);
		$this->_response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $modifyDate) . ' GMT', true);
		$this->_response->setHeader('Cache-Control', 'private', true);

		$css = XenForo_CssOutput::renderCssFromObjects($templates, true);
		$css = XenForo_CssOutput::prepareCssForOutput(
			$css,
			$this->_params['dir'],
			false
		);

		return $css;
	}
}