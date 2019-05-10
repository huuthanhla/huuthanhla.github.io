<?php

abstract class XenForo_EditHistoryHandler_Abstract
{
	protected $_prefix = '';

	abstract protected function _getContent($contentId, array $viewingUser);
	abstract protected function _canViewHistoryAndContent(array $content, array $viewingUser);
	abstract protected function _canRevertContent(array $content, array $viewingUser);
	abstract public function getText(array $content);
	abstract public function getTitle(array $content);
	abstract public function getBreadcrumbs(array $content);
	abstract public function getNavigationTab();
	abstract public function formatHistory($string, XenForo_View $view);
	abstract public function revertToVersion(array $content, $revertCount, array $history, array $previous = null);

	public function getContent($contentId, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return $this->_getContent($contentId, $viewingUser);
	}

	public function canViewHistoryAndContent(array $content, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return $this->_canViewHistoryAndContent($content, $viewingUser);
	}

	public function canRevertContent(array $content, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		return $this->_canRevertContent($content, $viewingUser);
	}

	public function buildContentLink(array $content)
	{
		return XenForo_Link::buildPublicLink($this->_prefix, $content);
	}

	/**
	 * Standardizes the viewing user array reference.
	 *
	 * @param array|null $viewingUser Viewing user array. Will be normalized.
	 */
	public function standardizeViewingUserReference(array &$viewingUser = null)
	{
		if (!is_array($viewingUser) || !isset($viewingUser['user_id']))
		{
			$viewingUser = XenForo_Visitor::getInstance()->toArray();
		}
	}
}