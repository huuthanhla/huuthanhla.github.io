<?php

class XenForo_ViewPublic_Member_View extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));

		$this->_params['user']['aboutHtml'] = new XenForo_BbCode_TextWrapper($this->_params['user']['about'], $bbCodeParser);
		$this->_params['user']['signatureHtml'] = new XenForo_BbCode_TextWrapper($this->_params['user']['signature'], $bbCodeParser, array('lightBox' => false));

		foreach ($this->_params['customFieldsGrouped'] AS &$fields)
		{
			$fields = XenForo_ViewPublic_Helper_User::addUserFieldsValueHtml($this, $fields);
		}
	}
}