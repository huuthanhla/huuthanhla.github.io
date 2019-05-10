<?php

class XenForo_ViewPublic_Editor_ToHtml extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Wysiwyg', array('view' => $this)));
		return array(
			'html' => $bbCodeParser->render($this->_params['bbCode'])
		);
	}
}