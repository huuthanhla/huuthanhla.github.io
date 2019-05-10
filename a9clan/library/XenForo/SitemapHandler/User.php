<?php

class XenForo_SitemapHandler_User extends XenForo_SitemapHandler_Abstract
{
	protected $_userModel;
	protected $_userProfileModel;

	public function getRecords($previousLast, $limit, array $viewingUser)
	{
		if (!XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'viewProfile'))
		{
			return array();
		}

		$userModel = $this->_getUserModel();
		$ids = $userModel->getUserIdsInRange($previousLast, $limit);

		$users = $userModel->getUsersByIds($ids, array(
			'join' => XenForo_Model_User::FETCH_USER_FULL,
			'followingUserId' => $viewingUser['user_id']
		));
		ksort($users);

		return $users;
	}

	public function isIncluded(array $entry, array $viewingUser)
	{
		return $this->_getUserProfileModel()->canViewFullUserProfile($entry, $null, $viewingUser);
	}

	public function getData(array $entry)
	{
		$result = array(
			'loc' => XenForo_Link::buildPublicLink('canonical:members', $entry),
			'priority' => 0.3
		);

		if ($entry['gravatar'] || $entry['avatar_date'])
		{
			$avatarUrl = htmlspecialchars_decode(
				XenForo_Template_Helper_Core::callHelper('avatar', array($entry, 'l'))
			);
			$avatarUrl = XenForo_Link::convertUriToAbsoluteUri($avatarUrl, true, $this->getCanonicalPaths());
			$result['image'] = $avatarUrl;
		}

		return $result;
	}

	public function isInterruptable()
	{
		return true;
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		if (!$this->_userModel)
		{
			$this->_userModel = XenForo_Model::create('XenForo_Model_User');
		}

		return $this->_userModel;
	}

	/**
	 * @return XenForo_Model_UserProfile
	 */
	protected function _getUserProfileModel()
	{
		if (!$this->_userProfileModel)
		{
			$this->_userProfileModel = XenForo_Model::create('XenForo_Model_UserProfile');
		}

		return $this->_userProfileModel;
	}
}