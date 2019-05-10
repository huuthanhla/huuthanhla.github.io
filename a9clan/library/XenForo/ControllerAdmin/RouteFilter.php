<?php

class XenForo_ControllerAdmin_RouteFilter extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('option');
	}

	/**
	 * Lists all available route filters.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$routeFilterModel = $this->_getRouteFilterModel();

		$viewParams = array(
			'routeFilters' => $routeFilterModel->getRouteFilters('public'),
		);

		return $this->responseView('XenForo_ViewAdmin_RouteFilter_List', 'route_filter_list', $viewParams);
	}

	/**
	 * Gets the add/edit form response for a route filter.
	 *
	 * @param array $routeFilter
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	protected function _getRouteFilterAddEditResponse(array $routeFilter)
	{
		$fullIndex = XenForo_Link::buildPublicLink('full:index');
		$fullThreadLink = XenForo_Link::buildPublicLink('full:threads', array('thread_id' => 1, 'title' => 'example'));
		$routeValue = str_replace(array($fullIndex, '?'), '', $fullThreadLink);

		$viewParams = array(
			'routeFilter' => $routeFilter,
			'fullThreadLink' => $fullThreadLink,
			'routeValue' => $routeValue
		);

		return $this->responseView('XenForo_ViewAdmin_RouteFilter_Edit', 'route_filter_edit', $viewParams);
	}

	/**
	 * Displays a form to add a new route filter.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		return $this->_getRouteFilterAddEditResponse(array(
			'find_route' => '',
			'replace_route' => '',
			'url_to_route_only' => 0,
			'enabled' => 1
		));
	}

	/**
	 * Displays a form to edit an existing route filter.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$routeFilterId = $this->_input->filterSingle('route_filter_id', XenForo_Input::UINT);
		$routeFilter = $this->_getRouteFilterOrError($routeFilterId);

		return $this->_getRouteFilterAddEditResponse($routeFilter);
	}

	/**
	 * Inserts a new route filter or updates an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$routeFilterId = $this->_input->filterSingle('route_filter_id', XenForo_Input::UINT);
		$dwData = $this->_input->filter(array(
			'find_route' => XenForo_Input::STRING,
			'replace_route' => XenForo_Input::STRING,
			'enabled' => XenForo_Input::UINT,
			'url_to_route_only' => XenForo_Input::UINT,
		));

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_RouteFilter');
		if ($routeFilterId)
		{
			$dw->setExistingData($routeFilterId);
		}
		$dw->bulkSet($dwData);
		$dw->set('route_type', 'public');
		$dw->save();

		$routeFilterId = $dw->get('route_filter_id');

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('route-filters') . $this->getLastHash($routeFilterId)
		);
	}

	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			return $this->_deleteData(
				'XenForo_DataWriter_RouteFilter', 'route_filter_id',
				XenForo_Link::buildAdminLink('route-filters')
			);
		}
		else
		{
			$routeFilterId = $this->_input->filterSingle('route_filter_id', XenForo_Input::UINT);
			$routeFilter = $this->_getRouteFilterOrError($routeFilterId);

			$viewParams = array(
				'routeFilter' => $routeFilter
			);

			return $this->responseView('XenForo_ViewAdmin_RouteFilter_Delete', 'route_filter_delete', $viewParams);
		}
	}

	/**
	 * Selectively enables or disables specified route filters
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionToggle()
	{
		return $this->_getToggleResponse(
			$this->_getRouteFilterModel()->getRouteFilters('public'),
			'XenForo_DataWriter_RouteFilter',
			'route-filters',
			'enabled'
		);
	}

	/**
	 * Gets the specified route filter or throws an exception.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function _getRouteFilterOrError($id)
	{
		$routeFilterModel = $this->_getRouteFilterModel();

		return $this->getRecordOrError(
			$id, $routeFilterModel, 'getRouteFilterById',
			'requested_route_filter_not_found'
		);
	}

	/**
	 * @return XenForo_Model_RouteFilter
	 */
	protected function _getRouteFilterModel()
	{
		return $this->getModelFromCache('XenForo_Model_RouteFilter');
	}
}