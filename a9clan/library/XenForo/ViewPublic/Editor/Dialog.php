<?php

class XenForo_ViewPublic_Editor_Dialog extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_renderer->setNeedsContainer(false);

		$template = $this->createTemplateObject($this->_templateName, $this->_params);
		$output = $template->render();

		$this->_response->setHeader('Expires', gmdate('D, d M Y H:i:s', XenForo_Application::$time + 3600) . ' GMT', true);
		$this->_response->setHeader('Cache-Control', 'public', true);

		return $this->_renderer->replaceRequiredExternalPlaceholders($template, $output);
	}
}