<?php

class XenForo_BounceParser
{
	protected $_message;

	protected $_verpBaseEmail;
	protected $_verpHmacKey;

	protected $_recipientTrusted = false;
	protected $_messageType = '';

	protected $_action;
	protected $_statusCode;
	protected $_diagnosticInfo;
	protected $_recipient;

	public static $statusMap = array(
		'9.9.9' => array('type' => 'soft', 'desc' => 'unknown_status'),
		'*.1.0' => array('type' => 'soft', 'desc' => 'unknown_address_status'),
		'*.1.1' => array('type' => 'hard', 'desc' => 'bad_destination_mailbox'),
		'*.1.2' => array('type' => 'hard', 'desc' => 'bad_destination_system'),
		'*.1.3' => array('type' => 'hard', 'desc' => 'bad_destination_mailbox_syntax'),
		'*.1.4' => array('type' => 'hard', 'desc' => 'destination_mailbox_ambiguous'),
		'*.1.5' => array('type' => 'none', 'desc' => 'destination_address_valid'),
		'*.1.6' => array('type' => 'hard', 'desc' => 'mailbox_moved'),
		'*.1.7' => array('type' => 'hard', 'desc' => 'bad_sender_mailbox_syntax'),
		'*.1.8' => array('type' => 'hard', 'desc' => 'bad_sender_system'),
		'*.2.0' => array('type' => 'soft', 'desc' => 'unknown_mailbox_status'),
		'*.2.1' => array('type' => 'hard', 'desc' => 'mailbox_disabled'),
		'*.2.2' => array('type' => 'soft', 'desc' => 'mailbox_full'),
		'*.2.3' => array('type' => 'soft', 'desc' => 'message_length_too_long'),
		'*.2.4' => array('type' => 'soft', 'desc' => 'mailing_list_expansion_problem'),
		'*.3.0' => array('type' => 'soft', 'desc' => 'unknown_system_issue'),
		'*.3.1' => array('type' => 'soft', 'desc' => 'mail_system_full'),
		'*.3.2' => array('type' => 'soft', 'desc' => 'system_not_accepting_messages'),
		'*.3.3' => array('type' => 'soft', 'desc' => 'system_not_capable_features'),
		'*.3.4' => array('type' => 'soft', 'desc' => 'message_too_big'),
		'*.3.5' => array('type' => 'soft', 'desc' => 'system_incorrectly_configured'),
		'*.4.0' => array('type' => 'soft', 'desc' => 'unknown_routing_status'),
		'*.4.1' => array('type' => 'soft', 'desc' => 'no_answer_from_host'),
		'*.4.2' => array('type' => 'soft', 'desc' => 'bad_connection'),
		'*.4.3' => array('type' => 'soft', 'desc' => 'directory_server_failure'),
		'*.4.4' => array('type' => 'soft', 'desc' => 'unable_to_route'),
		'*.4.5' => array('type' => 'soft', 'desc' => 'mail_system_congestion'),
		'*.4.6' => array('type' => 'soft', 'desc' => 'routing_loop'),
		'*.4.7' => array('type' => 'soft', 'desc' => 'delivery_time_expired'),
		'*.4.9' => array('type' => 'soft', 'desc' => 'routing_error'),
		'*.5.*' => array('type' => 'soft', 'desc' => 'protocol_status_issue'),
		'*.6.*' => array('type' => 'soft', 'desc' => 'media_status_issue'),
		'*.7.*' => array('type' => 'soft', 'desc' => 'security_status_issue'),
		'*.*.*' => array('type' => 'soft', 'desc' => 'unknown_issue'),
	);

	protected $_generalBounceStrings = array(
		'This is a permanent error',
		'could not be delivered',
		'#Delivery.+failed\s+permanently#',
		'did not reach the following',
		'was undeliverable',
		'was not delivered to',
		'permanent fatal errors',

		// need to include these to trigger bounce behavior - this is really only the case
		// for REALLY bad bounce messages
		'mailbox exceeds allowed',
		'not a valid user here'
	);

	protected $_generalDelayStrings = array(
		'message that you sent has not yet been delivered',
		'message has not yet been delivered',
		'delivery attempts will continue'
	);

	protected $_invalidMailBoxStrings = array(
		'deactivated mailbox',
		'mail-box not found',
		'mailbox not found',
		'mailbox currently suspended',
		'mailbox suspended',
		'does not exist',
		'mailbox not available',
		'mailbox unavailable',
		'no mail box available',
		'no mailbox here',
		'unknown user',
		'user unknown',
		'user is unknown',
		'user not found',
		'not a known user',
		'not our customer',
		'recipient rejected',
		'deactivated mailbox',
		'no such user',
		'no such person',
		'no mailbox found',
		'no longer on server',
		'not a valid mailbox',
		'invalid mailbox',
		'#account.+is\s+disabled#',
		'#no\s+mailbox.+currently\s+available#',
		'account has been disabled',
		'is not an active address',
		'not listed in domino directory',
		'not a valid user here',
		'name is not recognized',
		'mail receiving disabled',
		'couldn\'t be found is is invalid',
		'doesn\'t have an account',
		'#doesn\'t\s+have.+account#',
		'addresses failed'
	);

	protected $_quotaStrings = array(
		'mailbox exceeds allowed',
		'mailbox size limit exceeded',
		'quota exceeded',
		'full mailbox',
		'mailbox is full',
		'mailbox full',
		'mailfolder is full',
		'over quota'
	);

	protected $_challengeStrings = array(
		'boxbe.com',
		'bluebottle.com',
		'click on the following link',
		'requires confirmation',
		'sender not pre-approved',
		'spamarrest.com',
		'to complete this verification',
		'verification process'
	);

	protected $_autoReplyStrings = array(
		'from the office',
		'out of office',
		'out of the office',
		'on holiday',
		'on vacation',
		'out of town',
		'when I return',

		'message has been received',
		'reply as soon as possible',
		'acknowledge the receipt',

		'automated reply',
		'automated response',
		'automate response',
		'autoreply',
		'autoresponder',
	);

	public function __construct(Zend_Mail_Message $message, $verpBaseEmail = null, $verpHmacKey = null)
	{
		$this->_message = $message;
		$this->_verpBaseEmail = $verpBaseEmail;
		$this->_verpHmacKey = $verpHmacKey;

		$this->_process();
	}

	protected function _process()
	{
		$message = $this->_message;
		$textContent = null;
		$originalContent = null;
		$deliveryStatus = null;

		if ($message->isMultipart())
		{
			foreach ($message AS $part)
			{
				/** @var Zend_Mail_Part $part */
				if (!$part->headerExists('content-type'))
				{
					continue;
				}

				$contentType = $part->contentType;
				if (preg_match('#^message/delivery-status#i', $contentType))
				{
					$deliveryStatus = $part->getContent();
				}
				else if (preg_match('#^message/rfc822#i', $contentType))
				{
					$originalContent = $part->getContent();
				}
				else if (preg_match('#^text/plain#i', $contentType))
				{
					$textContent = $part->getContent();
				}
			}
		}

		if ($textContent === null)
		{
			$textContent = $message->getContent();
		}
		if ($originalContent === null)
		{
			list($textContent, $originalContent) = $this->_splitOriginalFromText($textContent);
		}

		$this->_processRecipient($textContent, $originalContent);

		if ($deliveryStatus)
		{
			$this->_processDeliveryStatus($deliveryStatus, $textContent, $originalContent);
		}
		else
		{
			$this->_processFromTextContent($textContent, $originalContent);
		}
	}

	protected function _splitOriginalFromText($textContent)
	{
		$originalContent = '';

		if (preg_match('/^(.*)--- message header[^\r\n]*(.*)$/siU', $textContent, $match))
		{
			$textContent = $match[1];
			$originalContent = $match[2];
		}
		else if (preg_match('/^(.*)\n\s*Original message[^\r\n]*(.*)$/siU', $textContent, $match))
		{
			$textContent = $match[1];
			$originalContent = $match[2];
		}
		else if (preg_match('/^(.*)---[^\r\n]*(.*)$/siU', $textContent, $match))
		{
			$textContent = $match[1];
			$originalContent = $match[2];
		}

		$textContent = trim($textContent);
		$originalContent = trim($originalContent);

		return array($textContent, $originalContent);
	}

	protected function _processRecipient($textContent, $originalContent)
	{
		if ($this->_verpHmacKey)
		{
			if (preg_match('#\n\s*X-To-Validate\s*:\s*([a-z0-9]+)\+([^\s]+)(\s|$)#i', $originalContent, $match))
			{
				$email = $match[2];
				$hmac = hash_hmac('md5', $email, $this->_verpHmacKey);

				$this->_recipientTrusted = (substr($hmac, 0, strlen($match[1])) === $match[1]);
				$this->_recipient = $email;
				return;
			}

			if ($this->_verpBaseEmail && $this->_message->headerExists('to'))
			{
				$matchRegex = str_replace('@', '\+([a-z0-9]+)\+([^@=]+=[^@=]+)@', preg_quote($this->_verpBaseEmail, '#'));
				if (preg_match("#$matchRegex#i", $this->_message->to, $verpMatch))
				{
					$verpEmail = str_replace('=', '@', $verpMatch[2]);
					$hmac = hash_hmac('md5', $verpEmail, $this->_verpHmacKey);

					$this->_recipientTrusted = (substr($hmac, 0, strlen($verpMatch[1])) === $verpMatch[1]);
					$this->_recipient = $verpEmail;
					return;
				}
			}
		}
		else
		{
			// no VERP enabled, so we need to trust the recipient that we find
			$this->_recipientTrusted = true;
		}

		if ($this->_message->headerExists('X-Failed-Recipients'))
		{
			$this->_recipient = $this->_message->getHeader('X-Failed-Recipients');
		}
		else if (preg_match('#\n\s*Return-Path\s*:\s*<([^>@]+@[^>]+)>(\s|$)#i', $originalContent, $match))
		{
			$this->_recipient = $match[2];
		}
	}

	protected function _processDeliveryStatus($deliveryStatus, $textContent, $originalContent)
	{
		$statusContent = preg_replace('#\r?\n\r?\n#', "\n", trim($deliveryStatus));
		Zend_Mime_Decode::splitMessage($statusContent, $statusFields, $null);

		foreach ($statusFields AS &$value)
		{
			if (is_array($value))
			{
				$value = reset($value);
			}
		}

		if (!empty($statusFields['action']))
		{
			$this->_action = strtolower($statusFields['action']);
			if ($this->_action == 'failed')
			{
				$this->_messageType = 'bounce';
			}
			else if ($this->_action == 'delayed')
			{
				$this->_messageType = 'delay';
			}
		}

		if (!empty($statusFields['status'])
			&& preg_match('/(\d\.\d\.\d)/', $statusFields['status'], $match)
		)
		{
			$this->_statusCode = $match[1];
		}

		if (!empty($statusFields['diagnostic-code']))
		{
			$this->_diagnosticInfo = preg_replace('#^.+;\s*#U', '', $statusFields['diagnostic-code']);

			if (!$this->_statusCode || $this->isStatusCodeAmbiguous($this->_statusCode))
			{
				if (preg_match('/(\D|^)(\d\.\d\.\d)(\D|$)/', $this->_diagnosticInfo, $match))
				{
					$this->_statusCode = $match[2];
				}
			}
		}

		if ($this->_action == 'failed' && $this->_diagnosticInfo && $this->_isMailboxInvalid($this->_diagnosticInfo))
		{
			$this->_statusCode = '5.1.1';
		}
		else if ($this->isStatusCodeAmbiguous($this->_statusCode) && $this->_diagnosticInfo && $this->_isMailboxQuotaExceeded($this->_diagnosticInfo))
		{
			$this->_statusCode = '5.2.2';
		}
		else if (
			($this->_statusCode == '4.7.0' && $this->_isChallengeResponse($textContent))
			|| ($this->isStatusCodeAmbiguous($this->_statusCode) && $this->_diagnosticInfo && $this->_isChallengeResponse($this->_diagnosticInfo))
		)
		{
			$this->_messageType = 'challenge';
			$this->_statusCode = null;
			$this->_action = null;
		}
	}

	protected function _processFromTextContent($textContent, $originalContent)
	{
		if (!$this->_messageType)
		{
			$this->_parseForBounce($textContent, $originalContent);
		}

		if (!$this->_messageType)
		{
			$this->_parseForChallenge($textContent, $originalContent);
		}

		if (!$this->_messageType)
		{
			$this->_parseForAutoReply($textContent, $originalContent);
		}
	}

	protected function _parseForBounce($textContent, $originalContent)
	{
		$list = $this->_convertStringRegexListToRegex($this->_generalBounceStrings);
		if (preg_match('#' . $list . '#i', $textContent))
		{
			$this->_messageType = 'bounce';
			$this->_action = 'failed';
			$this->_statusCode = '9.9.9'; // failure of some sort - treat it as a soft bounce
		}

		if (!$this->_messageType)
		{
			$list = $this->_convertStringRegexListToRegex($this->_generalDelayStrings);
			if (preg_match('#' . $list . '#i', $textContent))
			{
				$this->_messageType = 'delay';
				$this->_action = 'delayed';
				$this->_statusCode = '9.9.9';
			}
		}

		if ($this->_messageType)
		{
			if (preg_match('/\d\d\d\s+(\d\.\d\.\d)([^\r\n]*)(\r|\n|$)/', $textContent, $codeMatch))
			{
				$this->_statusCode = $codeMatch[1];
				$this->_diagnosticInfo = trim($codeMatch[2]);
			}
			else if (preg_match('/#(\d\.\d\.\d)/', $textContent, $codeMatch))
			{
				$this->_statusCode = $codeMatch[1];
				$this->_diagnosticInfo = '';
			}
		}

		if (preg_match('/^Hi\.\s+This\s+is\s+the/i', $textContent))
		{
			// qmail
			if (preg_match('/\n<([^>\s)]+@[^>\s)]+)>:?\s*([^\r\n]*)([\r\n]|$)/i', $textContent, $match))
			{
				$this->_diagnosticInfo = trim($match[2]);
			}
		}

		if ($this->_messageType == 'bounce' && $this->_isMailboxInvalid($textContent))
		{
			$this->_statusCode = '5.1.1';
		}
		else if ($this->_messageType == 'bounce' && $this->_isMailboxQuotaExceeded($textContent))
		{
			$this->_statusCode = '5.2.2';
		}
	}

	protected function _parseForChallenge($textContent, $originalContent)
	{
		if ($this->_isChallengeResponse($textContent))
		{
			$this->_messageType = 'challenge';
		}
	}

	protected function _parseForAutoReply($textContent, $originalContent)
	{
		$m = $this->_message;

		if (
			$m->headerExists('X-Autorespond')
			|| $m->headerExists('X-Autoreply')
			|| ($m->headerExists('Precedence') && $m->getHeader('Precedence') == 'auto_reply')
			|| ($m->headerExists('Precedence') && $m->getHeader('Precedence') == 'junk')
			|| ($m->headerExists('X-Precedence') && $m->getHeader('X-Precedence') == 'auto_reply')
			|| ($m->headerExists('Auto-Submitted') && $m->getHeader('Auto-Submitted') == 'auto-replied')
			|| ($m->headerExists('Auto-Submitted') && $m->getHeader('Auto-Submitted') == 'auto-replied (vacation)')
		)
		{
			$this->_messageType = 'autoreply';
		}
		else
		{
			$list = $this->_convertStringRegexListToRegex($this->_autoReplyStrings);
			if (preg_match('#' . $list . '#i', $textContent))
			{
				$this->_messageType = 'autoreply';
			}
		}
	}

	protected function _isMailboxInvalid($content)
	{
		$list = $this->_convertStringRegexListToRegex($this->_invalidMailBoxStrings);
		return preg_match('#' . $list . '#si', $content);
	}

	protected function _isMailboxQuotaExceeded($content)
	{
		$list = $this->_convertStringRegexListToRegex($this->_quotaStrings);
		return preg_match('#' . $list . '#si', $content);
	}

	protected function _isChallengeResponse($content)
	{
		$list = $this->_convertStringRegexListToRegex($this->_challengeStrings);
		return preg_match('#' . $list . '#si', $content);
	}

	protected function _convertStringRegexListToRegex(array $strings, $delim = '#')
	{
		$options = array();
		foreach ($strings AS $string)
		{
			if ($string[0] == $delim)
			{
				$options[] = '(' . substr($string, 1, -1) . ')';
			}
			else
			{
				$string = preg_quote($string, $delim);
				$options[] = str_replace(' ', '\s+', $string);
			}
		}

		return '(' . implode('|', $options) . ')';
	}

	public function getMessageDate()
	{
		if ($this->_message->headerExists('Date'))
		{
			try
			{
				$dt = new DateTime($this->_message->date);
				return intval($dt->format('U'));
			}
			catch (Exception $e) {}
		}

		return XenForo_Application::$time;
	}

	public function recipientTrusted()
	{
		return $this->_recipientTrusted;
	}

	public function isActionableBounce()
	{
		return ($this->isDsn() && $this->isFailure() && $this->getRecipient());
	}

	public function getMessageType()
	{
		return $this->_messageType ? $this->_messageType : 'unknown';
	}

	public function isDsn()
	{
		return ($this->_action && $this->_statusCode);
	}

	public function getAction()
	{
		return $this->_action;
	}

	public function isFailure()
	{
		return $this->_action === 'failed';
	}

	public function isDelayed()
	{
		return $this->_action === 'delayed';
	}

	public function getStatusCode()
	{
		return $this->_statusCode;
	}

	public function isStatusCodeAmbiguous($code)
	{
		return ($code == '5.0.0' || $code == '4.0.0' || $code == '9.9.9');
	}

	public function getStatusDetails()
	{
		return self::getStatusDetailsFromCode($this->_statusCode);
	}

	public function getDiagnosticInfo()
	{
		return $this->_diagnosticInfo;
	}

	public function getRecipient()
	{
		return $this->_recipient;
	}

	public function getMessage()
	{
		return $this->_message;
	}

	public static function getStatusDetailsFromCode($statusCode)
	{
		if (!$statusCode || !preg_match('#^\d\.\d\.\d$#', $statusCode))
		{
			return null;
		}

		foreach (self::$statusMap AS $code => $details)
		{
			$code = str_replace('\*', '\d+', preg_quote($code, '/'));
			if (preg_match("/^{$code}$/", $statusCode))
			{
				return $details;
			}
		}

		return self::$statusMap['*.*.*'];
	}
}