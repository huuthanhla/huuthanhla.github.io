<?php

/**
 * Filters BB codes based on rules. BB codes violating these rules will be stripped.
 *
 * @package XenForo_BbCode
 */
class XenForo_BbCode_Formatter_BbCode_Filter extends XenForo_BbCode_Formatter_BbCode_Abstract
{
	/**
	 * Callback for all tags.
	 *
	 * @var callback
	 */
	protected $_generalTagCallback = array('$this', 'filterTag');

	protected $_disabledTags = array();
	protected $_stripDisabled = true;

	protected $_nonPrintableTags = array('img', 'media');

	protected $_maxTextSize = -1;

	protected $_tagTally = array();
	protected $_smilieTally = 0;
	protected $_disabledTally = 0;
	protected $_printableLength = 0;

	public function renderTree(array $tree, array $extraStates = array())
	{
		$this->_tagTally = array();
		$this->_smilieTally = 0;
		$this->_disabledTally = 0;
		$this->_printableLength = 0;

		return parent::renderTree($tree, $extraStates);
	}

	public function disableTags($tags)
	{
		if (!is_array($tags))
		{
			$tags = array($tags);
		}

		$this->_disabledTags = array_merge($this->_disabledTags, $tags);
	}

	public function setMaxTextSize($value)
	{
		$value = intval($value);
		if ($value == 0)
		{
			$this->disableTags('size');
		}
		$this->_maxTextSize = $value;
	}

	public function setStripDisabled($value)
	{
		$this->_stripDisabled = (bool)$value;
	}

	public function getStripDisabled()
	{
		return $this->_stripDisabled;
	}

	public function configureFromSignaturePermissions(array $perms)
	{
		if (!XenForo_Permission::hasPermission($perms, 'signature', 'basicText'))
		{
			$this->disableTags(array('b', 'i', 'u', 's'));
		}
		if (!XenForo_Permission::hasPermission($perms, 'signature', 'extendedText'))
		{
			$this->disableTags(array('color', 'font', 'size'));
		}
		if (!XenForo_Permission::hasPermission($perms, 'signature', 'align'))
		{
			$this->disableTags(array('left', 'center', 'right', 'indent'));
		}
		if (!XenForo_Permission::hasPermission($perms, 'signature', 'link') || !XenForo_Permission::hasPermission($perms, 'signature', 'maxLinks'))
		{
			$this->disableTags(array('url', 'email'));
		}
		if (!XenForo_Permission::hasPermission($perms, 'signature', 'image') || !XenForo_Permission::hasPermission($perms, 'signature', 'maxImages'))
		{
			$this->disableTags('img');
		}
		if (!XenForo_Permission::hasPermission($perms, 'signature', 'media'))
		{
			$this->disableTags('media');
		}
		if (!XenForo_Permission::hasPermission($perms, 'signature', 'block'))
		{
			$this->disableTags(array('code', 'php', 'html', 'quote', 'spoiler'));
		}
		if (!XenForo_Permission::hasPermission($perms, 'signature', 'list'))
		{
			$this->disableTags('list');
		}

		foreach ($this->_tags AS $tagName => $tag)
		{
			if (isset($tag['allowSignature']) && !$tag['allowSignature'])
			{
				$this->disableTags($tagName);
			}
		}

		$this->setMaxTextSize(XenForo_Permission::hasPermission($perms, 'signature', 'maxTextSize'));
	}

	public function validateAsSignature($bbCode, array $perms, &$errors = array())
	{
		$errors = array();

		$length = XenForo_Permission::hasPermission($perms, 'signature', 'maxPrintable');
		if ($length != -1 && $this->getPrintableLength() > $length)
		{
			$diff = $this->getPrintableLength() - $length;
			$errors[] = new XenForo_Phrase('your_signature_is_x_characters_too_long', array('count' => XenForo_Locale::numberFormat($diff)));
		}

		$lines = XenForo_Permission::hasPermission($perms, 'signature', 'maxLines');
		if ($lines != -1)
		{
			$parser = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_Base'));
			$html = $parser->render($bbCode, array(
				'spoilerTextWithFallback' => true
			));

			$processed = preg_replace('#<br />\s*<(div|p|pre|ul|ol|blockquote)#i', '<$1', $html);
			$processed = preg_replace('#(<(ul|ol)[^>]*>)\s*<li[^>]*>#i', '$1', $processed);
			$processed = preg_replace('#</li>\s*(</(ul|ol)>)#i', '$1', $processed);
			$processed = preg_replace('#</?(div|p|pre|ul|ol|li|blockquote)[^>]*>\s*<(div|p|pre|ul|ol|li|blockquote)[^>]*>#i', '<br />', $processed);
			$processed = preg_replace('#</?(div|p|pre|ul|ol|li|blockquote)[^>]*>#i', '<br />', $processed);
			$processed = preg_replace('#^(<br[^>]*>)+#i', '', $processed);
			$processed = preg_replace('#(<br[^>]*>)+$#i', '', $processed);

			$lineCount = substr_count($processed, '<br') + 1;

			if ($lineCount > $lines)
			{
				$diff = $lineCount - $lines;
				$errors[] = new XenForo_Phrase('your_signature_is_x_liness_too_long', array('count' => XenForo_Locale::numberFormat($diff)));
			}
		}

		$links = XenForo_Permission::hasPermission($perms, 'signature', 'maxLinks');
		if ($links != -1 && ($this->getTagTally('url') + $this->getTagTally('email')) > $links)
		{
			$errors[] = new XenForo_Phrase('your_signature_may_only_have_x_links', array('count' => XenForo_Locale::numberFormat($links)));
		}

		$images = XenForo_Permission::hasPermission($perms, 'signature', 'maxImages');
		if ($images != -1 && $this->getTagTally('img') > $images)
		{
			$errors[] = new XenForo_Phrase('your_signature_may_only_have_x_images', array('count' => XenForo_Locale::numberFormat($images)));
		}

		$smilies = XenForo_Permission::hasPermission($perms, 'signature', 'maxSmilies');
		if ($smilies != -1 && $this->getSmilieTally() > $smilies)
		{
			$errors[] = new XenForo_Phrase('your_signature_may_only_have_x_smilies', array('count' => XenForo_Locale::numberFormat($smilies)));
		}

		if (!$this->_stripDisabled)
		{
			foreach ($this->_disabledTags AS $disabledTag)
			{
				if ($this->getTagTally($disabledTag))
				{
					$errors[] = new XenForo_Phrase('your_signature_may_not_contain_disabled_tags');
					break;
				}
			}
		}

		return (count($errors) == 0);
	}

	/**
	 * Callback that all tags with go through. Filters tags as necessary.
	 *
	 * @param array $tag
	 * @param array $rendererStates

	 * @return string
	 */
	public function filterTag(array $tag, array $rendererStates)
	{
		$rendererStates['parentTag'] = $tag['tag'];

		$tag = $this->_manipulateTag($tag, $rendererStates);

		$text = $this->renderSubTree($tag['children'], $rendererStates);

		$this->_tallyTags($tag, $text);

		// non-printable tags shouldn't count
		if (in_array($tag['tag'], $this->_nonPrintableTags))
		{
			$this->_printableLength -= $this->_getPrintableLength($text, $rendererStates);
		}

		if (in_array($tag['tag'], $this->_disabledTags))
		{
			return $this->_handleDisabledTag($tag, $text, $rendererStates);
		}

		return $this->_handleStandardTag($tag, $text, $rendererStates);
	}

	protected function _getPrintableLength($text, array $rendererStates)
	{
		if (!empty($rendererStates['parentTag']) && $rendererStates['parentTag'] == 'list')
		{
			$text = preg_replace('/\n?\[\*\]/', '', $text);
		}

		$text = preg_replace('/\r?\n/', '', $text);

		return utf8_strlen($text);
	}

	protected function _handleDisabledTag(array $tag, $text, array $rendererStates)
	{
		$this->_disabledTally++;

		if (!$this->_stripDisabled)
		{
			return $this->_handleStandardTag($tag, $text, $rendererStates);
		}

		if (in_array($tag['tag'], $this->_nonPrintableTags))
		{
			return '';
		}

		if ($tag['tag'] == 'list')
		{
			$text = preg_replace('/\n?\[\*\]/', "\n", $text);
		}

		return $text;
	}

	protected function _handleStandardTag(array $tag, $text, array $rendererStates)
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

		// note: necessary to return prepend/append unfiltered to keep them unchanged
		return $prepend . $text . $append;
	}

	protected function _manipulateTag(array $tag, array $rendererStates)
	{
		if ($tag['tag'] == 'size' && $this->_maxTextSize > 0 && !empty($tag['option']))
		{
			$s = $tag['option'];

			if (strval(intval($s)) == strval($s))
			{
				// size is just an int
				if ($s > $this->_maxTextSize)
				{
					$tag['option'] = $this->_maxTextSize;
					if (!empty($tag['original']))
					{
						$tag['original'][0] = preg_replace(
							'/(=|\'|")\s*' . preg_quote($s, '/') . '\s*(\'|"|\])/',
							'${1}' . $this->_maxTextSize . '${2}',
							$tag['original'][0]
						);
					}
				}
			}
			else
			{
				// not a size we can reliably parse - ignore it
				$tag['original'] = false;
			}
		}

		return $tag;
	}

	protected function _tallyTags(array $tag, $text)
	{
		if (isset($this->_tagTally[$tag['tag']]))
		{
			$this->_tagTally[$tag['tag']]++;
		}
		else
		{
			$this->_tagTally[$tag['tag']] = 1;
		}
	}

	public function filterString($string, array $rendererStates)
	{
		if (empty($rendererStates['stopSmilies']) && $this->_smilieTranslate)
		{
			$translated = strtr($string, $this->_smilieTranslate);
			$this->_smilieTally += preg_match_all("#\\0(\d+)\\0#", $translated, $null);
		}

		$this->_printableLength += $this->_getPrintableLength($string, $rendererStates);

		return parent::filterString($string, $rendererStates);
	}

	public function getDisabledTally()
	{
		return $this->_disabledTally;
	}

	public function getTagTally($tag)
	{
		return isset($this->_tagTally[$tag]) ? $this->_tagTally[$tag] : 0;
	}

	public function getSmilieTally()
	{
		return $this->_smilieTally;
	}

	public function getPrintableLength()
	{
		return $this->_printableLength;
	}
}