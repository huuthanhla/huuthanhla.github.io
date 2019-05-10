<?php

class XenForo_Model_EmailBounce extends XenForo_Model
{
	public function processBounceEmail($rawMessage)
	{
		$message = new Zend_Mail_Message(array('raw' => $rawMessage));

		$options = XenForo_Application::getOptions();
		$bounce = new XenForo_BounceParser($message,
			$options->enableVerp ? $options->bounceEmailAddress : null,
			XenForo_Application::getConfig()->globalSalt
		);

		$recipient = $bounce->getRecipient();
		if ($recipient)
		{
			$userId = $this->_getDb()->fetchOne('SELECT user_id FROM xf_user WHERE email = ?', $recipient);
			if (!$userId)
			{
				$userId = null;
			}
		}
		else
		{
			$userId = null;
		}

		$action = '';

		if ($userId && $bounce->isActionableBounce())
		{
			if ($bounce->recipientTrusted())
			{
				$details = $bounce->getStatusDetails();
				if ($details)
				{
					$action = $this->takeBounceAction($userId, $details['type'], $bounce->getMessageDate());
				}
			}
			else
			{
				$action = 'untrusted';
			}
		}

		return $this->_logBounceMessage($userId, $action, $bounce, $rawMessage);
	}

	public function takeBounceAction($userId, $bounceType, $bounceDate)
	{
		if (!$userId)
		{
			return '';
		}

		if ($bounceType == 'hard')
		{
			$this->triggerUserBounceAction($userId);

			return 'hard';
		}
		else if ($bounceType == 'soft')
		{
			$this->_getDb()->query("
				INSERT INTO xf_email_bounce_soft
					(user_id, bounce_date, bounce_total)
				VALUES
					(?, ?, 1)
				ON DUPLICATE KEY UPDATE
					bounce_total = bounce_total + 1
			", array($userId, gmdate('Y-m-d', $bounceDate)));

			if ($this->hasSoftBouncedTooMuch($userId))
			{
				$this->triggerUserBounceAction($userId);

				return 'soft_hard';
			}

			return 'soft';
		}

		return '';
	}

	public function triggerUserBounceAction($userId)
	{
		$user = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
		if ($user->setExistingData($userId))
		{
			if ($user->get('user_state') == 'valid'
				&& !$user->get('is_moderator')
				&& !$user->get('is_admin')
				&& !$user->get('is_staff')
			)
			{
				$user->set('user_state', 'email_bounce');
				$user->save();
			}
		}
	}

	protected function _logBounceMessage($userId, $action, XenForo_BounceParser $bounce, $rawMessage)
	{
		$this->_getDb()->insert('xf_email_bounce_log', array(
			'log_date' => XenForo_Application::$time,
			'email_date' => $bounce->getMessageDate(),
			'message_type' => $bounce->getMessageType(),
			'action_taken' => $action,
			'user_id' => $userId,
			'recipient' => $bounce->getRecipient(),
			'raw_message' => $rawMessage,
			'status_code' => $bounce->getStatusCode(),
			'diagnostic_info' => $bounce->getDiagnosticInfo()
		));
		return $this->_getDb()->lastInsertId();
	}

	public function getEmailBounceLogById($id)
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM xf_email_bounce_log
			WHERE bounce_id = ?
		", $id);
	}

	public function prepareEmailBounceConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['message_type']))
		{
			$sqlConditions[] = 'email_bounce.message_type = ' . $db->quote($conditions['message_type']);
		}

		if (!empty($conditions['action_taken']))
		{
			$sqlConditions[] = 'email_bounce.action_taken = ' . $db->quote($conditions['action_taken']);
		}

		if (!empty($conditions['user_id']))
		{
			$sqlConditions[] = 'email_bounce.user_id = ' . $db->quote($conditions['user_id']);
		}

		if (!empty($conditions['recipient']))
		{
			$sqlConditions[] = 'email_bounce.recipient = ' . $db->quote($conditions['recipient']);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function getEmailBounceLogs(array $conditions = array(), array $fetchOptions = array())
	{
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		$whereConditions = $this->prepareEmailBounceConditions($conditions, $fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			"
				SELECT email_bounce.*, user.*
				FROM xf_email_bounce_log AS email_bounce
				LEFT JOIN xf_user AS user ON (user.user_id = email_bounce.user_id)
				WHERE $whereConditions
				ORDER BY email_bounce.log_date DESC
			", $limitOptions['limit'], $limitOptions['offset']
		), 'bounce_id');
	}

	public function countEmailBounceLogs(array $conditions = array())
	{
		$fetchOptions = array();
		$whereConditions = $this->prepareEmailBounceConditions($conditions, $fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM xf_email_bounce_log AS email_bounce
			LEFT JOIN xf_user AS user ON (user.user_id = email_bounce.user_id)
			WHERE $whereConditions
		");
	}

	public function countRecentSoftBounces($userId, $numberOfDays)
	{
		$cutOffTimestamp = XenForo_Application::$time - intval($numberOfDays) * 86400;
		$cutOff = gmdate('Y-m-d', $cutOffTimestamp);

		$result = $this->_getDb()->fetchRow("
			SELECT COUNT(DISTINCT bounce_date) AS unique_days,
				DATEDIFF(MAX(bounce_date), MIN(bounce_date)) AS days_between,
				SUM(bounce_total) AS bounce_total
			FROM xf_email_bounce_soft
			WHERE user_id = ?
				AND bounce_date > ?
		", array($userId, $cutOff));

		if (!$result || !$result['unique_days'])
		{
			return array(
				'unique_days' => 0,
				'days_between' => 0,
				'bounce_total' => 0
			);
		}
		else
		{
			return $result;
		}
	}

	public function hasSoftBouncedTooMuch($userId)
	{
		$bounces = $this->countRecentSoftBounces($userId, 30);
		if (!$bounces['bounce_total'])
		{
			return false;
		}

		$thresholds = XenForo_Application::getOptions()->emailSoftBounceThreshold;
		return (
			$bounces['bounce_total'] >= $thresholds['bounce_total']
			&& $bounces['unique_days'] >= $thresholds['unique_days']
			&& $bounces['days_between'] >= $thresholds['days_between']
		);
	}

	public function pruneEmailBounceLogs($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = XenForo_Application::$time - 86400 * 30;
		}

		$db = $this->_getDb();
		return $db->delete('xf_email_bounce_log', 'log_date < ' . $db->quote($cutOff));
	}

	public function pruneSoftBounceHistory($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = XenForo_Application::$time - 86400 * 30;
		}
		$date = new DateTime("@$cutOff", new DateTimeZone('UTC'));
		$sqlDate = $date->format('Y-m-d');

		$db = $this->_getDb();
		return $db->delete('xf_email_bounce_soft', 'bounce_date < ' . $db->quote($sqlDate));
	}

	/**
	 * @return Zend_Mail_Storage_Abstract|null
	 *
	 * @throws Exception
	 */
	public function openBounceHandlerConnection()
	{
		if (!XenForo_Application::getOptions()->bounceEmailAddress)
		{
			return null;
		}

		$handler = XenForo_Application::getOptions()->emailBounceHandler;
		if (!$handler || empty($handler['enabled']))
		{
			return null;
		}

		$config = array(
			'host' => $handler['host'],
			'user' => $handler['username'],
			'password' => $handler['password']
		);
		if ($handler['port'])
		{
			$config['port'] = intval($handler['port']);
		}
		if ($handler['encryption'])
		{
			$config['ssl'] = strtoupper($handler['encryption']);
		}

		if ($handler['type'] == 'pop3')
		{
			$connection = new Zend_Mail_Storage_Pop3($config);
		}
		else if ($handler['type'] == 'imap')
		{
			$connection = new Zend_Mail_Storage_Imap($config);
		}
		else
		{
			throw new Exception("Unknown email handler $handler[type]");
		}

		return $connection;
	}
}