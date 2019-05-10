<?php

class XenForo_WarningHandler_User extends XenForo_WarningHandler_Abstract
{
	protected function _canView(array $content, array $viewingUser)
	{
		return true;
	}

	protected function _canWarn($userId, array $content, array $viewingUser)
	{
		return XenForo_Model::create('XenForo_Model_User')->canWarnUser($content, $null, $viewingUser);
	}

	protected function _canDeleteContent(array $content, array $viewingUser)
	{
		return false;
	}

	protected function _getContent(array $contentIds, array $viewingUser)
	{
		return XenForo_Model::create('XenForo_Model_User')->getUsersByIds($contentIds);
	}

	public function getContentTitle(array $content)
	{
		return $content['username'];
	}

	public function getContentUrl(array $content, $canonical = false)
	{
		return XenForo_Link::buildPublicLink(($canonical ? 'canonical:' : '') . 'members', $content);
	}
}