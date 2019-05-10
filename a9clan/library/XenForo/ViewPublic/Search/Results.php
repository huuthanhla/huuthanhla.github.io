<?php

class XenForo_ViewPublic_Search_Results extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$handlers = $this->_params['results']['handlers'];
		$modType = $this->_params['modType'];
		if ($modType && isset($handlers[$modType]))
		{
			$handlers[$modType]->setInlineModEnabled(true);

			$modTemplate = $this->_params['activeInlineMod']['template'];
			$this->_params['inlineModControlsHtml'] = $this->createTemplateObject($modTemplate, array(
				'inlineModOptions' => $this->_params['inlineModOptions']
			));
		}
		else
		{
			$this->_params['inlineModControlsHtml'] = '';
		}

		$this->_params['results'] = XenForo_ViewPublic_Helper_Search::renderSearchResults(
			$this, $this->_params['results'], $this->_params['search']
		);
	}
}