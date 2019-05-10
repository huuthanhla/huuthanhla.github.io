<?php

class XenForo_ViewPublic_Conversation_View extends XenForo_ViewPublic_Base
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

		if (!empty($this->_params['canReplyConversation']))
		{
			$draft = isset($this->_params['conversation']['draft_message']) ? $this->_params['conversation']['draft_message'] : '';

			$this->_params['qrEditor'] = XenForo_ViewPublic_Helper_Editor::getQuickReplyEditor(
				$this, 'message', $draft,
				array(
					'extraClass' => 'NoAutoComplete',
					'autoSaveUrl' => XenForo_Link::buildPublicLink('conversations/save-draft', $this->_params['conversation']),
					'json' => array('placeholder' => 'reply_placeholder')
				)
			);
		}
	}
}