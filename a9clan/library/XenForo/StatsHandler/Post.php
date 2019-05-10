<?php

class XenForo_StatsHandler_Post extends XenForo_StatsHandler_Abstract
{
	public function getStatsTypes()
	{
		return array(
			'post' => new XenForo_Phrase('posts'),
			'post_like' => new XenForo_Phrase('post_likes')
		);
	}

	public function getData($startDate, $endDate)
	{
		$db = $this->_getDb();

		$posts = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_post', 'post_date', 'message_state = ?'),
			array($startDate, $endDate, 'visible')
		);

		$postLikes = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_liked_content', 'like_date', 'content_type = ?'),
			array($startDate, $endDate, 'post')
		);

		return array(
			'post' => $posts,
			'post_like' => $postLikes
		);
	}
}