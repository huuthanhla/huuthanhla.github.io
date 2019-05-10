<?php

class XenForo_ViewPublic_Conversation_ViewNewMessages extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => $this->_params['canViewAttachments']
			)
		);
		XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['messages'], $bbCodeParser, $bbCodeOptions);
	}

	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);

		$output['lastDate'] = $this->_params['lastMessage']['message_date'];
		$output['count'] = count($this->_params['messages']);

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}