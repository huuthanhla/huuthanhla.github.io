<?php

/**
 * Thread prefix controller.
 */
class XenForo_ControllerAdmin_ThreadPrefix extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('thread');
	}

	public function actionIndex()
	{
		$prefixModel = $this->_getPrefixModel();

		$prefixGroups = $prefixModel->getAllPrefixGroups();
		$prefixes = $prefixModel->getPrefixesByGroups(array(), array(), $prefixCount);

		$prefixGroups = $prefixModel->mergePrefixesIntoGroups($prefixes, $prefixGroups);

		$viewParams = array(
			'prefixGroups' => $prefixGroups,
			'prefixCount' => $prefixCount,
		);

		return $this->responseView('XenForo_ViewAdmin_ThreadPrefix_List', 'thread_prefix_list', $viewParams);
	}

	protected function _getPrefixAddEditResponse(array $prefix,
		$viewName = 'XenForo_ViewAdmin_ThreadPrefix_Edit',
		$templateName = 'thread_prefix_edit',
		$viewParams = array())
	{
		$userGroups = $this->_getUserGroupModel()->getAllUserGroups();

		$prefixModel = $this->_getPrefixModel();
		$phraseModel = $this->_getPhraseModel();

		if (!empty($prefix['prefix_id']))
		{
			$selNodeIds = $prefixModel->getForumAssociationsByPrefix($prefix['prefix_id']);

			$selUserGroupIds = explode(',', $prefix['allowed_user_group_ids']);
			if (in_array(-1, $selUserGroupIds))
			{
				$allUserGroups = true;
				$selUserGroupIds = array_keys($userGroups);
			}
			else
			{
				$allUserGroups = false;
			}

			$masterTitle = $phraseModel->getMasterPhraseValue(
				$prefixModel->getPrefixTitlePhraseName($prefix['prefix_id'])
			);
		}
		else
		{
			$selNodeIds = array();
			$allUserGroups = true;
			$selUserGroupIds = array_keys($userGroups);
			$masterTitle = '';
		}

		if (!$selNodeIds)
		{
			$selNodeIds = array(0);
		}

		$displayStyles = array(
			'',
			'prefix prefixPrimary',
			'prefix prefixSecondary',
			'prefix prefixGreen',
			'prefix prefixOlive',
			'prefix prefixLightGreen',
			'prefix prefixBlue',
			'prefix prefixRoyalBlue',
			'prefix prefixSkyBlue',
			'prefix prefixRed',
			'prefix prefixOrange',
			'prefix prefixYellow',
			'prefix prefixGray',
			'prefix prefixSilver',
		);

		$viewParams = array_merge(array(
			'prefix' => $prefix,
			'prefixGroupOptions' => $prefixModel->getPrefixGroupOptions($prefix['prefix_group_id']),

			'selNodeIds' => $selNodeIds,
			'allUserGroups' => $allUserGroups,
			'selUserGroupIds' => $selUserGroupIds,
			'masterTitle' => $masterTitle,

			'displayStyles' => $displayStyles,
			'displayStylesOther' => !in_array($prefix['css_class'], $displayStyles),

			'nodes' => $this->_getNodeModel()->getAllNodes(),
			'userGroups' => $userGroups
		), $viewParams);
		return $this->responseView($viewName, $templateName, $viewParams);
	}

	public function actionAdd()
	{
		return $this->_getPrefixAddEditResponse($this->_getPrefixModel()->getDefaultPrefixValues());
	}

	public function actionEdit()
	{
		$prefixId = $this->_input->filterSingle('prefix_id', XenForo_Input::UINT);
		$prefix = $this->_getPrefixOrError($prefixId);

		return $this->_getPrefixAddEditResponse($prefix);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		$prefixId = $this->_input->filterSingle('prefix_id', XenForo_Input::UINT);

		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'prefix_group_id' => XenForo_Input::UINT,
			'display_order' => XenForo_Input::UINT,
			'css_class' => XenForo_Input::STRING,
			'usable_user_group_type' => XenForo_Input::STRING,
			'user_group_ids' => array(XenForo_Input::UINT, 'array' => true),
			'node_ids' => array(XenForo_Input::UINT, 'array' => true),
		));

		if ($input['usable_user_group_type'] == 'all')
		{
			$allowedGroupIds = array(-1); // -1 is a sentinel for all groups
		}
		else
		{
			$allowedGroupIds = $input['user_group_ids'];
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadPrefix');
		if ($prefixId)
		{
			$dw->setExistingData($prefixId);
		}
		$dw->bulkSet(array(
			'prefix_group_id' => $input['prefix_group_id'],
			'display_order' => $input['display_order'],
			'css_class' => $input['css_class'],
			'allowed_user_group_ids' => $allowedGroupIds
		));
		$dw->setExtraData(XenForo_DataWriter_ThreadPrefix::DATA_TITLE, $input['title']);
		$dw->save();

		$this->_getPrefixModel()->updatePrefixForumAssociationByPrefix($dw->get('prefix_id'), $input['node_ids']);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('thread-prefixes') . $this->getLastHash($dw->get('prefix_id'))
		);
	}

	/**
	 * Deletes the specified prefix
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_ThreadPrefix', 'prefix_id',
				XenForo_Link::buildAdminLink('thread-prefixes')
			);
		}
		else // show confirmation dialog
		{
			$prefixId = $this->_input->filterSingle('prefix_id', XenForo_Input::UINT);
			$prefix = $this->_getPrefixOrError($prefixId);

			$viewParams = array(
				'prefix' => $prefix
			);
			return $this->responseView('XenForo_ViewAdmin_ThreadPrefix_Delete', 'thread_prefix_delete', $viewParams);
		}
	}

	public function actionQuickSet()
	{
		$this->_assertPostOnly();

		$prefixIds = $this->_input->filterSingle('prefix_ids', XenForo_Input::UINT, array('array' => true));

		if (empty($prefixIds))
		{
			// nothing to do, just head back to the prefix list
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('thread-prefixes')
			);
		}

		$prefixModel = $this->_getPrefixModel();

		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'apply_css_class' => XenForo_Input::UINT,
				'css_class' => XenForo_Input::STRING,

				'apply_prefix_group_id' => XenForo_Input::UINT,
				'prefix_group_id' => XenForo_Input::UINT,

				'apply_user_group_ids' => XenForo_Input::UINT,
				'usable_user_group_type' => XenForo_Input::STRING,
				'user_group_ids' => array(XenForo_Input::UINT, 'array' => true),

				'apply_node_ids' => XenForo_Input::UINT,
				'node_ids' => array(XenForo_Input::UINT, 'array' => true),

				'prefix_id' => XenForo_Input::UINT,
			));

			XenForo_Db::beginTransaction();

			$prefixChanged = false;
			$orderChanged = false;
			foreach ($prefixIds AS $prefixId)
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadPrefix');
				$dw->setOption(XenForo_DataWriter_ThreadPrefix::OPTION_MASS_UPDATE, true);
				$dw->setExistingData($prefixId);

				if ($input['apply_css_class'])
				{
					$dw->set('css_class', $input['css_class']);
				}

				if ($input['apply_prefix_group_id'])
				{
					$dw->set('prefix_group_id', $input['prefix_group_id']);
					if ($dw->isChanged('prefix_group_id'))
					{
						$orderChanged = true;
					}
				}

				if ($input['apply_user_group_ids'])
				{
					if ($input['usable_user_group_type'] == 'all')
					{
						$allowedGroupIds = array(-1); // -1 is a sentinel for all groups
					}
					else
					{
						$allowedGroupIds = $input['user_group_ids'];
					}

					$dw->set('allowed_user_group_ids', $allowedGroupIds);
				}

				$dw->save();

				if ($input['apply_node_ids'])
				{
					$this->_getPrefixModel()->updatePrefixForumAssociationByPrefix($dw->get('prefix_id'), $input['node_ids']);
				}
			}

			if ($orderChanged)
			{
				$prefixModel->rebuildPrefixMaterializedOrder();
			}

			$prefixModel->rebuildPrefixCache();

			XenForo_Db::commit();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('thread-prefixes') . $this->getLastHash($input['prefix_id'])
			);

		}
		else
		{
			if ($prefixId = $this->_input->filterSingle('prefix_id', XenForo_Input::INT))
			{
				if ($prefixId > 0)
				{
					$prefix = $this->_getPrefixOrError($prefixId);
				}
				else
				{
					$prefix = $prefixModel->getDefaultPrefixValues();
				}

				$prefixes = $prefixModel->getPrefixes(array('prefix_ids' => $prefixIds));

				$viewParams = array(
					'prefixIds' => $prefixIds,
					'prefixes' => $prefixModel->preparePrefixes($prefixes),
				);

				return $this->_getPrefixAddEditResponse($prefix,
					'XenForo_ViewAdmin_ThreadPrefix_QuickSet_Editor',
					'thread_prefix_quickset_editor',
					$viewParams);
			}
			else
			{
				$viewParams = array(
					'prefixIds' => $prefixIds,
					'prefixOptions' => $prefixModel->getPrefixOptions(array('prefix_ids' => $prefixIds))
				);

				return $this->responseView(
					'XenForo_ViewAdmin_ThreadPrefix_QuickSet_PrefixChooser',
					'thread_prefix_quickset_prefix_chooser',
					$viewParams);
			}
		}
	}

	public function actionGroups()
	{
		$prefixGroups = $this->_getPrefixModel()->getAllPrefixGroups();

		$viewParams = array(
			'prefixGroups' => $this->_getPrefixModel()->preparePrefixGroups($prefixGroups)
		);

		return $this->responseView('XenForo_ViewAdmin_ThreadPrefix_Group_List', 'thread_prefix_group_list', $viewParams);
	}

	protected function _getPrefixGroupAddEditResponse(array $prefixGroup)
	{
		if (!empty($prefixGroup['prefix_group_id']))
		{
			$masterTitle = $this->_getPhraseModel()->getMasterPhraseValue(
				$this->_getPrefixModel()->getPrefixGroupTitlePhraseName($prefixGroup['prefix_group_id'])
			);
		}
		else
		{
			$masterTitle = '';
		}

		$viewParams = array(
			'prefixGroup' => $prefixGroup,
			'masterTitle' => $masterTitle
		);

		return $this->responseView('XenForo_ViewAdmin_ThreadPrefix_Group_Edit', 'thread_prefix_group_edit', $viewParams);
	}

	public function actionAddGroup()
	{
		return $this->_getPrefixGroupAddEditResponse(array(
			'display_order' => 1
		));
	}

	public function actionEditGroup()
	{
		$prefixGroupId = $this->_input->filterSingle('prefix_group_id', XenForo_Input::UINT);
		$prefixGroup = $this->_getPrefixGroupOrError($prefixGroupId);

		return $this->_getPrefixGroupAddEditResponse($prefixGroup);
	}

	public function actionSaveGroup()
	{
		$this->_assertPostOnly();

		$prefixGroupId = $this->_input->filterSingle('prefix_group_id', XenForo_Input::UINT);

		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadPrefixGroup');
		if ($prefixGroupId)
		{
			$dw->setExistingData($prefixGroupId);
		}
		$dw->set('display_order', $input['display_order']);
		$dw->setExtraData(XenForo_DataWriter_ThreadPrefix::DATA_TITLE, $input['title']);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('thread-prefixes') . $this->getLastHash('group_' . $dw->get('prefix_group_id'))
		);
	}

	public function actionDeleteGroup()
	{
		$prefixGroupId = $this->_input->filterSingle('prefix_group_id', XenForo_Input::UINT);

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadPrefixGroup');
			$dw->setExistingData($prefixGroupId);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('thread-prefixes'));
		}
		else
		{
			$viewParams = array(
				'prefixGroup' => $this->_getPrefixGroupOrError($prefixGroupId)
			);

			return $this->responseView(
				'XenForo_ViewAdmin_ThreadPrefix_Group_Delete',
				'thread_prefix_group_delete', $viewParams);
		}
	}

	/**
	 * Gets a valid prefix group or throws an exception.
	 *
	 * @param integer $prefixGroupId
	 *
	 * @return array
	 */
	protected function _getPrefixGroupOrError($prefixGroupId)
	{
		$info = $this->_getPrefixModel()->getPrefixGroupById($prefixGroupId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_prefix_group_not_found'), 404));
		}

		return $this->_getPrefixModel()->preparePrefixGroup($info);
	}

	/**
	 * Gets a valid prefix or throws an exception.
	 *
	 * @param integer $prefixId
	 *
	 * @return array
	 */
	protected function _getPrefixOrError($prefixId)
	{
		$info = $this->_getPrefixModel()->getPrefixById($prefixId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_prefix_not_found'), 404));
		}

		return $this->_getPrefixModel()->preparePrefix($info);
	}

	/**
	 * @return XenForo_Model_ThreadPrefix
	 */
	protected function _getPrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}

	/**
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}

	/**
	 * @return XenForo_Model_UserGroup
	 */
	protected function _getUserGroupModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserGroup');
	}
}