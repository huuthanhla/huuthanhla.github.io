<?php

class XenForo_Helper_Twitter
{
	/**
	 * @param string $callbackUrl URL to return to
	 *
	 * @return bool|Zend_Oauth_Consumer False if no Twitter app configured, otherwise Oauth consumer
	 */
	public static function getOauthConsumer($callbackUrl = '')
	{
		$options = XenForo_Application::getOptions();

		if (!$options->twitterAppKey || !$options->twitterAppSecret)
		{
			return false;
		}

		Zend_Oauth::setHttpClient(XenForo_Helper_Http::getClient('https://api.twitter.com/oauth'));

		return new Zend_Oauth_Consumer(array(
			'callbackUrl' => $callbackUrl,
			'siteUrl' => 'https://api.twitter.com/oauth',
			'authorizeUrl' => 'https://api.twitter.com/oauth/authenticate',
			'consumerKey' => trim($options->twitterAppKey),
			'consumerSecret' => trim($options->twitterAppSecret),
		));
	}

	/**
	 * Gets the Twitter service object for a token
	 *
	 * @param string|Zend_Oauth_Token_Access $token Access token object or access token string
	 * @param null|string $secret Access token secret if token is provided as string
	 *
	 * @return Zend_Service_Twitter
	 */
	public static function getService($token, $secret = null)
	{
		$options = XenForo_Application::getOptions();

		Zend_Oauth::setHttpClient(XenForo_Helper_Http::getClient('https://api.twitter.com/oauth'));

		if ($token instanceof Zend_Oauth_Token_Access)
		{
			$accessToken = $token;
		}
		else
		{
			$accessToken = new Zend_Oauth_Token_Access();
			$accessToken->setToken($token);
			$accessToken->setTokenSecret($secret);
		}

		return new Zend_Service_Twitter(array(
			'accessToken' => $accessToken,
			'oauthOptions' => array(
				'consumerKey' => trim($options->twitterAppKey),
				'consumerSecret' => trim($options->twitterAppSecret),
			),
			'httpClientOptions' => XenForo_Helper_Http::getExtraHttpClientOptions('https://api.twitter.com/oauth')
		));
	}

	/**
	 * Gets the user information from a token
	 *
	 * @param string|Zend_Oauth_Token_Access $token Access token object or access token string
	 * @param null|string $secret Access token secret if token is provided as string
	 * @param null|Exception $e Thrown exception returned if applicable
	 *
	 * @return array|boolean
	 */
	public static function getUserFromToken($token, $secret = null, &$returnE = null)
	{
		try
		{
			$twitter = self::getService($token, $secret);
			$details = $twitter->accountVerifyCredentials();

			// force array return
			$return = json_decode(json_encode($details->toValue()), true);

			if (is_array($return) && !empty($return['errors'][0]['message']))
			{
				$returnE = new Exception($return['errors'][0]['message'], $return['errors'][0]['code']);
				return false;
			}

			$returnE = null;
			return $return;
		}
		catch (Exception $e)
		{
			$returnE = $e;
			return false;
		}
	}
}