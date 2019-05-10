<?php

/**
 * Controller to manage user group promotions.
 */
class XenForo_ControllerAdmin_UserGroupPromotion extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('userGroup');
	}

	/**
	 * Displays a list of user group promotions.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$viewParams = array(
			'promotions' => $this->_getPromotionModel()->getPromotions()
		);
		return $this->responseView('XenForo_ViewAdmin_UserGroupPromotion_List', 'user_group_promotion_list', $viewParams);
	}

	/**
	 * Gets the add/edit form response for a promotion.
	 *
	 * @param array $promotion
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getPromotionAddEditResponse(array $promotion)
	{
		$userGroupOptions = $this->_getUserGroupModel()->getUserGroupOptions(
			$promotion['extra_user_group_ids']
		);

		$viewParams = array(
			'promotion' => $promotion,
			'userCriteria' => XenForo_Helper_Criteria::prepareCriteriaForSelection($promotion['user_criteria']),
			'userGroupOptions' => $userGroupOptions,
			'userCriteriaData' => XenForo_Helper_Criteria::getDataForUserCriteriaSelection()
		);

		return $this->responseView('XenForo_ViewAdmin_UserGroupPromotion_Edit', 'user_group_promotion_edit', $viewParams);
	}

	/**
	 * Displays a form to add a new promotion.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getPromotionAddEditResponse(array(
			'title' => '',
			'active' => 1,
			'user_criteria' => array(),
			'extra_user_group_ids' => ''
		));
	}

	/**
	 * Displays a form to edit an existing promotion.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$promotionId = $this->_input->filterSingle('promotion_id', XenForo_Input::UINT);
		$promotion = $this->_getPromotionOrError($promotionId);

		return $this->_getPromotionAddEditResponse($promotion);
	}

	/**
	 * Saves a promotion.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$promotionId = $this->_input->filterSingle('promotion_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'active' => XenForo_Input::UINT,
			'extra_user_group_ids' => array(XenForo_Input::UINT, 'array' => true),
			'user_criteria' => XenForo_Input::ARRAY_SIMPLE
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserGroupPromotion');
		if ($promotionId)
		{
			$dw->setExistingData($promotionId);
		}
		$dw->bulkSet($dwInput);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('user-group-promotions') . $this->getLastHash($dw->get('promotion_id'))
		);
	}

	/**
	 * Deletes a promotion.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_UserGroupPromotion', 'promotion_id',
				XenForo_Link::buildAdminLink('user-group-promotions')
			);
		}
		else
		{
			$promotionId = $this->_input->filterSingle('promotion_id', XenForo_Input::UINT);
			$promotion = $this->_getPromotionOrError($promotionId);

			$viewParams = array(
				'promotion' => $promotion
			);

			return $this->responseView('XenForo_ViewAdmin_UserGroupPromotion_Delete', 'user_group_promotion_delete', $viewParams);
		}
	}

	/**
	 * Displays a form to do some forms of management on promotions (history, manual adjustment).
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionManage()
	{
		$viewParams = array(
			'promotions' => $this->_getPromotionModel()->getPromotions()
		);
		return $this->responseView('XenForo_ViewAdmin_UserGroupPromotion_Manage', 'user_group_promotion_manage', $viewParams);
	}

	/**
	 * Display promotion history information.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionHistory()
	{
		$input = $this->_input->filter(array(
			'username' => XenForo_Input::STRING,
			'user_id' => XenForo_Input::UINT,
			'promotion_id' => XenForo_Input::UINT
		));

		$user = false;
		$promotion = false;

		if ($input['user_id'])
		{
			$user = $this->_getUserModel()->getUserById($input['user_id']);
			if (!$user)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'));
			}
		}

		if ($input['username'])
		{
			$user = $this->_getUserModel()->getUserByName($input['username']);
			if (!$user)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'));
			}

			$input['user_id'] = $user['user_id'];
		}

		if ($input['promotion_id'])
		{
			$promotion = $this->_getPromotionOrError($input['promotion_id']);
		}

		$linkParams = array();
		if ($input['promotion_id'])
		{
			$linkParams['promotion_id'] = $input['promotion_id'];
		}
		if ($input['user_id'])
		{
			$linkParams['user_id'] = $input['user_id'];
		}

		if ($this->_request->isPost())
		{
			// redirect to a get request approach
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('user-group-promotions/history', false, $linkParams)
			);
		}

		$promotionModel = $this->_getPromotionModel();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;

		$conditions = array(
			'promotion_id' => $input['promotion_id'],
			'user_id' => $input['user_id']
		);

		$entries = $promotionModel->getPromotionLogEntries($conditions, array(
			'join' => XenForo_Model_UserGroupPromotion::FETCH_USER_NAME | XenForo_Model_UserGroupPromotion::FETCH_PROMOTION_TITLE,
			'page' => $page,
			'perPage' => $perPage
		));
		$totalEntries = $promotionModel->countPromotionLogEntries($conditions);

		$this->canonicalizePageNumber($page, $perPage, $totalEntries, 'user-group-promotions/history');

		$viewParams = array(
			'entries' => $entries,
			'totalEntries' => $totalEntries,

			'page' => $page,
			'perPage' => $perPage,
			'linkParams' => $linkParams
		);
		return $this->responseView('XenForo_ViewAdmin_UserGroupPromotion_History', 'user_group_promotion_history', $viewParams);
	}

	/**
	 * Manually promotes or demotes a user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionManual()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'username' => XenForo_Input::STRING,
			'promotion_id' => XenForo_Input::UINT,
			'action' => XenForo_Input::STRING
		));

		$user = $this->_getUserModel()->getUserByName($input['username']);
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_user_not_found'));
		}

		$promotion = $this->_getPromotionOrError($input['promotion_id']);

		if ($input['action'] == 'promote')
		{
			$this->_getPromotionModel()->promoteUser($promotion, $user['user_id'], 'manual');
		}
		else
		{
			$this->_getPromotionModel()->demoteUser($promotion, $user['user_id'], true);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('user-group-promotions/manage')
		);
	}

	/**
	 * Demotes a user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDemote()
	{
		$promotion = $this->_getPromotionOrError(
			$this->_input->filterSingle('promotion_id', XenForo_Input::UINT)
		);

		$user = $this->_getUserModel()->getUserById(
			$this->_input->filterSingle('user_id', XenForo_Input::UINT)
		);
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_user_not_found'));
		}

		$promotionModel = $this->_getPromotionModel();

		$entry = $promotionModel->getPromotionLogEntry($promotion['promotion_id'], $user['user_id']);
		$isDemotion = (!$entry || $entry['promotion_state'] != 'disabled');

		if ($this->isConfirmedPost())
		{
			if ($isDemotion)
			{
				// user has been given this promotion, so demote them and don't allow reapplication
				$promotionModel->demoteUser($promotion, $user['user_id'], true);
			}
			else
			{
				// removing a disabled limit: "demote" but allow reapplication
				$promotionModel->demoteUser($promotion, $user['user_id'], false);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect()
			);
		}
		else
		{
			$viewParams = array(
				'promotion' => $promotion,
				'user' => $user,
				'entry' => $entry,
				'isDemotion' => $isDemotion,
				'redirect' => $this->getDynamicRedirect()
			);
			return $this->responseView('XenForo_ViewAdmin_UserGroupPromotion_Demote', 'user_group_promotion_demote', $viewParams);
		}
	}

	/**
	 * Selectively enables or disables specified promotions
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionToggle()
	{
		return $this->_getToggleResponse(
			$this->_getPromotionModel()->getPromotions(),
			'XenForo_DataWriter_UserGroupPromotion',
			'user-group-promotions');
	}

	/**
	 * Gets the specified promotion or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getPromotionOrError($id)
	{
		return $this->getRecordOrError(
			$id, $this->_getPromotionModel(), 'getPromotionById',
			'requested_promotion_not_found'
		);
	}

	/**
	 * @return XenForo_Model_UserGroupPromotion
	 */
	protected function _getPromotionModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroupPromotion');
	}

	/**
	 * @return XenForo_Model_UserGroup
	 */
	protected function _getUserGroupModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroup');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}