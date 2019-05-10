<?php

class XenForo_ViewPublic_Thread_SaveDraft extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$output = $this->_renderer->getDefaultOutputArray(get_class($this), $this->_params, $this->_templateName);

		$output['newPostCount'] = $this->_params['newPostCount'];
		$output['draftSaved'] = $this->_params['draftSaved'];
		$output['draftDeleted'] = $this->_params['draftDeleted'];

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($output);
	}
}