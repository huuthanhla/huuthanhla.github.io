<?php

class XenForo_ViewAdmin_Log_EmailBounceView extends XenForo_ViewAdmin_Base
{
	public function renderRaw()
	{
		$this->_response->setHeader('Content-Type', 'text/plain; charset=utf-8');
		$this->_response->setHeader('X-Content-Type-Options', 'nosniff');

		return $this->_params['bounce']['raw_message'];
	}
}