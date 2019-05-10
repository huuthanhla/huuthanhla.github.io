<?php

class XenForo_Captcha_KeyCaptcha extends XenForo_Captcha_Abstract
{
	/**
	 * KeyCAPTCHA user ID
	 *
	 * @var null|string
	 */
	protected $_keyUserId = null;

	/**
	 * KeyCAPTCHA private key
	 *
	 * @var null|string
	 */
	protected $_privateKey = null;


	public function __construct($keyUserId = null, $privateKey = null)
	{
		if (!$keyUserId || !$privateKey)
		{
			$extraKeys = XenForo_Application::getOptions()->extraCaptchaKeys;
			if (!empty($extraKeys['keyCaptchaUserId']) && !empty($extraKeys['keyCaptchaPrivateKey']))
			{
				$this->_keyUserId = $extraKeys['keyCaptchaUserId'];
				$this->_privateKey = $extraKeys['keyCaptchaPrivateKey'];
			}
		}
		else
		{
			$this->_keyUserId = $keyUserId;
			$this->_privateKey = $privateKey;
		}
	}

	/**
	 * Determines if CAPTCHA is valid (passed).
	 *
	 * @see XenForo_Captcha_Abstract::isValid()
	 */
	public function isValid(array $input)
	{
		if (!$this->_keyUserId)
		{
			return true; // if not configured, always pass
		}

		if (empty($input['keycaptcha_code']) || !is_string($input['keycaptcha_code']))
		{
			return false;
		}

		$parts = explode('|', $input['keycaptcha_code']);
		if (count($parts) < 4)
		{
			return false;
		}

		if ($parts[0] !== md5('accept' . $parts[1] . $this->_privateKey . $parts[2]))
		{
			return false;
		}

		if (substr($parts[2], 0, 7) !== 'http://')
		{
			return false;
		}

		try
		{
			$client = XenForo_Helper_Http::getClient($parts[2]);
			$contents = trim($client->request('GET')->getBody());
			return ($contents == '1');
		}
		catch (Zend_Http_Client_Adapter_Exception $e)
		{
			// this is an exception with the underlying request, so let it go through
			XenForo_Error::logException($e, false, 'KeyCAPTCHA connection error:');
			return true;
		}
	}

	/**
	 * Renders the CAPTCHA template.
	 *
	 * @see XenForo_Captcha_Abstract::renderInternal()
	 */
	public function renderInternal(XenForo_View $view)
	{
		if (!$this->_keyUserId)
		{
			return '';
		}

		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$sessionId = md5(uniqid('xfkeycaptcha'));
		$sign = md5($sessionId . $ip . $this->_privateKey);
		$sign2 = md5($sessionId . $this->_privateKey);

		return $view->createTemplateObject('captcha_keycaptcha', array(
			'keyUserId' => $this->_keyUserId,
			'sessionId' => $sessionId,
			'sign' => $sign,
			'sign2' => $sign2
		));
	}
}