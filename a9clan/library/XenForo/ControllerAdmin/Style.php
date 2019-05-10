<?php

/**
 * Admin controller for handling actions on styles.
 *
 * @package XenForo_Style
 */
class XenForo_ControllerAdmin_Style extends XenForo_ControllerAdmin_StyleAbstract
{
	/**
	 * Shows a list of styles.
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionIndex()
	{
		$styleModel = $this->_getStyleModel();

		$styles = $styleModel->getAllStylesAsFlattenedTree();

		$masterStyle = $styleModel->showMasterStyle() ? $styleModel->getStyleById(0, true) : array();

		$viewParams = array(
			'styles' => $styles,
			'masterStyle' => $masterStyle,
			'totalStyles' => count($styles) + ($styleModel->showMasterStyle() ? 1 : 0)
		);

		return $this->responseView('XenForo_ViewAdmin_Style_List', 'style_list', $viewParams);
	}

	/**
	 * Form to add a style.
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionAdd()
	{
		$styleModel = $this->_getStyleModel();

		$viewParams = array(
			'styles' => $styleModel->getAllStylesAsFlattenedTree(),
			'style' => array(
				'user_selectable' => 1
			)
		);

		return $this->responseView('XenForo_ViewAdmin_Style_Edit', 'style_edit', $viewParams);
	}

	/**
	 * Form to edit an existing style.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
		$style = $this->_getStyleOrError($styleId);

		$styleModel = $this->_getStyleModel();

		$viewParams = array(
			'styles' => $styleModel->getAllStylesAsFlattenedTree(),
			'style' => $style
		);

		return $this->responseView('XenForo_ViewAdmin_Style_Edit', 'style_edit', $viewParams);
	}

	/**
	 * Saves a style. May insert a new one or update an existing one.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'description' => XenForo_Input::STRING,
			'parent_id' => XenForo_Input::UINT,
			'user_selectable' => XenForo_Input::UINT,
		));
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Style');
		if ($styleId)
		{
			$writer->setExistingData($styleId);
		}

		$writer->bulkSet($input);
		$writer->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('styles') . $this->getLastHash($writer->get('style_id'))
		);
	}

	/**
	 * Deletes a style.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		if ($this->isConfirmedPost())
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Style');
			$dw->setExistingData($this->_input->filterSingle('style_id', XenForo_Input::UINT));
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('styles')
			);
		}
		else // show confirmation dialog
		{
			$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
			$style = $this->_getStyleOrError($styleId);

			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Style', XenForo_DataWriter::ERROR_EXCEPTION);
			$writer->setExistingData($style);
			$writer->preDelete();

			$viewParams = array(
				'style' => $style
			);

			return $this->responseView('XenForo_ViewAdmin_Style_Delete', 'style_delete', $viewParams);
		}
	}

	public function actionExport()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
		$style = $this->_getStyleOrError($styleId);

		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'addon_id' => XenForo_Input::STRING,
				'independent' => XenForo_Input::UINT
			));

			$this->_routeMatch->setResponseType('xml');

			$addOnId = ($input['addon_id'] ? $input['addon_id'] : null);

			$viewParams = array(
				'style' => $style,
				'xml' => $this->_getStyleModel()->getStyleXml($style, $addOnId, $input['independent'])
			);

			return $this->responseView('XenForo_ViewAdmin_Style_ExportXml', '', $viewParams);
		}
		else
		{
			$viewParams = array(
				'style' => $style,
				'addOnOptions' => $this->getModelFromCache('XenForo_Model_AddOn')->getAddOnOptionsList(false, true)
			);

			return $this->responseView('XenForo_ViewAdmin_Style_Export', 'style_export', $viewParams);
		}
	}

	public function actionImport()
	{
		$styleModel = $this->_getStyleModel();

		if ($this->isConfirmedPost())
		{
			$input = $this->_input->filter(array(
				'target' => XenForo_Input::STRING,
				'parent_style_id' => XenForo_Input::UINT,
				'overwrite_style_id' => XenForo_Input::UINT
			));

			$upload = XenForo_Upload::getUploadedFile('upload');
			if (!$upload)
			{
				return $this->responseError(new XenForo_Phrase('please_upload_valid_style_xml_file'));
			}

			if ($input['target'] == 'overwrite')
			{
				$this->_getStyleOrError($input['overwrite_style_id']);
				$input['parent_style_id'] = 0;
			}
			else
			{
				$input['overwrite_style_id'] = 0;
			}

			$document = $this->getHelper('Xml')->getXmlFromFile($upload);
			$styleModel->importStyleXml($document, $input['parent_style_id'], $input['overwrite_style_id']);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('styles')
			);
		}
		else
		{
			$viewParams = array(
				'styles' => $styleModel->getAllStylesAsFlattenedTree()
			);

			return $this->responseView('XenForo_ViewAdmin_Style_Import', 'style_import', $viewParams);
		}
	}

	/**
	 * Displays the list of templates in the specified style.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionTemplates()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
		$style = $this->_getStyleModel()->getStyleById($styleId, true);
		if (!$style)
		{
			return $this->responseError(new XenForo_Phrase('requested_style_not_found'), 404);
		}

		// set an edit_style_id cookie so we can switch to another area and maintain the current style selection
		XenForo_Helper_Cookie::setCookie('edit_style_id', $styleId);

		$styleModel = $this->_getStyleModel();
		$templateModel = $this->_getTemplateModel();

		if (!$templateModel->canModifyTemplateInStyle($styleId))
		{
			return $this->responseError(new XenForo_Phrase('templates_in_this_style_can_not_be_modified'));
		}

		$viewParams = array(
			'templates' => $templateModel->getEffectiveTemplateListForStyle($styleId),
			'can_import' => $templateModel->canImportTemplatesFromDevelopment(),
			'styles' => $styleModel->getAllStylesAsFlattenedTree($styleModel->showMasterStyle() ? 1 : 0),
			'masterStyle' => $styleModel->showMasterStyle() ? $styleModel->getStyleById(0, true) : array(),
			'style' => $style
		);

		return $this->responseView('XenForo_ViewAdmin_Template_List', 'template_list', $viewParams);
	}

	/**
	 * Displays a list of style properties in this style.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionStyleProperties()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
		$style = $this->_getStyleOrError($styleId, true);

		// set an edit_style_id cookie so we can switch to another area and maintain the current style selection
		XenForo_Helper_Cookie::setCookie('edit_style_id', $styleId);

		$styleModel = $this->_getStyleModel();
		$propertyModel = $this->_getStylePropertyModel();

		if (!$propertyModel->canEditStyleProperty($styleId))
		{
			return $this->responseError(new XenForo_Phrase('style_properties_in_style_can_not_be_modified'));
		}

		$groupId = $this->_input->filterSingle('group', XenForo_Input::STRING);
		if ($groupId)
		{
			$groups = $propertyModel->getEffectiveStylePropertiesByGroup($styleId);

			if (!isset($groups[$groupId]))
			{
				return $this->responseError(new XenForo_Phrase('requested_style_property_group_not_found'), 404);
			}

			$group = $groups[$groupId];

			if (empty($group['properties']))
			{
				return $this->responseReroute('XenForo_ControllerAdmin_StylePropertyDefinition', 'add');
			}

			list($scalars, $properties) = $propertyModel->filterPropertiesByType($group['properties']);
			unset($group['properties']);

			$viewParams = array(
				'styles' => $styleModel->getAllStylesAsFlattenedTree($styleModel->showMasterStyle() ? 1 : 0),
				'style' => $style,
				'masterStyle' => $styleModel->showMasterStyle() ? $styleModel->getStyleById(0, true) : array(),
				'groups' => $propertyModel->prepareStylePropertyGroups($groups, $styleId),
				'group' => $propertyModel->prepareStylePropertyGroup($group, $styleId),
				'colorPalette' => $propertyModel->prepareStyleProperties($groups['color']['properties'], $styleId),
				'scalars' => $propertyModel->prepareStyleProperties($scalars, $styleId),
				'properties' => $propertyModel->prepareStyleProperties($properties, $styleId),
				'canEditDefinition' => $propertyModel->canEditStylePropertyDefinition($styleId),
			);

			return $this->responseView('XenForo_ViewAdmin_StyleProperty_List', 'style_property_list', $viewParams);
		}
		else
		{
			$groups = $propertyModel->getEffectiveStylePropertyGroupsInStyle($styleId);

			$viewParams = array(
				'styles' => $styleModel->getAllStylesAsFlattenedTree($styleModel->showMasterStyle() ? 1 : 0),
				'masterStyle' => $styleModel->showMasterStyle() ? $styleModel->getStyleById(0, true) : array(),
				'style' => $style,
				'groups' => $propertyModel->prepareStylePropertyGroups($groups, $styleId),
				'canEditDefinition' => $propertyModel->canEditStylePropertyDefinition($styleId)
			);

			return $this->responseView('XenForo_ViewAdmin_StyleProperty_GroupList', 'style_property_group_list', $viewParams);
		}
	}

	/**
	 * Saves the style properties in this style.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionStylePropertiesSave()
	{
		$this->_assertPostOnly();

		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
		$style = $this->_getStyleOrError($styleId, true);

		if ($_input = $this->_getInputFromSerialized('_xfStylePropertiesData', true))
		{
			$this->_input = $_input;
		}

		$properties = $this->_input->filterSingle('properties', XenForo_Input::ARRAY_SIMPLE);
		$reset = $this->_input->filterSingle('reset', array(XenForo_Input::UINT, 'array' => true));

		// deal with checkboxes
		foreach ($this->_input->filterSingle('checkboxes', XenForo_Input::ARRAY_SIMPLE) AS $propertyDefinitionId)
		{
			if (!isset($properties[$propertyDefinitionId]))
			{
				$properties[$propertyDefinitionId] = 0;
			}
		}

		$group = $this->_input->filterSingle('group', XenForo_Input::STRING);
		$tabIndex = $this->_input->filterSingle('tab_index', XenForo_Input::UINT);
		$tabId = $this->_input->filterSingle('tab_id', XenForo_Input::STRING);
		if ($tabId == '(none)')
		{
			$tabId = '';
		}

		$this->_getStylePropertyModel()->saveStylePropertiesInStyleFromInput($styleId, $properties, $reset);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('styles/style-properties', $style, array('group' => $group)) . ($tabId ? '#' . $tabId : '')
		);
	}

	/**
	 * Selectively enables or disables specified styles, and selects the default style option
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionToggle()
	{
		$this->_assertPostOnly();

		$defaultStyleId = $this->_input->filterSingle('defaultStyleId', XenForo_Input::UINT);
		$style = $this->_getStyleModel()->getStyleById($defaultStyleId);
		if (!$style || !$style['user_selectable'])
		{
			return $this->responseError(new XenForo_Phrase('it_is_not_possible_to_prevent_users_selecting_the_default_style'));
		}

		$this->getModelFromCache('XenForo_Model_Option')->updateOptions(
			array('defaultStyleId' => $defaultStyleId
		));

		return $this->_getToggleResponse(
			$this->_getStyleModel()->getAllStyles(),
			'XenForo_DataWriter_Style',
			'styles',
			'user_selectable');
	}

	/**
	 * Lists all templates and style properties customized directly within the specified style
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCustomizedComponents()
	{
		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
		if (empty($styleId))
		{
			$styleId = $this->_getStyleModel()->getStyleIdFromCookie(false);
		}
		$style = $this->_getStyleOrError($styleId);

		$templates = $this->_getTemplateModel()->getAllTemplatesInStyle($styleId);
		$properties = $this->_getStylePropertyModel()->getStylePropertiesInStyles(array($styleId));

		foreach ($properties AS $key => $property)
		{
			if ($property['definition_style_id'] == $style['style_id'])
			{
				// this property is defined in this style - arguably, it's not really customized
				// plus we can't really delete it from here without weird stuff happening
				//unset($properties[$key]);
			}
		}

		if (empty($templates) && empty($properties))
		{
			return $this->responseError(new XenForo_Phrase('style_contains_no_customized_templates_or_style_properties'));
		}

		$viewParams = array(
			'style' => $style,
			'templates' => $templates,
			'properties' => $properties,
			'itemCount' => count($templates) + count($properties),
			'styles' => $this->_getStyleModel()->getAllStylesAsFlattenedTree(),
		);

		return $this->responseView('XenForo_ViewAdmin_Style_CustomizedComponents',
			'style_customized_components', $viewParams);
	}

	public function actionMassRevert()
	{
		$this->_assertPostOnly();

		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
		$style = $this->_getStyleOrError($styleId);

		$revertInfo = $this->_input->filter(array(
			'templates' => array(XenForo_Input::UINT, 'array' => true),
			'properties' => array(XenForo_Input::UINT, 'array' => true),
		));

		if ($this->isConfirmedPost())
		{
			if ($revertInfo['templates'])
			{
				foreach ($revertInfo['templates'] AS $templateId)
				{
					$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template', XenForo_DataWriter::ERROR_SILENT);
					$dw->setExistingData($templateId);
					$dw->delete();
				}
			}

			if ($revertInfo['properties'])
			{
				foreach ($revertInfo['properties'] AS $propertyId)
				{
					$dw = XenForo_DataWriter::create('XenForo_DataWriter_StyleProperty', XenForo_DataWriter::ERROR_SILENT);
					$dw->setExistingData($propertyId);
					$dw->setOption(XenForo_DataWriter_StyleProperty::OPTION_REBUILD_CACHE, false);
					$dw->delete();
				}

				$this->_getStylePropertyModel()->rebuildPropertyCacheInStyleAndChildren($styleId);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('styles', $style) . $this->getLastHash($style['style_id'])
			);
		}
		else
		{
			$viewParams = array(
				'templates' => $revertInfo['templates'],
				'properties' => $revertInfo['properties'],
				'style' => $style,
			);

			return $this->responseView('XenForo_ViewAdmin_Style_MassRevert',
				'style_mass_revert', $viewParams);
		}
	}
}