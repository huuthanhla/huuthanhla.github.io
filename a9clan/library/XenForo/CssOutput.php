<?php

/**
 * Class to output CSS data quickly for public facing pages. This class
 * is not designed to be used with the MVC structure; this allows us to
 * significantly reduce the amount of overhead in a request.
 *
 * This class is entirely self sufficient. It handles parsing the input,
 * getting the data, rendering it, and manipulating HTTP headers.
 *
 * @package XenForo_CssOutput
 */
class XenForo_CssOutput
{
	/**
	 * Style ID the CSS will be retrieved from.
	 *
	 * @var integer
	 */
	protected $_styleId = 0;

	/**
	 * Array of CSS templates that have been requested. These will have ".css" appended
	 * to them and requested as templates.
	 *
	 * @var array
	 */
	protected $_cssRequested = array();

	/**
	 * The timestamp of the last modification, according to the input. (Used to compare
	 * to If-Modified-Since header.)
	 *
	 * @var integer
	 */
	protected $_inputModifiedDate = 0;

	/**
	 * The direction in which text should be rendered. Either ltr or rtl.
	 *
	 * @var string
	 */
	protected $_textDirection = 'LTR';

	/**
	 * Date of the last modification to the style. Used to output Last-Modified header.
	 *
	 * @var integer
	 */
	protected $_styleModifiedDate = 0;

	/**
	 * List of user display styles to write out username CSS.
	 *
	 * @var array
	 */
	protected $_displayStyles = array();

	/**
	 * List of smilie sprite styles to write out sprite CSS.
	 *
	 * @var array
	 */
	protected $_smilieSprites = array();

	/**
	 * Constructor.
	 *
	 * @param array $input Array of input. Style and CSS will be pulled from this.
	 */
	public function __construct(array $input)
	{
		$this->parseInput($input);
	}

	/**
	 * Parses the style ID and the list of CSS out of the specified array of input.
	 * The style ID will be found in "style" and CSS list in "css". The CSS should be
	 * comma-delimited.
	 *
	 * @param array $input
	 */
	public function parseInput(array $input)
	{
		$this->_styleId = isset($input['style']) ? intval($input['style']) : 0;

		if (!empty($input['css']))
		{
			$css = is_scalar($input['css']) ? strval($input['css']) : '';
			if (preg_match('/./u', $css))
			{
				$this->_cssRequested = explode(',', $css);
			}
		}

		if (!empty($input['d']))
		{
			$this->_inputModifiedDate = intval($input['d']);
		}

		if (!empty($input['dir']) && is_string($input['dir']) && strtoupper($input['dir']) == 'RTL')
		{
			$this->_textDirection = 'RTL';
		}
	}

	public function handleIfModifiedSinceHeader(array $server)
	{
		$outputCss = true;
		if (isset($server['HTTP_IF_MODIFIED_SINCE']))
		{
			$modDate = strtotime($server['HTTP_IF_MODIFIED_SINCE']);
			if ($modDate !== false && $this->_inputModifiedDate <= $modDate)
			{
				header('Content-type: text/css; charset=utf-8', true, 304);
				$outputCss = false;
			}
		}

		return $outputCss;
	}

	/**
	 * Does any preparations necessary for outputting to be done.
	 */
	protected function _prepareForOutput()
	{
		$this->_displayStyles = XenForo_Application::get('displayStyles');
		$styles = XenForo_Application::get('styles');

		$smilieSprites = XenForo_Model::create('XenForo_Model_DataRegistry')->get('smilieSprites');
		if (is_array($smilieSprites))
		{
			$this->_smilieSprites = $smilieSprites;
		}

		if ($this->_styleId && isset($styles[$this->_styleId]))
		{
			$style = $styles[$this->_styleId];
		}
		else
		{
			$style = reset($styles);
		}

		if ($style)
		{
			$properties = unserialize($style['properties']);

			$this->_styleId = $style['style_id'];
			$this->_styleModifiedDate = $style['last_modified_date'];
		}
		else
		{
			$properties = array();

			$this->_styleId = 0;
		}

		$defaultProperties = XenForo_Application::get('defaultStyleProperties');

		XenForo_Template_Helper_Core::setStyleProperties(XenForo_Application::mapMerge($defaultProperties, $properties), false);
		XenForo_Template_Public::setStyleId($this->_styleId);
		XenForo_Template_Abstract::setLanguageId(0);
	}

	/**
	 * Renders the CSS and returns it.
	 *
	 * @return string
	 */
	public function renderCss()
	{
		$cacheId = 'xfCssCache_' . sha1(
			'style=' . $this->_styleId .
			'css=' . serialize($this->_cssRequested) .
			'd=' . $this->_inputModifiedDate .
			'dir=' . $this->_textDirection .
			'minify=' . XenForo_Application::get('options')->minifyCss)
			. (XenForo_Application::debugMode() ? 'debug' : '');

		if ($cacheObject = XenForo_Application::getCache())
		{
			if ($cacheCss = $cacheObject->load($cacheId, true))
			{
				return $cacheCss . "\n/* CSS returned from cache. */";
			}
		}

		$this->_prepareForOutput();

		if (XenForo_Application::isRegistered('bbCode'))
		{
			$bbCodeCache = XenForo_Application::get('bbCode');
		}
		else
		{
			$bbCodeCache = XenForo_Model::create('XenForo_Model_BbCode')->getBbCodeCache();
		}

		$params = array(
			'displayStyles' => $this->_displayStyles,
			'smilieSprites' => $this->_smilieSprites,
			'customBbCodes' => !empty($bbCodeCache['bbCodes']) ? $bbCodeCache['bbCodes'] : array(),
			'xenOptions' => XenForo_Application::get('options')->getOptions(),
			'dir' => $this->_textDirection,
			'pageIsRtl' => ($this->_textDirection == 'RTL')
		);

		$templates = array();
		foreach ($this->_cssRequested AS $cssName)
		{
			$cssName = trim($cssName);
			if (!$cssName)
			{
				continue;
			}

			$templateName = $cssName . '.css';
			if (!isset($templates[$templateName]))
			{
				$templates[$templateName] = new XenForo_Template_Public($templateName, $params);
			}
		}

		$css = self::renderCssFromObjects($templates, XenForo_Application::debugMode());
		$css = self::prepareCssForOutput(
			$css,
			$this->_textDirection,
			(XenForo_Application::get('options')->minifyCss && $cacheObject)
		);

		if ($cacheObject)
		{
			$cacheObject->save($css, $cacheId, array(), 86400);
		}

		return $css;
	}

	public static function prepareCssForOutput($css, $direction, $minify = false)
	{
		$css = self::translateCssRules($css);

		if ($direction == 'RTL')
		{
			$css = XenForo_Template_Helper_RightToLeft::getRtlCss($css);
		}

		$css = preg_replace('/rtl-raw\.([a-zA-Z0-9-]+\s*:)/', '$1', $css);

		if ($minify)
		{
			$css = Minify_CSS_Compressor::process($css);
		}

		return $css;
	}

	/**
	 * Renders the CSS from a collection of Template objects.
	 *
	 * @param array $templates Array of XenForo_Template_Abstract objects
	 * @param boolean $withDebug If true, output debug CSS when invalid properties are accessed
	 *
	 * @return string
	 */
	public static function renderCssFromObjects(array $templates, $withDebug = false)
	{
		$errors = array();
		$output = '@charset "UTF-8";' . "\n";

		ob_start();

		foreach ($templates AS $templateName => $template)
		{
			if ($withDebug)
			{
				XenForo_Template_Helper_Core::resetInvalidStylePropertyAccessList();
			}

			$rendered = $template->render();
			if ($rendered !== '')
			{
				$output .= "\n/* --- " . str_replace('*/', '', $templateName) . " --- */\n\n$rendered\n";
			}

			if ($withDebug)
			{
				$propertyError = self::createDebugErrorString(
					XenForo_Template_Helper_Core::getInvalidStylePropertyAccessList()
				);
				if ($propertyError)
				{
					$errors["$templateName"] = $propertyError;
				}
			}
		}

		$phpErrors = ob_get_clean();
		if ($phpErrors)
		{
			$errors["PHP"] = $phpErrors;
		}

		if ($withDebug && $errors)
		{
			$output .= self::getDebugErrorsAsCss($errors);
		}

		return $output;
	}

	/**
	 * Translates CSS rules for use by current browsers.
	 *
	 * @param string $output
	 *
	 * @return string
	 */
	public static function translateCssRules($output)
	{
		/**
		 * CSS3 temporary attributes translation.
		 * Some browsers implement custom attributes that refer to a future spec.
		 * This takes the (assumed) future attribute and translates it into
		 * browser-specific tags, so the CSS can be up to date with browser changes.
		 *
		 * @var array CSS translators: key = pattern to find, value = replacement pattern
		 */
		$cssTranslate = array(
			// border/outline-radius
			'/(?<=[^a-z0-9-])(border|outline)((-)(top|bottom)(-)(right|left))?-radius\s*:(\s*)([^ ;}][^;}]*)\s*(?=;|\})/siU'
				=> '-webkit-\1\3\4\5\6-radius:\7\8;'
					. ' -moz-\1-radius\3\4\6:\7\8;'
					. ' -khtml-\1\3\4\5\6-radius:\7\8;'
					. ' \0'
					,

			//TODO: this is not the most clever regex - need to compare it to the w3c spec for box-shadow
			// box-shadow: left bottom size (spread - Moz) color
			'/(?<=[^a-z0-9-])box-shadow\s*:(\s*)(([^\s;}]+)(\s+[^\s;}]+)(\s+[^\s;}]+)(\s+[^;}]+)|(none))\s*(?=;|\})/siU'
				=> '-webkit-box-shadow: \3\4\5\6\7;'
					. ' -moz-box-shadow: \3\4\5\6\7;'
					. ' -khtml-box-shadow: \3\4\5\6\7;'
					. ' \0'
					,

			// text-shadow - to fix the Chrome rendering bug, see http://jsbin.com/acalu4
			self::getTextShadowRegex()
				=> 'text-shadow: 0 0 0 transparent, \1'
					,

			// box-sizing
			'/(?<=[^a-z0-9-])box-sizing\s*:\s*([^\s;}]+)\s*(?=;|\})/siU'
				=> '-webkit-box-sizing: \1;'
					. ' -moz-box-sizing: \1;'
					. ' -ms-box-sizing: \1;'
					. ' \0'
					,

			// transform
			'/(?<=[^a-z0-9-])transform\s*:\s*([^;}]+)(?=;|\})/siU'
				=> '-webkit-transform: \1;'
					. ' -moz-transform: \1;'
					. ' -o-transform: \1;'
					. ' -ms-transform: \1;'
					. '\0'
					,

			// rgba borders
			'/(?<=[^a-z0-9-])border([a-z-]*)\s*:([^;}]*)rgba\(\s*(\d+\s*,\s*\d+\s*,\s*\d+)\s*,\s*([\d.]+)\s*\)([^;}]*)(?=;|\})/siU'
				=> 'border\1: \2rgb(\3)\5; border\1: \2rgba(\3, \4)\5; _border\1: \2rgb(\3)\5'
					,

			// columns
			'/(?<=[^a-z0-9-])column([a-zA-Z0-9-]+)\s*:\s*([^\s;}]+)\s*(?=;|\})/siU'
				=> '-webkit-column\1 : \2;'
					. ' -moz-column\1 : \2;'
					. '\0'
					,
		);
		$output = preg_replace(
			array_keys($cssTranslate),
			$cssTranslate,
			$output
		);

		//rgba translation - only for IE
		$output = preg_replace_callback('/
				(?<=[^a-z0-9-])
				(background\s*:\s*)
				([^;\}]*
					(
						rgba\(
							(\s*\d+%?\s*,\s*\d+%?\s*,\s*\d+%?\s*,\s*[0-9.]+\s*)
						\)
					)
				[^;}]*)
				\s*
				(?=;|})
			/siUx', array('self', '_handleRgbaReplacement'), $output
		);

		return $output;
	}

	/**
	 * Returns a regular expression that matches SINGLE SHADOW text-shadow rules.
	 * Used to fix a Chrome rendering 'feature'.
	 *
	 * @link http://code.google.com/p/chromium/issues/detail?id=23440
	 *
	 * @return string
	 */
	public static function getTextShadowRegex()
	{
		$dimension = '(-?\d+[a-z%]*)';
		$namedColor = '([a-z0-9]+)';
		$hexColor = '(#[a-f0-9]{3,6})';
		$rgbColor = '(rgb\s*\(\s*(\d+%?)\s*,\s*(\d+%?)\s*,\s*(\d+%?)\s*\))';
		$rgbaColor = '(rgba\s*\(\s*(\d+%?)\s*,\s*(\d+%?)\s*,\s*(\d+%?)\s*,\s*(\d(\.\d+)?)\s*\))';

		return "/(?<=[^a-z0-9-])text-shadow\s*:\s*("
			. "{$dimension}\s+{$dimension}\s+{$dimension}\s+"
			. "({$namedColor}|{$hexColor}|{$rgbColor}|{$rgbaColor})"
			. ")\s*(?=;|\})/siU";
	}

	/**
	 * Handles replacement of an rgba() color with a link to the rgba.php image file
	 * that will generate a 10x10 PNG to show the image.
	 *
	 * @param array $match Match from regex
	 *
	 * @return string
	 */
	protected static function _handleRgbaReplacement(array $match)
	{
		$components = preg_split('#\s*,\s*#', trim($match[4]));
		$value = $match[2];
		if (strpos($value, 'linear-gradient(') !== false)
		{
			// can't rewrite within a linear gradient
			return $match[0];
		}
		else if (strpos($value, 'url(') !== false)
		{
			// image and url, write rgb
			$value = str_replace(
				$match[3],
				"rgb($components[0], $components[1], $components[2])",
				$value
			);

			$filter = '';
		}
		else
		{
			$a = intval(255 * $components[3]);
			unset($components[3]);

			foreach ($components AS &$component)
			{
				if (substr($component, -1) == '%')
				{
					$component = intval(255 * intval($component) / 100);
				}
			}

			$value = str_replace(
				$match[3],
				"url(rgba.php?r=$components[0]&g=$components[1]&b=$components[2]&a=$a)",
				$value
			);

			$argb = sprintf('#%02X%02X%02X%02X', $a, $components[0], $components[1], $components[2]);

			$filter = "; _filter: progid:DXImageTransform.Microsoft.gradient(startColorstr=$argb,endColorstr=$argb)";
		}

		$newRule = $match[1] . $value . '; ';

		return "$newRule$match[0]$filter";
	}

	/**
	 * Creates the CSS property access debug string from a list of invalid style
	 * propery accesses.
	 *
	 * @param array $invalidPropertyAccess Format: [group] => true ..OR.. [group][value] => true
	 *
	 * @return string
	 */
	public static function createDebugErrorString(array $invalidPropertyAccess)
	{
		if (!$invalidPropertyAccess)
		{
			return '';
		}

		$invalidPropertyErrors = array();
		foreach ($invalidPropertyAccess AS $invalidGroup => $value)
		{
			if ($value === true)
			{
				$invalidPropertyErrors[] = "group: $invalidGroup";
			}
			else
			{
				foreach ($value AS $invalidProperty => $subValue)
				{
					$invalidPropertyErrors[] = "property: $invalidGroup.$invalidProperty";
				}
			}
		}

		if ($invalidPropertyErrors)
		{
			return "Invalid Property Access: " . implode(', ', $invalidPropertyErrors);
		}
		else
		{
			return '';
		}
	}

	/**
	 * Gets debug output for errors as CSS rules that will change the display
	 * of the page to make it clear errors occurred.
	 *
	 * @param array $errors Collection of errors: [template name] => error text
	 *
	 * @return string
	 */
	public static function getDebugErrorsAsCss(array $errors)
	{
		if (!$errors)
		{
			return '';
		}

		$errorOutput = array();
		foreach ($errors AS $errorFile => $errorText)
		{
			$errorOutput[] = "$errorFile: " . addslashes(str_replace(array("\n", "\r", "'", '"'), '', $errorText));
		}

		return "
			/** Error output **/
			body:before
			{
				background-color: #ccc;
				color: black;
				font-weight: bold;
				display: block;
				padding: 10px;
				margin: 10px;
				border: solid 1px #aaa;
				border-radius: 5px;
				content: 'CSS Error: " . implode('; ', $errorOutput) . "';
			}
		";
	}

	/**
	 * Outputs the specified CSS. Also outputs the necessary HTTP headers.
	 *
	 * @param string $css
	 */
	public function displayCss($css)
	{
		header('Content-type: text/css; charset=utf-8');
		header('Expires: Wed, 01 Jan 2020 00:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $this->_styleModifiedDate) . ' GMT');
		header('Cache-Control: public');

		$extraHeaders = XenForo_Application::gzipContentIfSupported($css);
		foreach ($extraHeaders AS $extraHeader)
		{
			header("$extraHeader[0]: $extraHeader[1]", $extraHeader[2]);
		}

		if (is_string($css) && $css && !ob_get_level() && XenForo_Application::get('config')->enableContentLength)
		{
			header('Content-Length: ' . strlen($css));
		}

		echo $css;
	}

	/**
	 * Static helper to execute a full request for CSS output. This will
	 * instantiate the object, pull the data from $_REQUEST, and then output
	 * the CSS.
	 */
	public static function run()
	{
		$dependencies = new XenForo_Dependencies_Public();
		$dependencies->preLoadData();

		$class = XenForo_Application::resolveDynamicClass(__CLASS__);

		$cssOutput = new $class($_REQUEST);
		if ($cssOutput->handleIfModifiedSinceHeader($_SERVER))
		{
			$cssOutput->displayCss($cssOutput->renderCss());
		}
	}
}