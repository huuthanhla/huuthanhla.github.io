<?php

class XenForo_Html_Renderer_BbCode
{
	protected $_options = array(
		'baseUrl' => ''
	);

	const BR_SUBSTITUTE = "\x1A";

	/**
	 * A map of tag handlers. Tag names are in lower case. Possible keys:
	 * 		* wrap - wraps tag content in some text; used %s for text (eg, [b]%s[/b])
	 * 		* filterCallback - callback to process tag; given tag content (string) and tag (XenForo_Html_Tag)
	 *
	 * @var array Key is tag name in lower case
	 */
	protected $_handlers = array(
		'b'          => array('wrap' => '[B]%s[/B]'),
		'strong'     => array('wrap' => '[B]%s[/B]'),

		'i'          => array('wrap' => '[I]%s[/I]'),
		'em'         => array('wrap' => '[I]%s[/I]'),

		'u'          => array('wrap' => '[U]%s[/U]'),
		's'          => array('wrap' => '[S]%s[/S]'),
		'strike'     => array('wrap' => '[S]%s[/S]'),

		'font'       => array('filterCallback' => array('$this', 'handleTagFont')),
		'a'          => array('filterCallback' => array('$this', 'handleTagA')),
		'img'        => array('filterCallback' => array('$this', 'handleTagImg')),

		'ul'         => array('wrap' => "[LIST]\n%s\n[/LIST]", 'skipCss' => true),
		'ol'         => array('wrap' => "[LIST=1]\n%s\n[/LIST]", 'skipCss' => true),
		'li'         => array('filterCallback' => array('$this', 'handleTagLi')),

		'blockquote' => array('wrap' => '[INDENT]%s[/INDENT]'),

		'h1'         => array('filterCallback' => array('$this', 'handleTagH')),
		'h2'         => array('filterCallback' => array('$this', 'handleTagH')),
		'h3'         => array('filterCallback' => array('$this', 'handleTagH')),
		'h4'         => array('filterCallback' => array('$this', 'handleTagH')),
		'h5'         => array('filterCallback' => array('$this', 'handleTagH')),
		'h6'         => array('filterCallback' => array('$this', 'handleTagH')),
	);

	/**
	 * Handlers for specific CSS rules. Value is a callback function name.
	 *
	 * @var array Key is the CSS rule name
	 */
	protected $_cssHandlers = array(
		'color'           => array('$this', 'handleCssColor'),
		'float'           => array('$this', 'handleCssFloat'),
		'font-family'     => array('$this', 'handleCssFontFamily'),
		'font-size'       => array('$this', 'handleCssFontSize'),
		'font-style'      => array('$this', 'handleCssFontStyle'),
		'font-weight'     => array('$this', 'handleCssFontWeight'),
		'padding-left'    => array('$this', 'handleCssPaddingLeft'), // editor implements LTR indent this way
		'padding-right'   => array('$this', 'handleCssPaddingRight'), // editor implements RTL indent this way
		'text-align'      => array('$this', 'handleCssTextAlign'),
		'text-decoration' => array('$this', 'handleCssTextDecoration'),
	);

	public static function renderFromHtml($html, array $options = array())
	{
		//echo $html; exit;
		//echo '<pre>' . htmlspecialchars($html) . '</pre>'; exit;

		$class = XenForo_Application::resolveDynamicClass(__CLASS__);
		/** @var $renderer XenForo_Html_Renderer_BbCode */
		$renderer = new $class($options);

		$html = $renderer->preFilter($html);

		$parser = new XenForo_Html_Parser($html);
		$parsed = $parser->parse();

		//$parser->printTags($parsed);

		$rendered = $renderer->render($parsed);
		//echo '<pre>' . htmlspecialchars($rendered) . '</pre>'; exit;

		return $rendered;
	}

	/**
	 * Constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = array())
	{
		$requestPaths = XenForo_Application::get('requestPaths');
		$this->_options['baseUrl'] = $requestPaths['fullBasePath'];

		$this->_options = array_merge($this->_options, $options);
	}

	public function preFilter($html)
	{
		// IE bug (#25781)
		$html = preg_replace(
			'#(<a[^>]+href="([^"]+)"[^>]*>)\\2(\[/?[a-z0-9_-]+)(</a>)\]#siU',
			'$1$2$4$3]',
			$html
		);

		// issue where URLs have been auto linked inside manually entered BB code options
		$html = preg_replace(
			'#(\[[a-z0-9_-]+=("|\'|))<a[^>]+href="([^"]+)"[^>]*>\\3</a>#siU',
			'$1$3',
			$html
		);

		$html = preg_replace_callback(
			'#(\[(code|php|html|img|plain)\])(.*)(\[/\\2\])#siU',
			array($this, '_stripStylingHtmlMatch'),
			$html
		);

		return $html;
	}

	protected function _stripStylingHtmlMatch(array $match)
	{
		$content = $match[3];
		$tags = 'b|i|u|s|strong|em|strike|a|span|font';

		$content = preg_replace('#<(' . $tags . ')(\s[^>]*)?>#i', '', $content);
		$content = preg_replace('#</(' . $tags . ')>#i', '', $content);

		return $match[1] . $content . $match[4];
	}

	/**
	 * Renders the specified tag and all children.
	 *
	 * @param XenForo_Html_Tag $tag
	 *
	 * @return string
	 */
	public function render(XenForo_Html_Tag $tag)
	{
		$output = $this->renderTag($tag);
		return $this->_postRender($output->text());
	}

	protected function _postRender($text)
	{
		$text = preg_replace('#\[img\]\[url\]([^\[]+)\[/url\]\[/img\]#i', '[IMG]$1[/IMG]', $text);

		do
		{
			$newText = preg_replace('#\[/(b|i|u|s|left|center|right|indent)\]([\r\n]*)\[\\1\]#i', '\\2', $text);
			if ($newText === null || $newText == $text)
			{
				break;
			}
			$text = $newText;
		}
		while (true);

		do
		{
			$newText = preg_replace('#(\[(font|color|size|url|email)=[^\]]*\])((?:(?>[^\[]+?)|\[(?!\\2))*)\[/\\2\]([\r\n]*)\\1#siU', '\\1\\3\\4', $text);
			if ($newText === null || $newText == $text)
			{
				break;
			}
			$text = $newText;
		}
		while (true);

		$text = XenForo_Input::cleanString($text);

		return trim($text);
	}

	public function renderTag(XenForo_Html_Tag $tag, array $state = array())
	{
		if ($tag->tagName() == 'br')
		{
			$output = new XenForo_Html_Renderer_BbCode_Element('block', self::BR_SUBSTITUTE);
			$output->incrementTrailingLines();
			return $output;
		}

		$state = array_merge($state, $this->_setTagStates($tag, $state));

		if (!empty($state['hidden']))
		{
			// ignore all under this
			return new XenForo_Html_Renderer_BbCode_Element('text', '');
		}

		$isPreFormatted = !empty($state['preFormatted']);

		$children = $this->renderChildren($tag, $state);

		if ($tag->isBlock() && !$isPreFormatted)
		{
			// ignore leading/trailing whitespace-only nodes on blocks
			$firstChild = reset($children);
			if ($firstChild && $firstChild->isWhiteSpace())
			{
				array_shift($children);
			}

			$lastChild = end($children);
			if ($lastChild && $lastChild->isWhiteSpace())
			{
				array_pop($children);
			}
		}

		$children = array_values($children); // need this to be contiguous
		$lastChild = count($children) - 1;
		$outputText = '';

		$output = new XenForo_Html_Renderer_BbCode_Element($tag->isBlock() ? 'block' : 'inline');
		$previousTrailing = 0;
		$initialLeading = 0;

		for ($i = 0; $i <= $lastChild; $i++)
		{
			$child = $children[$i]; /* @var $child XenForo_Html_Renderer_BbCode_Element */
			$previous = ($i > 0 ? $children[$i - 1] : false); /* @var $previous XenForo_Html_Renderer_BbCode_Element */
			$next = ($i < $lastChild ? $children[$i + 1] : false); /* @var $next XenForo_Html_Renderer_BbCode_Element */

			if ($child->isBr())
			{
				$previousTrailing++;
				continue;
			}

			if (!$isPreFormatted && $child->isWhiteSpace()
				&& $previous && $previous->isBlock()
				&& $next && $next->isBlock())
			{
				// whitespace node between 2 blocks - skip it
				continue;
			}

			$text = $child->text();
			if (!$isPreFormatted && $previousTrailing && $child->isText())
			{
				// follows a block
				$text = ltrim($text);
			}

			if ($outputText === '')
			{
				// no output so far, so push this up
				$initialLeading += $child->leadingLines();
			}
			else if ($child->leadingLines())
			{
				// this behaves like a block tag in terms of line spacing
				if ($previousTrailing && $child->leadingLines())
				{
					$previousTrailing -= 1; // a new block tag "merges" with the last line the previous
				}
				$previousTrailing += $child->leadingLines();

				if (!$isPreFormatted)
				{
					$outputText = strrev(preg_replace('/^( )+/', '', strrev($outputText)));
				}
			}

			if ($previousTrailing && $text !== '')
			{
				// covers previous trailing and my leading
				$outputText .= str_repeat("\n", $previousTrailing);
				$previousTrailing = 0;
			}

			$outputText .= $text;
			$previousTrailing += $child->trailingLines();
		}

		if ($output->isBlock() && !$isPreFormatted)
		{
			$outputText = trim($outputText);
		}

		if ($outputText !== '' || $tag->isVoid())
		{
			// only prepare this tag if we actually have text or it's never going to have text
			$tagName = $tag->tagName();
			$handler = (isset($this->_handlers[$tagName]) ? $this->_handlers[$tagName] : false);

			$preCssOutput = $outputText;
			if ($tagName && (!$handler || empty($handler['skipCss'])))
			{
				$outputText = $this->renderCss($tag, $outputText);
			}

			if ($handler)
			{
				if (!empty($handler['filterCallback']))
				{
					$callback = $handler['filterCallback'];
					if (is_array($callback) && $callback[0] == '$this')
					{
						$callback[0] = $this;
					}
					$outputText = call_user_func($callback, $outputText, $tag, $preCssOutput);
				}
				else if (isset($handler['wrap']))
				{
					$outputText = sprintf($handler['wrap'], $outputText);
				}
			}

			$output->append($outputText);
		}

		if ($output->isBlock() && !$output->isEmpty())
		{
			// add an extra line break before/after if we have something to output
			// note that tags without could've already incremented these
			$output->incrementLeadingLines();
			$output->incrementTrailingLines();

			if ($initialLeading)
			{
				// merge 1 of the initial leading lines with this
				$initialLeading--;
			}

			if ($previousTrailing)
			{
				// merge 1 of the left over trailing lines with this
				$previousTrailing--;
			}
		}

		if ($initialLeading)
		{
			// push initial leading lines up
			$output->incrementLeadingLines($initialLeading);
		}

		if ($previousTrailing)
		{
			$output->incrementTrailingLines($previousTrailing);
		}

		if ($output->leadingLines() || $output->trailingLines())
		{
			//$output->setType('block');
		}

		return $output;
	}

	protected function _setTagStates(XenForo_Html_Tag $tag, array $existingStates)
	{
		$states = array();

		switch ($tag->tagName())
		{
			case 'pre':
				$states['preFormatted'] = true;
				break;

			case 'script':
			case 'title':
			case 'style':
			case 'embed':
			case 'object':
			case 'iframe':
				$states['hidden'] = true;
				break;
		}

		return $states;
	}

	public function renderChildren(XenForo_Html_Tag $tag, array $state)
	{
		$output = array();
		foreach ($tag->children() AS $child)
		{
			if ($child instanceof XenForo_Html_Tag)
			{
				$output[] = $this->renderTag($child, $state);
			}
			else if ($child instanceof XenForo_Html_Text)
			{
				$output[] = $this->renderText($child, $state);
			}
		}

		return $output;
	}

	public function renderText(XenForo_Html_Text $text, array $state)
	{
		$text = $text->text();
		if (empty($state['preFormatted']))
		{
			$text = preg_replace('/[\r\n\t ]+/', ' ', $text);
		}

		return new XenForo_Html_Renderer_BbCode_Element('text', $text);
	}

	/**
	 * Renders the CSS for a given tag.
	 *
	 * @param XenForo_Html_Tag $tag
	 * @param string $stringOutput
	 *
	 * @return string BB code output
	 */
	public function renderCss(XenForo_Html_Tag $tag, $stringOutput)
	{
		$css = $tag->attribute('style');
		if ($css)
		{
			foreach ($css AS $cssRule => $cssValue)
			{
				if (strtolower($cssRule) == 'display' && strtolower($cssValue) == 'none')
				{
					return '';
				}

				if (!empty($this->_cssHandlers[$cssRule]))
				{
					$callback = $this->_cssHandlers[$cssRule];
					if (is_array($callback) && $callback[0] == '$this')
					{
						$callback[0] = $this;
					}
					$stringOutput = call_user_func($callback, $stringOutput, $cssValue, $tag);
				}
			}

			// images aligned on their own are done this way
			$alignRules = array_merge(array(
				'display' => '',
				'margin-left' => '',
				'margin-right' => ''
			), $css);
			if ($alignRules['display'] == 'block' && (!$tag->isVoid() || $stringOutput !== ''))
			{
				if ($alignRules['margin-left'] == 'auto' && $alignRules['margin-right'] == 'auto')
				{
					$stringOutput = '[CENTER]' . $stringOutput . '[/CENTER]';
				}
				else if ($alignRules['margin-left'] == 'auto' && substr($alignRules['margin-right'], 0, 1) == '0')
				{
					$stringOutput = '[RIGHT]' . $stringOutput . '[/RIGHT]';
				}
				else if (substr($alignRules['margin-left'], 0, 1) == '0' && $alignRules['margin-right'] == 'auto')
				{
					$stringOutput = '[LEFT]' . $stringOutput . '[/LEFT]';
				}
			}
		}

		$align = $tag->attribute('align');
		if ($align && (!$css || empty($css['text-align'])))
		{
			$stringOutput = $this->handleCssTextAlign($stringOutput, $align, $tag);
		}

		return $stringOutput;
	}

	public function convertUrlToAbsolute($url)
	{
		if (preg_match('#^(https?|ftp)://#i', $url))
		{
			return $url;
		}

		if (!$this->_options['baseUrl'])
		{
			return $url;
		}

		if ($url === '')
		{
			return $this->_options['baseUrl'];
		}

		preg_match('#^(?P<protocolHost>(?P<protocol>https?|ftp)://[^/]+)(?P<path>.*)$#i',
			$this->_options['baseUrl'], $baseParts
		);
		if (!$baseParts)
		{
			return $url;
		}

		if (substr($url, 0, 2) == '//')
		{
			return $baseParts['protocol'] . ':' . $url;
		}

		if ($url[0] == '/')
		{
			return $baseParts['protocolHost'] . $url;
		}

		if (preg_match('#^((\.\./)+)#', $url, $upMatch))
		{
			$count = strlen($upMatch[1]) / strlen($upMatch[2]);

			for ($i = 1; $i <= $count; $i++)
			{
				$baseParts['path'] = dirname($baseParts['path']);
			}

			$url = substr($url, strlen($upMatch[0]));
		}

		$baseParts['path'] = str_replace('\\', '/', $baseParts['path']);

		if (substr($baseParts['path'], -1) != '/')
		{
			$baseParts['path'] .= '/';
		}
		if ($url[0] == '/')
		{
			// path has trailing slash
			$url = substr($url, 1);
		}

		return $baseParts['protocolHost'] . $baseParts['path'] . $url;
	}

	public function handleTagFont($text, XenForo_Html_Tag $tag)
	{
		$color = trim($tag->attribute('color'));
		if ($color)
		{
			$text = "[COLOR={$color}]{$text}[/COLOR]";
		}

		$size = trim($tag->attribute('size'));
		if ($size && preg_match('/^[0-9]+(px)?$/i', $size))
		{
			$text = "[SIZE={$size}]{$text}[/SIZE]";
		}

		$face = trim($tag->attribute('face'));
		if ($face)
		{
			$text = "[FONT={$face}]{$text}[/FONT]";
		}

		return $text;
	}

	/**
	 * Handles A tags. Can generate URL or EMAIL tags in BB code.
	 *
	 * @param string $text Child text of the tag
	 * @param XenForo_Html_Tag $tag HTML tag triggering call
	 *
	 * @return string
	 */
	public function handleTagA($text, XenForo_Html_Tag $tag)
	{
		$href = trim($tag->attribute('href'));
		if ($href)
		{
			if (preg_match('/^mailto:(.+)$/i', $href, $match))
			{
				$target = $match[1];
				$type = 'EMAIL';
			}
			else
			{
				$target = $this->convertUrlToAbsolute($href);
				$type = 'URL';
			}


			if ($target == $text)
			{
				// look for part of a BB code at the end that may have been swallowed up
				if (preg_match('#\[/?([a-z0-9_-]+)$#i', $text, $match))
				{
					$append = $match[0];
					$text = substr($text, 0, -strlen($match[0]));
				}
				else
				{
					$append = '';
				}

				return "[$type]{$text}[/$type]$append";
			}
			else
			{
				return "[$type='$target']{$text}[/$type]";
			}
		}
		else
		{
			return $text;
		}
	}

	/**
	 * Handles IMG tags.
	 *
	 * @param string $text Child text of the tag (probably none)
	 * @param XenForo_Html_Tag $tag HTML tag triggering call
	 *
	 * @return string
	 */
	public function handleTagImg($text, XenForo_Html_Tag $tag)
	{
		if (($tag->attribute('class') == 'mceSmilie' || $tag->attribute('data-smilie')) && $tag->attribute('alt'))
		{
			// regular image smilies
			$output = trim($tag->attribute('alt'));
		}
		else if (strpos($tag->attribute('class'), 'mceSmilieSprite') !== false && $tag->attribute('alt'))
		{
			// sprite smilies
			$output = trim($tag->attribute('alt'));
		}
		else if (preg_match('#attach(Thumb|Full)(\d+)#', $tag->attribute('alt'), $match))
		{
			if ($match[1] == 'Full')
			{
				$output = '[ATTACH=full]' . $match[2] . '[/ATTACH]';
			}
			else
			{
				$output = '[ATTACH]' . $match[2] . '[/ATTACH]';
			}

		}
		else
		{
			$src = $tag->attribute('src');
			$output = '';

			if (preg_match('#^(data:|blob:|webkit-fake-url:)#i', $src))
			{
				// data URI - ignore
			}
			else if ($src)
			{
				if (XenForo_Application::isRegistered('smilies'))
				{
					$smilies = XenForo_Application::get('smilies');
				}
				else
				{
					$smilies = XenForo_Model::create('XenForo_Model_Smilie')->getAllSmiliesForCache();
					XenForo_Application::set('smilies', $smilies);
				}
				foreach ($smilies AS $smilie)
				{
					if ($src == $smilie['image_url'])
					{
						$output = reset($smilie['smilieText']);
						break;
					}
				}

				if (!$output)
				{
					$output =  "[IMG]" . $this->convertUrlToAbsolute($src) . "[/IMG]";
				}
			}
		}

		return $this->renderCss($tag, $output);
	}

	/**
	 * Handles LI tags.
	 *
	 * @param string $text Child text of the tag
	 * @param XenForo_Html_Tag $tag HTML tag triggering call
	 *
	 * @return string
	 */
	public function handleTagLi($text, XenForo_Html_Tag $tag)
	{
		$parent = $tag->parent();
		if ($parent && !in_array($parent->tagName(), array('ol', 'ul')))
		{
			if (trim($text) === '')
			{
				return '';
			}
			else
			{
				return '[LIST][*]' . $text . '[/LIST]';
			}
		}
		else
		{
			if (substr($text, -1) == self::BR_SUBSTITUTE)
			{
				// has a trailiing br. we need to add an extra line to make it really count
				$text .= "\n";
			}
			return '[*]' . $text;
		}
	}

	/**
	 * Handles heading tags.
	 *
	 * @param string $text Child text of the tag
	 * @param XenForo_Html_Tag $tag HTML tag triggering call
	 *
	 * @return string
	 */
	public function handleTagH($text, XenForo_Html_Tag $tag)
	{
		switch ($tag->tagName())
		{
			case 'h1': $size = 6; break;
			case 'h2': $size = 5; break;
			case 'h3': $size = 4; break;
			case 'h4': $size = 3; break;
			default: $size = false;
		}

		$text = '[B]' . $text . '[/B]';

		if ($size)
		{
			$text = '[SIZE=' . $size . ']' . $text . '[/SIZE]';
		}

		return $text . "\n";
	}

	/**
	 * Handles CSS (text) color rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssColor($text, $color)
	{
		return "[COLOR=$color]{$text}[/COLOR]";
	}

	/**
	 * Handles CSS float rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssFloat($text, $alignment)
	{
		switch (strtolower($alignment))
		{
			case 'left':
			case 'right':
				$alignmentUpper = strtoupper($alignment);
				return "[$alignmentUpper]{$text}[/$alignmentUpper]";

			default:
				return $text;
		}
	}

	/**
	 * Handles CSS font-family rules. The first font is used.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssFontFamily($text, $cssValue)
	{
		list($fontFamily) = explode(',', $cssValue);
		if (preg_match('/^(\'|")(.*)\\1$/', $fontFamily, $match))
		{
			$fontFamily = $match[2];
		}

		if ($fontFamily && preg_match('/^[a-z0-9 \-]+$/i', $fontFamily))
		{
			return "[FONT=$fontFamily]{$text}[/FONT]";
		}
		else
		{
			return $text;
		}
	}

	/**
	 * Handles CSS font-size rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssFontSize($text, $fontSize)
	{
		switch (strtolower($fontSize))
		{
			case 'xx-small':
			case '9px':
				$fontSize = 1; break;

			case 'x-small':
			case '10px':
				$fontSize = 2; break;

			case 'small':
			case '12px':
				$fontSize = 3; break;

			case 'medium':
			case '15px':
			case '100%':
				$fontSize = 4; break;

			case 'large':
			case '18px':
				$fontSize = 5; break;

			case 'x-large':
			case '22px':
				$fontSize = 6; break;

			case 'xx-large':
			case '26px':
				$fontSize = 7; break;

			default:
				if (!preg_match('/^[0-9]+(px)?$/i', $fontSize))
				{
					$fontSize = 0;
				}
		}

		if ($fontSize)
		{
			return "[SIZE=$fontSize]{$text}[/SIZE]";
		}
		else
		{
			return $text;
		}
	}

	/**
	 * Handles CSS font-style rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssFontStyle($text, $fontStyle)
	{
		switch (strtolower($fontStyle))
		{
			case 'italic':
			case 'oblique':
				return '[I]' . $text . '[/I]';

			default:
				return $text;
		}
	}

	/**
	 * Handles CSS font-weight rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssFontWeight($text, $fontWeight)
	{
		switch (strtolower($fontWeight))
		{
			case 'bold':
			case 'bolder':
			case '700':
			case '800':
			case '900':
				return '[B]' . $text . '[/B]';

			default:
				return $text;
		}
	}

	/**
	 * Handles CSS padding-left rules to represent LTR indent.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssPaddingLeft($text, $paddingAmount)
	{
		$language = XenForo_Visitor::getInstance()->getLanguage();
		if ($language['text_direction'] == 'RTL')
		{
			return $text;
		}

		if (preg_match('/^(\d+)px$/i', $paddingAmount, $match))
		{
			$depth = floor($match[1] / 30); // editor puts in 30px
			if ($depth)
			{
				return '[INDENT=' . $depth . ']' . $text . '[/INDENT]';
			}
		}

		return $text;
	}

	/**
	 * Handles CSS padding-left rules to represent RTL indent.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssPaddingRight($text, $paddingAmount)
	{
		$language = XenForo_Visitor::getInstance()->getLanguage();
		if ($language['text_direction'] != 'RTL')
		{
			return $text;
		}

		if (preg_match('/^(\d+)px$/i', $paddingAmount, $match))
		{
			$depth = floor($match[1] / 30); // editor puts in 30px
			if ($depth)
			{
				return '[INDENT=' . $depth . ']' . $text . '[/INDENT]';
			}
		}

		return $text;
	}

	/**
	 * Handles CSS text-align rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssTextAlign($text, $alignment)
	{
		switch (strtolower($alignment))
		{
			case 'left':
			case 'center':
			case 'right':
				$alignmentUpper = strtoupper($alignment);
				return "[$alignmentUpper]{$text}[/$alignmentUpper]";

			default:
				return $text;
		}
	}

	/**
	 * Handles CSS text-decoration rules.
	 *
	 * @param string $text Child text of the tag with the CSS
	 * @param string $alignment Value of the CSS rule
	 *
	 * @return string
	 */
	public function handleCssTextDecoration($text, $decoration)
	{
		switch (strtolower($decoration))
		{
			case 'underline':
				return "[U]{$text}[/U]";

			case 'line-through':
				return "[S]{$text}[/S]";

			default:
				return $text;
		}
	}
}

class XenForo_Html_Renderer_BbCode_Element
{
	protected $_type = '';
	protected $_text = '';
	protected $_isWhiteSpace = null;

	protected $_modifiers = array();
	protected $_leadingLines = 0;
	protected $_trailingLines = 0;

	public function __construct($type, $text = '')
	{
		$this->setType($type);
		$this->setText($text);
	}

	public function type()
	{
		return $this->_type;
	}

	public function text()
	{
		return $this->_text;
	}

	public function append($text)
	{
		$this->_text .= $text;
		$this->_setIsWhiteSpace();
	}

	public function setText($text)
	{
		$this->_text = $text;
		$this->_setIsWhiteSpace();
	}

	protected function _setIsWhiteSpace()
	{
		$this->_isWhiteSpace = (strlen(trim($this->_text)) == 0);
	}

	public function setType($type)
	{
		$this->_type = $type;
	}

	public function setModifier($key, $value = true)
	{
		$this->_modifiers[$key] = $value;
	}

	public function unsetModifier($key)
	{
		unset($this->_modifiers[$key]);
	}

	public function getModifier($key)
	{
		return (isset($this->_modifiers[$key]) ? $this->_modifiers[$key] : null);
	}

	public function incrementModifier($key, $offset = 1)
	{
		if (!isset($this->_modifiers[$key]))
		{
			$this->_modifiers[$key] = $offset;
		}
		else
		{
			$this->_modifiers[$key] += $offset;
		}
	}

	public function decrementModifier($key, $offset = 1)
	{
		if (isset($this->_modifiers[$key]))
		{
			$this->_modifiers[$key] -= $offset;
			if ($this->_modifiers[$key] <= 0)
			{
				$this->unsetModifier($key);
			}
		}
	}

	public function leadingLines()
	{
		return $this->_leadingLines;
	}

	public function incrementLeadingLines($offset = 1)
	{
		$this->_leadingLines += $offset;
	}

	public function decrementLeadingLines($offset = 1)
	{
		$this->_leadingLines = max(0, $this->_leadingLines - $offset);
	}

	public function trailingLines()
	{
		return $this->_trailingLines;
	}

	public function incrementTrailingLines($offset = 1)
	{
		$this->_trailingLines += $offset;
	}

	public function decrementTrailingLines($offset = 1)
	{
		$this->_trailingLines = max(0, $this->_trailingLines - $offset);
	}

	public function isBlock()
	{
		return ($this->_type == 'block');
	}

	public function isBr()
	{
		return ($this->_type == 'br');
	}

	public function isInline()
	{
		return !$this->isBlock();
	}

	public function isText()
	{
		return ($this->_type == 'text');
	}

	public function isEmpty()
	{
		return (strlen(trim($this->_text)) == 0);
	}

	public function isWhiteSpace()
	{
		return ($this->_isWhiteSpace && $this->isText());
	}
}
