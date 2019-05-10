<?php

class XenForo_Model_MailQueue extends XenForo_Model
{
	protected static $_deferredQueued = false;

	public function insertMailQueue(Zend_Mail $mailObj)
	{
		if (!self::$_deferredQueued)
		{
			XenForo_Application::defer('MailQueue', array(), 'MailQueue');
			self::$_deferredQueued = true;
		}

		XenForo_Application::getDb()->insert('xf_mail_queue', array(
			'mail_data' => serialize($mailObj),
			'queue_date' => XenForo_Application::$time
		));

		return true;
	}

	public function hasMailQueue()
	{
		$res = $this->_getDb()->fetchOne('
			SELECT MIN(mail_queue_id)
			FROM xf_mail_queue
		');
		return (bool)$res;
	}

	public function getMailQueue($limit = 20)
	{
		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT *
				FROM xf_mail_queue
				ORDER BY queue_date
			', $limit
		), 'mail_queue_id');
	}

	public function runMailQueue($targetRunTime)
	{
		$s = microtime(true);
		$transport = XenForo_Mail::getTransport();
		$db = $this->_getDb();

		do
		{
			$queue = $this->getMailQueue($targetRunTime ? 20 : 0);

			foreach ($queue AS $id => $record)
			{
				if (!$db->delete('xf_mail_queue', 'mail_queue_id = ' . $db->quote($id)))
				{
					// already been deleted - run elsewhere
					continue;
				}

				$mailObj = @unserialize($record['mail_data']);
				if (!($mailObj instanceof Zend_Mail))
				{
					continue;
				}

				$thisTransport = XenForo_Mail::getFinalTransportForMail($mailObj, $transport);

				try
				{
					$mailObj->send($thisTransport);
				}
				catch (Exception $e)
				{
					$toEmails = implode(', ', $mailObj->getRecipients());
					XenForo_Error::logException($e, false, "Email to $toEmails failed: ");

					// pipe may be messed up now, so let's be sure to get another one
					unset($transport);
					$transport = XenForo_Mail::getTransport();
				}

				if ($targetRunTime && microtime(true) - $s > $targetRunTime)
				{
					$queue = false;
					break;
				}
			}
		}
		while ($queue);

		return $this->hasMailQueue();
	}
}