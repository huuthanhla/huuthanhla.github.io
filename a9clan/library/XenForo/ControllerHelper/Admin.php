<?php

class XenForo_ControllerHelper_Admin extends XenForo_ControllerHelper_Abstract
{
	public function checkSuperAdminEdit(array $user)
	{
		if ($user['is_admin']
			&& $this->_getUserModel()->isUserSuperAdmin($user)
			&& !XenForo_Visitor::getInstance()->isSuperAdmin()
		)
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError(new XenForo_Phrase('you_must_be_super_administrator_to_edit_user'))
			);
		}
	}

	public function assertVisitorPasswordCorrect($password, $field = 'visitor_password')
	{
		$visitorUserId = XenForo_Visitor::getUserId();
		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($visitorUserId);
		if (!$auth || !$auth->authenticate($visitorUserId, $password))
		{
			throw $this->_controller->responseException(
				$this->_controller->responseError(array(
					$field => new XenForo_Phrase('your_existing_password_is_not_correct')
				))
			);
		}
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->_controller->getModelFromCache('XenForo_Model_User');
	}
}