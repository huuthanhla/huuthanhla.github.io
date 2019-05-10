<?php

class XenForo_DataWriter_Helper_Uri
{
	/**
	 * Verifies that the provided string is a valid URL
	 *
	 * @param string $url
	 *
	 * @return boolean
	 */
	public static function verifyUri(&$uri, XenForo_DataWriter $dw, $fieldName = false)
	{
		if (Zend_Uri::check($uri))
		{
			return true;
		}

		$dw->error(new XenForo_Phrase('please_enter_valid_url'), $fieldName);
		return false;
	}

	/**
	 * Verifies the provided string as either empty or a valid URI
	 *
	 * @param string $uri
	 * @param XenForo_DataWriter $dw
	 * @param string $fieldName
	 *
	 * @return boolean
	 */
	public static function verifyUriOrEmpty(&$uri, XenForo_DataWriter $dw, $fieldName = false)
	{
		if ($uri === 'http://')
		{
			$uri = '';
		}

		if ($uri === '')
		{
			return true;
		}

		if (substr(strtolower($uri), 0, 4) == 'www.')
		{
			$uri = 'http://' . $uri;
		}

		return XenForo_DataWriter_Helper_Uri::verifyUri($uri, $dw, $fieldName);
	}
}
