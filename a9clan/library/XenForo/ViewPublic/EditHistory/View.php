<?php

class XenForo_ViewPublic_EditHistory_View extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['contentFormatted'] = $this->_params['handler']->formatHistory(
			$this->_params['history']['old_text'], $this
		);
	}
}