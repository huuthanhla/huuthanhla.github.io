<?php

class XenForo_DataWriter_HelpPage extends XenForo_DataWriter
{
	const DATA_TITLE = 'title';
	const DATA_DESCRIPTION = 'description';

	const DATA_CONTENT = 'content';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_help_page_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_help_page' => array(
				'page_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'page_name' => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50,
					'verification' => array('$this', '_verifyPageName'),
					'requiredError' => 'please_enter_valid_url_portion'
				),
				'display_order' => array('type' => self::TYPE_UINT, 'default' => 1),
				'callback_class' => array('type' => self::TYPE_STRING, 'default' => ''),
				'callback_method' => array('type' => self::TYPE_STRING, 'default' =>'')
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|bool
	*/
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array('xf_help_page' => $this->_getHelpModel()->getHelpPageById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'page_id = ' . $this->_db->quote($this->getExisting('page_id'));
	}

	protected function _verifyPageName(&$data)
	{
		if ($data === '')
		{
			$this->error(new XenForo_Phrase('please_enter_valid_url_portion'), 'page_name');
			return false;
		}

		if (!preg_match('/^[a-z0-9_\-]+$/i', $data))
		{
			$this->error(new XenForo_Phrase('please_enter_node_name_using_alphanumeric'), 'page_name');
			return false;
		}

		if ($data === strval(intval($data)) || $data == '-')
		{
			$this->error(new XenForo_Phrase('node_names_contain_more_numbers_hyphen'), 'page_name');
			return false;
		}

		$page = $this->_getHelpModel()->getHelpPageByName($data);
		if ($page && $page['page_id'] !== $this->get('page_id'))
		{
			$this->error(new XenForo_Phrase('node_names_must_be_unique'), 'page_name');
			return false;
		}

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null && strlen($titlePhrase) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}

		$content = $this->getExtraData(self::DATA_CONTENT);
		if ($content !== null)
		{
			if (strlen($content) == 0)
			{
				$this->error(new XenForo_Phrase('please_enter_page_content'), 'content');
			}
			else
			{
				$templateWriter = XenForo_DataWriter::create('XenForo_DataWriter_Template');
				$templateWriter->set('template', $content);
				$templateErrors = $templateWriter->getErrors();
				if ($templateErrors)
				{
					$this->error(reset($templateErrors), 'content');
				}
			}
		}

		if ($this->get('callback_class') || $this->get('callback_method'))
		{
			$class = $this->get('callback_class');
			$method = $this->get('callback_method');

			if (!XenForo_Helper_Php::validateCallbackPhrased($class, $method, $errorPhrase))
			{
				$this->error($errorPhrase, 'callback_method');
			}
		}
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		$pageId = $this->get('page_id');

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($pageId), $titlePhrase
			);
		}

		$descriptionPhrase = $this->getExtraData(self::DATA_DESCRIPTION);
		if ($descriptionPhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getDescriptionPhraseName($pageId), $descriptionPhrase
			);
		}

		$content = $this->getExtraData(self::DATA_CONTENT);
		if ($content !== null)
		{
			$template = $this->_getHelpModel()->getHelpPageTemplate($pageId);

			$templateWriter = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			if ($template)
			{
				$templateWriter->setExistingData($template, true);
			}
			$templateWriter->setOption(XenForo_DataWriter_Template::OPTION_TEST_COMPILE, false);
			$templateWriter->set('title', $this->_getHelpModel()->getHelpPageTemplateName($pageId));
			$templateWriter->set('template', $content);
			$templateWriter->set('style_id', 0);
			$templateWriter->set('addon_id', '');
			$templateWriter->save();
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$pageId = $this->get('page_id');

		$this->_deleteMasterPhrase($this->_getTitlePhraseName($pageId));
		$this->_deleteMasterPhrase($this->_getDescriptionPhraseName($pageId));

		$template = $this->_getHelpModel()->getHelpPageTemplate($pageId);
		if ($template)
		{
			$templateWriter = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$templateWriter->setExistingData($template, true);
			$templateWriter->delete();
		}
	}

	protected function _getTitlePhraseName($pageId)
	{
		return $this->_getHelpModel()->getHelpPageTitlePhraseName($pageId);
	}

	protected function _getDescriptionPhraseName($pageId)
	{
		return $this->_getHelpModel()->getHelpPageDescriptionPhraseName($pageId);
	}

	/**
	 * @return XenForo_Model_Help
	 */
	protected function _getHelpModel()
	{
		return $this->getModelFromCache('XenForo_Model_Help');
	}
}