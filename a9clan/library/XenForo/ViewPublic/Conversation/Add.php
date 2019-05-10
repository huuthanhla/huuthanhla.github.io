<?php

class XenForo_ViewPublic_Conversation_Add extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'message', !empty($this->_params['draft']) ? $this->_params['draft']['message'] : '',
			array(
				'extraClass' => 'NoAutoComplete',
				'autoSaveUrl' => XenForo_Link::buildPublicLink('conversations/save-draft')
			)
		);
	}
}