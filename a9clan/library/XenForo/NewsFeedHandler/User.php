<?php

/**
 * News feed handler for user profile changes
 *
 * @author kier
 *
 */
class XenForo_NewsFeedHandler_User extends XenForo_NewsFeedHandler_Abstract
{
	/**
	 * Just returns a value for each requested ID
	 * but does no actual DB work
	 *
	 * @param array $contentIds
	 * @param XenForo_Model_NewsFeed $model
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	public function getContentByIds(array $contentIds, $model, array $viewingUser)
	{
		return array_fill_keys($contentIds, true);
	}

	public function canViewNewsFeedItem(array $item, $content, array $viewingUser)
	{
		return XenForo_Model::create('XenForo_Model_UserProfile')->canViewFullUserProfile($item, $null, $viewingUser);
	}

	/**
	 * Prepares the news feed item for display
	 *
	 * @param array $item News feed item
	 * @param array $content News feed item content
	 * @param array $viewingUser Information about the viewing user (keys: user_id, permission_combination_id, permissions)
	 *
	 * @return array
	 */
	protected function _prepareNewsFeedItemAfterAction(array $item, $content, array $viewingUser)
	{
		if (isset($item[$item['action']]['old']))
		{
			$item[$item['action']]['old'] = XenForo_Helper_String::censorString($item[$item['action']]['old']);
		}

		if (isset($item[$item['action']]['new']))
		{
			$item[$item['action']]['new'] = XenForo_Helper_String::censorString($item[$item['action']]['new']);
		}

		return $item;
	}

	/**
	 * Sets a key with the same name as the item's 'action'
	 * and unserializes the 'extra_data' field into it,
	 * before deleting 'extra_data'
	 *
	 * @param array $item
	 *
	 * @return $item
	 */
	protected function _setFieldFromExtraData(array $item)
	{
		$item[$item['action']] = unserialize($item['extra_data']);

		unset($item['extra_data']);

		return $item;
	}

	protected function _prepareStatus(array $item)
	{
		return $this->_setFieldFromExtraData($item);
	}

	protected function _prepareHomepage(array $item)
	{
		return $this->_setFieldFromExtraData($item);
	}

	protected function _prepareLocation(array $item)
	{
		return $this->_setFieldFromExtraData($item);
	}

	protected function _prepareOccupation(array $item)
	{
		return $this->_setFieldFromExtraData($item);
	}






}