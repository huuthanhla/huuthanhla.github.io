<?php

/**
* Data writer for profile post comments
*
* @package XenForo_ProfilePost
*/
class XenForo_DataWriter_ProfilePostComment extends XenForo_DataWriter
{
	const DATA_PROFILE_USER = 'profileUser';

	const DATA_PROFILE_POST = 'profilePost';

	/**
	 * Option to control whether or not to log the IP address of the message sender
	 *
	 * @var string
	 */
	const OPTION_SET_IP_ADDRESS = 'setIpAddress';

	/**
	 * Controls the maximum number of tag alerts that can be sent.
	 *
	 * @var string
	 */
	const OPTION_MAX_TAGGED_USERS = 'maxTaggedUsers';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_comment_not_found';

	protected $_taggedUsers = array();

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_profile_post_comment' => array(
				'profile_post_comment_id'   => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'profile_post_id'           => array('type' => self::TYPE_UINT,   'required' => true),
				'user_id'                   => array('type' => self::TYPE_UINT,   'required' => true),
				'username'                  => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
						'requiredError' => 'please_enter_valid_name'
				),
				'comment_date'           => array('type' => self::TYPE_UINT,   'required' => true, 'default' => XenForo_Application::$time),
				'message'                => array('type' => self::TYPE_STRING, 'required' => true,
						'requiredError' => 'please_enter_valid_message'
				),
				'ip_id'                  => array('type' => self::TYPE_UINT,   'default' => 0),
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_profile_post_comment' => $this->_getProfilePostModel()->getProfilePostCommentById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'profile_post_comment_id = ' . $this->_db->quote($this->getExisting('profile_post_comment_id'));
	}

	/**
	 * Gets the data writer's default options.
	 *
	 * @return array
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_SET_IP_ADDRESS => true,
			self::OPTION_MAX_TAGGED_USERS => 0
		);
	}

	protected function _preSave()
	{
		if ($this->isChanged('message'))
		{
			$maxLength = 420;
			if (utf8_strlen($this->get('message')) > $maxLength)
			{
				$this->error(new XenForo_Phrase('please_enter_message_with_no_more_than_x_characters', array('count' => $maxLength)), 'message');
			}
		}

		// do this auto linking after length counting
		/** @var $taggingModel XenForo_Model_UserTagging */
		$taggingModel = $this->getModelFromCache('XenForo_Model_UserTagging');

		$this->_taggedUsers = $taggingModel->getTaggedUsersInMessage(
			$this->get('message'), $newMessage, 'text'
		);
		$this->set('message', $newMessage);
	}

	protected function _postSave()
	{
		 $profilePostId = $this->get('profile_post_id');

		if ($this->isInsert())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
			$dw->setExistingData($profilePostId);
			$dw->insertNewComment($this->get('profile_post_comment_id'), $this->get('comment_date'));
			$dw->save();

			$profileUser = $this->getExtraData(self::DATA_PROFILE_USER);
			$profilePost = $this->getExtraData(self::DATA_PROFILE_POST);

			$userModel = $this->_getUserModel();

			$alertedUserIds = array();

			if ($profilePost && $profileUser && $profileUser['user_id'] != $this->get('user_id'))
			{
				// alert profile owner - only if not ignoring either profile post itself or this comment
				if (!$userModel->isUserIgnored($profileUser, $this->get('user_id'))
					&& !$userModel->isUserIgnored($profileUser, $profilePost['user_id'])
					&& XenForo_Model_Alert::userReceivesAlert($profileUser, 'profile_post', 'comment_your_profile')
				)
				{
					XenForo_Model_Alert::alert(
						$profileUser['user_id'],
						$this->get('user_id'),
						$this->get('username'),
						'profile_post',
						$profilePostId,
						'comment_your_profile'
					);

					$alertedUserIds[] = $profileUser['user_id'];
				}
			}

			if ($profilePost && $profilePost['profile_user_id'] != $profilePost['user_id']
				&& $profilePost['user_id'] != $this->get('user_id')
			)
			{
				// alert post owner
				$user = $userModel->getUserById($profilePost['user_id'], array(
					'join' => XenForo_Model_User::FETCH_USER_OPTION | XenForo_Model_User::FETCH_USER_PROFILE
				));
				if ($user && !$userModel->isUserIgnored($user, $this->get('user_id'))
					&& XenForo_Model_Alert::userReceivesAlert($user, 'profile_post', 'comment_your_post')
				)
				{
					XenForo_Model_Alert::alert(
						$user['user_id'],
						$this->get('user_id'),
						$this->get('username'),
						'profile_post',
						$profilePostId,
						'comment_your_post'
					);

					$alertedUserIds[] = $user['user_id'];
				}
			}

			$otherCommenterIds = $this->_getProfilePostModel()->getProfilePostCommentUserIds($profilePostId);

			$otherCommenters = $userModel->getUsersByIds($otherCommenterIds, array(
				'join' => XenForo_Model_User::FETCH_USER_OPTION  | XenForo_Model_User::FETCH_USER_PROFILE
			));

			$profileUserId = empty($profileUser) ? 0 : $profileUser['user_id'];
			$profilePostUserId = empty($profilePost) ? 0 : $profilePost['user_id'];

			foreach ($otherCommenters AS $otherCommenter)
			{
				switch ($otherCommenter['user_id'])
				{
					case $profileUserId:
					case $profilePostUserId:
					case $this->get('user_id'):
					case 0:
						break;

					default:
						if (!$userModel->isUserIgnored($otherCommenter, $this->get('user_id'))
							&& XenForo_Model_Alert::userReceivesAlert($otherCommenter, 'profile_post', 'comment_other_commenter')
						)
						{
							XenForo_Model_Alert::alert(
								$otherCommenter['user_id'],
								$this->get('user_id'),
								$this->get('username'),
								'profile_post',
								$profilePostId,
								'comment_other_commenter'
							);

							$alertedUserIds[] = $otherCommenter['user_id'];
						}
				}
			}

			$maxTagged = $this->getOption(self::OPTION_MAX_TAGGED_USERS);
			if ($maxTagged && $this->_taggedUsers && $profilePost && $profileUser)
			{
				if ($maxTagged > 0)
				{
					$alertTagged = array_slice($this->_taggedUsers, 0, $maxTagged, true);
				}
				else
				{
					$alertTagged = $this->_taggedUsers;
				}
				$this->_getProfilePostModel()->alertTaggedMembers(
					$profilePost, $profileUser, $alertTagged, $alertedUserIds, true, array(
						'user_id' => $this->get('user_id'),
						'username' => $this->get('username')
					)
				);
			}

			if ($this->getOption(self::OPTION_SET_IP_ADDRESS) && !$this->get('ip_id'))
			{
				$this->_updateIpData();
			}
		}
	}

	protected function _postDelete()
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
		$dw->setExistingData($this->get('profile_post_id'));
		$dw->rebuildProfilePostCommentCounters();
		$dw->save();
	}

	/**
	* Upates the IP data.
	*/
	protected function _updateIpData()
	{
		if (!empty($this->_extraData['ipAddress']))
		{
			$ipAddress = $this->_extraData['ipAddress'];
		}
		else
		{
			$ipAddress = null;
		}

		$ipId = XenForo_Model_Ip::log($this->get('user_id'), 'profile_post_comment', $this->get('profile_post_comment_id'), 'insert', $ipAddress);
		$this->set('ip_id', $ipId, '', array('setAfterPreSave' => true));

		$this->_db->update('xf_profile_post_comment',
			array('ip_id' => $ipId),
			'profile_post_comment_id = ' .  $this->_db->quote($this->get('profile_post_comment_id'))
		);
	}

	/**
	 * @return XenForo_Model_ProfilePost
	 */
	protected function _getProfilePostModel()
	{
		return $this->getModelFromCache('XenForo_Model_ProfilePost');
	}
}