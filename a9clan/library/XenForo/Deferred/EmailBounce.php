<?php

class XenForo_Deferred_EmailBounce extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		/* @var $emailModel XenForo_Model_EmailBounce */
		$emailModel = XenForo_Model::create('XenForo_Model_EmailBounce');

		if (!isset($data['start']))
		{
			$data['start'] = time();
		}

		$s = microtime(true);

		try
		{
			$connection = $emailModel->openBounceHandlerConnection();
			if (!$connection)
			{
				return false;
			}
		}
		catch (Zend_Mail_Exception $e)
		{
			XenForo_Error::logException($e);
			return false;
		}

		$total = $connection->countMessages();
		if (!$total)
		{
			return false;
		}

		$finished = true;

		for ($messageId = $total, $i = 0; $messageId > 0; $messageId--, $i++)
		{
			if ($i > 0 && $targetRunTime && (microtime(true) - $s) >= $targetRunTime)
			{
				$finished = false;
				break;
			}
			$headers = $connection->getRawHeader($messageId);
			$content = $connection->getRawContent($messageId);

			$connection->removeMessage($messageId);

			$rawMessage = trim($headers) . "\r\n\r\n" . trim($content);
			$emailModel->processBounceEmail($rawMessage);
		}

		$connection->close();

		if ($finished)
		{
			return false;
		}
		else
		{
			if (time() - $data['start'] > 60 * 30)
			{
				// don't let a single run of this run for more than 30 minutes
				return false;
			}

			return $data;
		}
	}
}