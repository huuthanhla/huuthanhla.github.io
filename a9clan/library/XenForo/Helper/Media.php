<?php

class XenForo_Helper_Media
{
	const AUTO_EMBED_MEDIA_DISABLED = 0;
	const AUTO_EMBED_MEDIA_ENABLED  = 1;
	const AUTO_EMBED_MEDIA_AND_LINK = 2;

	protected static $_instance = null;

	protected $_bbCodeMediaSites = null;

	protected $_bbCodeModel = null;

	private function __construct()
	{
		$this->_bbCodeModel = XenForo_Model::create('XenForo_Model_BbCode');
	}

	/**
	* Gets the browsing user's info.
	*
	* @return XenForo_Visitor
	*/
	protected static final function _getInstance()
	{
		if (!self::$_instance)
		{
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public static function convertMediaLinkToEmbedHtml($url, array $options = array())
	{
		$object = self::_getInstance();

		return $object->_convertMediaLinkToEmbedHtml($url, $options);
	}

	/**
	 * Attempts to match a URL to a BB code media site, and produce the appropriate [media] tag
	 *
	 * @param string $url
	 * @param array $options
	 *
	 * @return string|boolean
	 */
	protected function _convertMediaLinkToEmbedHtml($url, array $options = array())
	{
		foreach ($this->_getAllBbCodeMediaSites() AS $siteId => $site)
		{
			foreach ($site['regexes'] AS $regex)
			{
				if (preg_match($regex, $url, $matches))
				{
					$mediaId = $this->_getMediaKeyFromCallback($url, $matches['id'], $site, $siteId);
					if ($mediaId === false)
					{
						return false;
					}
					if (!$mediaId)
					{
						$mediaId = urldecode($matches['id']);
					}

					$matchBbCode = '[MEDIA=' . $siteId . ']' . $mediaId . '[/MEDIA]';

					if (isset($options['embedType']) && $options['embedType'] == self::AUTO_EMBED_MEDIA_AND_LINK)
					{
						$matchBbCode .= "\n" . str_replace('{$url}', "{$url}", $options['linkBbCode']) . "\n";
					}

					return $matchBbCode;
				}
			}
		}

		return false;
	}

	protected function _getMediaKeyFromCallback($url, $matchedId, array $site, $siteId)
	{
		if (!empty($site['match_callback_class']) && !empty($site['match_callback_method']))
		{
			$class = $site['match_callback_class'];
			$method = $site['match_callback_method'];

			if (XenForo_Application::autoload($class) && method_exists($class, $method))
			{
				return call_user_func_array(array($class, $method), array($url, $matchedId, $site, $siteId));
			}
		}

		return null;
	}

	protected function _getAllBbCodeMediaSites()
	{
		if (is_null($this->_bbCodeMediaSites))
		{
			$bbCodeModel = $this->_getBbCodeModel();

			$this->_bbCodeMediaSites = $bbCodeModel->getAllBbCodeMediaSites();

			foreach ($this->_bbCodeMediaSites AS $siteId => &$site)
			{
				$site['regexes'] = $bbCodeModel->convertMatchUrlsToRegexes($site['match_urls'], $site['match_is_regex']);
			}
		}

		return $this->_bbCodeMediaSites;
	}

	/**
	 * @return XenForo_Model_BbCode
	 */
	protected function _getBbCodeModel()
	{
		return $this->_bbCodeModel;
	}
}