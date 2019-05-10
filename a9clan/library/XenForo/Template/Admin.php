<?php

/**
* Renderer for admin templates.
*
* @package XenForo_Core
*/
class XenForo_Template_Admin extends XenForo_Template_Abstract
{
	/**
	* Cached template data. Key is the template name; value is the compiled template.
	*
	* @var array
	*/
	protected static $_templateCache = array();

	/**
	* A list of templates that still need to be loaded. Key is the template name.
	*
	* @var array
	*/
	protected static $_toLoad = array();

	/**
	* Base path to compiled templates that are stored on disk.
	*
	* @var string
	*/
	protected static $_filePath = '';

	/**
	* Array of required external resources for this type of template.
	* All child classes must redefine this property!
	*
	* @var array
	*/
	protected static $_required = array();

	/**
	 * Extra container data from template renders.
	 *
	 * @var array
	 */
	protected static $_extraData = array();

	/**
	 * Gets the URL to fetch the list of required CSS templates. Requirements
	 * should be a list of CSS templates, not including the trailing ".css".
	 *
	 * @param array $requirements
	 *
	 * @return string
	 */
	public function getRequiredCssUrl(array $requirements)
	{
		sort($requirements);

		$args = array(
			'css' => implode(',', $requirements)
		);

		if (isset($this->_params['visitorLanguage']['text_direction']))
		{
			$args['dir'] = $this->_params['visitorLanguage']['text_direction'];
		}
		else
		{
			$args['dir'] = 'LTR';
		}

		if (XenForo_Application::isRegistered('adminStyleModifiedDate'))
		{
			$args['d'] = XenForo_Application::get('adminStyleModifiedDate');
		}
		else
		{
			$args['d'] = XenForo_Application::$time;
		}

		return XenForo_Link::buildAdminLink('_css', '', $args);
	}

		/**
	* Goes to the data source to load the list of templates.
	*
	* @param array Template list
	*
	* @return array Key-value pairs of template titles/compiled templates
	*/
	protected function _getTemplatesFromDataSource(array $templateList)
	{
		$db = XenForo_Application::getDb();

		return $db->fetchPairs('
			SELECT title, template_compiled
			FROM xf_admin_template_compiled
			WHERE title IN (' . $db->quote($templateList) . ')
				AND language_id = ?
		', self::$_languageId);
	}

	/**
	* Helper function get the list of templates that are waiting to be loaded.
	*
	* @return array
	*/
	public function getToLoadList()
	{
		return self::$_toLoad;
	}

	/**
	* Resets the to load list to empty.
	*/
	protected function _resetToLoadList()
	{
		self::$_toLoad = array();
	}

	/**
	* Merges key-value pairs of template names/compiled templates into the local template
	* cache.
	*
	* @param array Templates (key: name, value: compiled output)
	*/
	protected function _mergeIntoTemplateCache(array $templates)
	{
		self::$_templateCache = array_merge(self::$_templateCache, $templates);
	}

	/**
	* Non-static method for pre-loading a template.
	*
	* @param string Template name
	*/
	protected function _preloadTemplate($templateName)
	{
		self::preloadTemplate($templateName);
	}

	/**
	* Loads a template out of the local template cache. If the template does not
	* exist, it will be set to an empty string. This will be overwritten if
	* the template is loaded from the data source.
	*
	* @param string Template name
	*
	* @return string Compiled template
	*/
	protected function _loadTemplateFromCache($templateName)
	{
		if (isset(self::$_templateCache[$templateName]))
		{
			return self::$_templateCache[$templateName];
		}
		else
		{
			// set it for next time. If we load it, this will be overwritten
			self::$_templateCache[$templateName] = '';
			return '';
		}
	}

	/**
	* Loads the file path where a template is located in the file system, if
	* templates are being stored in the file system.
	*
	* @param string Template name
	*
	* @param string Empty string (not using file system) or file path
	*/
	protected function _loadTemplateFilePath($templateName)
	{
		if (self::$_filePath)
		{
			return self::$_filePath . '/' . preg_replace('/[^a-z0-9_\.-]/i', '', $templateName) . '.php';
		}
		else
		{
			return '';
		}
	}

	/**
	* Determines whether we are using templates in the file system.
	*
	* @return boolean
	*/
	protected function _usingTemplateFiles()
	{
		return (self::$_filePath != '');
	}

	/**
	* Gets the list of required external resources.
	*
	* @return array
	*/
	protected function _getRequiredExternals()
	{
		return self::$_required;
	}

	/**
	* Sets the list of required external resources.
	*
	* @param array
	*/
	protected function _setRequiredExternals(array $required)
	{
		self::$_required = $required;
	}

	/**
	 * Merges in extra container data from the template render.
	 *
	 * @param array
	 */
	protected function _mergeExtraContainerData(array $extraData)
	{
		self::$_extraData = XenForo_Application::mapMerge(self::$_extraData, $extraData);
	}

	/**
	 * Gets extra container data.
	 *
	 * @return array
	 */
	public static function getExtraContainerData()
	{
		return self::$_extraData;
	}

	/**
	* Specify a template that needs to be preloaded for use later. This is useful
	* if you think a render is going to be called before the template you require
	* is to be used.
	*
	* @param string Template to preload
	*/
	public static function preloadTemplate($templateName)
	{
		if (!isset(self::$_templateCache[$templateName]))
		{
			self::$_toLoad[$templateName] = true;
		}
	}

	/**
	* Manually sets a template. This is primarily useful for testing.
	*
	* @param string Name of the template
	* @param string Value for the template
	*/
	public static function setTemplate($templateName, $templateValue)
	{
		self::$_templateCache[$templateName] = $templateValue;
	}

	/**
	* Resets the template system state.
	*/
	public static function reset()
	{
		self::$_templateCache = array();
		self::$_toLoad = array();
	}
}