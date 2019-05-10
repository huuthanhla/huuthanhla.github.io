<?php

/**
 * View to handle returning 304 .
 * This is identical to XenForo_ViewPublic_Attachment_View304
 *
 * @package XenForo_Attachment
 */
class XenForo_ViewAdmin_Attachment_View304 extends XenForo_ViewAdmin_Base
{
	public function renderRaw()
	{
		$this->_response->setHttpResponseCode(304);
		$this->_response->clearHeader('Last-Modified');

		return '';
	}
}