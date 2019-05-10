<?php

class XenForo_ControllerAdmin_UserTitleLadder extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('user');
	}

	/**
	 * Displays a list of all trophies, with an option to delete.
	 * Also shows form to create new.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$optionModel = XenForo_Model::create('XenForo_Model_Option');
		$options = $optionModel->getOptionsByIds(array('userTitleLadderField'));

		$viewParams = array(
			'titles' => $this->_getLadderModel()->getUserTitleLadder(),

			'options' => $optionModel->prepareOptions($options),
			'canEditOptionDefinition' => $optionModel->canEditOptionAndGroupDefinitions()
		);

		return $this->responseView('XenForo_ViewAdmin_UserTitleLadder_List', 'user_title_ladder_list', $viewParams);
	}

	/**
	 * Updates existing titles, deletes specified ones, and optionally creates
	 * a new one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUpdate()
	{
		$this->_assertPostOnly();

		$update = $this->_input->filterSingle('update', XenForo_Input::ARRAY_SIMPLE);
		$delete = $this->_input->filterSingle('delete', array(XenForo_Input::UINT, 'array' => true));
		foreach ($delete AS $deletePoint)
		{
			unset($update[$deletePoint]);
		}

		$input = $this->_input->filter(array(
			'minimum_level' => XenForo_Input::UINT,
			'title' => XenForo_Input::STRING
		));

		$trophyModel = $this->_getLadderModel();

		XenForo_Db::beginTransaction();

		$trophyModel->deleteUserTitleLadderEntries($delete, false);
		$trophyModel->updateUserTitleLadder($update, false);

		if ($input['title'])
		{
			$trophyModel->insertUserTitleLadderEntry($input['title'], $input['minimum_level'], false);
		}

		$trophyModel->rebuildUserTitleLadderCache();

		XenForo_Db::commit();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('user-title-ladder')
		);
	}

	/**
	 * @return XenForo_Model_UserTitleLadder
	 */
	protected function _getLadderModel()
	{
		return $this->getModelFromCache('XenForo_Model_UserTitleLadder');
	}
}