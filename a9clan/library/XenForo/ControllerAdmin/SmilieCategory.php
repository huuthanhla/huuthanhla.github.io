<?php

/**
 * Controller to manage smilie categories in the admin control panel.
 *
 * @package XenForo_Smilie
 */
class XenForo_ControllerAdmin_SmilieCategory extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('bbCodeSmilie');
	}

	/**
	 * Displays a list of smilie categories.
	 *
	 * @return XenForo_Controller_ResponseAbstract
	 */
	public function actionIndex()
	{
		$smilieModel = $this->_getSmilieModel();

		$smilieCategories = $smilieModel->getAllSmilieCategories();

		$viewParams = array(
			'smilieCategories' => $smilieModel->prepareSmilieCategories($smilieCategories)
		);

		return $this->responseView('XenForo_ViewAdmin_SmilieCategory_List', 'smilie_category_list', $viewParams);
	}

	public function actionAdd()
	{
		$viewParams = array(
			'smilieCategory' => array(
				'display_order' => 0
			)
		);
		return $this->responseView('XenForo_ViewAdmin_SmilieCategory_Edit', 'smilie_category_edit', $viewParams);
	}

	public function actionEdit()
	{
		$smilieCategoryId = $this->_input->filterSingle('smilie_category_id', XenForo_Input::UINT);
		$smilieCategory = $this->_getSmilieCategoryOrError($smilieCategoryId);

		$viewParams = array(
			'smilieCategory' => $smilieCategory
		);
		return $this->responseView('XenForo_ViewAdmin_SmilieCategory_Edit', 'smilie_category_edit', $viewParams);
	}

	public function actionSave()
	{
		$this->_assertPostOnly();

		$smilieCategoryId = $this->_input->filterSingle('smilie_category_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array(
			'smilie_category_id' => XenForo_Input::STRING,
			'display_order' => XenForo_Input::UINT,
		));

		$titlePhrase = $this->_input->filterSingle('title', XenForo_Input::STRING);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_SmilieCategory');
		if ($smilieCategoryId)
		{
			$dw->setExistingData($smilieCategoryId);
		}
		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_SmilieCategory::DATA_TITLE, $titlePhrase);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('smilies') . $this->getLastHash('c' . $dw->get('smilie_category_id'))
		);
	}

	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_SmilieCategory', 'smilie_category_id',
				XenForo_Link::buildAdminLink('smilies')
			);
		}
		else
		{
			$smilieCategoryId = $this->_input->filterSingle('smilie_category_id', XenForo_Input::STRING);
			$smilieCategory = $this->_getSmilieCategoryOrError($smilieCategoryId);

			$viewParams = array(
				'smilieCategory' => $smilieCategory
			);
			return $this->responseView('XenForo_ViewAdmin_SmilieCategory_Delete', 'smilie_category_delete', $viewParams);
		}
	}

	/**
	 * Gets a valid smilie category or throws an exception.
	 *
	 * @param string $smilieCategoryId
	 *
	 * @return array
	 */
	protected function _getSmilieCategoryOrError($smilieCategoryId)
	{
		$smilieModel = $this->_getSmilieModel();

		$info = $smilieModel->getSmilieCategoryById($smilieCategoryId);
		if (!$info)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_smilie_category_not_found'), 404));
		}

		$smilieCategory = $smilieModel->prepareSmilieCategory($info);

		return $smilieCategory;
	}

	/**
	 * @return XenForo_Model_Smilie
	 */
	protected function _getSmilieModel()
	{
		return $this->getModelFromCache('XenForo_Model_Smilie');
	}
}