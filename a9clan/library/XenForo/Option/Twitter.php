<?php

/**
 * Helper for the Twitter option.
 *
 * @package XenForo_Options
 */
abstract class XenForo_Option_Twitter
{
	public static function verifyTweetOption(array &$option, XenForo_DataWriter $dw, $fieldName)
	{
		if (!empty($option['enabled']))
		{
			if (!empty($option['via']) && !XenForo_Helper_UserField::verifyTwitter(array(), $option['via'], $error))
			{
				$dw->error($error, $fieldName);
				return false;
			}

			if (!empty($option['related']) && !XenForo_Helper_UserField::verifyTwitter(array(), $option['related'], $error))
			{
				$dw->error($error, $fieldName);
				return false;
			}
		}

		return true;
	}
}