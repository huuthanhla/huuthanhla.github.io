<?php

class XenForo_StatsHandler_ProfilePost extends XenForo_StatsHandler_Abstract
{
	public function getStatsTypes()
	{
		return array(
			'profile_post' => new XenForo_Phrase('profile_posts'),
			'profile_post_like' => new XenForo_Phrase('profile_post_likes'),
			'profile_post_comment' => new XenForo_Phrase('profile_post_comments')
		);
	}

	public function getData($startDate, $endDate)
	{
		$db = $this->_getDb();

		$profilePosts = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_profile_post', 'post_date', 'message_state = ?'),
			array($startDate, $endDate, 'visible')
		);

		$profilePostLikes = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_liked_content', 'like_date', 'content_type = ?'),
			array($startDate, $endDate, 'profile_post')
		);

		$profilePostComments = $db->fetchPairs(
			$this->_getBasicDataQuery('xf_profile_post_comment', 'comment_date'),
			array($startDate, $endDate)
		);

		return array(
			'profile_post' => $profilePosts,
			'profile_post_like' => $profilePostLikes,
			'profile_post_comment' => $profilePostComments,
		);
	}
}