<?php

class XenForo_ViewPublic_Account_Ignore extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		foreach ($this->_params['users'] AS $userId => &$user)
		{
			$user = $this->createTemplateObject('member_list_item_ignored', array('user' => $user));
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array(
			'users' => $this->_params['users'],
			'userIds' => $this->_params['userIds']
		));
	}
}