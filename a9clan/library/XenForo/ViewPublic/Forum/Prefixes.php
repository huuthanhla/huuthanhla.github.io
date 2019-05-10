<?php

/**
 * View handling for displaying a list of all prefixes available to a node
 *
 * @package XenForo_Nodes
 */
class XenForo_ViewPublic_Forum_Prefixes extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$prefixGroups = array();

		foreach ($this->_params['prefixGroups'] AS $prefixGroupId => $prefixGroup)
		{
			$group = array(
				'prefix_group_id' => $prefixGroupId,
				'prefixes' => array()
			);

			if ($prefixGroupId)
			{
				$group['title'] = $prefixGroup['title'];
			}

			foreach ($prefixGroup['prefixes'] AS $prefixId => $prefix)
			{
				$group['prefixes'][] = array(
					'prefix_id' => $prefixId,
					'title' => new XenForo_Phrase('thread_prefix_' . $prefixId),
					'css' => $prefix['css_class']
				);
			}

			$prefixGroups[] = $group;
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'node_id' => $this->_params['forum']['node_id'],
			'prefixGroups' => $prefixGroups
		));
	}
}