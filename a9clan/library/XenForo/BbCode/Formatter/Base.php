<?php

/**
 * Base class for defining the formatting used by the BB code parser.
 * This class implements HTML formatting.
 *
 * @package XenForo_BbCode
 */
class XenForo_BbCode_Formatter_Base
{
	/**
	 * Lookup array that translates a smilie replacement text to an untypeable
	 * sentinel value (\0-id-\0).
	 *
	 * @var array Format: [smilie replacement text] => sentinel value
	 */
	protected $_smilieTranslate = array();

	/**
	 * Essentially the reverse of the above lookup, this one translates a smilie ID
	 * to the actual "rich" replacement (for HTML, an image tag).
	 *
	 * @var array Format: [smilie id] => final replacement
	 */
	protected $_smilieReverse = array();

	/**
	 * Array to store smilie paths for [IMG] lookup
	 *
	 * @var array Format [path] => smilie ID
	 */
	protected $_smiliePaths = array();

	/**
	 * List of media sites that are known.
	 *
	 * @var array Format: [media site id] => info
	 */
	protected $_mediaSites = array();

	/**
	 * List of tags this formatter knows about.
	 *
	 * @var array|null
	 */
	protected $_tags = null;

	/**
	 * View for rendering tags that require templates.
	 *
	 * @var XenForo_View|null
	 */
	protected $_view = null;

	/**
	 * String used for outputting [IMG] tags. Will be passed the following params:
	 * 1	URL
	 * 2	Additional CSS classes
	 *
	 * @var string
	 */
	protected $_imageTemplate = '<img src="%1$s" class="bbCodeImage%2$s" alt="[&#x200B;IMG]" data-url="%3$s" />';

	/**
	 * String used for outputting smilies. Will be passed the following params:
	 * 1	Image URL
	 * 2	Smilie text
	 * 3	Smilie title
	 *
	 * @var string
	 */
	protected $_smilieTemplate = '<img src="%1$s" class="mceSmilie" alt="%2$s" title="%3$s    %2$s" />';

	/**
	 * String used for outputting smilies as sprites. Will be passed the following params:
	 * 1	Smilie ID
	 * 2	Smilie text
	 * 3	Smilie title
	 *
	 * @var string
	 */
	protected $_smilieSpriteTemplate = '<img src="styles/default/xenforo/clear.png" class="mceSmilieSprite mceSmilie%1$d" alt="%2$s" title="%3$s    %2$s" />';

	/**
	 * Cache to store processed smilie URLs, to avoid having to process them for every single smilie.
	 *
	 * @var array
	 */
	protected $_smilieUrlCache = array();

	/**
	 * List of ignored users, for quoted content mostly.
	 *
	 * @var array Key: user ID, value: user name
	 */
	protected $_ignoredUsers = array();

	/**
	 * Direction of the text on the page by default.
	 *
	 * @var string	LTR or RTL
	 */
	protected $_textDirection = 'LTR';

	/**
	 * Maximum depth of tags that will be parsed. 0 to disable.
	 *
	 * @var int
	 */
	protected $_tagDepthLimit = 20;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->_tags = $this->getTags();
		$this->preLoadData();

		if (XenForo_Visitor::hasInstance())
		{
			$visitor = XenForo_Visitor::getInstance();
			if (!empty($visitor['ignoredUsers']))
			{
				$this->_ignoredUsers = $visitor['ignoredUsers'];
			}

			$language = $visitor->getLanguage();
			$this->_textDirection = $language['text_direction'];
		}
	}

	/**
	 * Pre-loads any required data, such as templates or phrases that may be be used.
	 */
	public function preLoadData()
	{
	}

	/**
	 * Add the specified list of smilies to the list that will be processed.
	 *
	 * @param array $smilies List of smilies with data from the DB (smilie_id, smilieText [array], image_url)
	 */
	public function addSmilies(array $smilies)
	{
		foreach ($smilies AS $smilie)
		{
			foreach ($smilie['smilieText'] AS $text)
			{
				$this->_smilieTranslate[$text] = "\0" . $smilie['smilie_id'] . "\0";
			}

			if (empty($smilie['sprite_params']))
			{
				$this->_smilieReverse[$smilie['smilie_id']] = $this->_processSmilieTemplate($smilie);
			}
			else
			{
				$this->_smilieReverse[$smilie['smilie_id']] = $this->_processSmilieSpriteTemplate($smilie);
			}

			$this->_smiliePaths[$smilie['image_url']] = $smilie['smilie_id'];
		}
	}

	/**
	 * Populates the image smilie template with data
	 *
	 * @param array $smilie
	 *
	 * @return string
	 */
	protected function _processSmilieTemplate(array $smilie)
	{
		return sprintf($this->_smilieTemplate,
			$this->_prepareSmilieUrl($smilie['image_url']),
			htmlspecialchars(reset($smilie['smilieText'])),
			htmlspecialchars($smilie['title'])
		);
	}

	/**
	 * Populates the sprite smilie template with data
	 *
	 * @param array $smilie
	 *
	 * @return string
	 */
	protected function _processSmilieSpriteTemplate(array $smilie)
	{
		return sprintf($this->_smilieSpriteTemplate,
			$smilie['smilie_id'],
			htmlspecialchars(reset($smilie['smilieText'])),
			htmlspecialchars($smilie['title']),
			$this->_prepareSmilieUrl($smilie['image_url']),
			$smilie['sprite_params']['w'],
			$smilie['sprite_params']['h'],
			$smilie['sprite_params']['x'],
			$smilie['sprite_params']['y']
		);
	}

	/**
	 * Prepares a smilie URL for use in an <img /> tag. Fetches the result from cache if possible.
	 *
	 * @param string $smilieUrl
	 *
	 * @return string
	 */
	protected function _prepareSmilieUrl($smilieUrl)
	{
		if (!isset($this->_smilieUrlCache[$smilieUrl]))
		{
			$this->_smilieUrlCache[$smilieUrl] = $this->_prepareSmilieUrlInternal($smilieUrl);
		}

		return $this->_smilieUrlCache[$smilieUrl];
	}

	/**
	 * Prepares a smilie URL for use in an <img /> tag.
	 *
	 * @param string $smilieUrl
	 *
	 * @return string
	 */
	protected function _prepareSmilieUrlInternal($smilieUrl)
	{
		return htmlspecialchars($smilieUrl);
	}

	/**
	 * Adds to the list of acceptable media sites.
	 *
	 * @param array $sites
	 */
	public function addMediaSites(array $sites)
	{
		$this->_mediaSites = array_merge($this->_mediaSites, $sites);
	}

	/**
	 * Sets the view that is used to render tags requiring templates.
	 *
	 * @param XenForo_View $view
	 */
	public function setView(XenForo_View $view = null)
	{
		$this->_view = $view;
		if ($view)
		{
			$this->preLoadTemplates($view);
		}
	}

	/**
	 * @return null|XenForo_View
	 */
	public function getView()
	{
		return $this->_view;
	}

	/**
	 * Tells the view to pre-load the templates that are required.
	 *
	 * @param XenForo_View $view
	 */
	public function preLoadTemplates(XenForo_View $view)
	{
		$view->preLoadTemplate('bb_code_tag_code');
		$view->preLoadTemplate('bb_code_tag_php');
		$view->preLoadTemplate('bb_code_tag_html');
		$view->preLoadTemplate('bb_code_tag_quote');
		$view->preLoadTemplate('bb_code_tag_attach');
		$view->preLoadTemplate('bb_code_tag_spoiler');
	}

	/**
	 * Get the list of parsable tags and their parsing rules.
	 *
	 * @return array
	 */
	public function getTags()
	{
		if ($this->_tags !== null)
		{
			return $this->_tags;
		}

		return array(
			'b' => array(
				'hasOption' => false,
				'replace' => array('<b>', '</b>')
			),
			'i' => array(
				'hasOption' => false,
				'replace' => array('<i>', '</i>')
			),
			'u' => array(
				'hasOption' => false,
				'replace' => array('<span style="text-decoration: underline">', '</span>')
			),
			's' => array(
				'hasOption' => false,
				'replace' => array('<span style="text-decoration: line-through">', '</span>')
			),

			'color' => array(
				'hasOption' => true,
				'optionRegex' => '/^(rgb\(\s*\d+%?\s*,\s*\d+%?\s*,\s*\d+%?\s*\)|#[a-f0-9]{6}|#[a-f0-9]{3}|[a-z]+)$/i',
				'replace' => array('<span style="color: %s">', '</span>')
			),
			'font' => array(
				'hasOption' => true,
				'optionRegex' => '/^[a-z0-9 \-]+$/i', // regex matched to HTML->BB code regex
				'replace' => array('<span style="font-family: \'%s\'">', '</span>')
			),
			'size' => array(
				'hasOption' => true,
				'optionRegex' => '/^[0-9]+(px)?$/i',
				'callback' => array($this, 'renderTagSize'),
			),

			'left' => array(
				'hasOption' => false,
				'callback' => array($this, 'renderTagAlign'),
				'trimLeadingLinesAfter' => 1,
			),
			'center' => array(
				'hasOption' => false,
				'callback' => array($this, 'renderTagAlign'),
				'trimLeadingLinesAfter' => 1,
			),
			'right' => array(
				'hasOption' => false,
				'callback' => array($this, 'renderTagAlign'),
				'trimLeadingLinesAfter' => 1,
			),
			'indent' => array(
				'trimLeadingLinesAfter' => 1,
				'optionRegex' => '/^[0-9]+$/',
				'callback' => array($this, 'renderTagIndent')
			),

			'url' => array(
				'parseCallback' => array($this, 'parseValidatePlainIfNoOption'),
				'callback' => array($this, 'renderTagUrl'),
			),
			'email' => array(
				'parseCallback' => array($this, 'parseValidatePlainIfNoOption'),
				'callback' => array($this, 'renderTagEmail')
			),

			'img' => array(
				'hasOption' => false,
				'plainChildren' => true,
				'callback' => array($this, 'renderTagImage')
			),

			'quote' => array(
				'trimLeadingLinesAfter' => 2,
				'callback' => array($this, 'renderTagQuote')
			),

			'code' => array(
				'parseCallback' => array($this, 'parseValidateTagCode'),
				'stopSmilies' => true,
				'stopLineBreakConversion' => true,
				'trimLeadingLinesAfter' => 2,
				'callback' => array($this, 'renderTagCode')
			),
			'php' => array(
				'hasOption' => false,
				'plainChildren' => true,
				'stopSmilies' => true,
				'stopLineBreakConversion' => true,
				'trimLeadingLinesAfter' => 2,
				'callback' => array($this, 'renderTagPhp')
			),
			'html' => array(
				'hasOption' => false,
				'plainChildren' => true,
				'stopSmilies' => true,
				'stopLineBreakConversion' => true,
				'trimLeadingLinesAfter' => 2,
				'callback' => array($this, 'renderTagHtml')
			),

			'list' => array(
				'trimLeadingLinesAfter' => 1,
				'callback' => array($this, 'renderTagList')
			),

			'plain' => array(
				'hasOption' => false,
				'plainChildren' => true,
				'stopSmilies' => true,
				'replace' => array('', '')
			),

			'media' => array(
				'hasOption' => true,
				'plainChildren' => true,
				'callback' => array($this, 'renderTagMedia')
			),

			'spoiler' => array(
				'trimLeadingLinesAfter' => 1,
				'callback' => array($this, 'renderTagSpoiler')
			),

			'attach' => array(
				'plainChildren' => true,
				'callback' => array($this, 'renderTagAttach')
			),

			'user' => array(
				'hasOption' => true,
				'stopSmilies' => true,
				'callback' => array($this, 'renderTagUser')
			)
		);
	}

	public function addCustomTags(array $tags)
	{
		foreach ($tags AS $tagName => $tag)
		{
			$tagInfo = $this->_setupCustomTagInfo($tagName, $tag);
			if ($tagInfo)
			{
				$this->_tags[$tagName] = $tagInfo;
			}
		}
	}

	protected function _setupCustomTagInfo($tagName, array $tag)
	{
		$output = array();

		if ($tag['bb_code_mode'] == 'replace')
		{
			$output['replace'] = $tag['replace_html'];
		}
		else if ($tag['bb_code_mode'] == 'callback')
		{
			$output['callback'] = array($tag['callback_class'], $tag['callback_method']);
		}

		if ($tag['has_option'] == 'yes')
		{
			$output['hasOption'] = true;
		}
		else if ($tag['has_option'] == 'no')
		{
			$output['hasOption'] = false;
		}

		if (strlen($tag['option_regex']))
		{
			$output['optionRegex'] = $tag['option_regex'];
		}
		if ($tag['trim_lines_after'])
		{
			$output['trimLeadingLinesAfter'] = $tag['trim_lines_after'];
		}
		if ($tag['plain_children'])
		{
			$output['plainChildren'] = true;
		}
		if ($tag['disable_smilies'])
		{
			$output['stopSmilies'] = true;
		}
		if ($tag['disable_nl2br'])
		{
			$output['stopLineBreakConversion'] = true;
		}
		if ($tag['allow_empty'])
		{
			$output['keepEmpty'] = true;
		}

		$output['allowSignature'] = $tag['allow_signature'];

		return $output;
	}

	/**
	 * Allows the text to be filtered before parsing.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function preFilterText($text)
	{
		return $text;
	}

	/**
	 * Resets rendering state and renders a parsed BB code tree
	 * to the required output format. Note that this initializes the default states,
	 * so it is likely not the correct function to call for child tags.
	 *
	 * @param array $tree Tree from {@link parse()}.
	 * @param array $extraStates A list of extra states to push into the formatter
	 *
	 * @return string Output text
	 */
	public function renderTree(array $tree, array $extraStates = array())
	{
		$rendererStates = $extraStates + array(
			'stopSmilies' => 0,
			'stopLineBreakConversion' => 0,
			'tagDataStack' => array(),
			'noFollowDefault' => true, // add nofollow attributes
			'shortenUrl' => true, // add ... in middle of long URLs
			'lightBox' => true, // add 'LbImage' class to [IMG] output
			'imgToSmilie' => false, // attempt to convert [IMG] to smilie if URL matches
		);

		$output = $this->renderSubTree($tree, $rendererStates);
		return $this->filterFinalOutput($output);
	}

	/**
	 * Renders a parsed BB code tree to the required output format. This does
	 * not reset the rendering states, meaning it is ok for recursive calls.
	 *
	 * @param array $tree Tree from {@link parse()}
	 * @param array $rendererStates Renderer states to push down. Except in specific cases, cannot be pushed up.
	 *
	 * @return string Output text
	 */
	public function renderSubTree(array $tree, array $rendererStates)
	{
		$output = '';
		$trimLeadingLines = 0;

		foreach ($tree AS $element)
		{
			$output .= $this->renderTreeElement($element, $rendererStates, $trimLeadingLines);
		}

		return $output;
	}

	/**
	 * Renders a tree element, that be a tag (valid or not) or a string.
	 *
	 * @param array|string $element Tree element
	 * @param array $rendererStates Renderer states to push down. Except in specific cases, cannot be pushed up.
	 * @param integer $trimLeadingLines By reference. Number of leading lines to strip off next element.
	 *
	 * @return string Rendered element.
	 */
	public function renderTreeElement($element, array $rendererStates, &$trimLeadingLines)
	{
		if (is_array($element))
		{
			return $this->renderTag($element, $rendererStates, $trimLeadingLines);
		}
		else
		{
			return $this->renderString($element, $rendererStates, $trimLeadingLines);
		}
	}

	/**
	 * Renders a string tree element.
	 *
	 * @param string $string
	 * @param array $rendererStates Renderer states to push down. Except in specific cases, cannot be pushed up.
	 * @param integer $trimLeadingLines By reference. Number of leading lines to strip off next element.
	 *
	 * @return string Rendered string
	 */
	public function renderString($string, array $rendererStates, &$trimLeadingLines)
	{
		if ($trimLeadingLines)
		{
			$string = $this->trimLeadingLines($string, $trimLeadingLines);
			$trimLeadingLines = 0;
		}

		return $this->filterString($string, $rendererStates);
	}

	/**
	 * Trims the given number of leading blank lines off of the given string.
	 *
	 * @param string $string
	 * @param integer $amount
	 *
	 * @return string
	 */
	public function trimLeadingLines($string, $amount)
	{
		$amount = intval($amount);
		if ($amount <= 0)
		{
			return $string;
		}

		return preg_replace('#^([ \t]*\r?\n){1,' . $amount . '}#i', '', $string);
	}

	/**
	 * Renders a tag. This tag may be valid or invalid.
	 *
	 * @param array $element Tag element.
	 * @param array $rendererStates Renderer states to push down. Except in specific cases, cannot be pushed up.
	 * @param integer $trimLeadingLines By reference. Number of leading lines to strip from next element. May be modified by tag.
	 *
	 * @return string Rendered tag.
	 */
	public function renderTag(array $element, array $rendererStates, &$trimLeadingLines)
	{
		$trimLeadingLines = 0;

		if (isset($rendererStates['tagDataStack']))
		{
			$rendererStates['tagDataStack'][] = $element;
		}

		$tagDepthReached = (
			$this->_tagDepthLimit
			&& !empty($rendererStates['tagDataStack'])
			&& count($rendererStates['tagDataStack']) > $this->_tagDepthLimit
		);

		$tagInfo = $this->_getTagRule($element['tag']);
		if (!$tagInfo || $tagDepthReached)
		{
			$output = $this->renderInvalidTag($element, $rendererStates);
		}
		else
		{
			if (!empty($tagInfo['stopSmilies']))
			{
				$rendererStates['stopSmilies']++;
			}
			if (!empty($tagInfo['stopLineBreakConversion']))
			{
				$rendererStates['stopLineBreakConversion']++;
			}

			$output = $this->renderValidTag($tagInfo, $element, $rendererStates);

			if (!empty($tagInfo['trimLeadingLinesAfter']))
			{
				$trimLeadingLines = $tagInfo['trimLeadingLinesAfter'];
			}
		}

		return $output;
	}

	/**
	 * Gets information about the specified tag.
	 *
	 * @param string $tagName
	 *
	 * @return array|false
	 */
	protected function _getTagRule($tagName)
	{
		$tagName = strtolower($tagName);

		if (!empty($this->_tags[$tagName]) && is_array($this->_tags[$tagName]))
		{
			return $this->_tags[$tagName];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Renders an invalid tag. This tag is simply displayed in its original form.
	 *
	 * @param array $tag Tag data from tree
	 * @param array $rendererStates Renderer states to push down. Except in specific cases, cannot be pushed up.
	 *
	 * @return string Rendered version
	 */
	public function renderInvalidTag(array $tag, array $rendererStates)
	{
		return $this->renderTagUnparsed($tag, $rendererStates);
	}

	/**
	 * Renders a tag as if it's unparsed (in its original form).
	 *
	 * @param array $tag Tag data from tree
	 * @param array $rendererStates Renderer states to push down. Except in specific cases, cannot be pushed up.
	 *
	 * @return string Rendered version
	 */
	public function renderTagUnparsed(array $tag, array $rendererStates)
	{
		if (!empty($tag['original']) && is_array($tag['original']))
		{
			list($prepend, $append) = $tag['original'];
		}
		else
		{
			$prepend = '';
			$append = '';
		}

		$output = $this->filterString($prepend, $rendererStates)
			. $this->renderSubTree($tag['children'], $rendererStates)
			. $this->filterString($append, $rendererStates);

		return $output;
	}

	/**
	 * Renders a tag.
	 *
	 * @param array $tagInfo Information about how to parse the tag
	 * @param array $tag Tag data from tree
	 * @param array $rendererStates Renderer states to push down. Except in specific cases, cannot be pushed up.
	 *
	 * @return string Rendered version
	 */
	public function renderValidTag(array $tagInfo, array $tag, array $rendererStates)
	{
		if (!empty($tagInfo['callback']))
		{
			if (is_array($tagInfo['callback']) && $tagInfo['callback'][0] == '$this')
			{
				$tagInfo['callback'][0] = $this;
			}
			if (!is_callable($tagInfo['callback']))
			{
				return $this->renderInvalidTag($tag, $rendererStates);
			}
			return call_user_func($tagInfo['callback'], $tag, $rendererStates, $this);
		}
		else if (!empty($tagInfo['replace']))
		{
			$text = $this->renderSubTree($tag['children'], $rendererStates);
			$option = $this->filterString($tag['option'], array_merge($rendererStates, array(
				'stopSmilies' => true,
				'stopLineBreakConversion' => true
			)));

			if (empty($tagInfo['keepEmpty']) && trim($text) === '')
			{
				return $text;
			}

			if (is_array($tagInfo['replace']))
			{
				list($prepend, $append) = $tagInfo['replace'];
				return $this->_wrapInHtml($prepend, $append, $text, $option);
			}
			else
			{
				return strtr($tagInfo['replace'], array(
					'{text}' => $text,
					'{option}' => $option
				));
			}
		}
		else
		{
			return $this->renderInvalidTag($tag, $rendererStates);
		}
	}

	protected function _wrapInHtml($prepend, $append, $text, $option = null)
	{
		if ($option === null)
		{
			return $prepend . $text . $append;
		}
		else
		{
			return sprintf($prepend, $option) . $text . sprintf($append, $option);
		}
	}

	/**
	 * Similar to rendering the tree, but this function renders all tags to plain text
	 * (as if they weren't special tags). This can be useful for functions that can only
	 * take plain text children.
	 *
	 * Note that this output is not escaped in anyway!
	 *
	 * @param array $tree Tree or sub-tree to stringify
	 *
	 * @return string Tree as a string (like the original input)
	 */
	public function stringifyTree(array $tree)
	{
		$output = '';

		foreach ($tree AS $element)
		{
			if (is_array($element))
			{
				if (!empty($element['original']) && is_array($element['original']))
				{
					list($prepend, $append) = $element['original'];
				}
				else
				{
					$prepend = '';
					$append = '';
				}

				$output .= $prepend . $this->stringifyTree($element['children']) . $append;
			}
			else
			{
				$output .= strval($element);
			}
		}

		return $output;
	}

	/**
	 * Filter a string for the current output format. A string is simply the text
	 * between tags. This function is responsible for things like word wrap and smilies
	 * and output escaping.
	 *
	 * @param string $string
	 * @param array $rendererStates List of states the renderer may be in
	 *
	 * @return string Filtered/escaped string
	 */
	public function filterString($string, array $rendererStates)
	{
		$string = XenForo_Helper_String::censorString($string);

		if (empty($rendererStates['stopSmilies']))
		{
			$string = $this->replaceSmiliesInText($string, 'htmlspecialchars');
		}
		else
		{
			$string = htmlspecialchars($string);
		}

		if (empty($rendererStates['stopLineBreakConversion']))
		{
			$string = nl2br($string);
		}

		return $string;
	}

	/**
	 * Filters the final string output.
	 *
	 * @param string $output
	 *
	 * @return string
	 */
	public function filterFinalOutput($output)
	{
		return trim($output);
	}

	/**
	 * Gets a valid, full URL if possible. False is returned if not possible.
	 *
	 * @param string $url URL to validate
	 *
	 * @return string|false
	 */
	protected function _getValidUrl($url)
	{
		$url = trim($url);

		if (!$url)
		{
			return false;
		}

		switch ($url[0])
		{
			case '#':
			case '/':
			case ' ':
			case "\r":
			case "\n":
				return false;
		}

		if (preg_match('/\r?\n/', $url))
		{
			return false;
		}

		if (preg_match('#^https?://#i', $url))
		{
			return $url;
		}

		return 'http://' . $url;
	}

	/**
	 * Renders an indent tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagIndent(array $tag, array $rendererStates)
	{
		$text = $this->renderSubTree($tag['children'], $rendererStates);
		if (trim($text) === '')
		{
			$text = '<br />';
		}

		if (isset($tag['option']))
		{
			$amount = intval($tag['option']);
			if ($amount > 10)
			{
				$amount = 10;
			}
		}
		else
		{
			$amount = 1;
		}

		$invisibleSpace = $this->_endsInBlockTag($text) ? '' : '&#8203;';

		if ($amount < 1)
		{
			return $this->_wrapInHtml('<div>', $invisibleSpace . '</div>', $text);
		}
		else
		{
			$paddingSide = ($this->_textDirection == 'RTL' ? 'padding-right' : 'padding-left');
			return $this->_wrapInHtml('<div style="' . $paddingSide . ': ' . (30 * $amount) . 'px">', $invisibleSpace . '</div>', $text);
		}
	}

	/**
	 * Renders an alignment (left, center, right) tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagAlign(array $tag, array $rendererStates)
	{
		$text = $this->renderSubTree($tag['children'], $rendererStates);

		$invisibleSpace = $this->_endsInBlockTag($text) ? '' : '&#8203;';

		switch (strtolower($tag['tag']))
		{
			case 'left':
			case 'center':
			case 'right':
				return $this->_wrapInHtml('<div style="text-align: ' . $tag['tag'] . '">', $invisibleSpace. '</div>', $text);

			default:
				return $this->_wrapInHtml('<div>', $invisibleSpace. '</div>', $text);
		}
	}

	protected function _endsInBlockTag($text)
	{
		return preg_match('#</(p|div)>$#i', substr(rtrim($text), -6));
	}

	/**
	 * Renders a size tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagSize(array $tag, array $rendererStates)
	{
		$text = $this->renderSubTree($tag['children'], $rendererStates);
		if (trim($text) === '')
		{
			return $text;
		}

		$size = $this->getTextSize($tag['option']);
		if ($size)
		{
			return $this->_wrapInHtml('<span style="font-size: ' . htmlspecialchars($size) . '">', '</span>', $text);
		}
		else
		{
			return $text;
		}
	}

	/**
	 * Gets the effective text size to use.
	 *
	 * @param string $inputSize
	 *
	 * @return string|false
	 */
	public function getTextSize($inputSize)
	{
		if (strval(intval($inputSize)) == strval($inputSize))
		{
			// int only, translate size
			if ($inputSize <= 0)
			{
				$size = false;
			}
			else
			{
				switch ($inputSize)
				{
					case 1: $size = '9px'; break;
					case 2: $size = '10px'; break;
					case 3: $size = '12px'; break;
					case 4: $size = '15px'; break;
					case 5: $size = '18px'; break;
					case 6: $size = '22px'; break;

					case 7:
					default:
						$size = '26px';
				}
			}
		}
		else
		{
			// int and unit
			if (preg_match('/^([0-9]+)px$/i', $inputSize, $match))
			{
				if ($match[1] < 8)
				{
					$size = '8px';
				}
				else if ($match[1] > 36)
				{
					$size = '36px';
				}
				else
				{
					$size = $inputSize;
				}
			}
			else
			{
				$size = false;
			}
		}

		return $size;
	}

	/**
	 * Disables parsing of child tags if this tag does not have an option.
	 * Useful for tags like url/email, where the address may be in the body
	 * of the tag.
	 *
	 * @param array $tagInfo Info about the tag we're parsing.
	 * @param string|null $tagOption Any option passed into the tag
	 *
	 * @return array|boolean True if tag is ok as is, array to change states, false to reject tag
	 */
	public function parseValidatePlainIfNoOption(array $tagInfo, $tagOption)
	{
		if (empty($tagOption))
		{
			return array('plainChildren' => true);
		}
		else
		{
			return true;
		}
	}

	/**
	 * Renders a URL tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagUrl(array $tag, array $rendererStates)
	{
		if (!empty($tag['option']))
		{
			$url = $tag['option'];
			$text = $this->renderSubTree($tag['children'], $rendererStates);
		}
		else
		{
			$url = $this->stringifyTree($tag['children']);
			$text = urldecode($url);
			if (!preg_match('/./u', $text))
			{
				$text = $url;
			}
			$text = XenForo_Helper_String::censorString($text);

			if (!empty($rendererStates['shortenUrl']))
			{
				$length = utf8_strlen($text);
				if ($length > 100)
				{
					$text = utf8_substr_replace($text, '...', 35, $length - 35 - 45);
				}
			}

			$text = htmlspecialchars($text);
		}

		$url = $this->_getValidUrl($url);
		if (!$url)
		{
			return $text;
		}
		else
		{
			list($class, $target, $type) = XenForo_Helper_String::getLinkClassTarget($url);
			if ($type == 'internal')
			{
				$noFollow = '';
			}
			else
			{
				$noFollow = (empty($rendererStates['noFollowDefault']) ? '' : ' rel="nofollow"');
			}

			$href = XenForo_Helper_String::censorString($url);
			$proxyHref = $this->_handleLinkProxyOption($href, $type);

			$proxyAttr = '';
			if ($proxyHref)
			{
				$proxyAttr = ' data-proxy-href="' . htmlspecialchars($proxyHref) . '"';
				$class .= ' ProxyLink';
			}

			$class = $class ? " class=\"$class\"" : '';
			$target = $target ? " target=\"$target\"" : '';

			return $this->_wrapInHtml(
				'<a href="' . htmlspecialchars($href) . '"' . $target . $class . $proxyAttr . $noFollow . '>',
				'</a>',
				$text
			);
		}
	}

	/**
	 * Renders an email tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagEmail(array $tag, array $rendererStates)
	{
		if (!empty($tag['option']))
		{
			$email = $tag['option'];
			$text = $this->renderSubTree($tag['children'], $rendererStates);
		}
		else
		{
			$email = $this->stringifyTree($tag['children'], $rendererStates);
			$text = $this->filterString($email, $rendererStates);
		}

		if (strpos($email, '@') === false)
		{
			// invalid URL, ignore
			return $text;
		}

		$email = XenForo_Helper_String::censorString($email);

		return $this->_wrapInHtml('<a href="mailto:' . htmlspecialchars($email) . '">', '</a>', $text);
	}

	/**
	 * Renders a img tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagImage(array $tag, array $rendererStates)
	{
		$url = $this->stringifyTree($tag['children']);

		$validUrl = $this->_getValidUrl($url);
		if (!$validUrl)
		{
			return $this->filterString($url, $rendererStates);
		}

		$censored = XenForo_Helper_String::censorString($validUrl);
		if ($censored != $validUrl)
		{
			return $this->filterString($url, $rendererStates);
		}

		// attempts to convert smilies posted as [IMG] tags back into smilies
		if ($rendererStates['imgToSmilie'])
		{
			foreach ($this->_smiliePaths AS $smiliePath => $smilieId)
			{
				if (strpos($url, $smiliePath) !== false && substr($url, strlen($smiliePath) * -1) == $smiliePath)
				{
					return $this->_smilieReverse[$smilieId];
				}
			}
		}

		$imageUrl = $this->_handleImageProxyOption($validUrl);

		return sprintf($this->_imageTemplate,
			htmlspecialchars($imageUrl),
			$rendererStates['lightBox'] ? ' LbImage' : '',
			htmlspecialchars($validUrl)
		);
	}

	/**
	 * Pass an image URL to the image proxy system if appropriate
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	protected function _handleImageProxyOption($url)
	{
		list($class, $target, $type, $schemeMatch) = XenForo_Helper_String::getLinkClassTarget($url);

		if ($type == 'external' || !$schemeMatch)
		{
			$options = XenForo_Application::getOptions();
			if (!empty($options->imageLinkProxy['images']))
			{
				$url = $this->_generateProxyLink('image', $url);
			}
		}

		return $url;
	}

	/**
	 * Pass a link URL to the proxy / redirect system if appropriate
	 *
	 * @param string $url
	 * @param string $linkType
	 *
	 * @return string|false
	 */
	protected function _handleLinkProxyOption($url, $linkType)
	{
		if ($linkType == 'external')
		{
			$options = XenForo_Application::getOptions();

			if (!empty($options->imageLinkProxy['links']))
			{
				return $this->_generateProxyLink('link', $url);
			}
		}

		return false;
	}

	protected function _generateProxyLink($proxyType, $url)
	{
		$hash = hash_hmac('md5', $url,
			XenForo_Application::getConfig()->globalSalt . XenForo_Application::getOptions()->imageLinkProxyKey
		);
		return 'proxy.php?' . $proxyType . '=' . urlencode($url) . '&hash=' . $hash;
	}

	/**
	 * Modifies the parsing options for a code tag. Users must explicitly
	 * opt in to allow BB codes to be used within.
	 *
	 * @param array $tagInfo Info about the tag we're parsing.
	 * @param string|null $tagOption Any option passed into the tag
	 *
	 * @return array|boolean True if tag is ok as is, array to change states, false to reject tag
	 */
	public function parseValidateTagCode(array $tagInfo, $tagOption)
	{
		if (strtolower($tagOption) == 'rich')
		{
			return true;
		}
		else
		{
			return array('plainChildren' => true);
		}
	}

	/**
	 * Renders a code tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagCode(array $tag, array $rendererStates)
	{
		switch (strtolower(strval($tag['option'])))
		{
			case 'php':
				return $this->renderTagPhp($tag, $rendererStates);

			case 'html':
				return $this->renderTagHtml($tag, $rendererStates);
		}

		$content = $this->renderSubTree($tag['children'], $rendererStates);

		if ($this->_view)
		{
			$template = $this->_view->createTemplateObject('bb_code_tag_code', array(
				'content' => $content
			));
			return $template->render();
		}
		else
		{
			return $this->_wrapInHtml('<pre style="margin: 1em auto" title="Code">', '</pre>', $content);
		}
	}

	/**
	 * Renders an HTML tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagHtml(array $tag, array $rendererStates)
	{
		$content = $this->stringifyTree($tag['children']);
		$content = $this->filterString($content, $rendererStates);

		if ($this->_view)
		{
			$template = $this->_view->createTemplateObject('bb_code_tag_html', array(
				'content' => $content
			));
			return $template->render();
		}
		else
		{
			return $this->_wrapInHtml('<pre style="margin: 1em auto" title="HTML">', '</pre>', $content);
		}
	}

	/**
	 * Renders a PHP tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagPhp(array $tag, array $rendererStates)
	{
		$content = $this->stringifyTree($tag['children']);
		$content = XenForo_Helper_String::censorString($content);
		$content = preg_replace('/^[ \t]*\r?\n/', '', $content);

		if (strpos($content, '<?') == false)
		{
			$tagAdded = true;
			$content = "<?php\n$content";
		}
		else
		{
			$tagAdded = false;
		}

		$content = highlight_string($content, true);

		if ($tagAdded)
		{
			$content = preg_replace(
				'#&lt;\?php<br\s*/?>#',
				'',
				$content,
				1
			);
		}

		if ($this->_view)
		{
			$template = $this->_view->createTemplateObject('bb_code_tag_php', array(
				'content' => $content
			));
			return $template->render();
		}
		else
		{
			return $this->_wrapInHtml('<div style="margin: 1em auto" title="PHP">', '</div>', $content);
		}
	}

	/**
	 * Renders a quote tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagQuote(array $tag, array $rendererStates)
	{
		$keys = array_keys($tag['children']);
		if (!$keys)
		{
			return '';
		}

		$first = reset($keys);
		$last = end($keys);

		if (is_string($tag['children'][$first]))
		{
			$tag['children'][$first] = ltrim($tag['children'][$first]);
		}
		if (is_string($tag['children'][$last]))
		{
			$tag['children'][$last] = rtrim($tag['children'][$last]);
		}

		$content = $this->renderSubTree($tag['children'], $rendererStates);
		if ($content === '')
		{
			return '';
		}

		$source = false;
		$attributes = array();

		/*
		 * NOTE: changes to this code must also be reflected in
		 * XenForo_Model_Post::alertQuotedMembers()
		 */
		if ($tag['option'])
		{
			$parts = explode(',', $tag['option']);
			$name = $this->filterString(array_shift($parts),
				array_merge($rendererStates, array(
					'stopSmilies' => true,
					'stopLineBreakConversion' => true
				))
			);

			foreach ($parts AS $part)
			{
				$partAttributes = explode(':', $part, 2);
				if (isset($partAttributes[1]))
				{
					$attrName = trim($partAttributes[0]);
					$attrValue = trim($partAttributes[1]);
					if ($attrName !== '' && $attrValue !== '')
					{
						$attributes[$attrName] = $attrValue;
					}
				}
			}

			list($firstName, $firstValue) = each($attributes);
			if ($firstName && $firstName != 'member')
			{
				$source = array('type' => $firstName, 'id' => intval($firstValue));
			}
		}
		else
		{
			$name = false;
		}

		if ($this->_view)
		{
			$template = $this->_view->createTemplateObject('bb_code_tag_quote', array(
				'content' => $content,
				'nameHtml' => $name,
				'source' => $source,
				'attributes' => $attributes,
				'ignored' => (isset($attributes['member']) && isset($this->_ignoredUsers[intval($attributes['member'])]))
			));
			return $template->render();
		}
		else
		{
			return $this->_renderTagQuoteFallback($name, $content);
		}
	}

	/**
	 * Returns HTML output for a quote tag when the view is not available
	 *
	 * @param string $name Name of quoted user
	 * @param string $content Quoted text
	 *
	 * @return string
	 */
	protected function _renderTagQuoteFallback($name, $content)
	{
		if ($name)
		{
			$name = '<div>' . $name . '</div>';
		}

		return $this->_wrapInHtml('<blockquote>', '</blockquote>', $name . $content);
	}

	/**
	 * Renders a list tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagList(array $tag, array $rendererStates)
	{
		$listType = ($tag['option'] == '1' ? 'ol' : 'ul');

		$elements = array();
		$lastElement = '';
		$trimLeadingLines = 0;

		foreach ($tag['children'] AS $child)
		{
			if (is_array($child))
			{
				$childText = $this->renderTag($child, $rendererStates, $trimLeadingLines);
				if (preg_match('#^<(ul|ol)#', $childText))
				{
					$lastElement = rtrim($lastElement);
					if (substr($lastElement, -6) == '<br />')
					{
						$lastElement = substr($lastElement, 0, -6);
					}
				}
				$lastElement .= $childText;
			}
			else
			{
				if (strpos($child, '[*]') !== false)
				{
					$parts = explode('[*]', $child);

					$beforeFirst = array_shift($parts);
					if ($lastElement !== '' || trim($beforeFirst) !== '')
					{
						$lastElement .= $this->renderString($beforeFirst, $rendererStates, $trimLeadingLines);
					}

					foreach ($parts AS $part)
					{
						$this->_appendListElement($elements, $lastElement);
						$lastElement = $this->renderString($part, $rendererStates, $trimLeadingLines);
					}
				}
				else
				{
					$lastElement .= $this->renderString($child, $rendererStates, $trimLeadingLines);
				}
			}
		}

		$this->_appendListElement($elements, $lastElement);

		if (!$elements)
		{
			return '';
		}

		return $this->_renderListOutput($listType, $elements);
	}

	/**
	 * Given already parsed list elements, gets the output for the list.
	 *
	 * @param string $listType Type of list (ol or ul)
	 * @param array $elements List of elements in the list. These are already rendered.
	 *
	 * @return string
	 */
	protected function _renderListOutput($listType, array $elements)
	{
		return "<$listType>\n<li>" . implode("</li>\n<li>", $elements) . "</li>\n</$listType>";
	}

	/**
	 * Appends a list element if it is not empty.
	 *
	 * @param array $elements By reference. List of existing elements.
	 * @param string $appendString String to append (if not empty)
	 */
	protected function _appendListElement(array &$elements, $appendString)
	{
		if ($appendString !== '')
		{
			$appendString = rtrim($appendString);
			if (substr($appendString, -6) == '<br />')
			{
				$appendString = substr($appendString, 0, -6);
			}

			$elements[] = $appendString;
		}
	}

	public function renderTagAttach(array $tag, array $rendererStates)
	{
		$id = intval($this->stringifyTree($tag['children']));
		if (!$id)
		{
			return '';
		}

		if (!$this->_view)
		{
			$phrase = new XenForo_Phrase('view_attachment_x', array('name' => $id));
			return '<a href="' . XenForo_Link::buildPublicLink('full:attachments', array('attachment_id' => $id)) . '">' . $phrase . '</a>';
		}

		if (empty($rendererStates['attachments'][$id]))
		{
			$attachment = array('attachment_id' => $id);
			$validAttachment = false;
			$canView = false;
		}
		else
		{
			$attachment = $rendererStates['attachments'][$id];
			$validAttachment = true;
			$canView = empty($rendererStates['viewAttachments']) ? false : true;
		}

		$template = $this->_view->createTemplateObject('bb_code_tag_attach', array(
			'attachment' => $attachment,
			'validAttachment' => $validAttachment,
			'canView' => $canView,
			'full' => (strtolower($tag['option']) == 'full')
		));
		return $template->render();
	}

	public function renderTagUser(array $tag, array $rendererStates)
	{
		$content = $this->renderSubTree($tag['children'], $rendererStates);
		if ($content === '')
		{
			return '';
		}

		$userId = intval($tag['option']);
		if (!$userId)
		{
			return $content;
		}

		$link = XenForo_Link::buildPublicLink('full:members', array('user_id' => $userId));
		$username = $this->stringifyTree($tag['children']);

		return $this->_wrapInHtml('<a href="' . htmlspecialchars($link) . '" class="username" data-user="' . $userId . ', ' . htmlspecialchars($username) . '">', '</a>', $content);
	}

	/**
	 * Renders a media tag. Media tags embed rich media (usually videos). To embed a video,
	 * the source must be known.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagMedia(array $tag, array $rendererStates)
	{
		$mediaKey = trim($this->stringifyTree($tag['children']));
		if (preg_match('#[&?"\'<>\r\n]#', $mediaKey) || strpos($mediaKey, '..') !== false)
		{
			return '';
		}

		$censored = XenForo_Helper_String::censorString($mediaKey);
		if ($censored != $mediaKey)
		{
			return '';
		}

		$mediaSiteId = strtolower($tag['option']);
		if ($mediaSiteId == 'youtube')
		{
			// youtube iframe embed bug workaround
			$mediaKey = str_replace('/', '', $mediaKey);
		}
		if (isset($this->_mediaSites[$mediaSiteId]))
		{
			$embedHtml = $this->_getMediaSiteHtmlFromCallback($mediaKey, $this->_mediaSites[$mediaSiteId], $mediaSiteId);

			if (!$embedHtml)
			{
				$embedHtml = strtr($this->_mediaSites[$mediaSiteId]['embed_html'], array(
					'{$id}' => urlencode($mediaKey),
					'{$id:digits}' => intval($mediaKey)
				));
			}

			return $embedHtml;
		}
		else
		{
			return '';
		}
	}

	/**
	 * Renders a spoiler tag.
	 *
	 * @param array $tag Information about the tag reference; keys: tag, option, children
	 * @param array $rendererStates Renderer states to push down
	 *
	 * @return string Rendered tag
	 */
	public function renderTagSpoiler(array $tag, array $rendererStates)
	{
		$keys = array_keys($tag['children']);
		if (!$keys)
		{
			return '';
		}

		$first = reset($keys);
		$last = end($keys);

		if (is_string($tag['children'][$first]))
		{
			$tag['children'][$first] = ltrim($tag['children'][$first]);
		}
		if (is_string($tag['children'][$last]))
		{
			$tag['children'][$last] = rtrim($tag['children'][$last]);
		}

		$content = $this->renderSubTree($tag['children'], $rendererStates);
		if ($content === '')
		{
			return '';
		}

		if ($tag['option'])
		{
			$title = $this->filterString($tag['option'],
				array_merge($rendererStates, array(
					'stopSmilies' => true,
					'stopLineBreakConversion' => true
				))
			);
		}
		else
		{
			$title = false;
		}

		if ($this->_view)
		{
			$template = $this->_view->createTemplateObject('bb_code_tag_spoiler', array(
				'content' => $content,
				'titleHtml' => $title
			));
			return $template->render();
		}
		else
		{
			return $this->_renderTagSpoilerFallback($title, $content, $rendererStates);
		}
	}

	/**
	 * Returns HTML output for a spoiler tag when the view is not available
	 *
	 * @param string $title Title of spoiler
	 * @param string $content Spoiler text
	 * @param array $rendererStates
	 *
	 * @return string
	 */
	protected function _renderTagSpoilerFallback($title, $content, array $rendererStates)
	{
		if (!empty($rendererStates['spoilerTextWithFallback']))
		{
			return '<div>' . $content . '</div>';
		}
		else
		{
			$spoilerText = new XenForo_Phrase('spoiler');
			return '<div><b>' . ($title ? ($spoilerText . ': ' . $title) : $spoilerText) . '</b></div>';
		}
	}

	/**
	 * Attempts to fetch media tag embed HTML using the callback method defined for a media site, if one is specified.
	 *
	 * @param string $mediaKey
	 * @param array $site Information about the site to render this media
	 * @param string $siteId
	 *
	 * @return string|boolean Returns false if callback is invalid
	 */
	protected function _getMediaSiteHtmlFromCallback($mediaKey, array $site, $siteId)
	{
		if (!empty($site['callback']) && is_array($site['callback']))
		{
			$class = $site['callback'][0];
			$method = $site['callback'][1];

			if (XenForo_Application::autoload($class) && method_exists($class, $method))
			{
				return call_user_func_array($site['callback'], array($mediaKey, $site, $siteId));
			}
		}

		return false;
	}

	/**
	 * Replaces smilie strings in text with the appropriate "rich" markup.
	 * This method also escapes the output before the smilies are ultimately replaced.
	 * This is necessary to prevent the rich output from being escaped.
	 *
	 * @param string $text Text to replace in
	 * @param mixed $escapeCallback Callback for escaping. If empty, no escaping is done.
	 *
	 * @return string
	 */
	public function replaceSmiliesInText($text, $escapeCallback = '')
	{
		if ($this->_smilieTranslate)
		{
			$text = strtr($text, $this->_smilieTranslate);
		}

		if ($escapeCallback)
		{
			if ($escapeCallback == 'htmlspecialchars')
			{
				$text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
			}
			else
			{
				$text = call_user_func($escapeCallback, $text);
			}
		}

		if ($this->_smilieTranslate)
		{
			$split = preg_split("#\\0(\d+)\\0#", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
			$text = '';
			foreach ($split AS $key => $value)
			{
				// odd keys contain the delimiter we want
				if ($key % 2 == 0)
				{
					$text .= $value;
				}
				else if (isset($this->_smilieReverse[$value]))
				{
					$text .= $this->_smilieReverse[$value];
				}
			}
		}

		return $text;
	}

	/**
	 * Create the specified BB code formatter.
	 *
	 * @param string $class Name of the class. If empty, uses this class; if doesn't contain an underscore, assumes a partial name
	 * @param array|boolean Set of options to configure formatter; defaults to pulling as necessary; if false, doesn't look in registry etc
	 *
	 * @return XenForo_BbCode_Formatter_Base
	 */
	public static function create($class = '', $options = array())
	{
		if (!$class)
		{
			$class = __CLASS__;
		}
		else if (strpos($class, '_') === false)
		{
			$class = 'XenForo_BbCode_Formatter_' . $class;
		}

		$class = XenForo_Application::resolveDynamicClass($class, 'bb_code');

		/** @var XenForo_BbCode_Formatter_Base $formatter */
		$formatter = new $class();

		if (is_array($options))
		{
			$baseOptions = array(
				'smilies' => null,
				'bbCode' => null,
				'view' => null
			);
			$options = array_merge($baseOptions, $options);
		}
		else
		{
			$options = array(
				'smilies' => array(),
				// omit bbCode as we basically always want this for custom tags
				'bbCode' => null,
				'view' => false
			);
		}

		if (!is_array($options['smilies']))
		{
			if (XenForo_Application::isRegistered('smilies'))
			{
				$options['smilies'] = XenForo_Application::get('smilies');
			}
			else
			{
				$options['smilies'] = XenForo_Model::create('XenForo_Model_Smilie')->getAllSmiliesForCache();
				XenForo_Application::set('smilies', $options['smilies']);
			}
		}

		if ($options['smilies'])
		{
			$formatter->addSmilies($options['smilies']);
		}

		if (!is_array($options['bbCode']))
		{
			if (XenForo_Application::isRegistered('bbCode'))
			{
				$options['bbCode'] = XenForo_Application::get('bbCode');
			}
			else
			{
				$options['bbCode'] = XenForo_Model::create('XenForo_Model_BbCode')->getBbCodeCache();
				XenForo_Application::set('bbCode', $options['bbCode']);
			}
		}

		if (!empty($options['bbCode']['mediaSites']))
		{
			$formatter->addMediaSites($options['bbCode']['mediaSites']);
		}

		if (!empty($options['bbCode']['bbCodes']))
		{
			$formatter->addCustomTags($options['bbCode']['bbCodes']);
		}

		if ($options['view'])
		{
			$formatter->setView($options['view']);
		}

		return $formatter;
	}
}