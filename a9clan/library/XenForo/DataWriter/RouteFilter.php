<?php

class XenForo_DataWriter_RouteFilter extends XenForo_DataWriter
{
	/**
	 * Option that represents whether the cache will be automatically
	 * rebuilt. Defaults to true.
	 *
	 * @var string
	 */
	const OPTION_REBUILD_CACHE = 'rebuildCache';

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'requested_route_filter_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_route_filter' => array(
				'route_filter_id'   => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'route_type'        => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 25),
				'prefix'            => array('type' => self::TYPE_STRING, 'default' => '', 'maxLength' => 25),
				'find_route'        => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 255,
					'verification' => array('$this', '_validateRoute')
				),
				'replace_route'     => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 255,
					'verification' => array('$this', '_validateRoute'),
					'requiredError' => 'please_enter_a_replacement_value'
				),
				'enabled'           => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
				'url_to_route_only' => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
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

		return array('xf_route_filter' => $this->_getRouteFilterModel()->getRouteFilterById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'route_filter_id = ' . $this->_db->quote($this->getExisting('route_filter_id'));
	}

	/**
	 * Gets the default options for this data writer.
	 */
	protected function _getDefaultOptions()
	{
		return array(
			self::OPTION_REBUILD_CACHE => true
		);
	}

	protected function _validateRoute(&$value)
	{
		$value = trim($value);
		$value = ltrim($value, '/');

		if (strpos($value, '/') === false)
		{
			$value .= '/';
		}

		return true;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if (substr($this->get('find_route'), -1) == '/' && substr($this->get('replace_route'), -1) != '/')
		{
			$this->set('replace_route', $this->get('replace_route') . '/');
		}

		if ($this->isChanged('find_route'))
		{
			if (!preg_match('#^([^\?&=/\. \#\[\]%:;{}]+)(/|$)#', $this->get('find_route'), $match))
			{
				$this->error(new XenForo_Phrase('find_route_must_start_with_route_prefix'), 'find_route');
			}
			else
			{
				$this->set('prefix', $match[1]);
			}
		}

		if ($this->isChanged('replace_route'))
		{
			if (!preg_match('#^([^\?&=/\. \#\[\]%:;{}]+)(/|$)#', $this->get('replace_route'), $match))
			{
				$this->error(new XenForo_Phrase('replace_route_must_start_with_route_prefix'), 'replace_route');
			}
		}

		if (!$this->get('url_to_route_only'))
		{
			$fromCount = $this->_countWildcards($this->get('find_route'));
			$toCount = $this->_countWildcards($this->get('replace_route'));

			if ($fromCount != $toCount)
			{
				$this->error(new XenForo_Phrase('find_and_replace_fields_must_have_same_number_of_wildcards'), 'replace_route');
			}
		}
	}

	protected function _countWildcards($string)
	{
		return preg_match_all('/\{([a-z0-9_]+)(:([^}]+))?\}/i', $string, $null);
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getRouteFilterModel()->rebuildRouteFilterCache();
		}
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		if ($this->getOption(self::OPTION_REBUILD_CACHE))
		{
			$this->_getRouteFilterModel()->rebuildRouteFilterCache();
		}
	}

	/**
	 * Gets the route prefix model object.
	 *
	 * @return XenForo_Model_RouteFilter
	 */
	protected function _getRouteFilterModel()
	{
		return $this->getModelFromCache('XenForo_Model_RouteFilter');
	}
}