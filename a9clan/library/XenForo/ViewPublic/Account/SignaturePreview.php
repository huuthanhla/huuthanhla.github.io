<?php

class XenForo_ViewPublic_Account_SignaturePreview extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$this->_params['signatureParsed'] = new XenForo_BbCode_TextWrapper($this->_params['signature'], $bbCodeParser, array('lightBox' => false));
	}
}