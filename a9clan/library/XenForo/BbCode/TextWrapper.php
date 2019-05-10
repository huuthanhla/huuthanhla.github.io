<?php

/**
 * Helper to allow deferred rendering of BB codes in text. This is useful when
 * putting BB code into a template. The text does not need to explicitly
 * be rendered in the View.
 *
 * When this object is coerced to a string, the text will be rendered.
 *
 * @package XenForo_BbCode
 */
class XenForo_BbCode_TextWrapper
{
	/**
	 * Text to render. May be already parsed array.
	 *
	 * @var string|array
	 */
	protected $_text = '';

	/**
	 * @var XenForo_BbCode_Parser
	 */
	protected $_parser = null;

	/**
	 * Extra states for the formatter.
	 *
	 * @var array
	 */
	protected $_extraStates = array();

	protected $_cache = array();

	protected static $_cacheWritten = array();

	/**
	 * Constructor.
	 *
	 * @param string|array $text May be already parsed array
	 * @param XenForo_BbCode_Parser $parser
	 * @param array $extraStates
	 * @param array $cache information about how to handle parser caching
	 */
	public function __construct($text, XenForo_BbCode_Parser $parser, array $extraStates = array(), array $cache = array())
	{
		$this->_text = $text;
		$this->_parser = $parser;
		$this->_extraStates = $extraStates;
		$this->_cache = $cache;
	}

	/**
	 * Renders the text.
	 *
	 * @return string
	 */
	public function __toString()
	{
		try
		{
			if (XenForo_Application::getOptions()->cacheBbCodeTree && !empty($this->_cache['contentType']) && !empty($this->_cache['contentId']))
			{
				$tree = null;

				if (!empty($this->_cache['cache'])
					&& !empty($this->_cache['cacheVersion'])
					&& $this->_cache['cacheVersion'] == XenForo_Application::getOptions()->bbCodeCacheVersion
				)
				{
					if (is_array($this->_cache['cache']))
					{
						$tree = $this->_cache['cache'];
					}
					else
					{
						$tree = @unserialize($this->_cache['cache']);
					}
				}

				if (!$tree)
				{
					try
					{
						// need to update
						$tree = $this->_parser->parse($this->_text);
						$this->_cache['cache'] = $tree;
						$this->_cache['cacheVersion'] = XenForo_Application::getOptions()->bbCodeCacheVersion;

						$uniqueId = $this->_cache['contentType'] . '-' . $this->_cache['contentId'];

						if (empty(self::$_cacheWritten[$uniqueId]))
						{
							XenForo_Application::getDb()->query('
								INSERT INTO xf_bb_code_parse_cache
									(content_type, content_id, parse_tree, cache_version, cache_date)
								VALUES (?, ?, ?, ?, ?)
								ON DUPLICATE KEY UPDATE parse_tree = VALUES(parse_tree),
									cache_version = VALUES(cache_version),
									cache_date = VALUES(cache_date)
							', array(
								$this->_cache['contentType'], $this->_cache['contentId'],
								serialize($tree), $this->_cache['cacheVersion'], XenForo_Application::$time
							));

							self::$_cacheWritten[$uniqueId] = true;
						}
					}
					catch (Exception $e)
					{
						return $this->_parser->render($this->_text, $this->_extraStates);
					}
				}

				return $this->_parser->render($tree, $this->_extraStates);
			}
			else
			{
				return $this->_parser->render($this->_text, $this->_extraStates);
			}
		}
		catch (Exception $e)
		{
			XenForo_Error::logException($e, false, "BB code to string error:");
			return '';
		}
	}
}