<?php

class XenForo_ControllerPublic_RecentActivity extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Gets the global news feed
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		// require member viewing permissions to see this.
		// this is partially related to the selected tab for this action but it
		// does also bring some very detailed data up (new likes, avatar changes, etc).
		// visitors without this permission can generally find the new stuff
		// in other ways.
		if (!$this->getModelFromCache('XenForo_Model_User')->canViewMemberList())
		{
			return $this->responseNoPermission();
		}

		$this->_assertNewsFeedEnabled();

		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('recent-activity')
		);

		$newsFeedId = $this->_input->filterSingle('news_feed_id', XenForo_Input::UINT);

		$viewParams = $this->getModelFromCache('XenForo_Model_NewsFeed')->getNewsFeed(array(), $newsFeedId);

		// online users
		$visitor = XenForo_Visitor::getInstance();

		$sessionModel = $this->getModelFromCache('XenForo_Model_Session');

		$viewParams['onlineUsers'] = $sessionModel->getSessionActivityQuickList(
			$visitor->toArray(),
			array('cutOff' => array('>', $sessionModel->getOnlineStatusTimeout())),
			($visitor['user_id'] ? $visitor->toArray() : null)
		);

		return $this->responseView(
			'XenForo_ViewPublic_NewsFeed_View',
			'news_feed_page_global',
			$viewParams
		);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('viewing_latest_content');
	}

	/**
	 * Throws a 503 error if the news feed is disabled
	 */
	protected function _assertNewsFeedEnabled()
	{
		if (!XenForo_Application::get('options')->enableNewsFeed)
		{
			throw $this->responseException(
				$this->responseError(new XenForo_Phrase('news_feed_disabled'), 503) // 503 Service Unavailable
			);
		}
	}
}