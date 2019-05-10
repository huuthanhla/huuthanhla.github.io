<?php


abstract class XenForo_DataWriter_TemplateModificationAbstract extends XenForo_DataWriter
{
	protected $_modTableName = '';
	protected $_logTableName = '';

	/**
	 * @return XenForo_Model_TemplateModificationAbstract
	 */
	abstract protected function _getModificationModel();

	abstract protected function _reparseTemplate($title, $fullCompile = true);

	const OPTION_REPARSE_TEMPLATE = 'reparseTemplate';
	const OPTION_FULL_TEMPLATE_COMPILE = 'fullTemplateCompile';
	const OPTION_VERIFY_MODIFICATION_KEY = 'verifyModificationKey';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_template_modification_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			$this->_modTableName => array(
				'modification_id'  => array('type' => self::TYPE_UINT,   'autoIncrement' => true),
				'addon_id'         => array('type' => self::TYPE_STRING, 'maxLength' => 25, 'default' => ''),
				'template'         => array('type' => self::TYPE_BINARY, 'required' => true, 'maxLength' => 50,
					'requiredError' => 'please_enter_valid_title'
				),
				'modification_key' => array('type' => self::TYPE_BINARY, 'maxLength' => 50, 'required' => true,
					'requiredError' => 'please_enter_modification_key',
					'verification' => array('$this', '_verifyModificationKey')
				),
				'execution_order'  => array('type' => self::TYPE_UINT, 'default' => 0),
				'description'      => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 255),
				'enabled'          => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'action'           => array('type' => self::TYPE_STRING, 'required' => true,
					'allowedValues' => array('str_replace', 'preg_replace', 'callback')
				),
				'find'             => array('type' => self::TYPE_STRING, 'required' => true,
					'requiredError' => 'please_enter_search_text'
				),
				'replace'          => array('type' => self::TYPE_STRING, 'default' => ''),
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}

		return array($this->_modTableName => $this->_getModificationModel()->getModificationById($id));
	}

	protected function _getUpdateCondition($tableName)
	{
		return 'modification_id = ' . $this->_db->quote($this->getExisting('modification_id'));
	}

	/**
	* Gets the default set of options for this data writer.
	* If in debug mode and we have a development directory config, we set the template
	* dev output directory automatically.
	*
	* @return array
	*/
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REPARSE_TEMPLATE => true,
			self::OPTION_FULL_TEMPLATE_COMPILE => true,
			self::OPTION_VERIFY_MODIFICATION_KEY => true
		);
	}

	protected function _verifyModificationKey($key)
	{
		if ($this->isInsert() || $key != $this->getExisting('modification_key'))
		{
			$keyConflict = $this->_getModificationModel()->getModificationByKey($key);
			if ($keyConflict && $keyConflict['modification_id'] != $this->get('modification_id'))
			{
				$this->error(new XenForo_Phrase('template_modification_keys_must_be_unique'), 'modification_key');
				return false;
			}
		}

		if ($this->getOption(self::OPTION_VERIFY_MODIFICATION_KEY) && preg_match('/[^a-zA-Z0-9_]/', $key))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'modification_key');
			return false;
		}

		return true;
	}

	protected function _preSave()
	{
		if (($this->get('action') == 'preg_replace' || $this->get('action') == 'callback') && $this->get('find'))
		{
			if (preg_match('/\W[\s\w]*e[\s\w]*$/', $this->get('find')))
			{
				// can't run a /e regex
				$this->error(new XenForo_Phrase('please_enter_valid_regular_expression'), 'find');
			}
			else
			{
				try
				{
					preg_replace($this->get('find'), '', '');
				}
				catch (ErrorException $e)
				{
					$this->error(new XenForo_Phrase('please_enter_valid_regular_expression'), 'find');
				}
			}
		}

		if ($this->get('action') == 'callback' && ($this->isChanged('replace') || $this->isChanged('action')))
		{
			if (preg_match('/^([a-z0-9_\\\\]+)::([a-z0-9_]+)$/i', $this->get('replace'), $match))
			{
				if (!XenForo_Helper_Php::validateCallbackPhrased($match[1], $match[2], $errorPhrase))
				{
					$this->error($errorPhrase, 'replace');
				}
			}
			else
			{
				$this->error(new XenForo_Phrase('please_enter_valid_callback_method'), 'replace');
			}
		}
	}

	/**
	* Post-save handler.
	*/
	protected function _postSave()
	{
		if ($this->getOption(self::OPTION_REPARSE_TEMPLATE))
		{
			$this->_reparseTemplate($this->get('template'), $this->getOption(self::OPTION_FULL_TEMPLATE_COMPILE));

			if ($this->isChanged('template') && $this->getExisting('template'))
			{
				$this->_reparseTemplate($this->getExisting('template'), $this->getOption(self::OPTION_FULL_TEMPLATE_COMPILE));
			}
		}
	}


	/**
	 * Post-delete handler.
	 */
	protected function _postDelete()
	{
		$this->_db->delete($this->_logTableName,
			'modification_id = ' . $this->_db->quote($this->get('modification_id'))
		);

		if ($this->getOption(self::OPTION_REPARSE_TEMPLATE))
		{
			$this->_reparseTemplate($this->get('template'), $this->getOption(self::OPTION_FULL_TEMPLATE_COMPILE));
		}
	}
}