<?php

/**
 * Controller to manage the permission splash page and other actions that deal with
 * permissions themselves (editing permission definitions, etc).
 *
 * @package XenForo_Permissions
 */
class XenForo_ControllerAdmin_Permission extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		switch (strtolower($action))
		{
			case 'test':
			case 'analyze':
				break;

			default:
				$this->assertDebugMode();
				$this->assertAdminPermission('dev');
		}
	}

	public function actionTest()
	{
		$this->assertAdminPermission('user');
		$this->_routeMatch->setSections('testPermissions');

		$class = XenForo_Application::resolveDynamicClass('XenForo_Session');
		$publicSession = new $class();
		$publicSession->start();
		if ($publicSession->get('user_id') != XenForo_Visitor::getUserId())
		{
			return $this->responseError(new XenForo_Phrase('please_login_via_public_login_page_before_testing_permissions'));
		}

		if ($this->_request->isPost())
		{
			$username = $this->_input->filterSingle('username', XenForo_Input::STRING);

			/* @var $userModel XenForo_Model_User */
			$userModel = $this->getModelFromCache('XenForo_Model_User');
			$user = $userModel->getUserByName($username);
			if (!$user)
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
			}

			$publicSession->set('permissionTest', array(
				'user_id' => $user['user_id'],
				'username' => $user['username']
			));
			$publicSession->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('index')
			);
		}
		else
		{
			return $this->responseView('XenForo_ViewAdmin_Permission_Test', 'permission_test');
		}
	}

	public function actionAnalyze()
	{
		$this->assertAdminPermission('user');
		$this->_routeMatch->setSections('analyzePermissions');

		$permissionModel = $this->_getPermissionModel();

		$contentId = $this->_input->filterSingle('content_id', XenForo_Input::UINT);

		/* @var $nodeModel XenForo_Model_Node */
		$nodeModel = XenForo_Model::create('XenForo_Model_Node');
		$nodeOptions = $nodeModel->getNodeOptionsArray($nodeModel->getAllNodes(), $contentId);

		$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
		$type = $this->_input->filterSingle('type', XenForo_Input::STRING);

		$user = false;
		$userGroups = $this->getModelFromCache('XenForo_Model_UserGroup')->getAllUserGroups();
		$showResults = false;
		$results = array();
		$interfaceGroups = array();

		if ($this->isConfirmedPost())
		{
			$permissions = $permissionModel->getAllPermissionsGrouped();

			$user = $this->getModelFromCache('XenForo_Model_User')->getUserByName($username);
			if ($user)
			{
				$combination = $permissionModel->getPermissionCombinationById($user['permission_combination_id']);
				$permEntries = $permissionModel->getAllPermissionEntriesGrouped();

				$userGroupIds = explode(',', $combination['user_group_list']);
				$userId = $combination['user_id'];

				$groupEntries = array();
				foreach ($userGroupIds AS $userGroupId)
				{
					if (isset($permEntries['userGroups'][$userGroupId]))
					{
						$groupEntries[$userGroupId] = $permEntries['userGroups'][$userGroupId];
					}
				}

				if ($userId && isset($permEntries['users'][$userId]))
				{
					$userEntries = $permEntries['users'][$userId];
				}
				else
				{
					$userEntries = array();
				}

				$systemEntries = $permEntries['system'];

				$permCache = $permissionModel->buildPermissionCacheForCombination(
					$permissions, $systemEntries, $groupEntries, $userEntries
				);
				$finalPermCache = $permissionModel->canonicalizePermissionCache($permCache);

				$typeSetup = $this->_getPermissionAnalysisTypeData($type);
				$interfaceGroups = $typeSetup['displayPerms'];
				$choiceTerms = $typeSetup['choiceTerms'];
				$globalChoiceTerms = $permissionModel->getPermissionChoices('userGroup', false);

				foreach ($interfaceGroups AS $interfaceGroup)
				{
					foreach ($interfaceGroup['permissions'] AS $permission)
					{
						$permissionGroupId = $permission['permission_group_id'];
						$permissionId = $permission['permission_id'];

						$groups = array();
						foreach ($userGroupIds AS $userGroupId)
						{
							$value =
								isset($groupEntries[$userGroupId][$permissionGroupId][$permissionId])
								? $groupEntries[$userGroupId][$permissionGroupId][$permissionId]
								: ($permission['permission_type'] == 'integer' ? 0 : 'unset');

							$groups[$userGroupId] = $this->_resolvePermissionValueTerm(
								$value, $permission, $globalChoiceTerms
							);
						}

						$systemValue = $this->_resolvePermissionValueTerm(
							isset($systemEntries[$permissionGroupId][$permissionId]) ? $systemEntries[$permissionGroupId][$permissionId] : false,
							$permission, $globalChoiceTerms
						);
						$userValue = $this->_resolvePermissionValueTerm(
							isset($userEntries[$permissionGroupId][$permissionId]) ? $userEntries[$permissionGroupId][$permissionId] : false,
							$permission, $globalChoiceTerms
						);

						if ($typeSetup['handler'])
						{
							$contentPerms = $typeSetup['handler']->getContentPermissionDetails(
								$permissionModel, $userGroupIds, $userId, $contentId, $permission,
								$permissions, $permCache
							);
							if (!isset($contentPerms[$contentId]) || !isset($contentPerms[$contentId]['final']))
							{
								continue;
							}

							$finalDisplay = $contentPerms[$contentId]['final'];
							foreach ($contentPerms AS &$content)
							{
								$content['user'] = $this->_resolvePermissionValueTerm(
									$content['user'], $permission, $choiceTerms
								);
								$content['content'] = $this->_resolvePermissionValueTerm(
									$content['content'], $permission, $choiceTerms
								);
								foreach ($content['groups'] AS &$group)
								{
									$group = $this->_resolvePermissionValueTerm(
										$group, $permission, $choiceTerms
									);
								}
								$content['final'] = $this->_resolveFinalPermissionValueTerm($content['final'], $permission);
							}
						}
						else
						{
							$contentPerms = false;
							$finalDisplay = $finalPermCache[$permissionGroupId][$permissionId];
						}

						$permission = $permissionModel->preparePermission($permission);

						$results[$permission['interface_group_id']][] = array(
							'title' => $permission['title'],
							'interfaceTitle' => $interfaceGroup['title'],
							'type' => $permission['permission_type'],
							'global' => array(
								'groups' => $groups,
								'user' => $userValue,
								'system' => $systemValue,
								'final' => $this->_resolveFinalPermissionValueTerm($finalPermCache[$permissionGroupId][$permissionId], $permission)
							),
							'content' => $contentPerms,
							'final' => $this->_resolveFinalPermissionValueTerm($finalDisplay, $permission)
						);
						$showResults = true;
					}
				}
			}
		}

		$globalChoices = $permissionModel->getPermissionChoices('userGroup', false);

		$viewParams = array(
			'username' => $username,
			'user' => $user,

			'type' => $type ? $type : 'global',
			'contentId' => $contentId,

			'userGroups' => $userGroups,
			'nodeOptions' => $nodeOptions,
			'interfaceGroups' => $interfaceGroups,

			'showResults' => $showResults,
			'results' => $results,
			'globalChoices' => $globalChoices,
			'contentChoices' => $permissionModel->getPermissionChoices('userGroup', true) + $globalChoices,
		);
		return $this->responseView('XenForo_ViewAdmin_Permission_Analyze', 'permission_analyze', $viewParams);
	}

	protected function _resolvePermissionValueTerm($value, array $permission, array $choiceTerms)
	{
		if ($value === false)
		{
			return '';
		}

		if ($permission['permission_type'] == 'integer')
		{
			if (strval($value) === '-1')
			{
				return new XenForo_Phrase('unlimited');
			}
			else
			{
				return strval($value);
			}
		}
		else
		{
			return isset($choiceTerms[$value]) ? $choiceTerms[$value] : '';
		}
	}

	protected function _resolveFinalPermissionValueTerm($value, array $permission)
	{
		if ($permission['permission_type'] == 'integer')
		{
			if (strval($value) === '-1')
			{
				return new XenForo_Phrase('unlimited');
			}
			else
			{
				return strval($value);
			}
		}
		else
		{
			return $value ? new XenForo_Phrase('yes') : new XenForo_Phrase('no');
		}
	}

	protected function _getPermissionAnalysisTypeData($type)
	{
		$permissionModel = $this->_getPermissionModel();
		$globalChoices = $permissionModel->getPermissionChoices('userGroup', false);

		if ($type != 'global')
		{
			$handlers = $permissionModel->getContentPermissionTypeHandlers();
			$handler = isset($handlers[$type]) ? $handlers[$type] : false;
			$displayPerms = array();
			$choiceTerms = $permissionModel->getPermissionChoices('userGroup', true) + $globalChoices;

			if ($type == 'node')
			{
				$viewNodePermission = $permissionModel->preparePermission(
					$permissionModel->getViewNodeContentPermission(0, 0, 0)
				);
				$displayPerms['generalPermissions'] = array(
					'title' => '',
					'interface_group_id' => 'generalPermissions',
					'permissions' => array($viewNodePermission)
				);

				$nodeTypePermissionGroups = $this->getModelFromCache('XenForo_Model_Node')->getNodeTypesGroupedByPermissionGroup();
				$nodeInterfaceGroups = $permissionModel->getUserCollectionContentPermissionsForGroupedInterface(
					'node', 0, array_keys($nodeTypePermissionGroups), 0, 0
				);
				foreach ($nodeInterfaceGroups AS $interfaceGroups)
				{
					$displayPerms = array_merge($displayPerms, $interfaceGroups);
				}
			}
		}
		else
		{
			$handler = false;
			$displayPerms = $permissionModel->getUserCollectionPermissionsForInterface(0, 0);
			$choiceTerms = $globalChoices;
		}

		return array(
			'handler' => $handler,
			'displayPerms' => $displayPerms,
			'choiceTerms' => $choiceTerms
		);
	}

	/**
	 * Shows the permission, permission group, and interface group definitions.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDefinitions()
	{
		$permissionModel = $this->_getPermissionModel();

		$permissionGroups = $permissionModel->preparePermissionGroups($permissionModel->getAllPermissionGroups());
		$interfaceGroups = $permissionModel->preparePermissionInterfaceGroups($permissionModel->getAllPermissionInterfaceGroups());

		$permissions = $permissionModel->preparePermissions($permissionModel->getAllPermissions());
		$permissionsGrouped = array();
		$permissionsUngrouped = array();
		foreach ($permissions AS $permission)
		{
			if (isset($interfaceGroups[$permission['interface_group_id']]))
			{
				$permissionsGrouped[$permission['interface_group_id']][] = $permission;
			}
			else
			{
				$permissionsUngrouped[] = $permission;
			}
		}

		$viewParams = array(
			'permissionGroups' => $permissionGroups,
			'permissionsGrouped' => $permissionsGrouped,
			'permissionsUngrouped' => $permissionsUngrouped,
			'interfaceGroups' => $interfaceGroups,
			'totalPermissions' => count($permissions),
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_Definition', 'permission_definition', $viewParams);
	}

	/**
	 * Helper function to handle displaying the permission add/edit form.
	 *
	 * @param array $permission Array of information about the permission being editor (or the default set if adding)
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getPermissionAddEditResponse(array $permission)
	{
		$permissionModel = $this->_getPermissionModel();
		$addOnModel = $this->_getAddOnModel();

		$masterTitle = $permissionModel->getPermissionMasterTitlePhraseValue(
			$permission['permission_group_id'], $permission['permission_id']
		);

		$viewParams = array(
			'permission' => $permission,
			'masterTitle' => $masterTitle,
			'permissionGroups' => $permissionModel->getPermissionGroupNames(),
			'interfaceGroups' => $permissionModel->getPermissionInterfaceGroupNames(),
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($permission['addon_id']) ? $permission['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_PermissionEdit', 'permission_permission_edit', $viewParams);
	}

	/**
	 * Form to create a new permission.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionAdd()
	{
		return $this->_getPermissionAddEditResponse($this->_getPermissionModel()->getDefaultPermission());
	}

	/**
	 * Form to edit an existing permission.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionEdit()
	{
		$permissionGroupId = $this->_input->filterSingle('permission_group_id', XenForo_Input::STRING);
		$permissionId = $this->_input->filterSingle('permission_id', XenForo_Input::STRING);

		$permission = $this->_getValidPermissionOrError($permissionGroupId, $permissionId);
		return $this->_getPermissionAddEditResponse($permission);
	}

	/**
	 * Inserts a new permission or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionSave()
	{
		$this->_assertPostOnly();

		$originalPermissionId = $this->_input->filterSingle('original_permission_id', XenForo_Input::STRING);
		$originalPermissionGroupId = $this->_input->filterSingle('original_permission_group_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'permission_id' => XenForo_Input::STRING,
			'permission_group_id' => XenForo_Input::STRING,
			'depend_permission_id' => XenForo_Input::STRING,
			'permission_type' => XenForo_Input::STRING,
			'default_value' => array(XenForo_Input::STRING, 'default' => 'unset'),
			'default_value_int' => XenForo_Input::INT,
			'interface_group_id' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));
		$titleValue = $this->_input->filterSingle('title', XenForo_Input::STRING);

		// these have been removed from the form - just force them to this
		$dwInput['default_value'] = 'unset';
		$dwInput['default_value_int'] = 0;

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Permission');
		if ($originalPermissionId && $originalPermissionGroupId)
		{
			$dw->setExistingData(array($originalPermissionGroupId, $originalPermissionId));
		}
		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_Permission::DATA_TITLE, $titleValue);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('permissions/definitions') . $this->getLastHash($dwInput['permission_group_id'] . '_' . $dwInput['permission_id'])
		);
	}

	/**
	 * Deletes a permission.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionDelete()
	{
		$permissionGroupId = $this->_input->filterSingle('permission_group_id', XenForo_Input::STRING);
		$permissionId = $this->_input->filterSingle('permission_id', XenForo_Input::STRING);

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Permission');
			$dw->setExistingData(array($permissionGroupId, $permissionId));
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('permissions/definitions')
			);
		}
		else // show confirmation dialog
		{
			$permission = $this->_getValidPermissionOrError($permissionGroupId, $permissionId);

			$viewParams = array(
				'permission' => $permission
			);

			return $this->responseView('XenForo_ViewAdmin_Permission_PermissionDelete', 'permission_permission_delete', $viewParams);
		}
	}

	/**
	 * Helper to get the permission group add/edit form controller response.
	 *
	 * @param array $permissionGroup
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getPermissionGroupAddEditResponse(array $permissionGroup)
	{
		$addOnModel = $this->_getAddOnModel();

		$masterTitle = $this->_getPermissionModel()->getPermissionGroupMasterTitlePhraseValue($permissionGroup['permission_group_id']);

		$viewParams = array(
			'permissionGroup' => $permissionGroup,
			'masterTitle' => $masterTitle,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($permissionGroup['addon_id']) ? $permissionGroup['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_PermissionGroupEdit', 'permission_permission_group_edit', $viewParams);
	}

	/**
	 * Form to create a new permission group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionGroupAdd()
	{
		return $this->_getPermissionGroupAddEditResponse(
			$this->_getPermissionModel()->getDefaultPermissionGroup()
		);
	}

	/**
	 * Form to edit an existing permission group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionGroupEdit()
	{
		$permissionGroupId = $this->_input->filterSingle('permission_group_id', XenForo_Input::STRING);
		$permissionGroup = $this->_getValidPermissionGroupOrError($permissionGroupId);

		return $this->_getPermissionGroupAddEditResponse($permissionGroup);
	}

	/**
	 * Inserts a new permission group or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionGroupSave()
	{
		$this->_assertPostOnly();

		$originalPermissionGroupId = $this->_input->filterSingle('original_permission_group_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'permission_group_id' => XenForo_Input::STRING,
			'addon_id' => XenForo_Input::STRING
		));
		$titleValue = $this->_input->filterSingle('title', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_PermissionGroup');
		if ($originalPermissionGroupId)
		{
			$dw->setExistingData($originalPermissionGroupId);
		}
		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_PermissionGroup::DATA_TITLE, $titleValue);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('permissions/definitions') . $this->getLastHash("group_{$dwInput['permission_group_id']}")
		);
	}

	/**
	 * Deletes a permission group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPermissionGroupDelete()
	{
		$permissionGroupId = $this->_input->filterSingle('permission_group_id', XenForo_Input::STRING);

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_PermissionGroup');
			$dw->setExistingData($permissionGroupId);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('permissions/definitions')
			);
		}
		else // show confirmation dialog
		{
			$permissionGroup = $this->_getValidPermissionGroupOrError($permissionGroupId);

			$viewParams = array(
				'permissionGroup' => $permissionGroup
			);

			return $this->responseView('XenForo_ViewAdmin_Permission_PermissionGroupDelete', 'permission_permission_group_delete', $viewParams);
		}
	}

	protected function _getInterfaceGroupAddEditResponse(array $interfaceGroup)
	{
		$addOnModel = $this->_getAddOnModel();

		$masterTitle = $this->_getPermissionModel()->getPermissionInterfaceGroupMasterTitlePhraseValue(
			$interfaceGroup['interface_group_id']
		);

		$viewParams = array(
			'interfaceGroup' => $interfaceGroup,
			'masterTitle' => $masterTitle,
			'addOnOptions' => $addOnModel->getAddOnOptionsListIfAvailable(),
			'addOnSelected' => (isset($interfaceGroup['addon_id']) ? $interfaceGroup['addon_id'] : $addOnModel->getDefaultAddOnId())
		);

		return $this->responseView('XenForo_ViewAdmin_Permission_InterfaceGroupEdit', 'permission_interface_group_edit', $viewParams);
	}

	/**
	 * Displays a form to add a new permission interface group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInterfaceGroupAdd()
	{
		return $this->_getInterfaceGroupAddEditResponse(
			$this->_getPermissionModel()->getDefaultPermissionInterfaceGroup()
		);
	}

	/**
	 * Displays a form to edit an existing permission interface group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInterfaceGroupEdit()
	{
		$interfaceGroupId = $this->_input->filterSingle('interface_group_id', XenForo_Input::STRING);
		$interfaceGroup = $this->_getValidPermissionInterfaceGroupOrError($interfaceGroupId);

		return $this->_getInterfaceGroupAddEditResponse($interfaceGroup);
	}

	/**
	 * Inserts a new permission interface group or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInterfaceGroupSave()
	{
		$this->_assertPostOnly();

		$originalInterfaceGroupId = $this->_input->filterSingle('original_interface_group_id', XenForo_Input::STRING);

		$dwInput = $this->_input->filter(array(
			'interface_group_id' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING
		));
		$titleValue = $this->_input->filterSingle('title', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_PermissionInterfaceGroup');
		if ($originalInterfaceGroupId)
		{
			$dw->setExistingData($originalInterfaceGroupId);
		}
		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_PermissionInterfaceGroup::DATA_TITLE, $titleValue);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('permissions/definitions') . $this->getLastHash("igroup_{$dwInput['interface_group_id']}")
		);
	}

	/**
	 * Deletes a permission interface group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionInterfaceGroupDelete()
	{
		$interfaceGroupId = $this->_input->filterSingle('interface_group_id', XenForo_Input::STRING);

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_PermissionInterfaceGroup');
			$dw->setExistingData($interfaceGroupId);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('permissions/definitions')
			);
		}
		else // show confirm dialog
		{
			$interfaceGroup = $this->_getValidPermissionInterfaceGroupOrError($interfaceGroupId);

			$viewParams = array(
				'interfaceGroup' => $interfaceGroup
			);

			return $this->responseView('XenForo_ViewAdmin_Permission_InterfaceGroupDelete', 'permission_interface_group_delete', $viewParams);
		}
	}

	/**
	 * Gets a valid permission record or raises a controller response exception.
	 *
	 * @param string $groupId
	 * @param string $permissionId
	 *
	 * @return array
	 */
	protected function _getValidPermissionOrError($groupId, $permissionId)
	{
		$info = $this->_getPermissionModel()->getPermissionByGroupAndId($groupId, $permissionId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_permission_not_found'), 404));
		}

		return $this->_getPermissionModel()->preparePermission($info);
	}

	/**
	 * Gets a valid permission group record or raises a controller response exception.
	 *
	 * @param string $permissionGroupId
	 *
	 * @return array
	 */
	protected function _getValidPermissionGroupOrError($permissionGroupId)
	{
		$permissionGroup = $this->_getPermissionModel()->getPermissionGroupById($permissionGroupId);
		if (!$permissionGroup)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_permission_group_not_found'), 404));
		}

		return $this->_getPermissionModel()->preparePermissionGroup($permissionGroup);
	}

	/**
	 * Gets a valid permission interface group record or raises a controller response exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getValidPermissionInterfaceGroupOrError($id)
	{
		$info = $this->_getPermissionModel()->getPermissionInterfaceGroupById($id);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_permission_interface_group_not_found'), 404));
		}

		return $this->_getPermissionModel()->preparePermissionInterfaceGroup($info);
	}

	/**
	 * Gets the permission model.
	 *
	 * @return XenForo_Model_Permission
	 */
	protected function _getPermissionModel()
	{
		return $this->getModelFromCache('XenForo_Model_Permission');
	}

	/**
	 * Get the add-on model.
	 *
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}
}