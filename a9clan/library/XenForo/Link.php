<?php

/**
 * Helper methods to generate links to content. Links generated
 * by this are not necessarily HTML escaped. The calling code
 * should escape them for the output context they apply to.
 *
 * @package XenForo_Core
 */
class XenForo_Link
{
	/**
	 * Stores a cache of handlers for prefixes. Many types of links will
	 * be generated multiple times on a page, so this cache reduces the
	 * amount of object creation/validation necessary.
	 *
	 * @var array
	 */
	protected static $_handlerCache = array();

	/**
	 * URL prefix to use when generating a canonical link.
	 *
	 * @var string|null
	 */
	protected static $_canonicalLinkPrefix = null;

	/**
	 * If true, uses friendly URLs that don't include index.php or a query string (unless required).
	 *
	 * @var boolean
	 */
	protected static $_useFriendlyUrls = false;

	/**
	 * If true, Romanize titles before outputting them in URLs.
	 *
	 * @var boolean
	 */
	protected static $_romanizeTitles = false;

	/**
	 * @var string
	 */
	protected static $_indexRoute = 'forums/';

	protected $_linkString = '';
	protected $_canPrependFull = true;

	/**
	 * Constructor. Use the static methods in general. However, you can create
	 * an object of this type from a link builder to generate an arbitrary URL.
	 *
	 * @param string $linkString
	 * @param boolean $canPrependFull True if the default full link prefix can be prepended to make a full URL
	 */
	public function __construct($linkString, $canPrependFull = true)
	{
		$this->_linkString = $linkString;
		$this->_canPrependFull = $canPrependFull;
	}

	/**
	 * @return string Link
	 */
	public function __toString()
	{
		return $this->_linkString;
	}

	/**
	 * @return boolean
	 */
	public function canPrependFull()
	{
		return $this->_canPrependFull;
	}

	/**
	 * Builds a link to a public resource. The type should contain a prefix
	 * optionally split by a "/" with the specific action (eg "templates/edit").
	 *
	 * @param string $type Prefix and action
	 * @param mixed $data Data that the prefix/action should be applied to, if applicable
	 * @param array $extraParams Additional params
	 *
	 * @return string The link
	 */
	public static function buildPublicLink($type, $data = null, array $extraParams = array(), $skipPrepend = false)
	{
		$type = self::_checkForFullLink($type, $fullLink, $fullLinkPrefix);

		$link = self::_buildLink('public', $type, $data, $extraParams, $prefix);
		$queryString = self::buildQueryString($extraParams);

		if ($link instanceof XenForo_Link)
		{
			$isRaw = true;
			$canPrependFull = $link->canPrependFull();
		}
		else
		{
			$isRaw = false;
			$canPrependFull = true;

			if (strpos($link, '#') !== false)
			{
				list($link, $hash) = explode('#', $link);
			}

			if ($link == self::$_indexRoute)
			{
				$link = '';
			}
			else if (XenForo_Application::isRegistered('routeFiltersOut'))
			{
				$routeFilters = XenForo_Application::get('routeFiltersOut');
				if (isset($routeFilters[$prefix]))
				{
					foreach ($routeFilters[$prefix] AS $filter)
					{
						list($from, $to) = self::translateRouteFilterToRegex(
							$filter['find_route'], $filter['replace_route']
						);

						$newLink = preg_replace($from, $to, $link);
						if ($newLink != $link)
						{
							$link = $newLink;
							break;
						}
					}
				}
			}
		}

		if (self::$_useFriendlyUrls || $isRaw)
		{
			$outputLink = ($queryString !== '' ? "$link?$queryString" : $link);
		}
		else
		{
			if ($queryString !== '' && $link !== '')
			{
				$append = "?$link&$queryString";
			}
			else
			{
				// 1 or neither of these has content
				$append = $link . $queryString;
				if ($append !== '')
				{
					$append = "?$append";
				}
			}
			if ($skipPrepend)
			{
				$outputLink = $append;
			}
			else
			{
				$outputLink = 'index.php' .  $append;
			}
		}

		if ($fullLink && $canPrependFull)
		{
			$outputLink = $fullLinkPrefix . $outputLink;
		}

		// deal with a hash in the $type {xen:link prefix#hash..}
		if (($hashPos = strpos($type, '#')) !== false)
		{
			$hash = substr($type, $hashPos + 1);
		}

		if ($outputLink === '')
		{
			$outputLink = '.';
		}

		return $outputLink . (empty($hash) ? '' : '#' . $hash);
	}

	/**
	 * Builds a link to an admin resource. The type should contain a prefix
	 * optionally split by a "/" with the specific action (eg "templates/edit").
	 *
	 * @param string $type Prefix and action
	 * @param mixed $data Data that the prefix/action should be applied to, if applicable
	 * @param array $extraParams Additional params
	 *
	 * @return string The link
	 */
	public static function buildAdminLink($type, $data = null, array $extraParams = array())
	{
		$type = self::_checkForFullLink($type, $fullLink, $fullLinkPrefix);

		$link = self::_buildLink('admin', $type, $data, $extraParams);
		$queryString = self::buildQueryString($extraParams);

		if (strpos($link, '#') !== false)
		{
			list($link, $hash) = explode('#', $link);
		}

		if ($queryString !== '' && $link !== '')
		{
			$append = $link . '&' . $queryString;
		}
		else
		{
			// 1 or neither of these has content
			$append = $link . $queryString;
		}

		// deal with a hash in the $type {xen:link prefix#hash..}
		if (($hashPos = strpos($type, '#')) !== false)
		{
			$append .= substr($type, $hashPos + 1);
		}

		$outputLink = 'admin.php' . ($append !== '' ? '?' : '') . $append;
		if ($fullLink)
		{
			$outputLink = $fullLinkPrefix . $outputLink;
		}

		return $outputLink . (empty($hash) ? '' : '#' . $hash);
	}

	/**
	 * Builds a link along the lines of <prefix>/<sub-component>/<data.id>/<action>.
	 *
	 * @param array $subComponents List of sub-components that are valid as keys, with specific child keys (title, intId, stringId)
	 * @param string $outputPrefix
	 * @param string $action
	 * @param string $extension
	 * @param mixed $data
	 *
	 * @return string|false String if sub-component matched with appropriate data, false otherwise
	 */
	public static function buildSubComponentLink(array $subComponents, $outputPrefix, $action, $extension, $data)
	{
		$parts = explode('/', $action, 2);
		$subComponentName = strtolower($parts[0]);
		foreach ($subComponents AS $key => $subComponent)
		{
			if ($key == $subComponentName)
			{
				$action = (isset($parts[1]) ? $parts[1] : '');

				if (isset($subComponent['intId']))
				{
					$titleField = (isset($subComponent['title']) ? $subComponent['title'] : '');
					return self::buildBasicLinkWithIntegerParam("$outputPrefix/$parts[0]", $action, $extension, $data, $subComponent['intId'], $titleField);
				}
				else if (isset($subComponent['stringId']))
				{
					return self::buildBasicLinkWithStringParam("$outputPrefix/$parts[0]", $action, $extension, $data, $subComponent['stringId']);
				}
				else
				{
					return false;
				}
			}
		}

		return false;
	}

	/**
	 * Check to see if a full link is requested.
	 *
	 * @param string $type Link type
	 * @param boolean $fullLink Modified by ref. Returns whether a full link is requested.
	 * @param string $fullLinkPrefix If a full link is requested, the prefix to use
	 *
	 * @return string Link type, with full link param stripped off if necessary
	 */
	protected static function _checkForFullLink($type, &$fullLink, &$fullLinkPrefix)
	{
		if (!$type)
		{
			$fullLink = false;
			$fullLinkPrefix = '';
			return $type;
		}

		if ($type[0] == 'c' && substr($type, 0, 10) == 'canonical:')
		{
			$type = substr($type, 10);
			$fullLink = true;
			$fullLinkPrefix = self::getCanonicalLinkPrefix() . '/';
		}
		else if ($type[0] == 'f' && substr($type, 0, 5) == 'full:')
		{
			$type = substr($type, 5);
			$fullLink = true;

			$paths = XenForo_Application::get('requestPaths');
			$fullLinkPrefix = $paths['fullBasePath'];
		}
		else
		{
			$fullLink = false;
			$fullLinkPrefix = '';
		}

		return $type;
	}

	/**
	 * Internal link builder.
	 *
	 * @param string $group Type of link being built (admin or public)
	 * @param string $type Type of data the link is for (prefix and action)
	 * @param mixed $data
	 * @param array $extraParams
	 * @param string|null $prefix The prefix found
	 *
	 * @return string
	 */
	protected static function _buildLink($group, $type, $data, array &$extraParams, &$prefix = null)
	{
		if (isset($extraParams['_params']) && is_array($extraParams['_params']))
		{
			$params = $extraParams['_params'];
			unset($extraParams['_params']);

			$extraParams = array_merge($params, $extraParams);
		}

		$extension = '';
		if (($dotPos = strrpos($type, '.')) !== false)
		{
			$extension = substr($type, $dotPos + 1);
			$type = substr($type, 0, $dotPos);
		}

		if (($hashPos = strpos($type, '#')) !== false)
		{
			$type = substr($type, 0, $hashPos);
		}

		if (($slashPos = strpos($type, '/')) !== false)
		{
			list($prefix, $action) = explode('/', $type, 2);

			if ($action == 'index')
			{
				$action = '';
			}
		}
		else
		{
			$prefix = $type;
			$action = '';
		}
		unset($type);

		$handler = self::_getPrefixHandler($group, $prefix, (boolean)$data);
		if ($handler === false)
		{
			$link = false;
		}
		else
		{
			$link = $handler->buildLink($prefix, $prefix, $action, $extension, $data, $extraParams);
		}

		if ($link === false || $link === null)
		{
			return self::buildBasicLink($prefix, $action, $extension);
		}
		else
		{
			return $link;
		}
	}

	/**
	 * Gets the object that should handle building the link for this prefix.
	 * May also return false if only the standard behavior is desired.
	 *
	 * @param string $group Type of link (public or admin)
	 * @param string $originalPrefix Prefix to build the link for (should be the "original prefix" in the DB)
	 * @param boolean $haveData Whether we have a data element
	 *
	 * @return object|false Object with "buildLink" method or false
	 */
	protected static function _getPrefixHandler($group, $originalPrefix, $haveData)
	{
		if (!isset(self::$_handlerCache[$group]))
		{
			self::$_handlerCache[$group] = self::_loadHandlerInfoForGroup($group);
		}

		if (!isset(self::$_handlerCache[$group][$originalPrefix]))
		{
			return false;
		}

		$info =& self::$_handlerCache[$group][$originalPrefix];

		if ($haveData)
		{
			if (!isset($info['handlerWithData']))
			{
				$info['handlerWithData'] = self::_loadPrefixHandlerClass($info, true);
			}
			return $info['handlerWithData'];
		}
		else
		{
			if (!isset($info['handlerNoData']))
			{
				$info['handlerNoData'] = self::_loadPrefixHandlerClass($info, false);
			}
			return $info['handlerNoData'];
		}
	}

	/**
	 * Load the prefix link build handler class based on current settings.
	 *
	 * @param array $info Info about how to build this link (includes build_link, route_class keys)
	 * @param boolean $haveData True if we have a data param for this link
	 *
	 * @return object|false Object with "buildLink" method or false
	 */
	protected static function _loadPrefixHandlerClass(array $info, $haveData)
	{
		if ($info['build_link'] == 'none' || ($info['build_link'] == 'data_only' && !$haveData))
		{
			// never build or only build when we have data (and we don't now)
			return false;
		}

		if ($info['build_link'] == 'all')
		{
			// always build - check for a previous call
			if (isset($info['handlerWithData']))
			{
				return $info['handlerWithData'];
			}
			else if (isset($info['handlerNoData']))
			{
				return $info['handlerNoData'];
			}
		}

		// ...otherwise load the class we need

		$class = XenForo_Application::resolveDynamicClass($info['route_class'], 'route_prefix');
		if (!$class)
		{
			return false;
		}

		$handler = new $class();
		if (!method_exists($handler, 'buildLink'))
		{
			return false;
		}

		return $handler;
	}

	/**
	 * Loads all the link build handler data for an entire group of prefixes.
	 *
	 * @param string $group Type of prefix (public or admin)
	 *
	 * @return array Keys are "original prefixes" and values are info about output prefix/class/build settings
	 */
	protected static function _loadHandlerInfoForGroup($group)
	{
		return XenForo_Model::create('XenForo_Model_RoutePrefix')->getPrefixesForRouteCache($group);
	}

	/**
	 * Gets the name of the specified prefix handler class.
	 *
	 * @param string $group
	 * @param string $prefix
	 *
	 * @return string|false
	 */
	public static function getPrefixHandlerClassName($group, $prefix)
	{
		if (!isset(self::$_handlerCache[$group]))
		{
			self::$_handlerCache[$group] = self::_loadHandlerInfoForGroup($group);
		}

		if (!isset(self::$_handlerCache[$group][$prefix]))
		{
			return false;
		}

		return self::$_handlerCache[$group][$prefix]['route_class'];
	}

	/**
	 * Examines action and extra parameters from a link build call and formulates
	 * a page number link parameter if applicable.
	 *
	 * @param string $action
	 * @param array $params
	 *
	 * @return string $action
	 */
	public static function getPageNumberAsAction($action, array &$params)
	{
		if (isset($params['page']))
		{
			if (strval($params['page']) !== XenForo_Application::$integerSentinel && $params['page'] <= 1)
			{
				unset($params['page']);
			}
			else if (!$action)
			{
				if ($params['page'] != XenForo_Application::$integerSentinel)
				{
					$params['page'] = intval($params['page']);
				}

				$action = "page-$params[page]";

				unset($params['page']);
			}
		}

		return $action;
	}

	/**
	 * Helper to manually set handler info for a group. Keys should be "original prefixes"
	 * and values should be arrays with keys matching the xf_route_prefix table.
	 *
	 * @param string $group Type of prefix to handle (public or admin)
	 * @param array $info Info to set
	 */
	public static function setHandlerInfoForGroup($group, array $info)
	{
		self::$_handlerCache[$group] = $info;
	}

	/**
	 * Gets the handler info for the group of prefixes.
	 *
	 * @param string $group
	 *
	 * @return array
	 */
	public static function getHandlerInfoForGroup($group)
	{
		if (!isset(self::$_handlerCache[$group]))
		{
			self::$_handlerCache[$group] = self::_loadHandlerInfoForGroup($group);
		}

		return self::$_handlerCache[$group];
	}

	/**
	 * Resets the handlers for all groups or for a particular group. Mainly used for testing.
	 *
	 * @param string|false $group If false, resets all handlers; otherwise, resets the specified handler group
	 */
	public static function resetHandlerInfo($group = false)
	{
		if ($group === false)
		{
			self::$_handlerCache = array();
		}
		else
		{
			unset(self::$_handlerCache[strval($group)]);
		}
	}

	/**
	 * Builds a basic link: a prefix and action only.
	 *
	 * @param string $prefix
	 * @param string $action
	 * @param string $extension
	 *
	 * @return string
	 */
	public static function buildBasicLink($prefix, $action, $extension = '')
	{
		if ($extension)
		{
			self::prepareExtensionAndAction($extension, $action);
		}

		if ($prefix === 'index' && $action === '')
		{
			return '';
		}
		else
		{
			return "$prefix/$action$extension";
		}
	}

	/**
	 * Prepares the link extension and action, if necessary. If an extension is specified,
	 * the provided value will be prefixed with a ".". If there is an extension and there's
	 * no action, an explicit "index" action will be specified.
	 *
	 * @param string $extension Initially, the extension to the link specified; prefixed with "." if necessary
	 * @param string $action The link action; modified if necessary
	 */
	public static function prepareExtensionAndAction(&$extension, &$action, $prepareAction = true)
	{
		if ($extension)
		{
			$extension = '.' . $extension;
			if ($action === '')
			{
				$action = 'index';
			}
		}
	}

	/**
	 * Builds a basic link for a request that may have an integer param.
	 * Output will be in the format [prefix]/[title].[int]/[action]/ or similar,
	 * based on whether the correct values in data are set.
	 *
	 * @param string $prefix Link prefix
	 * @param string $action Link action
	 * @param string $extension Link extension (for content type)
	 * @param mixed $data Specific data to link to. If available, an array or an object that implements ArrayAccess
	 * @param string $intField The name of the field that holds the integer identifier
	 * @param string $titleField If there is a title field, the name of the field that holds the title
	 *
	 * @return false|string False if no data is provided, the link otherwise
	 */
	public static function buildBasicLinkWithIntegerParam($prefix, $action, $extension, $data, $intField, $titleField = '')
	{
		if ((is_array($data) || $data instanceof ArrayAccess) && isset($data[$intField]))
		{
			self::prepareExtensionAndAction($extension, $action);

			$title = (($titleField && !empty($data[$titleField])) ? $data[$titleField] : '');
			return "$prefix/" . self::buildIntegerAndTitleUrlComponent($data[$intField], $title) . "/$action$extension";
		}
		else
		{
			return false;
		}
	}

	/**
	 * Builds a basic link for a request that may have a string param.
	 * Output will be in the format [prefix]/[param]/[action].
	 *
	 * Note that it is expected that the string param is already clean enough
	 * to be inserted into the link.
	 *
	 * @param string $prefix Link prefix
	 * @param string $action Link action
	 * @param string $extension Link extension (for content type)
	 * @param mixed $data Specific data to link to. If available, an array or an object that implements ArrayAccess, or a simple string to be used directly
	 * @param string $strField The name of the field that holds the string identifier
	 *
	 * @return false|string False if no data is provided, the link otherwise
	 */
	public static function buildBasicLinkWithStringParam($prefix, $action, $extension, $data, $strField)
	{
		if ($data)
		{
			self::prepareExtensionAndAction($extension, $action);

			if ((is_array($data) || $data instanceof ArrayAccess)
				&& isset($data[$strField])
				&& $data[$strField] !== '')
			{
				return "$prefix/" . $data[$strField] . "/$action$extension";
			}
			else if (is_string($data))
			{
				return "$prefix/$data/$action$extension";
			}
		}

		return false;
	}

	/**
	 * Builds the URL component for an integer and title. Outputs <int> or <int>-<title>.
	 *
	 * @param integer $integer
	 * @param string $title
	 * @param boolean|null $romanize If true, non-latin strings are romanized. If null, use default setup
	 *
	 * @return string
	 */
	public static function buildIntegerAndTitleUrlComponent($integer, $title = '', $romanize = null)
	{
		if ($title && XenForo_Application::get('options')->includeTitleInUrls)
		{
			if ($romanize === null)
			{
				$romanize = self::$_romanizeTitles;
			}
			$title = self::getTitleForUrl($title, $romanize);
			if ($title !== '')
			{
				# /item-title.id/ (where delimiter is '.')
				return urlencode($title) . XenForo_Application::URL_ID_DELIMITER . intval($integer);
			}
		}

		return intval($integer);
	}

	/**
	 * Cache for getting prepped/Romanized titles for URLs
	 *
	 * @var array
	 */
	protected static $_titleCache = array();

	/**
	 * Gets version of a title that is valid in a URL. Invalid elements are stripped
	 * or replaced with '-'. It may not be possible to reverse a URL'd title to the
	 * original title.
	 *
	 * @param string $title
	 * @param boolean $romanize If true, non-latin strings are romanized
	 *
	 * @return string
	 */
	public static function getTitleForUrl($title, $romanize = false)
	{
		$title = strval($title);

		if (isset(self::$_titleCache[$title]))
		{
			return self::$_titleCache[$title];
		}

		$original = $title;

		if ($romanize)
		{
			$title = utf8_romanize(utf8_deaccent($title));
		}

		$title = strtr(
			$title,
			'`!"$%^&*()-+={}[]<>;:@#~,./?|' . "\r\n\t\\",
			'                             ' . '    '
		);
		$title = strtr($title, array('"' => '', "'" => ''));

		if ($romanize)
		{
			$title = preg_replace('/[^a-zA-Z0-9_ -]/', '', $title);
		}

		$title = preg_replace('/[ ]+/', '-', trim($title));
		$title = strtr($title, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');

		self::$_titleCache[$original] = $title;

		return $title;
	}

	/**
	 * Builds a query string from an array of items. Keys of the array will become
	 * names of items in the query string. Nested arrays are supported.
	 *
	 * @param array $elements Elements to build the query string from
	 * @param string $prefix For nested arrays, specifies the base context we're in.
	 * 		Leave default unless wanting all elements inside an array.
	 *
	 * @return string
	 */
	public static function buildQueryString(array $elements, $prefix = '')
	{
		$output = array();

		foreach ($elements AS $name => $value)
		{
			if (is_array($value))
			{
				if (!$value)
				{
					continue;
				}

				$encodedName = ($prefix ? $prefix . '[' . urlencode($name) . ']' : urlencode($name));
				$childOutput = self::buildQueryString($value, $encodedName);
				if ($childOutput !== '')
				{
					$output[] = $childOutput;
				}
			}
			else
			{
				if ($value === null || $value === false || $value === '')
				{
					continue;
				}

				$value = strval($value);

				if ($prefix)
				{
					// part of an array
					$output[] = $prefix . '[' . urlencode($name) . ']=' . urlencode($value);
				}
				else
				{
					$output[] = urlencode($name) . '=' . urlencode($value);
				}
			}
		}

		return implode('&', $output);
	}

	/**
	 * Set the prefix for links that are generated as canonical links.
	 *
	 * @param string $linkPrefix
	 */
	public static function setCanonicalLinkPrefix($linkPrefix)
	{
		self::$_canonicalLinkPrefix = self::convertUriToAbsoluteUri($linkPrefix, true);
	}

	/**
	 * Gets the canonical link prefix to use for generating canonical links.
	 *
	 * @return string
	 */
	public static function getCanonicalLinkPrefix()
	{
		if (self::$_canonicalLinkPrefix === null)
		{
			self::$_canonicalLinkPrefix = self::convertUriToAbsoluteUri(
				rtrim(XenForo_Application::get('options')->boardUrl, '/'),
				true
			);
		}

		return self::$_canonicalLinkPrefix;
	}

	/**
	 * Sets whether friendly URLs should be used for generating links.
	 *
	 * @param boolean $value
	 */
	public static function useFriendlyUrls($value)
	{
		self::$_useFriendlyUrls = $value;
	}

	/**
	 * Sets whether friendly titles should be romanized in links.
	 *
	 * @param boolean $value
	 */
	public static function romanizeTitles($value)
	{
		self::$_romanizeTitles = $value;
	}

	/**
	 * Sets the index route
	 *
	 * @param $value
	 */
	public static function setIndexRoute($value)
	{
		self::$_indexRoute = $value;
	}

	/**
	 * @return string
	 */
	public static function getIndexRoute()
	{
		return self::$_indexRoute;
	}

	/**
	 * Converts what may be a relative link into an absolute URI.
	 *
	 * @param string $uri URI to convert
	 * @param boolean $includeHost If true, includes host, port, and protocol
	 * @param array|null $paths Paths to override (uses application level if not provided)
	 *
	 * @return string
	 */
	public static function convertUriToAbsoluteUri($uri, $includeHost = false, array $paths = null)
	{
		if (!$paths)
		{
			$paths = XenForo_Application::get('requestPaths');
		}

		if ($uri == '.')
		{
			$uri = ''; // current directory
		}

		if (substr($uri, 0, 2) == '//')
		{
			return $paths['protocol'] . ':' . $uri;
		}
		else if (substr($uri, 0, 1) == '/')
		{
			if ($includeHost)
			{
				return $paths['protocol'] . '://' . $paths['host'] . $uri;
			}
			else
			{
				return $uri;
			}
		}
		else if (preg_match('#^[a-z0-9-]+://#i', $uri))
		{
			return $uri;
		}
		else if ($includeHost)
		{
			return $paths['fullBasePath'] . $uri;
		}
		else
		{
			return $paths['basePath'] . $uri;
		}
	}

	public static function translateRouteFilterToRegex($from, $to)
	{
		$to = strtr($to, array('\\' => '\\\\', '$' => '\\$'));

		$findReplacements = array();
		$replacementChr = chr(26);

		preg_match_all('/\{([a-z0-9_]+)(:([^}]+))?\}/i', $from, $matches, PREG_SET_ORDER);
		foreach ($matches AS $i => $match)
		{
			$placeholder = $replacementChr . $i . $replacementChr;

			if (!empty($match[3]))
			{
				switch ($match[3])
				{
					case 'digit': $replace = '(\d+)'; break;
					case 'string': $replace = '([^/.]+)'; break;
					default: $replace = '([^/]*)';
				}
			}
			else
			{
				$replace = '([^/]*)';
			}

			$findReplacements[$placeholder] = $replace;

			$from = str_replace($match[0], $placeholder, $from);
			$to = str_replace($match[0], '$' . ($i + 1), $to);
		}

		$from = preg_quote($from, '#');
		foreach ($findReplacements AS $findPlaceholder => $findReplacement)
		{
			$from = str_replace($findPlaceholder, $findReplacement, $from);
		}

		return array('#^' . $from . '#', $to);
	}
}