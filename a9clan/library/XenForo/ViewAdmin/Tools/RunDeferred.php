<?php

class XenForo_ViewAdmin_Tools_RunDeferred extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);
		$output['status'] = $this->_params['status'];
		$output['continueProcessing'] = true;
		$output['canCancel'] = $this->_params['canCancel'];

		return $output;
	}
}