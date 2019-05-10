<?php

$startTime = microtime(true);
$fileDir = dirname(__FILE__);

@set_time_limit(120);
ignore_user_abort(true);

require($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);

$dependencies = new XenForo_Dependencies_Public();
$dependencies->preLoadData();

/** @var XenForo_Model_Deferred $deferredModel */
$deferredModel = XenForo_Model::create('XenForo_Model_Deferred');

$deferredModel->setNextDeferredTime(XenForo_Application::$time + 30);

$remaining = $deferredModel->run(false);
$output = array('moreDeferred' => ($remaining ? true : false));

header('Content-Type: application/json; charset=UTF-8');
header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
echo json_encode($output);