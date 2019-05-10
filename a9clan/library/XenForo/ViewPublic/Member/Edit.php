<?php

class XenForo_ViewPublic_Member_Edit extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['aboutEditor'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'about', $this->_params['user']['about']
		);

		$this->_params['signatureEditor'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
			$this, 'signature', $this->_params['user']['signature']
		);
	}
}