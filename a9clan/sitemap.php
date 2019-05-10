<?php

$startTime = microtime(true);
$fileDir = dirname(__FILE__);

require($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);

$dependencies = new XenForo_Dependencies_Public();
$dependencies->preLoadData();

/** @var XenForo_Model_Sitemap $sitemapModel */
$sitemapModel = XenForo_Model::create('XenForo_Model_Sitemap');
$sitemap = $sitemapModel->getCurrentSitemap();
if (!$sitemap)
{
	header('Content-Type: text/plain; charset=utf-8', true, 404);
	echo 'no sitemap';
	exit;
}

$counter = isset($_GET['c']) && is_string($_GET['c']) ? intval($_GET['c']) : null;

if (!$counter)
{
	if ($sitemap['file_count'] > 1)
	{
		header('Content-Type: application/xml; charset=utf-8');
		header('Content-Disposition: inline; filename="sitemap-index.xml"');
		echo $sitemapModel->buildSitemapIndex($sitemap);
		exit;
	}

	$counter = 1;
}

$fileName = $sitemapModel->getSitemapFileName($sitemap['sitemap_id'], $counter, $sitemap['is_compressed']);
if (file_exists($fileName))
{
	if ($sitemap['is_compressed'])
	{
		header('Content-Type: application/gzip; charset=utf-8');
		header('Content-Disposition: attachment; filename="sitemap-' . $counter . '.xml.gz"');
	}
	else
	{
		header('Content-Type: application/xml; charset=utf-8');
		header('Content-Disposition: inline; filename="sitemap-' . $counter . '.xml"');
	}

	readfile($fileName);
}
else
{
	header('Content-Type: text/plain; charset=utf-8', true, 404);
	echo 'invalid sitemap file';
}