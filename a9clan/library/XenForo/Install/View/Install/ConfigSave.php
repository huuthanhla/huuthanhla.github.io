<?php

class XenForo_Install_View_Install_ConfigSave extends XenForo_Install_View_Base
{
	public function renderRaw()
	{
		$this->_response->setHeader('Content-type', 'application/octet-stream', true);
		$this->setDownloadFileName('config.php');

		return $this->_params['generated'];
	}
}