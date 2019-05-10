<?php

class XenForo_ViewAdmin_Banning_User_List extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		if (!empty($this->_params['filterView']))
		{
			$this->_templateName = 'ban_user_list_items';
		}

		return null;
	}
}