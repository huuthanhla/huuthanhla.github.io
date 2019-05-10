<?php

class XenForo_SpamHandler_Post extends XenForo_SpamHandler_Abstract
{
	/**
	 * Checks that the options array contains a non-empty 'delete_messages' key
	 *
	 * @param array $user
	 * @param array $options
	 *
	 * @return boolean
	 */
	public function cleanUpConditionCheck(array $user, array $options)
	{
		return !empty($options['delete_messages']);
	}

	/**
	 * @see XenForo_SpamHandler_Abstract::cleanUp()
	 */
	public function cleanUp(array $user, array &$log, &$errorKey)
	{
		if ($posts = $this->getModelFromCache('XenForo_Model_Post')->getPostsByUserInOthersThreads($user['user_id']))
		{
			$postIds = array_keys($posts);

			$this->getModelFromCache('XenForo_Model_SpamPrevention')->submitSpamCommentData('post', $postIds);

			$deleteType = (XenForo_Application::get('options')->spamMessageAction == 'delete' ? 'hard' : 'soft');

			$log['post'] = array(
				'deleteType' => $deleteType,
				'postIds' => $postIds
			);

			$inlineModModel = $this->getModelFromCache('XenForo_Model_InlineMod_Post');
			$inlineModModel->enableLogging = false;

			return $inlineModModel->deletePosts(
				$postIds, array('deleteType' => $deleteType, 'skipPermissions' => true), $errorKey
			);
		}

		return true;
	}

	/**
	 * @see XenForo_SpamHandler_Abstract::restore()
	 */
	public function restore(array $log, &$errorKey = '')
	{
		if ($log['deleteType'] == 'soft')
		{
			$inlineModModel = $this->getModelFromCache('XenForo_Model_InlineMod_Post');
			$inlineModModel->enableLogging = false;

			return $inlineModModel->undeletePosts(
				$log['postIds'], array('skipPermissions' => true), $errorKey
			);
		}

		return true;
	}
}