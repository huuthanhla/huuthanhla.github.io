<?php

class XenForo_DataWriter_BbCode extends XenForo_DataWriter
{
	const OPTION_REBUILD_CACHE = 'rebuildCache';

	const DATA_TITLE = 'title';
	const DATA_DESCRIPTION = 'description';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_bb_code_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_bb_code' => array(
				'bb_code_id'         => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 25,
					'verification' => array('$this', '_verifyBbCodeId'),
					'requiredError' => 'please_enter_valid_bb_code_tag'
				),
				'bb_code_mode'       => array('type' => self::TYPE_STRING, 'required' => true,
					'allowedValues' => array('replace', 'callback')
				),
				'has_option'         => array('type' => self::TYPE_STRING, 'required' => true,
					'allowedValues' => array('yes', 'no', 'optional')
				),
				'replace_html'       => array('type' => self::TYPE_STRING, 'default' => '', 'noTrim' => true),
				'replace_html_email' => array('type' => self::TYPE_STRING, 'default' => '', 'noTrim' => true),
				'replace_text'       => array('type' => self::TYPE_STRING, 'default' => '', 'noTrim' => true),
				'callback_class'     => array('type' => self::TYPE_STRING, 'maxLength' => 75, 'default' => ''),
				'callback_method'    => array('type' => self::TYPE_STRING, 'maxLength' => 50, 'default' => ''),
				'option_regex'       => array('type' => self::TYPE_STRING, 'default' => ''),
				'trim_lines_after'   => array('type' => self::TYPE_UINT,   'default' => 0, 'max' => 10),
				'plain_children'     => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'disable_smilies'    => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'disable_nl2br'      => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'disable_autolink'   => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'allow_empty'        => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'allow_signature'    => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'editor_icon_url'    => array('type' => self::TYPE_STRING,  'default' => '', 'maxLength' => 200),
				'sprite_mode'        => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'sprite_params'      => array('type' => self::TYPE_SERIALIZED, 'default' => ''),
				'example'            => array('type' => self::TYPE_STRING,  'default' => ''),
				'active'             => array('type' => self::TYPE_BOOLEAN, 'default' => 1),
				'addon_id'           => array('type' => self::TYPE_STRING,  'default' => '', 'maxLength' => 25),
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'bb_code_id'))
		{
			return false;
		}

		return array('xf_bb_code' => $this->_getBbCodeModel()->getBbCodeById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'bb_code_id = ' . $this->_db->quote($this->getExisting('bb_code_id'));
	}

	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REBUILD_CACHE => true
		);
	}

	/**
	 * Verifies that the ID is valid.
	 *
	 * @param string $siteId
	 *
	 * @return boolean
	 */
	protected function _verifyBbCodeId(&$bbCodeId)
	{
		$bbCodeId = strtolower($bbCodeId);

		if (preg_match('/[^a-z0-9_]/', $bbCodeId))
		{
			$this->error(new XenForo_Phrase('please_enter_an_id_using_only_alphanumeric'), 'bb_code_id');
			return false;
		}

		if ($this->isInsert() || $bbCodeId != $this->getExisting('bb_code_id'))
		{
			$existing = $this->_getBbCodeModel()->getBbCodeById($bbCodeId);
			if ($existing)
			{
				$this->error(new XenForo_Phrase('bb_code_tags_must_be_unique'), 'bb_code_id');
				return false;
			}
		}

		return true;
	}

	protected function _preSave()
	{
		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null && strlen($titlePhrase) == 0)
		{
			$this->error(new XenForo_Phrase('please_enter_valid_title'), 'title');
		}

		if (strlen($this->get('option_regex')))
		{
			if (preg_match('/\W[\s\w]*e[\s\w]*$/', $this->get('option_regex')))
			{
				// can't run a /e regex
				$this->error(new XenForo_Phrase('please_enter_valid_regular_expression'), 'option_regex');
			}
			else
			{
				try
				{
					preg_replace($this->get('option_regex'), '', '');
				}
				catch (ErrorException $e)
				{
					$this->error(new XenForo_Phrase('please_enter_valid_regular_expression'), 'option_regex');
				}
			}
		}

		if ($this->get('bb_code_mode') == 'replace')
		{
			$this->set('callback_class', '');
			$this->set('callback_method', '');
		}
		else if ($this->get('bb_code_mode') == 'callback')
		{
			$this->set('replace_html', '');
			$this->set('replace_html_email', '');
			$this->set('replace_text', '');

			$class = $this->get('callback_class');
			$method = $this->get('callback_method');

			if (!XenForo_Helper_Php::validateCallbackPhrased($class, $method, $errorPhrase))
			{
				$this->error($errorPhrase, 'callback_method');
			}
		}
	}

	protected function _postSave()
	{
		$bbCodeId = $this->get('bb_code_id');

		if ($this->isUpdate() && $this->isChanged('bb_code_id'))
		{
			$this->_renameMasterPhrase(
				$this->_getTitlePhraseName($this->getExisting('bb_code_id')),
				$this->_getTitlePhraseName($bbCodeId)
			);

			$this->_renameMasterPhrase(
				$this->_getDescriptionPhraseName($this->getExisting('bb_code_id')),
				$this->_getDescriptionPhraseName($bbCodeId)
			);
		}

		if ($this->isUpdate() && $this->isChanged('addon_id'))
		{
			$this->_changePhraseAddOn(
				$this->_getTitlePhraseName($bbCodeId),
				$this->get('addon_id')
			);

			$this->_changePhraseAddOn(
				$this->_getDescriptionPhraseName($bbCodeId),
				$this->get('addon_id')
			);
		}

		$titlePhrase = $this->getExtraData(self::DATA_TITLE);
		if ($titlePhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getTitlePhraseName($bbCodeId), $titlePhrase, $this->get('addon_id'),
				array('global_cache' => 1)
			);
		}

		$descriptionPhrase = $this->getExtraData(self::DATA_DESCRIPTION);
		if ($descriptionPhrase !== null)
		{
			$this->_insertOrUpdateMasterPhrase(
				$this->_getDescriptionPhraseName($bbCodeId), $descriptionPhrase, $this->get('addon_id')
			);
		}

		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getBbCodeModel()->rebuildBbCodeCache();

			if ($this->isInsert()
				|| $this->isChanged('bb_code_id')
				|| $this->isChanged('active')
				|| $this->isChanged('has_option')
				|| $this->isChanged('plain_children')
			)
			{
				$this->_getBbCodeModel()->updateBbCodeParseCacheVersion();
			}

			$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
		}
	}

	protected function _postDelete()
	{
		$bbCodeId = $this->get('bb_code_id');

		$this->_deleteMasterPhrase($this->_getTitlePhraseName($bbCodeId));
		$this->_deleteMasterPhrase($this->_getDescriptionPhraseName($bbCodeId));

		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getBbCodeModel()->rebuildBbCodeCache();
			$this->_getBbCodeModel()->updateBbCodeParseCacheVersion();
			$this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
		}
	}

	protected function _getTitlePhraseName($bbCodeId)
	{
		return $this->_getBbCodeModel()->getBbcodeTitlePhraseName($bbCodeId);
	}

	protected function _getDescriptionPhraseName($bbCodeId)
	{
		return $this->_getBbCodeModel()->getBbcodeDescriptionPhraseName($bbCodeId);
	}

	/**
	 * @return XenForo_Model_BbCode
	 */
	protected function _getBbCodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_BbCode');
	}
}