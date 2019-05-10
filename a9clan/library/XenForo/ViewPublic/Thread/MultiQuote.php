<?php

class XenForo_ViewPublic_Thread_MultiQuote extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Wysiwyg', array('view' => $this)));

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'quote' => $this->_params['quote'],
			'quoteHtml' => $bbCodeParser->render($this->_params['quote'])
		));
	}
}