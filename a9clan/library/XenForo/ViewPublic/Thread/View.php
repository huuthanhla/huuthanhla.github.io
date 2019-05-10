<?php

class XenForo_ViewPublic_Thread_View extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$bbCodeParser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base', array('view' => $this)));
		$bbCodeOptions = array(
			'states' => array(
				'viewAttachments' => $this->_params['canViewAttachments']
			),
			'contentType' => 'post',
			'contentIdKey' => 'post_id'
		);
		XenForo_ViewPublic_Helper_Message::bbCodeWrapMessages($this->_params['posts'], $bbCodeParser, $bbCodeOptions);

		if (!empty($this->_params['canQuickReply']))
		{
			$draft = isset($this->_params['thread']['draft_message']) ? $this->_params['thread']['draft_message'] : '';

			$this->_params['qrEditor'] = XenForo_ViewPublic_Helper_Editor::getQuickReplyEditor(
				$this, 'message', $draft,
				array(
					'autoSaveUrl' => XenForo_Link::buildPublicLink('threads/save-draft', $this->_params['thread']),
					'json' => array('placeholder' => 'reply_placeholder')
				)
			);
		}
	}
}