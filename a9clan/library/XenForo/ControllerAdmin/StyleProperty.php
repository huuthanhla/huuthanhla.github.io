<?php

class XenForo_ControllerAdmin_StyleProperty extends XenForo_ControllerAdmin_StyleAbstract
{
	public function actionIndex()
	{
		$style = $this->_getStyleFromCookie();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildAdminLink('styles/style-properties', $style)
		);
	}

	public function actionColor()
	{
		$style = $this->_getStyleFromCookie();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildAdminLink('styles/style-properties', $style, array('group' => 'color'))
		);
	}

	public function actionColorReference()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

		$propertyModel = $this->_getStylePropertyModel();

		$colors = $propertyModel->getColorPalettePropertiesInStyle($styleId);
		$colors = $propertyModel->prepareStyleProperties($colors);

		$colorsGrouped = array();
		foreach ($colors AS $propertyId => $property)
		{
			$colorsGrouped[$property['sub_group']][$propertyId] = $property;
		}

		$viewParams = array(
			'colors' => $colors,
			'colorsGrouped' => $colorsGrouped
		);

		return $this->responseView(
			'XenForo_ViewAdmin_StyleProperty_ColorReference',
			'style_property_color_reference',
			$viewParams
		);
	}

	public function actionDelete()
	{
		$propertyId = $this->_input->filterSingle('property_id', XenForo_Input::UINT);

		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_StyleProperty');
			$dw->setExistingData($propertyId);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('styles/customized-components', array('style_id' => $dw->get('style_id')))
			);
		}
		else
		{
			$property = $this->getRecordOrError($propertyId, $this->_getStylePropertyModel(),
				'getStylePropertyById', 'requested_style_property_not_found');

			if (empty($property['style_id']))
			{
				return $this->responseError(new XenForo_Phrase('can_not_delete_property_from_master_style'));
			}

			$viewParams = array(
				'property' => $property,
				'style' => $this->_getStyleOrError($property['style_id']),
			);

			return $this->responseView('XenForo_ViewAdmin_StyleProperty_Delete', 'style_property_delete', $viewParams);
		}
	}

	/**
	 * Gets a style ID from the edit_style_id cookie if available.
	 *
	 * @return integer
	 */
	protected function _getStyleFromCookie()
	{
		$styleModel = $this->_getStyleModel();

		$styleId = $styleModel->getStyleIdFromCookie();

		$style = $styleModel->getStyleById($styleId, true);
		if (!$style || !$this->_getStylePropertyModel()->canEditStyleProperty($styleId))
		{
			$style = $styleModel->getStyleById(XenForo_Application::get('options')->defaultStyleId);
		}

		return $style;
	}
}