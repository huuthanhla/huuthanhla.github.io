<?php

/**
 * View handling for viewing the details of a specific category.
 *
 * @package XenForo_Nodes
 */
class XenForo_ViewPublic_Category_View extends XenForo_ViewPublic_Base
{
	/**
	 * Help render the HTML output.
	 *
	 * @return mixed
	 */
	public function renderHtml()
	{
		$this->_params['renderedNodes'] = XenForo_ViewPublic_Helper_Node::renderNodeTreeFromDisplayArray(
			$this, $this->_params['nodeList'], 2 // start at level 2, which means only 1 level of recursion
		);
	}
}