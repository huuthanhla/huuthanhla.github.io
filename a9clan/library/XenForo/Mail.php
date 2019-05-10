<?php

/**
 * Class to manage preparing and sending emails. This sends plain text
 * and HTML emails.
 *
 * @package XenForo_Mail
 */
class XenForo_Mail
{
	/**
	 * A cache of previously sent emails.
	 *
	 * @var array Format: [email title][language id] => template code
	 */
	protected static $_emailCache = array();

	/**
	 * Stores whether or not the transport layer has been setup. This is setup
	 * when the first mail is to be sent, but can be explicitly called if desired.
	 *
	 * @var boolean
	 */
	protected static $_transportSetup = false;

	/**
	 * List of email templates that need to be pre-cached.
	 *
	 * @var array Format: [email title] => true
	 */
	protected static $_preCache = array('MAIL_CONTAINER' => true);

	/**
	 * The title of the email to be sent by this instance.
	 *
	 * @var string
	 */
	protected $_emailTitle = '';

	/**
	 * Parameters to pass to the email template.
	 *
	 * @var array Key-value pairs
	 */
	protected $_params = array();

	/**
	 * The language ID the email should be sent in.
	 *
	 * @var integer
	 */
	protected $_languageId = 0;

	/**
	 * Controls whether the phrase value for all languages should
	 * be pre-cached. This is useful when sending the same email to
	 * multiple users (eg, subscription notifications).
	 *
	 * @var boolean
	 */
	protected $_preCacheAllLanguages = false;

	/**
	 * Captured exception when an email fails to send (at the transport level).
	 *
	 * @var Exception|null
	 */
	protected $_failureException = null;

	protected static $_headerMap = array(
		'cc' => 'addCc',
		'bcc' => 'addBcc',
		'reply-to' => 'setReplyTo',
		'message-id' => 'setMessageId',
	);

	/**
	 * Constructor.
	 *
	 * @param string $emailTitle Title of the email template
	 * @param array $params Key-value params to pass to email template
	 * @param integer|null $languageId Language of email; if null, uses language of current user (if setup)
	 */
	public function __construct($emailTitle, array $params, $languageId = null)
	{
		if (!XenForo_Application::isRegistered('languages'))
		{
			XenForo_Application::set('languages', XenForo_Model::create('XenForo_Model_Language')->getAllLanguagesForCache());
		}

		if ($languageId === null)
		{
			$languageId = XenForo_Phrase::getLanguageId();
		}
		else if (!$languageId)
		{
			$languageId = XenForo_Application::get('options')->defaultLanguageId;
		}
		else
		{
			$languages = XenForo_Application::get('languages');
			if (!isset($languages[$languageId]))
			{
				$languageId = XenForo_Application::get('options')->defaultLanguageId;
			}
		}

		$this->_emailTitle = $emailTitle;
		$this->_params = $params;
		$this->_languageId = $languageId;

		if (!isset(self::$_emailCache[$emailTitle][$languageId]))
		{
			self::$_preCache[$emailTitle] = true;
		}
	}

	/**
	 * Enables pre-caching of this email template in all languages. This will
	 * only apply if the email template needs to be loaded.
	 */
	public function enableAllLanguagePreCache()
	{
		$this->_preCacheAllLanguages = true;
	}

	/**
	 * Sends the given email.
	 *
	 * @param string $toEmail The email address the email is sent to
	 * @param string $toName Name of the person receiving it
	 * @param array $headers List of additional headers to send
	 * @param string $fromEmail Email address the email should come from; if not specified, uses board default
	 * @param string $fromName Name the email should come from; if not specified, uses board default
	 * @param string $returnPath The return path of the email (where bounces should go to)
	 *
	 * @return boolean True on success
	 */
	public function send($toEmail, $toName = '', array $headers = array(), $fromEmail = '', $fromName = '', $returnPath = '')
	{
		if (!$toEmail)
		{
			return false;
		}

		$mailObj = $this->getPreparedMailHandler($toEmail, $toName, $headers, $fromEmail, $fromName, $returnPath);
		if (!$mailObj)
		{
			return false;
		}

		return $this->sendMail($mailObj);
	}

	/**
	 * Sends the given mail object. The mail transport system will be setup first,
	 * if necessary.
	 *
	 * @param Zend_Mail $mailObj Mail to send.
	 *
	 * @return boolean
	 */
	public function sendMail(Zend_Mail $mailObj)
	{
		if (!XenForo_Application::get('config')->enableMail)
		{
			return true;
		}

		$transport = self::getTransport();
		$transport = self::getFinalTransportForMail($mailObj, $transport);

		try
		{
			$mailObj->send($transport);
		}
		catch (Exception $e)
		{
			$this->_failureException = $e;
			$toEmails = implode(', ', $mailObj->getRecipients());
			XenForo_Error::logException($e, false, "Email to $toEmails failed: ");
			return false;
		}

		return true;
	}

	/**
	 * Prepares an email for sending, but places it in a queue for sending later.
	 *
	 * @param string $toEmail The email address the email is sent to
	 * @param string $toName Name of the person receiving it
	 * @param array $headers List of additional headers to send
	 * @param string $fromEmail Email address the email should come from; if not specified, uses board default
	 * @param string $fromName Name the email should come from; if not specified, uses board default
	 * @param string $returnPath The return path of the email (where bounces should go to)
	 *
	 * @return boolean True on success
	 */
	public function queue($toEmail, $toName = '', array $headers = array(), $fromEmail = '', $fromName = '', $returnPath = '')
	{
		if (!$toEmail)
		{
			return false;
		}

		if (!XenForo_Application::get('config')->enableMail)
		{
			return true;
		}

		if (!XenForo_Application::get('config')->enableMailQueue)
		{
			return $this->send($toEmail, $toName, $headers, $fromEmail, $fromName, $returnPath);
		}

		$mailObj = $this->getPreparedMailHandler($toEmail, $toName, $headers, $fromEmail, $fromName, $returnPath);
		if (!$mailObj)
		{
			return false;
		}

		return XenForo_Model::create('XenForo_Model_MailQueue')->insertMailQueue($mailObj);
	}

	/**
	 * Gets the fully prepared, internal mail object. This can be called directly
	 * to allow advanced manipulation before sending
	 *
	 * @param string $toEmail The email address the email is sent to
	 * @param string $toName Name of the person receiving it
	 * @param array $headers List of additional headers to send
	 * @param string $fromEmail Email address the email should come from; if not specified, uses board default
	 * @param string $fromName Name the email should come from; if not specified, uses board default
	 * @param string $returnPath The return path of the email (where bounces should go to)
	 *
	 * @return Zend_Mail|false
	 */
	public function getPreparedMailHandler($toEmail, $toName = '', array $headers = array(), $fromEmail = '', $fromName = '', $returnPath = '')
	{
		if (!$toEmail)
		{
			return false;
		}

		$contents = $this->prepareMailContents();
		if (!$contents)
		{
			return false;
		}

		$contents = $this->wrapMailContainer($contents['subject'], $contents['bodyText'], $contents['bodyHtml']);

		$mailObj = new Zend_Mail('utf-8');
		$mailObj->setSubject($contents['subject'])
			->setBodyText($contents['bodyText'])
			->addTo($toEmail, $toName);

		if ($contents['bodyHtml'] !== '')
		{
			$mailObj->setBodyHtml($contents['bodyHtml']);
		}

		$options = XenForo_Application::getOptions();
		if (!$fromName)
		{
			$fromName = ($options->emailSenderName ? $options->emailSenderName : $options->boardTitle);
		}

		if ($fromEmail)
		{
			$mailObj->setFrom($fromEmail, $fromName);
		}
		else
		{
			$mailObj->setFrom($options->defaultEmailAddress, $fromName);
		}

		if ($returnPath)
		{
			$mailObj->setReturnPath($returnPath);
		}
		else
		{
			$bounceEmailAddress = $options->bounceEmailAddress;
			if (!$bounceEmailAddress)
			{
				$bounceEmailAddress = $options->defaultEmailAddress;
			}

			$bounceHmac = substr(hash_hmac('md5', $toEmail, XenForo_Application::getConfig()->globalSalt), 0, 8);

			$mailObj->addHeader('X-To-Validate', "$bounceHmac+$toEmail");

			if ($options->enableVerp)
			{
				$verpValue = str_replace('@', '=', $toEmail);
				$bounceEmailAddress = str_replace('@', "+$bounceHmac+$verpValue@", $bounceEmailAddress);
			}
			$mailObj->setReturnPath($bounceEmailAddress);
		}

		foreach ($headers AS $headerName => $headerValue)
		{
			if (isset(self::$_headerMap[strtolower($headerName)])) {
				$func = self::$_headerMap[strtolower($headerName)];
				$mailObj->$func($headerValue);
			}
			else
			{
				$mailObj->addHeader($headerName, $headerValue);
			}
		}

		if (!$mailObj->getMessageId())
		{
			$mailObj->setMessageId();
		}

		return $mailObj;
	}

	/**
	 * Prepares the subject, plain text body, and HTML body.
	 *
	 * @param string|null $emailTitle Title of email to send. If not specified, uses value from consructor.
	 * @param array|null $params Params to pass to email template. If not specified, uses value from constructor.
	 *
	 * @return array|false False if the template can't be found; otherwise array with subject, bodyText, and bodyHtml keys
	 */
	public function prepareMailContents($emailTitle = null, array $params = null)
	{
		if ($emailTitle === null)
		{
			$emailTitle = $this->_emailTitle;
		}
		if ($params === null)
		{
			$params = $this->_params;
		}

		$__template = $this->_loadEmailTemplate($emailTitle);
		if (!$__template)
		{
			return false;
		}

		$__defaultLanguage = XenForo_Template_Helper_Core::getDefaultLanguage();
		$__languages = XenForo_Application::get('languages');
		if (isset($__languages[$this->_languageId]))
		{
			XenForo_Template_Helper_Core::setDefaultLanguage($__languages[$this->_languageId]);
		}

		$xenOptions = XenForo_Application::get('options')->getOptions();

		extract($params);
		$emailLanguage = XenForo_Template_Helper_Core::getDefaultLanguage();
		$emailIsRtl = (isset($emailLanguage['text_direction']) && $emailLanguage['text_direction'] == 'RTL');

		$__oldErrors = error_reporting(E_ALL & ~E_NOTICE);
		XenForo_Application::disablePhpErrorHandler();

		// these variables come from the $__template
		$__subject = $__bodyText = $__bodyHtml = '';

		eval($__template);

		XenForo_Application::enablePhpErrorHandler();
		error_reporting($__oldErrors);

		XenForo_Template_Helper_Core::setDefaultLanguage($__defaultLanguage);

		if ($emailIsRtl)
		{
			$__bodyHtml = preg_replace_callback('/<rtlcss>(.*)<\/rtlcss>/sU', array($this, '_replaceRtlCss'), $__bodyHtml);
		}
		else
		{
			$__bodyHtml = preg_replace('/<rtlcss>(.*)<\/rtlcss>/sU', '\1', $__bodyHtml);
		}

		return array(
			'subject' => $__subject,
			'bodyText' => $__bodyText,
			'bodyHtml' => $__bodyHtml
		);
	}

	protected function _replaceRtlCss(array $matches)
	{
		return XenForo_Template_Helper_RightToLeft::getRtlCss($matches[1]);
	}

	/**
	 * Wraps the mail container template around a given message.
	 *
	 * @param string $subject
	 * @param string $bodyText
	 * @param string $bodyHtml
	 *
	 * @return array Wrapped mail; keys: subject, bodyText, bodyHtml
	 */
	public function wrapMailContainer($subject, $bodyText, $bodyHtml)
	{
		$contents = $this->prepareMailContents('MAIL_CONTAINER', array(
			'subject' => $subject,
			'bodyText' => $bodyText,
			'bodyHtml' => $bodyHtml
		));

		if ($contents)
		{
			// remove the bodyHtml so we skip an HTML email if there's nothing
			if ($bodyHtml === '')
			{
				$contents['bodyHtml'] = '';
			}

			return $contents;
		}
		else
		{
			return array(
				'subject' => $subject,
				'bodyText' => $bodyText,
				'bodyHtml' => $bodyHtml
			);
		}
	}

	/**
	 * Loads the specified email template from the cache or DB.
	 *
	 * @param string $emailTitle
	 *
	 * @return string
	 */
	protected function _loadEmailTemplate($emailTitle)
	{
		if (isset(self::$_emailCache[$emailTitle][$this->_languageId]))
		{
			return self::$_emailCache[$emailTitle][$this->_languageId];
		}

		self::$_preCache[$emailTitle] = true;
		self::$_emailCache[$emailTitle][$this->_languageId] = '';
		$this->_loadEmailTemplatesFromDb();

		return self::$_emailCache[$emailTitle][$this->_languageId];
	}

	/**
	 * Loads all email templates that are to be pre-cached from the DB.
	 * They will be placed on the local email cache.
	 */
	protected function _loadEmailTemplatesFromDb()
	{
		if (!self::$_preCache)
		{
			return;
		}

		$db = XenForo_Application::getDb();

		if ($this->_preCacheAllLanguages)
		{
			$languageClause = '';
		}
		else
		{
			$languageClause = 'AND language_id = ' . $db->quote($this->_languageId);
		}

		$templateResult = $db->query('
			SELECT language_id, title, template_compiled
			FROM xf_email_template_compiled
			WHERE title IN (' . $db->quote(array_keys(self::$_preCache)) . ')
				' . $languageClause . '
		');
		while ($template = $templateResult->fetch())
		{
			self::$_emailCache[$template['title']][$template['language_id']] = $template['template_compiled'];
		}

		self::$_preCache = array();
	}

	/**
	 * Gets the failure exception if there is one.
	 *
	 * @return Exception|null
	 */
	public function getFailureException()
	{
		return $this->_failureException;
	}

	/**
	 * Set up the default mail transport object. If no transport is given,
	 * the default is selected based on board configuration.
	 *
	 * @param Zend_Mail_Transport_Abstract|null $transport If specified, used as default transport; otherwise, use board config
	 */
	public static function setupTransport(Zend_Mail_Transport_Abstract $transport = null)
	{
		if (!$transport)
		{
			$transport = self::getDefaultTransport();
		}

		Zend_Mail::setDefaultTransport($transport);

		self::$_transportSetup = true;
	}

	/**
	 * Gets the transport that will be used to send XF mails.
	 * This will reflect explicit overrides that the get default method won't.
	 *
	 * @return Zend_Mail_Transport_Abstract
	 */
	public static function getTransport()
	{
		if (!self::$_transportSetup)
		{
			self::setupTransport();
		}

		return Zend_Mail::getDefaultTransport();
	}

	/**
	 * Gets the default mail transport object.
	 *
	 * @return Zend_Mail_Transport_Abstract
	 */
	public static function getDefaultTransport()
	{
		$options = XenForo_Application::get('options');

		$transportOption = $options->get('emailTransport', false);
		if ($transportOption['emailTransport'] == 'smtp')
		{
			return self::_getDefaultSmtpTransport($transportOption);
		}
		else
		{
			return self::_getDefaultSendmailTransport($transportOption);
		}
	}

	/**
	 * Get the default SMTP mail tranport object, based on the configuration in the
	 * given array.
	 *
	 * @param array $transportOption Data from option (smtpPort, smtpAuth, etc)
	 *
	 * @return Zend_Mail_Transport_Smtp
	 */
	protected static function _getDefaultSmtpTransport(array $transportOption)
	{
		$config = array();

		if (!empty($transportOption['smtpPort']) && intval($transportOption['smtpPort']) != 0)
		{
			$config['port'] = intval($transportOption['smtpPort']);
		}
		if (!empty($transportOption['smtpAuth']) && $transportOption['smtpAuth'] != 'none')
		{
			$config['auth'] = $transportOption['smtpAuth'];
			$config['username'] = (!empty($transportOption['smtpLoginUsername']) ? $transportOption['smtpLoginUsername'] : '');
			$config['password'] = (!empty($transportOption['smtpLoginPassword']) ? $transportOption['smtpLoginPassword'] : '');
		}
		if (!empty($transportOption['smtpEncrypt']) && $transportOption['smtpEncrypt'] != 'none')
		{
			$config['ssl'] = $transportOption['smtpEncrypt'];
		}

		return new Zend_Mail_Transport_Smtp($transportOption['smtpHost'], $config);
	}

	/**
	 * Get the default sendmail (built-in PHP mail()) transport object, based on the
	 * configuration in the given array.
	 *
	 * @param array $transportOption Deata from option (sendmailReturnPath, etc)
	 *
	 * @return Zend_Mail_Transport_Sendmail
	 */
	protected static function _getDefaultSendmailTransport(array $transportOption)
	{
		$config = null;

		if (!empty($transportOption['sendmailReturnPath']))
		{
			$options = XenForo_Application::get('options');

			$bounceEmailAddress = $options->bounceEmailAddress;
			if (!$bounceEmailAddress)
			{
				$bounceEmailAddress = $options->defaultEmailAddress;
			}

			if (XenForo_Helper_Email::isEmailValid($bounceEmailAddress))
			{
				$config = '-f "' . $bounceEmailAddress . '"';
			}
		}

		return new Zend_Mail_Transport_Sendmail($config);
	}

	public static function getFinalTransportForMail(Zend_Mail $mailObj, Zend_Mail_Transport_Abstract $transport)
	{
		$returnPath = $mailObj->getReturnPath();
		if ($returnPath && $transport instanceof Zend_Mail_Transport_Sendmail)
		{
			$transportOption = XenForo_Application::getOptions()->get('emailTransport', false);
			if (!empty($transportOption['sendmailReturnPath']) && XenForo_Helper_Email::isEmailValid($returnPath))
			{
				$config = '-f "' . $returnPath . '"';
			}
			else
			{
				$config = null;
			}

			$transport = new Zend_Mail_Transport_Sendmail($config);
		}

		return $transport;
	}

	/**
	 * Reset the email cache. The MAIL_CONTAINER will still be listed as
	 * pre-cacheable.
	 */
	public static function resetEmailCache()
	{
		self::$_emailCache = array();
		self::$_preCache['MAIL_CONTAINER'] = true;
	}

	/**
	 * Sets a value in the email cache.
	 *
	 * @param string $emailTitle
	 * @param integer $languageId
	 * @param string $template
	 */
	public static function setEmailCache($emailTitle, $languageId, $template)
	{
		self::$_emailCache[$emailTitle][$languageId] = $template;
	}

	/**
	 * Factory.
	 *
	 * @param string $emailTitle Title of the email template
	 * @param array $params Key-value params to pass to email template
	 * @param integer|null $languageId Language of email; if null, uses language of current user (if setup)
	 *
	 * @return XenForo_Mail
	 */
	public static function create($emailTitle, array $params, $languageId = null)
	{
		$createClass = XenForo_Application::resolveDynamicClass('XenForo_Mail', 'mail');
		return new $createClass($emailTitle, $params, $languageId);
	}
}