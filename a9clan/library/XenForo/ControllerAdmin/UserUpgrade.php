<?php

/**
 * Controller for managing user upgrades.
 *
 * @package XenForo_UserUpgrade
 */
class XenForo_ControllerAdmin_UserUpgrade extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('userUpgrade');
	}

	/**
	 * Displays a list of user upgrades, and the related option configuration.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$upgradeModel = $this->_getUserUpgradeModel();
		$optionModel = XenForo_Model::create('XenForo_Model_Option');

		$options = $optionModel->getOptionsByIds(array('payPalPrimaryAccount', 'payPalAlternateAccounts'));
		krsort($options); // just make sure the primary account is first

		$viewParams = array(
			'upgrades' => $upgradeModel->prepareUserUpgrades($upgradeModel->getAllUserUpgrades()),

			'options' => $optionModel->prepareOptions($options),
			'canEditOptionDefinition' => $optionModel->canEditOptionAndGroupDefinitions()
		);

		return $this->responseView('XenForo_ViewAdmin_UserUpgrade_List', 'user_upgrade_list', $viewParams);
	}

	/**
	 * Gets the upgrade add/edit form response.
	 *
	 * @param array $upgrade
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	protected function _getUpgradeAddEditResponse(array $upgrade)
	{
		$viewParams = array(
			'upgrade' => $upgrade,

			'userGroupOptions' => $this->getModelFromCache('XenForo_Model_UserGroup')->getUserGroupOptions(
				$upgrade['extra_group_ids']
			),

			'disabledUpgradeOptions' => $this->_getUserUpgradeModel()->getUserUpgradeOptions(
				$upgrade['disabled_upgrade_ids'], $upgrade['user_upgrade_id']
			)
		);

		return $this->responseView('XenForo_ViewAdmin_UserUpgrade_Edit', 'user_upgrade_edit', $viewParams);
	}

	/**
	 * Displays a form to add a user upgrade.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getUpgradeAddEditResponse(array(
			'user_upgrade_id' => null,
			'title' => '',
			'description' => '',
			'display_order' => 1,
			'extra_group_ids' => '',
			'recurring' => 0,
			'cost_amount' => 5,
			'cost_currency' => 'usd',
			'length_amount' => 1,
			'length_unit' => 'month',
			'disabled_upgrade_ids' => '',
			'can_purchase' => 1
		));
	}

	/**
	 * Displays a form to edit a user upgrade.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$userUpgradeId = $this->_input->filterSingle('user_upgrade_id', XenForo_Input::UINT);
		$upgrade = $this->_getUserUpgradeOrError($userUpgradeId);

		return $this->_getUpgradeAddEditResponse($upgrade);
	}

	/**
	 * Inserts a new upgrade or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$userUpgradeId = $this->_input->filterSingle('user_upgrade_id', XenForo_Input::UINT);
		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'extra_group_ids' => array(XenForo_Input::UINT, 'array' => true),
			'recurring' => XenForo_Input::UINT,
			'cost_amount' => XenForo_Input::UNUM,
			'cost_currency' => XenForo_Input::STRING,
			'length_amount' => XenForo_Input::UINT,
			'length_unit' => XenForo_Input::STRING,
			'disabled_upgrade_ids' => array(XenForo_Input::UINT, 'array' => true),
			'can_purchase' => XenForo_Input::UINT
		));
		if ($this->_input->filterSingle('length_type', XenForo_Input::STRING) == 'permanent')
		{
			$input['length_amount'] = 0;
			$input['length_unit'] = '';
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserUpgrade');
		if ($userUpgradeId)
		{
			$dw->setExistingData($userUpgradeId);
		}
		$dw->bulkSet($input);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('user-upgrades') . $this->getLastHash($dw->get('user_upgrade_id'))
		);
	}

	/**
	 * Deletes a user upgrade.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_UserUpgrade', 'user_upgrade_id',
				XenForo_Link::buildAdminLink('user-upgrades')
			);
		}
		else // show a confirmation dialog
		{
			$userUpgradeId = $this->_input->filterSingle('user_upgrade_id', XenForo_Input::UINT);
			$upgrade = $this->_getUserUpgradeOrError($userUpgradeId);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserUpgrade', XenForo_DataWriter::ERROR_EXCEPTION);
			$dw->setExistingData($upgrade, true);
			$dw->preDelete();

			$viewParams = array(
				'upgrade' => $upgrade
			);

			return $this->responseView('XenForo_ViewAdmin_UserUpgrade_Delete', 'user_upgrade_delete', $viewParams);
		}
	}

	protected function _getUpgradeRecordsListParams($active, $userUpgradeId = null, $username = null)
	{
		$userUpgradeModel = $this->_getUserUpgradeModel();

		if ($userUpgradeId === null)
		{
			$userUpgradeId = $this->_input->filterSingle('user_upgrade_id', XenForo_Input::UINT);
		}
		if ($userUpgradeId)
		{
			$upgrade = $this->_getUserUpgradeOrError($userUpgradeId);
		}
		else
		{
			$upgrade = null;
		}

		if ($username === null)
		{
			$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
		}
		if ($username)
		{
			$user = $this->_getUserModel()->getUserByName($username);
		}
		else
		{
			$user = null;
		}

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;
		$pageNavParams = array();

		$fetchOptions = array(
			'page' => $page,
			'perPage' => $perPage,
			'join' => XenForo_Model_UserUpgrade::JOIN_UPGRADE,
		);

		$orderBy = $this->_input->filterSingle('order', XenForo_Input::STRING);
		$orderDirection = $this->_input->filterSingle('direction', XenForo_Input::STRING);
		if ($orderBy)
		{
			$fetchOptions['order'] = $orderBy;
			$fetchOptions['direction'] = $orderDirection;
			$pageNavParams['order'] = $orderBy;
			$pageNavParams['direction'] = $orderDirection;
		}

		$conditions = array(
			'active' => $active
		);
		if ($upgrade)
		{
			$conditions['user_upgrade_id'] = $userUpgradeId;
		}
		if ($user)
		{
			$conditions['user_id'] = $user['user_id'];
			$pageNavParams['username'] = $user['username'];
		}

		$total = $userUpgradeModel->countUserUpgradeRecords($conditions);

		return array(
			'upgrade' => $upgrade,
			'upgradeRecords' => $userUpgradeModel->getUserUpgradeRecords($conditions, $fetchOptions),

			'user' => $user,

			'totalRecords' => $total,
			'perPage' => $perPage,
			'page' => $page,
			'pageNavParams' => $pageNavParams
		);
	}

	/**
	 * Displays a list of active upgrades, either across all upgrades or a specific one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionActive()
	{
		$viewParams = $this->_getUpgradeRecordsListParams(true);
		$this->canonicalizePageNumber(
			$viewParams['page'], $viewParams['perPage'], $viewParams['totalRecords'],
			'user-upgrades/active', $viewParams['upgrade']
		);
		return $this->responseView('XenForo_ViewAdmin_UserUpgrade_Active', 'user_upgrade_active', $viewParams);
	}

	/**
	 * Displays a list of expired upgrades, either across all upgrades or a specific one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionExpired()
	{
		$viewParams = $this->_getUpgradeRecordsListParams(false);
		$this->canonicalizePageNumber(
			$viewParams['page'], $viewParams['perPage'], $viewParams['totalRecords'],
			'user-upgrades/expired', $viewParams['upgrade']
		);
		return $this->responseView('XenForo_ViewAdmin_UserUpgrade_Expired', 'user_upgrade_expired', $viewParams);
	}

	/**
	 * Displays a form to manually upgrade a user with the specified upgrade,
	 * or actually upgrades the user.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionManual()
	{
		$userUpgradeId = $this->_input->filterSingle('user_upgrade_id', XenForo_Input::UINT);
		$upgrade = $this->_getUserUpgradeOrError($userUpgradeId);

		if ($this->_request->isPost())
		{
			$endDate = $this->_input->filterSingle('end_date', XenForo_Input::DATE_TIME);
			if (!$endDate)
			{
				$endDate = null; // if not specified, don't overwrite
			}

			$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
			$user = $this->getModelFromCache('XenForo_Model_User')->getUserByName($username);
			if (!$user)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
			}

			$this->_getUserUpgradeModel()->upgradeUser($user['user_id'], $upgrade, true, $endDate);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('user-upgrades')
			);
		}
		else
		{
			if ($upgrade['length_unit'])
			{
				$endDate = strtotime('+' . $upgrade['length_amount'] . ' ' . $upgrade['length_unit']);
			}
			else
			{
				$endDate = false;
			}

			$viewParams = array(
				'upgrade' => $upgrade,
				'endDate' => $endDate
			);

			return $this->responseView('XenForo_ViewAdmin_UserUpgrade_Manual', 'user_upgrade_manual', $viewParams);
		}
	}

	/**
	 * Displays a form to confirm downgrade of a user,
	 * or actually downgrades.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDowngrade()
	{
		$userUpgradeModel = $this->_getUserUpgradeModel();

		$userUpgradeRecordId = $this->_input->filterSingle('user_upgrade_record_id', XenForo_Input::UINT);
		$upgradeRecord = $userUpgradeModel->getActiveUserUpgradeRecordById($userUpgradeRecordId);
		if (!$upgradeRecord)
		{
			return $this->responseError(new XenForo_Phrase('requested_user_upgrade_not_found'), 404);
		}

		if ($this->_request->isPost())
		{
			$userUpgradeModel->downgradeUserUpgrade($upgradeRecord, false);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect()
			);
		}
		else
		{
			$viewParams = array(
				'upgradeRecord' => $upgradeRecord,
				'upgrade' => $userUpgradeModel->getUserUpgradeById($upgradeRecord['user_upgrade_id']),

				'redirect' => $this->getDynamicRedirect()
			);

			return $this->responseView('XenForo_ControllerAdmin_UserUpgrade_Downgrade', 'user_upgrade_downgrade', $viewParams);
		}
	}

	/**
	 * Displays a form to change the expiration of an upgrade
	 * or actually changes it.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditActive()
	{
		$userUpgradeModel = $this->_getUserUpgradeModel();

		$userUpgradeRecordId = $this->_input->filterSingle('user_upgrade_record_id', XenForo_Input::UINT);
		$upgradeRecord = $userUpgradeModel->getActiveUserUpgradeRecordById($userUpgradeRecordId);
		if (!$upgradeRecord)
		{
			return $this->responseError(new XenForo_Phrase('requested_user_upgrade_not_found'), 404);
		}

		if ($this->_request->isPost())
		{
			if ($this->_input->filterSingle('end_type', XenForo_Input::STRING) == 'permanent')
			{
				$endDate = 0;
			}
			else
			{
				$endDate = $this->_input->filterSingle('end_date', XenForo_Input::DATE_TIME);
				if ($endDate)
				{
					$dt = new DateTime("@$endDate");
					$dt->setTimezone(XenForo_Locale::getDefaultTimeZone());
					if ($upgradeRecord['end_date'])
					{
						$original = new DateTime("@$upgradeRecord[end_date]");
						$original->setTimezone(XenForo_Locale::getDefaultTimeZone());

						$parts = explode('-', $original->format('G-i-s'));
						$dt->setTime(intval($parts[0]), intval($parts[1]), intval($parts[2]));
					}
					else
					{
						$dt->setTime(12, 0, 0);
					}
					$endDate = $dt->format('U');
				}
			}

			$userUpgradeModel->updateActiveUpgradeEndDate($upgradeRecord['user_upgrade_record_id'], $endDate);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$this->getDynamicRedirect()
			);
		}
		else
		{
			$viewParams = array(
				'upgradeRecord' => $upgradeRecord,
				'user' => $this->_getUserModel()->getUserById($upgradeRecord['user_id']),
				'upgrade' => $userUpgradeModel->getUserUpgradeById($upgradeRecord['user_upgrade_id']),
				'redirect' => $this->getDynamicRedirect()
			);

			return $this->responseView('XenForo_ControllerAdmin_UserUpgrade_EditActive', 'user_upgrade_edit_active', $viewParams);
		}
	}

	public function actionTransactionLog()
	{
		$upgradeModel = $this->_getUserUpgradeModel();

		$logId = $this->_input->filterSingle('id', XenForo_Input::UINT);
		if ($logId)
		{
			$log = $upgradeModel->getTransactionLogById($logId);
			if (!$log)
			{
				return $this->responseError(new XenForo_Phrase('requested_log_entry_not_found'));
			}

			$log['transactionDetails'] = @unserialize($log['transaction_details']);

			$viewParams = array(
				'log' => $log
			);
			return $this->responseView('XenForo_ViewAdmin_UserUpgrade_TransactionLogView', 'user_upgrade_transaction_log_view', $viewParams);
		}

		$conditions = $this->_input->filter(array(
			'transaction_id' => XenForo_Input::STRING,
			'subscriber_id' => XenForo_Input::STRING,
			'username' => XenForo_Input::STRING,
			'user_id' => XenForo_Input::UINT,
			'user_upgrade_id' => XenForo_Input::UINT
		));

		if ($conditions['username'])
		{
			/** @var XenForo_Model_User $userModel */
			$userModel = $this->getModelFromCache('XenForo_Model_User');
			$user = $userModel->getUserByName($conditions['username']);
			if (!$user)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'));
			}

			$conditions['user_id'] = $user['user_id'];
			$conditions['username'] = '';
		}

		foreach ($conditions AS $condition => $value)
		{
			if (!$value)
			{
				unset($conditions[$condition]);
			}
		}

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;

		$logs = $upgradeModel->getTransactionLogs($conditions, array(
			'page' => $page,
			'perPage' => $perPage
		));
		if (!$logs)
		{
			return $this->responseMessage(new XenForo_Phrase('no_results_found'));
		}

		$totalLogs = $upgradeModel->countTransactionLogs($conditions);

		$viewParams = array(
			'logs' => $logs,
			'totalLogs' => $totalLogs,

			'page' => $page,
			'perPage' => $perPage,

			'conditions' => $conditions
		);
		return $this->responseView('XenForo_ViewAdmin_UserUpgrade_TransactionLog', 'user_upgrade_transaction_log', $viewParams);
	}

	public function actionTransactionLogSearch()
	{
		$viewParams = array(
			'upgrades' => $this->_getUserUpgradeModel()->getAllUserUpgrades()
		);
		return $this->responseView(
			'XenForo_ViewAdmin_UserUpgrade_TransactionLogSearch',
			'user_upgrade_transaction_log_search',
			$viewParams
		);
	}

	/**
	 * Selectively enables or disables specified user upgrades
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionToggle()
	{
		return $this->_getToggleResponse(
			$this->_getUserUpgradeModel()->getAllUserUpgrades(),
			'XenForo_DataWriter_UserUpgrade',
			'user-upgrades',
			'can_purchase');
	}

	/**
	 * Gets the specified user upgrade or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getUserUpgradeOrError($id)
	{
		$userUpgradeModel = $this->_getUserUpgradeModel();

		return $this->getRecordOrError(
			$id, $userUpgradeModel, 'getUserUpgradeById',
			'requested_user_upgrade_not_found'
		);
	}

	/**
	 * @return XenForo_Model_UserUpgrade
	 */
	protected function _getUserUpgradeModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserUpgrade');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}