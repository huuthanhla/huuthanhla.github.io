<?php

class XenForo_ViewPublic_Conversation_SaveDraft extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		return array(
			'draftSaved' => $this->_params['draftSaved'],
			'draftDeleted' => $this->_params['draftDeleted']
		);
	}
}