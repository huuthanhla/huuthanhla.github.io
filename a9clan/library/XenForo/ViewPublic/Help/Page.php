<?php

class XenForo_ViewPublic_Help_Page extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['templateHtml'] = $this->createTemplateObject(
			$this->_params['templateName'],
			$this->_params
		);
	}
}