<?php

class XenForo_ViewPublic_Account_Follow extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		foreach ($this->_params['followUsers'] AS $userId => &$user)
		{
			$user = $this->createTemplateObject('member_list_item_follower', array('user' => $user));
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'users' => $this->_params['followUsers'],
			'userIds' => $this->_params['following']
		));
	}
}