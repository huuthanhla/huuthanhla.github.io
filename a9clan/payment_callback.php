<?php

$startTime = microtime(true);
$fileDir = dirname(__FILE__);

require($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);

$deps = new XenForo_Dependencies_Public();
$deps->preLoadData();

$response = new Zend_Controller_Response_Http();
$processor = new XenForo_UserUpgradeProcessor_PayPal();
$processor->initCallbackHandling(new Zend_Controller_Request_Http());

$logExtra = array();
$logMessage = false;

try
{
	if (!$processor->validateRequest($logMessage))
	{
		$logType = 'error';

		$response->setHttpResponseCode(500);
	}
	else if (!$processor->validatePreConditions($logMessage))
	{
		$logType = 'error';
	}
	else
	{
		$logType = 'info';
		$logMessage = $processor->processTransaction();
	}

	if (is_array($logMessage))
	{
		$temp = $logMessage;
		list($logType, $logMessage) = $temp;
	}
}
catch (Exception $e)
{
	$response->setHttpResponseCode(500);
	XenForo_Error::logException($e);

	$logType = 'error';
	$logMessage = 'Exception: ' . $e->getMessage();
	$logExtra['_e'] = $e;
}

$processor->log($logType, $logMessage, $logExtra);

$response->setBody(htmlspecialchars($logMessage));
$response->sendResponse();