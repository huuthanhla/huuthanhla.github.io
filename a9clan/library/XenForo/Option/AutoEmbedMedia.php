<?php

class XenForo_Option_AutoEmbedMedia
{
	/**
	 * Verifies the autoEmbedMedia setting
	 *
	 * @param array $values
	 * @param XenForo_DataWriter $dw Calling DW
	 * @param string $fieldName Name of field/option
	 *
	 * @return true
	 */
	public static function verifyOption(array &$values, XenForo_DataWriter $dw, $fieldName)
	{
		if (empty($values['linkBbCode']))
		{
			$values['linkBbCode'] = '[i][size=2][url={$url}]View: {$url}[/url][/size][/i]';
		}

		if ($values['embedType'] != XenForo_Helper_Media::AUTO_EMBED_MEDIA_DISABLED)
		{
			if (strpos($values['linkBbCode'], '{$url}') === false)
			{
				$dw->error(new XenForo_Phrase('link_bbcode_must_include_url_token'), $fieldName);
				return false;
			}
		}

		return true;
	}
}