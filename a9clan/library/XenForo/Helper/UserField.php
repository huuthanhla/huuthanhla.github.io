<?php

class XenForo_Helper_UserField
{
	public static function verifyFacebook(array $field, &$value, &$error)
	{
		if (preg_match('#facebook\.com/(\#!/)?profile\.php\?id=(?P<id>\d+)#i', $value, $match))
		{
			$value = $match['id'];
		}
		else if (preg_match('#facebook\.com/(\#!/)?(?P<id>[a-z0-9\.]+)#i', $value, $match))
		{
			if (substr($match['id'], -4) != '.php')
			{
				$value = $match['id'];
			}
		}

		if (!preg_match('/^[a-z0-9\.]+$/i', $value))
		{
			$error = new XenForo_Phrase('please_enter_valid_facebook_username_using_alphanumeric_dot_numbers');
			return false;
		}

		return true;
	}

	public static function verifyTwitter(array $field, &$value, &$error)
	{
		if ($value[0] == '@')
		{
			$value = substr($value, 1);
		}

		if (!preg_match('/^[a-z0-9_]+$/i', $value))
		{
			$error = new XenForo_Phrase('please_enter_valid_twitter_name_using_alphanumeric');
			return false;
		}

		return true;
	}
}