<?php

class XenForo_Captcha_SolveMedia extends XenForo_Captcha_Abstract
{
	/**
	 * Challenge key
	 *
	 * @var null|string
	 */
	protected $_cKey = null;

	/**
	 * Verification key
	 *
	 * @var null|string
	 */
	protected $_vKey = null;


	public function __construct($cKey = null, $vKey = null)
	{
		if (!$cKey || !$vKey)
		{
			$extraKeys = XenForo_Application::getOptions()->extraCaptchaKeys;
			if (!empty($extraKeys['solveMediaCKey']) && !empty($extraKeys['solveMediaVKey']))
			{
				$this->_cKey = $extraKeys['solveMediaCKey'];
				$this->_vKey = $extraKeys['solveMediaVKey'];
			}
		}
		else
		{
			$this->_cKey = $cKey;
			$this->_vKey = $vKey;
		}
	}

	/**
	 * Determines if CAPTCHA is valid (passed).
	 *
	 * @see XenForo_Captcha_Abstract::isValid()
	 */
	public function isValid(array $input)
	{
		if (!$this->_cKey)
		{
			return true; // if not configured, always pass
		}

		if (empty($input['adcopy_challenge']) || empty($input['adcopy_response']))
		{
			return false;
		}

		try
		{
			$client = XenForo_Helper_Http::getClient('http://verify.solvemedia.com/papi/verify');
			$client->setParameterPost(array(
				'privatekey' => $this->_vKey,
				'challenge' => $input['adcopy_challenge'],
				'response' => $input['adcopy_response'],
				'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
			));
			$contents = trim($client->request('POST')->getBody());
			$parts = explode("\n", $contents, 3);
			$result = trim($parts[0]);
			$error = isset($parts[1]) ? trim($parts[1]) : null;

			if ($result == 'true')
			{
				return true;
			}

			switch ($error)
			{
				case 'wrong answer':
				case 'invalid remoteip':
					// generally end user mistakes
					return false;

				default:
					// this is likely a configuration error, log and let it through
					XenForo_Error::logError("Solve Media CAPTCHA error: $error");
					return true;
			}
		}
		catch (Zend_Http_Client_Adapter_Exception $e)
		{
			// this is an exception with the underlying request, so let it go through
			XenForo_Error::logException($e, false, "Solve Media connection error: ");
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
		if (!$this->_cKey)
		{
			return '';
		}

		return $view->createTemplateObject('captcha_solve_media', array(
			'cKey' => $this->_cKey
		));
	}
}