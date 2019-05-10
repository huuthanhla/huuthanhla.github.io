<?php

class XenForo_BbCode_Formatter_HtmlEmail extends XenForo_BbCode_Formatter_Base
{
	/**
	 * Stores the root server url, eg: http://localhost
	 *
	 * @var string
	 */
	protected static $_boardRoot = null;

	/**
	 * String used for outputting smilies as sprites. Will be passed the following params:
	 * 1	Smilie ID
	 * 2	Smilie text
	 * 3	Smilie title
	 *
	 * @var string
	 */
	protected $_smilieSpriteTemplate = '<img src="%9$s" class="mceSmilieSprite mceSmilie%1$d" alt="%2$s" title="%3$s    %2$s" style="width: %5$dpx; height %6$dpx; background: url(\'%4$s\') no-repeat %7$dpx %8$dpx" />';

	public function getTags()
	{
		$tags = parent::getTags();

		if (isset($tags['media']))
		{
			$tags['media']['trimLeadingLinesAfter'] = 1;
		}

		return $tags;
	}

	/**
	 * @see XenForo_BbCode_Formatter_Base::_prepareSmilieUrl()
	 */
	protected function _prepareSmilieUrlInternal($smilieUrl)
	{
		if ($smilieUrl[0] == '/')
		{
			if (self::$_boardRoot === null)
			{
				$boardUrl = XenForo_Application::get('options')->boardUrl;
				self::$_boardRoot = substr($boardUrl, 0, strpos($boardUrl, '/', 8));
			}

			// absolute path to this server
			return self::$_boardRoot . parent::_prepareSmilieUrlInternal($smilieUrl);

		}
		else if (!preg_match('#^https?://#i', $smilieUrl))
		{
			// relative path to this server
			return XenForo_Application::get('options')->boardUrl . '/' . parent::_prepareSmilieUrlInternal($smilieUrl);
		}
		else
		{
			// no change required
			return parent::_prepareSmilieUrlInternal($smilieUrl);
		}
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
			$smilie['sprite_params']['y'],
			$this->_prepareSmilieUrl('styles/default/xenforo/clear.png')
		);
	}

	protected function _setupCustomTagInfo($tagName, array $tag)
	{
		$output = parent::_setupCustomTagInfo($tagName, $tag);
		if (isset($output['replace']) && strlen($tag['replace_html_email']))
		{
			$output['replace'] = $tag['replace_html_email'];
		}

		return $output;
	}

	public function renderTagMedia(array $tag, array $rendererStates)
	{
		$mediaSiteId = strtolower($tag['option']);
		if (isset($this->_mediaSites[$mediaSiteId]))
		{
			$phrase = new XenForo_Phrase('embedded_media');
			return '<table cellpadding="0" cellspacing="0" border="0" width="100%"'
				. ' style="background-color: #F0F7FC; border: 1px solid #A5CAE4; border-radius: 5px; margin: 5px 0; padding: 5px; font-size: 11px; text-align: center">'
				. '<tr><td>' . $phrase->render() .'</td></tr></table>';
		}
		else
		{
			return '';
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
		// these styles are based on the XenForo 1.x master style for quote output.

		if ($name)
		{
			$name = '<tr><td style="
				background-color: #F9D9B0;
				border: 1px solid #F9D9B0;
				border-bottom-color: #F9BC6D;
				border-radius: 4px;
				font-size: 11px;
				font-family: \'Trebuchet MS\', Helvetica, Arial, sans-serif;
				line-height: 1.4;
				padding: 3px 8px;
				margin: 0;
				color: #141414">' . $name . '</td></tr>';
		}

		return '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="<rtlcss>
			background-color: #FFF4E5;
			border: 1px solid #F9D9B0;
			border-radius: 5px;
			margin: 1em 86px 1em 0</rtlcss>">' . $name . '<tr>
			<td style="
				font-size: 9pt;
				font-style: italic;
				font-family: Georgia, \'Times New Roman\', Times, serif;
				line-height: 1.4;
				padding: 10px;
				margin: 0">' . $content . '</td></tr></table>';
	}

	protected function _handleImageProxyOption($url)
	{
		return $url;
	}

	protected function _handleLinkProxyOption($url, $linkType)
	{
		return $url;
	}
}