<?php

class XenForo_ViewAdmin_BbCode_Export extends XenForo_ViewAdmin_Base
{
	/**
	 * Render the exported date to XML.
	 *
	 * @return string
	 */
	public function renderXml()
	{
		$this->setDownloadFileName('bb_codes.xml');
		return $this->_params['xml']->saveXml();
	}
}