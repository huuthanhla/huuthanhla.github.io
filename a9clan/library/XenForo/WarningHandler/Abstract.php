<?php

abstract class XenForo_WarningHandler_Abstract
{
	abstract protected function _canView(array $content, array $viewingUser);
	abstract protected function _canWarn($userId, array $content, array $viewingUser);
	abstract protected function _canDeleteContent(array $content, array $viewingUser);
	abstract protected function _getContent(array $contentIds, array $viewingUser);
	abstract public function getContentTitle(array $content);
	abstract public function getContentUrl(array $content, $canonical = false);

	public function canView(array $content, array $viewingUser = null)
	{
		$this->_standardizeViewingUser($viewingUser);
		return $this->_canView($content, $viewingUser);
	}

	public function canWarn($userId, array $content, array $viewingUser = null)
	{
		$this->_standardizeViewingUser($viewingUser);

		if (isset($content['user_id']) && $userId != $content['user_id'])
		{
			return false; // generally correct
		}

		return $this->_canWarn($userId, $content, $viewingUser);
	}

	public function canDeleteContent($content, array $viewingUser = null)
	{
		$this->_standardizeViewingUser($viewingUser);

		return $this->_canDeleteContent($content, $viewingUser);
	}

	public function getContent($contentIds, array $viewingUser = null)
	{
		if (!is_array($contentIds))
		{
			$returnSingle = $contentIds;
			$contentIds = array($contentIds);
		}
		else
		{
			$returnSingle = false;
		}

		$this->_standardizeViewingUser($viewingUser);
		$output = $this->_getContent($contentIds, $viewingUser);

		if ($returnSingle)
		{
			return (isset($output[$returnSingle]) ? $output[$returnSingle] : false);
		}
		else
		{
			return $output;
		}
	}

	public function getContentDetails(array $content)
	{
		return $this->getContentTitle($content);
	}

	final public function warn(array $warning, array $content, $publicMessage = '', array $viewingUser = null)
	{
		$this->_standardizeViewingUser($viewingUser);
		$this->_warn($warning, $content, $publicMessage, $viewingUser);
	}

	protected function _warn(array $warning, array $content, $publicMessage, array $viewingUser)
	{
	}

	final public function reverseWarning(array $warning, array $content = array())
	{
		$this->_reverseWarning($warning, $content);
	}

	protected function _reverseWarning(array $warning, array $content)
	{
	}

	final public function prepareWarning(array $warning, array $viewingUser = null)
	{
		$this->_standardizeViewingUser($viewingUser);
		return $this->_prepareWarning($warning, $viewingUser);
	}

	final public function deleteContent(array $content, $reason = '', array $viewingUser = null)
	{
		$this->_standardizeViewingUser($viewingUser);
		$this->_deleteContent($content, $reason, $viewingUser);
	}

	protected function _deleteContent(array $content, $reason, array $viewingUser)
	{
	}

	public function getContentTitleForDisplay($title)
	{
		return $title;
	}

	protected function _prepareWarning(array $warning, array $viewingUser)
	{
		$warning['content_title'] = $this->getContentTitleForDisplay($warning['content_title']);

		return $warning;
	}

	public function canPubliclyDisplayWarning()
	{
		return false;
	}

	protected function _standardizeViewingUser(array &$viewingUser = null)
	{
		if (!is_array($viewingUser) || !isset($viewingUser['user_id']))
		{
			$viewingUser = XenForo_Visitor::getInstance()->toArray();
		}
	}
}