<?php

class XenForo_Install_View_RunDeferred extends XenForo_Install_View_Base
{
	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);
		$output['status'] = $this->_params['status'];
		$output['continueProcessing'] = true;

		return $output;
	}
}