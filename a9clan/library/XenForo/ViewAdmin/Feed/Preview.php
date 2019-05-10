<?php

class XenForo_ViewAdmin_Feed_Preview extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		XenForo_Template_Helper_Core::setThreadPrefixes($this->_params['prefixes']);

		// don't pass a view to this, because the templates don't exist in the admin
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base'));

		$this->_params['entry']['messageHtml'] = new XenForo_BbCode_TextWrapper($this->_params['entry']['message'], $bbCodeParser);
	}
}