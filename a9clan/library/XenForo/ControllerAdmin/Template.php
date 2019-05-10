<?php

/**
 * Admin controller for handling actions on templates.
 *
 * @package XenForo_Templates
 */
class XenForo_ControllerAdmin_Template extends XenForo_ControllerAdmin_StyleAbstract
{
	/**
	 * Template index. This is a list of templates, so redirect this to a
	 * style-specific list.
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionIndex()
	{
		$styleModel = $this->_getStyleModel();

		$styleId = $styleModel->getStyleIdFromCookie();

		$style = $this->_getStyleModel()->getStyleById($styleId, true);
		if (!$style || !$this->_getTemplateModel()->canModifyTemplateInStyle($styleId))
		{
			$style = $this->_getStyleModel()->getStyleById(XenForo_Application::get('options')->defaultStyleId);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildAdminLink('styles/templates', $style)
		);
	}

	/**
	 * Form to add a template to the specified style. If not in debug mode,
	 * users are prevented from adding a template to the master style.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAdd()
	{
		$input = $this->_input->filter(array(
			'style_id' => XenForo_Input::UINT
		));

		$template = array(
			'template_id' => 0,
			'style_id' => $input['style_id']
		);

		return $this->_getTemplateAddEditResponse($template, $input['style_id']);
	}

	/**
	 * Form to edit a specified template. A style_id input must be specified. If the style ID
	 * of the requested template and the style ID of the input differ, the request is
	 * treated as adding a customized version of the requested template in the input
	 * style.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$input = $this->_input->filter(array(
			'template_id' => XenForo_Input::UINT,
			'style_id' => XenForo_Input::UINT
		));

		$template = $this->_getTemplateOrError($input['template_id']);

		if (!$this->_input->inRequest('style_id'))
		{
			// default to editing in the specified style
			$input['style_id'] = $template['style_id'];
		}

		if ($input['style_id'] != $template['style_id'])
		{
			$specificTemplate = $this->_getTemplateModel()->getTemplateInStyleByTitle($template['title'], $input['style_id']);
			if ($specificTemplate)
			{
				$template = $specificTemplate;
			}
		}

		$template['template'] = $this->_getStylePropertyModel()->replacePropertiesInTemplateForEditor(
			$template['template'], $input['style_id']
		);

		return $this->_getTemplateAddEditResponse($template, $input['style_id']);
	}

	/**
	 * Saves a template. This may either be an insert or an update.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();

		if ($this->_input->filterSingle('delete', XenForo_Input::STRING))
		{
			// user clicked delete
			return $this->responseReroute('XenForo_ControllerAdmin_Template', 'deleteConfirm');
		}

		$templateModel = $this->_getTemplateModel();

		$data = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'template' => array(XenForo_Input::STRING, 'noTrim' => true),
			'style_id' => XenForo_Input::UINT,
			'addon_id' => XenForo_Input::STRING,
			'disable_modifications' => XenForo_Input::UINT
		));

		// only allow templates to be edited in non-master styles, unless in debug mode
		if (!$templateModel->canModifyTemplateInStyle($data['style_id']))
		{
			return $this->responseError(new XenForo_Phrase('this_template_can_not_be_modified'));
		}

		$propertyModel = $this->_getStylePropertyModel();

		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle($data['style_id'])
		);
		$propertyChanges = $propertyModel->translateEditorPropertiesToArray(
			$data['template'], $data['template'], $properties
		);

		/** @var $writer XenForo_DataWriter_Template */
		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');
		if ($templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT))
		{
			$writer->setExistingData($templateId);
		}

		$writer->bulkSet($data);
		$writer->reparseTemplate();

		if ($writer->get('style_id') > 0)
		{
			// force an update to resolve any out of date issues
			$writer->updateVersionId();
			$writer->set('last_edit_date', XenForo_Application::$time);
		}

		$writer->save();

		$propertyModel->saveStylePropertiesInStyleFromTemplate($data['style_id'], $propertyChanges, $properties);

		if ($this->_input->filterSingle('reload', XenForo_Input::STRING))
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				XenForo_Link::buildAdminLink('templates/edit', $writer->getMergedData(), array('style_id' => $writer->get('style_id')))
			);
		}
		else
		{
			$style = $this->_getStyleModel()->getStyleByid($writer->get('style_id'), true);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('styles/templates', $style) . $this->getLastHash($writer->get('title'))
			);
		}
	}

	/**
	 * Delete confirmation and action.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
		$template = $this->_getTemplateOrError($templateId);

		if ($this->isConfirmedPost()) // delete the template
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$writer->setExistingData($templateId);

			if (!$this->_getTemplateModel()->canModifyTemplateInStyle($writer->get('style_id')))
			{
				return $this->responseError(new XenForo_Phrase('this_template_can_not_be_modified'));
			}

			$writer->delete();

			$style = $this->_getStyleModel()->getStyleByid($writer->get('style_id'), true);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('styles/templates', $style)
			);
		}
		else // show a delete confirmation dialog
		{
			$viewParams = array(
				'template' => $template,
				'style' => $this->_getStyleModel()->getStyleById($template['style_id']),
			);

			return $this->responseView('XenForo_ViewAdmin_Template_Delete', 'template_delete', $viewParams);
		}
	}

	// legacy
	public function actionDeleteConfirm() { return $this->actionDelete(); }

	public function actionViewModifications()
	{
		$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
		if ($templateId)
		{
			$template = $this->_getTemplateOrError($templateId);
			$styleId = $template['style_id'];
		}
		else
		{
			$title = $this->_input->filterSingle('title', XenForo_Input::STRING);
			$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

			$template = $this->_getTemplateModel()->getEffectiveTemplateByTitle($title, $styleId);
			if (!$template)
			{
				return $this->responseError(new XenForo_Phrase('requested_template_not_found'), 404);
			}
		}

		/** @var $modificationModel XenForo_Model_TemplateModification */
		$modificationModel = $this->getModelFromCache('XenForo_Model_TemplateModification');
		$newTemplate = $modificationModel->applyModificationsToTemplate($template['title'], $template['template']);

		$diff = new XenForo_Diff();
		$diffs = $diff->findDifferences($template['template'], $newTemplate);

		$viewParams = array(
			'template' => $template,
			'newTemplate' => $newTemplate,
			'diffs' => $diffs,
			'styleId' => $styleId,
			'canManuallyApply' => $styleId > 0,
		);

		return $this->responseView('XenForo_ViewAdmin_Template_ViewModifications', 'template_view_modifications', $viewParams);
	}

	public function actionApplyModifications()
	{
		$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
		$template = $this->_getTemplateOrError($templateId);

		$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);

		if (!$this->isConfirmedPost() || !$styleId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildAdminLink('templates/edit', $template, array('style_id' => $styleId))
			);
		}

		if ($template['style_id'] != $styleId)
		{
			$styleTemplate = $this->_getTemplateModel()->getTemplateInStyleByTitle($template['title'], $styleId);
			if ($styleTemplate)
			{
				$template = $styleTemplate;
			}
		}

		/** @var $modificationModel XenForo_Model_TemplateModification */
		$modificationModel = $this->getModelFromCache('XenForo_Model_TemplateModification');
		$newTemplate = $modificationModel->applyModificationsToTemplate($template['title'], $template['template']);

		if ($template['style_id'] == $styleId)
		{
			// updating
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($template);
		}
		else
		{
			// create new template
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template', XenForo_DataWriter::ERROR_SILENT);
			$dw->bulkSet(array(
				'title' => $template['title'],
				'style_id' => $styleId,
				'addon_id' => $template['addon_id']
			));
		}

		$dw->set('disable_modifications', 1);
		$dw->set('template', $newTemplate);
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
			XenForo_Link::buildAdminLink('templates/edit', $template, array('style_id' => $styleId))
		);
	}

	public function actionHistory()
	{
		$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
		$template = $this->_getTemplateOrError($templateId);

		$oldId = $this->_input->filterSingle('old', XenForo_Input::UINT);
		$newId = $this->_input->filterSingle('new', XenForo_Input::UINT);

		$list = $this->_getTemplateModel()->getHistoryForTemplate($template['title'], $template['style_id']);
		$newestHistory = reset($list);

		if ($oldId)
		{
			// doing a comparison
			$oldText = isset($list[$oldId]) ? $list[$oldId]['template'] : '';

			if ($newId)
			{
				$newText = isset($list[$newId]) ? $list[$newId]['template'] : '';
			}
			else
			{
				$newText = $template['template'];
			}

			$diffHandler = new XenForo_Diff();
			$diffs = $diffHandler->findDifferences($oldText, $newText, XenForo_Diff::DIFF_TYPE_LINE);
		}
		else
		{
			$diffs = array();
		}

		$viewId = $this->_input->filterSingle('view', XenForo_Input::UINT);
		if ($viewId)
		{
			$history = isset($list[$viewId]) ? $list[$viewId] : false;
		}
		else
		{
			$history = false;
		}

		$viewParams = array(
			'template' => $template,
			'list' => $list,
			'oldId' => ($oldId ? $oldId : ($newestHistory ? $newestHistory['template_history_id'] : 0)),
			'newId' => $newId,
			'diffs' => $diffs,
			'history' => $history
		);

		if ($history)
		{
			return $this->responseView('XenForo_ViewAdmin_Template_HistoryView', 'template_history_view', $viewParams);
		}
		else if ($oldId)
		{
			return $this->responseView('XenForo_ViewAdmin_Template_HistoryCompare', 'template_history_compare', $viewParams);
		}
		else
		{
			return $this->responseView('XenForo_ViewAdmin_Template_History', 'template_history', $viewParams);
		}
	}

	protected function _getTemplateWithParentForCompare($templateId = null)
	{
		if ($templateId === null)
		{
			$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
		}

		$template = $this->_getTemplateOrError($templateId);

		if (!$template['style_id'])
		{
			throw $this->responseException(
				$this->responseError(new XenForo_Phrase('you_cannot_compare_custom_changes_for_master_template'))
			);
		}

		$style = $this->_getStyleModel()->getStyleById($template['style_id']);
		if (!$style)
		{
			throw $this->responseException(
				$this->responseError(new XenForo_Phrase('requested_template_not_found'), 404)
			);
		}

		$parentStyle = $this->_getStyleModel()->getStyleById($style['parent_id'], true);
		if (!$parentStyle)
		{
			throw $this->responseException(
				$this->responseError(new XenForo_Phrase('requested_template_not_found'), 404)
			);
		}

		if ($parentStyle['style_id'])
		{
			$parentTemplate = $this->_getTemplateModel()->getEffectiveTemplateByTitle($template['title'], $parentStyle['style_id']);
		}
		else
		{
			$parentTemplate = $this->_getTemplateModel()->getTemplateInStyleByTitle($template['title'], 0);
		}
		if (!$parentTemplate)
		{
			throw $this->responseException(
				$this->responseError(new XenForo_Phrase('this_template_does_not_have_parent_version'))
			);
		}

		return array(
			'template' => $template,
			'style' => $style,
			'parentStyle' => $parentStyle,
			'parentTemplate' => $parentTemplate
		);
	}

	public function actionCompare()
	{
		$results = $this->_getTemplateWithParentForCompare();
		$template = $results['template'];
		$style = $results['style'];
		$parentStyle = $results['parentStyle'];
		$parentTemplate = $results['parentTemplate'];

		$diff = new XenForo_Diff();
		$diffs = $diff->findDifferences($parentTemplate['template'], $template['template']);

		$viewParams = array(
			'template' => $template,
			'parentTemplate' => $parentTemplate,
			'style' => $style,
			'parentStyle' => $parentStyle,
			'diffs' => $diffs
		);

		return $this->responseView('XenForo_ViewAdmin_Template_Compare', 'template_compare', $viewParams);
	}

	public function actionMergeOutdated()
	{
		$results = $this->_getTemplateWithParentForCompare();
		$template = $results['template'];
		$style = $results['style'];
		$parentStyle = $results['parentStyle'];
		$parentTemplate = $results['parentTemplate'];

		if (!$parentTemplate['last_edit_date'] || $parentTemplate['last_edit_date'] < $template['last_edit_date'])
		{
			return $this->responseError(new XenForo_Phrase('custom_template_out_of_date_edited_recently_no_merge'));
		}

		$previousVersion = $this->_getTemplateModel()->getLatestTemplateHistoryForTemplate(
			$template['title'], $parentTemplate['style_id'], $template['last_edit_date']
		);
		if (!$previousVersion)
		{
			return $this->responseError(new XenForo_Phrase('no_previous_version_of_parent_could_be_found'));
		}

		if ($this->isConfirmedPost())
		{
			$merged = $this->_input->filterSingle('merged', array(
				XenForo_Input::STRING, 'array' => true, 'noTrim' => true
			));
			$final = implode("\n", $merged);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$dw->setExistingData($template);
			$dw->set('template', $final);
			$dw->set('last_edit_date', XenForo_Application::$time);
			$dw->save();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('templates/outdated')
			);
		}
		else
		{
			$diff = new XenForo_Diff3();
			$diffs = $diff->findDifferences(
				$template['template'], $previousVersion['template'], $parentTemplate['template']
			);

			$viewParams = array(
				'template' => $template,
				'parentTemplate' => $parentTemplate,
				'previousVersion' => $previousVersion,
				'style' => $style,
				'parentStyle' => $parentStyle,
				'diffs' => $diffs
			);

			return $this->responseView('XenForo_ViewAdmin_Template_MergeOutdated', 'template_merge_outdated', $viewParams);
		}
	}

	/**
	 * Fetches template data for each template specified by title in the incoming requirement array
	 *
	 * @return XenForo_ControllerResponse_View
	 */
	public function actionLoadMultiple()
	{
		$data = $this->_input->filter(array(
			'style_id' => XenForo_Input::UINT,
			'title' => XenForo_Input::STRING,
			'includeTitles' => array(XenForo_Input::STRING, array('array' => true))
		));

		$propertyModel = $this->_getStylePropertyModel();

		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle($data['style_id'])
		);
		$templates = $this->_getTemplateModel()->getEffectiveTemplatesByTitles($data['includeTitles'], $data['style_id']);

		foreach ($templates AS &$template)
		{
			$template['link'] = XenForo_Link::buildAdminLink('templates/edit', $template, array('style_id' => $data['style_id']));
			$template['deleteLink'] = $template['style_id'] == $data['style_id'] ? XenForo_Link::buildAdminLink('templates/delete', $template) : false;

			$template['template'] = $propertyModel->replacePropertiesInTemplateForEditor(
				$template['template'], $data['style_id'], $properties
			);
		}

		$viewParams = array(
			'style_id' => $data['style_id'],
			'title' => $data['title'],
			'templateData' => $templates
		);

		return $this->responseView('XenForo_ViewAdmin_Template_LoadMultiple', 'template_load_multiple', $viewParams);
	}

	/**
	 * Saves multiple templates in a single action
	 *
	 * @return XenForo_ControllerResponse_Reroute|XenForo_ControllerResponse_Redirect
	 */
	public function actionSaveMultiple()
	{
		$this->_assertPostOnly();

		$templateModel = $this->_getTemplateModel();

		$data = $this->_input->filter(array(
			'includeTitles' => array(XenForo_Input::STRING, array('array' => true)),
			'titleArray'    => array(XenForo_Input::STRING, array('array' => true)),
			'templateArray' => array(XenForo_Input::STRING, array('array' => true, 'noTrim' => true)),
			'styleidArray'  => array(XenForo_Input::STRING, array('array' => true)),
			'style_id'      => XenForo_Input::UINT,
			'template_id'   => XenForo_Input::UINT,
			'addon_id'      => XenForo_Input::STRING,
			'disable_modifications' => XenForo_Input::UINT
		));

		// only allow templates to be edited in non-master styles, unless in debug mode
		if (!$templateModel->canModifyTemplateInStyle($data['style_id']))
		{
			return $this->responseError(new XenForo_Phrase('this_template_can_not_be_modified'));
		}

		$propertyModel = $this->_getStylePropertyModel();

		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle($data['style_id'])
		);

		$writerErrors = array();
		$propertyChanges = array();

		$existingMasters = $this->_getTemplateModel()->getTemplatesInStyleByTitles($data['titleArray']);
		$existingEffective = $this->_getTemplateModel()->getEffectiveTemplatesByTitles($data['titleArray'], $data['style_id']);

		foreach ($data['titleArray'] AS $templateId => $title)
		{
			$isPrimaryTemplate = ($data['template_id'] == $templateId);

			if (!isset($data['templateArray'][$templateId]) && !$isPrimaryTemplate)
			{
				// template hasn't been changed
				continue;
			}

			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			if ($templateId && $data['styleidArray'][$templateId] == $data['style_id'])
			{
				$writer->setExistingData($templateId);
				$exists = true;
			}
			else
			{
				// only change the style ID of a newly inserted template
				$writer->set('style_id', $data['style_id']);
				if (isset($existingMasters[$title]))
				{
					$writer->set('addon_id', $existingMasters[$title]['addon_id']);
				}
				$exists = false;
			}

			if ($isPrimaryTemplate)
			{
				$writer->set('addon_id', $data['addon_id']);
				$writer->set('disable_modifications', $data['disable_modifications']);
			}

			$writer->set('title', $title);

			$templatePropertyChanges = array();
			if (isset($data['templateArray'][$templateId]))
			{
				$templatePropertyChanges = $propertyModel->translateEditorPropertiesToArray(
					$data['templateArray'][$templateId], $templateText, $properties
				);

				$writer->set('template', $templateText);
			}
			else if (!$exists)
			{
				continue; // can't create
			}
			else if ($isPrimaryTemplate)
			{
				// need to ensure that we run this in case disable_modifications is changed
				$writer->reparseTemplate();
			}

			if ($writer->get('style_id') > 0)
			{
				// force an update to resolve any out of date issues
				$writer->set('last_edit_date', XenForo_Application::$time);
				$writer->updateVersionId();
			}

			$writer->preSave();

			if ($errors = $writer->getErrors())
			{
				$writerErrors[$title] = $errors;
			}
			else
			{
				if (!$exists && isset($existingEffective[$title]))
				{
					$save = (!isset($templateText) || $existingEffective[$title]['template'] != $templateText
						// save a custom version even if there are no changes, if 'disable_modifications' is checked
						|| ($isPrimaryTemplate && $data['disable_modifications']));
				}
				else
				{
					$save = true;
				}
				if ($save)
				{
					$writer->save();
				}

				$propertyChanges = array_merge($propertyChanges, $templatePropertyChanges);
			}
		}

		$propertyModel->saveStylePropertiesInStyleFromTemplate($data['style_id'], $propertyChanges, $properties);

		if ($writerErrors)
		{
			$errorText = '';

			foreach ($writerErrors AS $templateTitle => $errors)
			{
				$errorText .= "\n\n$templateTitle:";

				foreach ($errors AS $i => $error)
				{
					$errorText .= "\n" . ($i + 1) . ")\t$error";
				}
			}

			return $this->responseError(new XenForo_Phrase('following_templates_contained_errors_and_were_not_saved_x',
				array('errors' => $errorText), false
			));
		}

		if ($this->_input->filterSingle('_TemplateEditorAjax', XenForo_Input::UINT))
		{
			return $this->responseReroute('XenForo_ControllerAdmin_Template', 'loadMultiple');
		}
		else
		{
			$style = $this->_getStyleModel()->getStyleByid($data['style_id'], true);

			$templateId = $this->_input->filterSingle('template_id', XenForo_Input::UINT);
			if ($templateId && $last = $templateModel->getTemplateById($templateId))
			{
				if ($last['style_id'] != $data['style_id'])
				{
					$last = $templateModel->getEffectiveTemplateByTitle($last['title'], $data['style_id']);
				}

				$lastHash = $this->getLastHash($last['title']);
			}
			else
			{
				$lastHash = '';
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('styles/templates', $style) . $lastHash
			);
		}
	}

	/**
	 * Template searching.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSearch()
	{
		$styleModel = $this->_getStyleModel();

		$defaultStyleId = (XenForo_Application::debugMode()
			? 0
			: XenForo_Application::get('options')->defaultStyleId
		);

		if ($this->_input->inRequest('style_id'))
		{
			$styleId = $this->_input->filterSingle('style_id', XenForo_Input::UINT);
		}
		else
		{
			$styleId = XenForo_Helper_Cookie::getCookie('edit_style_id');
			if ($styleId === false)
			{
				$styleId = $defaultStyleId;
			}
		}

		if ($this->_input->filterSingle('search', XenForo_Input::UINT))
		{
			$templateModel = $this->_getTemplateModel();

			$input = $this->_input->filter(array(
				'title' => XenForo_Input::STRING,
				'template' => XenForo_Input::STRING,
				'template_case_sensitive' => XenForo_Input::UINT,
				'template_state' => array(XenForo_Input::STRING, 'array' => true)
			));

			if (!$templateModel->canModifyTemplateInStyle($styleId))
			{
				return $this->responseError(new XenForo_Phrase('templates_in_this_style_can_not_be_modified'));
			}

			$conditions = array();
			if (!empty($input['title']))
			{
				$conditions['title'] = $input['title'];
			}
			if (!empty($input['template']))
			{
				// translate @x searches to "{xen:property x" as that is what is stored
				$propertyModel = $this->_getStylePropertyModel();
				$properties = $propertyModel->keyPropertiesByName(
					$propertyModel->getEffectiveStylePropertiesInStyle($styleId)
				);
				$text = $propertyModel->convertAtPropertiesForSearch($input['template'], $properties);
				$conditions['template'] = $text;

				if (!empty($input['template_case_sensitive']))
				{
					$conditions['template_case_sensitive'] = true;
				}
			}
			if ($styleId && !empty($input['template_state']) && count($input['template_state']) < 3)
			{
				$conditions['template_state'] = $input['template_state'];
			}

			if (empty($conditions))
			{
				return $this->responseError(new XenForo_Phrase('please_complete_required_fields'));
			}

			$templates = $templateModel->getEffectiveTemplateListForStyle($styleId, $conditions);

			$viewParams = array(
				'style' => $styleModel->getStyleById($styleId, true),
				'templates' => $templates
			);
			return $this->responseView('XenForo_ViewAdmin_Template_SearchResults', 'template_search_results', $viewParams);
		}
		else
		{
			$showMaster = $styleModel->showMasterStyle();

			$viewParams = array(
				'styles' => $styleModel->getAllStylesAsFlattenedTree($showMaster ? 1 : 0),
				'masterStyle' => $showMaster ? $styleModel->getStyleById(0, true) : false,
				'styleId' => $styleId
			);
			return $this->responseView('XenForo_ViewAdmin_Template_Search', 'template_search', $viewParams);
		}
	}

	/**
	 * Displays a list of outdated templates.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionOutdated()
	{
		$templates = $this->_getTemplateModel()->getOutdatedTemplates();

		$grouped = array();
		foreach ($templates AS $template)
		{
			$grouped[$template['style_id']][$template['template_id']] = $template;
		}

		$success = $this->_input->filterSingle('success', XenForo_Input::STRING);
		$successIds = $success ? explode(',', $success) : array();
		$successIds = array_map('intval', array_map('trim', $successIds));
		$successTemplates = $this->_getTemplateModel()->getTemplatesByIds($successIds);

		$viewParams = array(
			'templatesGrouped' => $grouped,
			'totalTemplates' => count($templates),
			'styles' => $this->_getStyleModel()->getAllStyles(),
			'successTemplates' => $successTemplates
		);
		return $this->responseView('XenForo_ViewAdmin_Template_Outdated', 'template_outdated', $viewParams);
	}

	public function actionAutoMerge()
	{
		if ($this->isConfirmedPost())
		{
			$success = $this->_input->filterSingle('success', array(XenForo_Input::UINT, 'array' => true));
			$skip = $this->_input->filterSingle('skip', array(XenForo_Input::UINT, 'array' => true));

			$diff = new XenForo_Diff3();
			$continue = false;
			$start = microtime(true);
			$maxTime = XenForo_Application::getConfig()->rebuildMaxExecution;

			$outdated = $this->_getTemplateModel()->getOutdatedTemplates();
			foreach ($outdated AS $template)
			{
				if (in_array($template['template_id'], $skip))
				{
					continue;
				}

				$merged = $this->_getTemplateModel()->autoMergeTemplate($template, $diff);
				if ($merged)
				{
					$success[] = $template['template_id'];
				}
				else
				{
					$skip[] = $template['template_id'];
				}

				if ($maxTime && microtime(true) - $start >= $maxTime)
				{
					$continue = true;
					break;
				}
			}

			if ($continue)
			{
				$viewParams = array(
					'success' => $success,
					'skip' => $skip
				);
				return $this->responseView('XenForo_ViewAdmin_Template_AutoMerge', 'template_auto_merge', $viewParams);
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('templates/outdated', false, array('success' => implode(',', $success)))
				);
			}
		}
		else
		{
			return $this->responseView('XenForo_ViewAdmin_Template_AutoMergeConfirm', 'template_auto_merge_confirm');
		}
	}
}