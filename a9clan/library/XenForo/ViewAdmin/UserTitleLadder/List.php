<?php

class XenForo_ViewAdmin_UserTitleLadder_List extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		$this->_params['renderedOptions'] = XenForo_ViewAdmin_Helper_Option::renderPreparedOptionsHtml(
			$this, $this->_params['options'], false
		);
	}
}