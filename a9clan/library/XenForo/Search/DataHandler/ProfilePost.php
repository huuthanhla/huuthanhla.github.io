<?php

/**
 * Handles searching of profile posts.
 *
 * @package XenForo_Search
 */
class XenForo_Search_DataHandler_ProfilePost extends XenForo_Search_DataHandler_Abstract
{
	/**
	 * @var XenForo_Model_ProfilePost
	 */
	protected $_profilePostModel = null;

	/**
	 * @var XenForo_Model_UserProfile
	 */
	protected $_userProfileModel = null;

	/**
	 * @var XenForo_Model_User
	 */
	protected $_userModel = null;

	/**
	 * Inserts into (or replaces a record) in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_insertIntoIndex()
	 */
	protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
	{
		if ($data['message_state'] != 'visible')
		{
			return;
		}

		$metadata = array();
		$metadata['profile_user'] = $data['profile_user_id'];

		$indexer->insertIntoIndex(
			'profile_post', $data['profile_post_id'],
			'', $data['message'],
			$data['post_date'], $data['user_id'], $data['profile_post_id'], $metadata
		);
	}

	/**
	 * Updates a record in the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_updateIndex()
	 */
	protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
	{
		$indexer->updateIndex('profile_post', $data['profile_post_id'], $fieldUpdates);
	}

	/**
	 * Deletes one or more records from the index.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::_deleteFromIndex()
	 */
	protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
	{
		$profilePostIds = array();
		foreach ($dataList AS $data)
		{
			$profilePostIds[] = is_array($data) ? $data['profile_post_id'] : $data;
		}

		$indexer->deleteFromIndex('profile_post', $profilePostIds);
	}

	/**
	 * Rebuilds the index for a batch.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::rebuildIndex()
	 */
	public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
	{
		$profilePostIds = $this->_getProfilePostModel()->getProfilePostIdsInRange($lastId, $batchSize);
		if (!$profilePostIds)
		{
			return false;
		}

		$this->quickIndex($indexer, $profilePostIds);

		return max($profilePostIds);
	}

	/**
	 * Rebuilds the index for the specified content.

	 * @see XenForo_Search_DataHandler_Abstract::quickIndex()
	 */
	public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
	{
		$profilePostModel = $this->_getProfilePostModel();

		$profilePosts = $profilePostModel->getProfilePostsByIds($contentIds);

		foreach ($profilePosts AS $profilePost)
		{
			$this->insertIntoIndex($indexer, $profilePost);
		}

		return true;
	}

	public function getInlineModConfiguration()
	{
		return array(
			'name' => new XenForo_Phrase('profile_post'),
			'route' => 'inline-mod/profile-post/switch',
			'cookie' => 'profilePosts',
			'template' => 'inline_mod_controls_profile_post'
		);
	}

	/**
	 * Gets the type-specific data for a collection of results of this content type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getDataForResults()
	 */
	public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
	{
		$profilePostModel = $this->_getProfilePostModel();

		$profilePosts = $profilePostModel->getProfilePostsByIds($ids, array(
			'join' =>
				XenForo_Model_ProfilePost::FETCH_USER_POSTER |
				XenForo_Model_ProfilePost::FETCH_USER_RECEIVER |
				XenForo_Model_ProfilePost::FETCH_USER_RECEIVER_PRIVACY,
			'permissionCombinationId' => $viewingUser['permission_combination_id'],
			'viewingUser' => $viewingUser
		));

		return $profilePosts;
	}

	/**
	 * Determines if this result is viewable.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::canViewResult()
	 */
	public function canViewResult(array $result, array $viewingUser)
	{
		$profilePostModel = $this->_getProfilePostModel();

		$receivingUser = $profilePostModel->getProfileUserFromProfilePost($result, $viewingUser);

		return $profilePostModel->canViewProfilePostAndContainer($result, $receivingUser, $null, $viewingUser);
	}

	/**
	 * Prepares a result for display.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::prepareResult()
	 */
	public function prepareResult(array $result, array $viewingUser)
	{
		$profilePostModel = $this->_getProfilePostModel();

		$receivingUser = $profilePostModel->getProfileUserFromProfilePost($result, $viewingUser);

		return $this->_getProfilePostModel()->prepareProfilePost($result, $receivingUser, $viewingUser);
	}

	/**
	 * Gets the date of the result (from the result's content).
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getResultDate()
	 */
	public function getResultDate(array $result)
	{
		return $result['post_date'];
	}

	/**
	 * Renders a result to HTML.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::renderResult()
	 */
	public function renderResult(XenForo_View $view, array $result, array $search)
	{
		return $view->createTemplateObject('search_result_profile_post', array(
			'profilePost' => $result,
			'search' => $search,
			'enableInlineMod' => $this->_inlineModEnabled
		));
	}

	public function addInlineModOption(array &$result)
	{
		$profilePostModel = $this->_getProfilePostModel();

		$receivingUser = $profilePostModel->getProfileUserFromProfilePost($result);
		return $profilePostModel->addInlineModOptionToProfilePost($result, $receivingUser);
	}

	/**
	 * Returns an array of content types handled by this class
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getSearchContentTypes()
	 */
	public function getSearchContentTypes()
	{
		return array('profile_post');
	}

	/**
	 * Get type-specific constrints from input.
	 *
	 * @param XenForo_Input $input
	 *
	 * @return array
	 */
	public function getTypeConstraintsFromInput(XenForo_Input $input)
	{
		$constraints = array();

		if ($profileUsersInput = $input->filterSingle('profile_users', XenForo_Input::STRING))
		{
			$userModel = $this->_getUserModel();

			$profileUsers = $userModel->getUsersByNames(explode(',', $profileUsersInput));
			if ($profileUsers)
			{
				$constraints['profile_user'] = array_keys($profileUsers);
			}
		}

		return $constraints;
	}

	/**
	 * Process a type-specific constraint.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::processConstraint()
	 */
	public function processConstraint(XenForo_Search_SourceHandler_Abstract $sourceHandler, $constraint, $constraintInfo, array $constraints)
	{
		if ($constraint == 'profile_user')
		{
			if ($constraintInfo)
			{
				return array(
					'metadata' => array('profile_user', $constraintInfo)
				);
			}
		}

		return false;
	}

	/**
	 * Gets the search form controller response for this type.
	 *
	 * @see XenForo_Search_DataHandler_Abstract::getSearchFormControllerResponse()
	 */
	public function getSearchFormControllerResponse(XenForo_ControllerPublic_Abstract $controller, XenForo_Input $input, array $viewParams)
	{
		$params = $input->filterSingle('c', XenForo_Input::ARRAY_SIMPLE);

		if (!empty($params['profile_user']))
		{
			$profileUsers = $this->_getUserModel()->getUsersByIds($params['profile_user']);
			foreach ($profileUsers AS &$profileUser)
			{
				$profileUser = $profileUser['username'];
			}
		}

		$viewParams['search'] = array_merge($viewParams['search'], array(
			'profile_users' => empty($profileUsers) ? '' : implode(', ', $profileUsers)
		));

		return $controller->responseView('XenForo_ViewPublic_Search_Form_ProfilePost', 'search_form_profile_post', $viewParams);
	}

	/**
	 * @return XenForo_Model_ProfilePost
	 */
	protected function _getProfilePostModel()
	{
		if (!$this->_profilePostModel)
		{
			$this->_profilePostModel = XenForo_Model::create('XenForo_Model_ProfilePost');
		}

		return $this->_profilePostModel;
	}

	/**
	 * @return XenForo_Model_UserProfile
	 */
	protected function _getUserProfileModel()
	{
		if (!$this->_userProfileModel)
		{
			$this->_userProfileModel = XenForo_Model::create('XenForo_Model_UserProfile');
		}

		return $this->_userProfileModel;
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		if (!$this->_userModel)
		{
			$this->_userModel = XenForo_Model::create('XenForo_Model_User');
		}

		return $this->_userModel;
	}
}