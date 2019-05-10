<?php

class XenForo_ViewPublic_Member_Find extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$results = array();
		foreach ($this->_params['users'] AS $user)
		{
			$results[$user['username']] = array(
				'avatar' => XenForo_Template_Helper_Core::callHelper('avatar', array($user, 's')),
				'username' => htmlspecialchars($user['username'])
			);
		}

		return array(
			'results' => $results
		);
	}
}