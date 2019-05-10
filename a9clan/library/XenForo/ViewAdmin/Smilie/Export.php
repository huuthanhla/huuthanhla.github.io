<?php

/**
 * Helper to get the smilie export data (likely in XML format).
 *
 * @package XenForo_Smilies
 */
class XenForo_ViewAdmin_Smilie_Export extends XenForo_ViewAdmin_Base
{
	/**
	 * Render the exported date to XML.
	 *
	 * @return string
	 */
	public function renderXml()
	{
		$this->setDownloadFileName('smilies.xml');
		return $this->_params['xml']->saveXml();
	}
}