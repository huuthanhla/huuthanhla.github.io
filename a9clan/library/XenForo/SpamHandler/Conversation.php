<?php

class XenForo_SpamHandler_Conversation extends XenForo_SpamHandler_Abstract
{
	/**
	 * @param array $user
	 * @param array $options
	 *
	 * @return boolean
	 */
	public function cleanUpConditionCheck(array $user, array $options)
	{
		return !empty($options['delete_conversations']);
	}

	/**
	 * @see XenForo_SpamHandler_Abstract::cleanUp()
	 */
	public function cleanUp(array $user, array &$log, &$errorKey)
	{
		$log['conversation'] = array(
			'count' => $this->getModelFromCache('XenForo_Model_Conversation')
				->deleteConversationsStartedByUser($user['user_id'])
		);

		return true;
	}

	/**
	 * @see XenForo_SpamHandler_Abstract::restore()
	 */
	public function restore(array $log, &$errorKey = '')
	{
		return true;
	}
}