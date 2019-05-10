<?php

class XenForo_ViewPublic_Error_RegistrationRequired extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		if (!empty($_POST) && !isset($this->_params['postData']))
		{
			$this->_params['postData'] = $_POST;
		}
	}

	public function renderJson()
	{
		if (!empty($this->_params['text']))
		{
			$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);

			if (!empty($_POST))
			{
				// ugly hack to reverse this as we need the original value for the login redirect
				$requestPaths = XenForo_Application::get('requestPaths');
				$requestUri = $this->_renderer->getRequest()->get('_xfRequestUri');
				if ($requestUri && is_string($requestUri))
				{
					$output['templateHtml'] = str_replace(
						htmlspecialchars($requestUri),
						htmlspecialchars($requestPaths['requestUri']),
						$output['templateHtml']
					);
				}
			}

			$output['error'] = $this->_params['text'];
			$output['errorTemplateHtml'] = $output['templateHtml'];
			unset($output['templateHtml']);
			$output['errorOverlayType'] = 'formOverlay';
			return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
		}

		return null;
	}
}