<?php

// CLI only
if (PHP_SAPI != 'cli')
{
	die('This script may only be run at the command line.');
}

$fileDir = realpath(dirname(__FILE__) . '/../../../');
chdir($fileDir);

require_once($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Error::setIgnorePendingUpgrade(true);

XenForo_Phrase::setPhrases(require($fileDir . '/install/language_en.php'));
XenForo_Template_Install::setFilePath($fileDir . '/install/templates');

XenForo_Application::get('db')->setProfiler(false); // this causes lots of memory usage in debug mode, so stop that

$dependencies = new XenForo_Dependencies_Install();
$dependencies->preLoadData();

/** @var $upgradeModel XenForo_Install_Model_Upgrade */
$upgradeModel = XenForo_Model::create('XenForo_Install_Model_Upgrade');

/** @var $installModel XenForo_Install_Model_Install */
$installModel = XenForo_Model::create('XenForo_Install_Model_Install');

$cliHelper = new XenForo_Install_CliHelper();

if ($upgradeModel->getNewestUpgradeVersionId() > XenForo_Application::$versionId)
{
	$cliHelper->triggerFatalError('An upgrade was found for a version of XenForo that is newer than the uploaded files. Please reupload all of the files for the new version and reload this page.');
}

if ($upgradeModel->getLatestUpgradeVersionId() === XenForo_Application::$versionId
	&& XenForo_Application::get('options')->currentVersionId >= XenForo_Application::$versionId)
{
	//$cliHelper->triggerFatalError('You are already running the latest version.');
}

if (class_exists('XenForo_Install_Data_FileSums'))
{
	$hashes = XenForo_Install_Data_FileSums::getHashes();
	foreach ($hashes AS $key => $hash)
	{
		if (!preg_match('#^(\./)?(install/|library/XenForo/Install/|library/XenForo/Application.php)#', $key))
		{
			unset($hashes[$key]);
		}
	}
	$fileErrors = XenForo_Helper_Hash::compareHashes($hashes);
	$hashesExist = true;
}
else
{
	$fileErrors = array();
	$hashesExist = false;
}

if ($fileErrors)
{
	$cliHelper->printWarning("Some files do not contain the expected contents:\n\t" . implode("\n\t", array_keys($fileErrors)) . "\nContinue only if you're sure.");
}

$lastCompletedVersion = $upgradeModel->getLatestUpgradeVersionId();

$cliHelper->printMessage(sprintf(
	"Current version: %s\nUpgrade target: %s (%s)",
	$lastCompletedVersion, XenForo_Application::$versionId, XenForo_Application::$version
), 2);

do
{
	$response = $cliHelper->askQuestion('Are you sure you want to upgrade? [y/n]');
	if ($cliHelper->validateYesNoAnswer($response, $continue))
	{
		if (!$continue)
		{
			$cliHelper->triggerFatalError("You chose to not continue with the upgrade. Process terminated.");
		}
		break;
	}
}
while (true);

if ($lastCompletedVersion < XenForo_Application::$versionId)
{
	$controller = new XenForo_Install_Controller_Upgrade(
		new Zend_Controller_Request_Http(),
		new Zend_Controller_Response_Http(),
		new XenForo_RouteMatch()
	);

	do
	{
		$nextVersionId = $upgradeModel->getNextUpgradeVersionId($lastCompletedVersion);
		$upgrade = $nextVersionId ? $upgradeModel->getUpgrade($nextVersionId) : false;
		if (!$upgrade)
		{
			break;
		}

		$step = 1;
		$position = 0;
		$data = array();
		$versionName = $upgrade->getVersionName();

		while (method_exists($upgrade, 'step' . $step))
		{
			if ($position)
			{
				$cliHelper->printStatus("Running upgrade to $versionName, step $step ($position)...");
			}
			else
			{
				$cliHelper->printStatus("Running upgrade to $versionName, step $step...");
			}

			$result = $upgrade->{'step' . $step}($position, $data, $controller);
			if ($result instanceof XenForo_ControllerResponse_Abstract)
			{
				$cliHelper->clearStatus();
				$cliHelper->triggerFatalError('This step must be completed via the web interface.');
			}
			else if ($result === 'complete')
			{
				$cliHelper->clearStatus(null, "Running upgrade to $versionName, step $step... done.");
				break;
			}
			else if ($result === true || $result === null)
			{
				$cliHelper->clearStatus(null, "Running upgrade to $versionName, step $step... done.");

				$step++;
				$position = 0;
				$data = array();
			}
			else if (is_array($result))
			{
				// stay on the same step
				$position = $result[0];
				$data = !empty($result[2]) ? $result[2] : array();
			}
			else
			{
				$cliHelper->clearStatus();
				$cliHelper->triggerFatalError('The upgrade step returned an unexpected result.');
			}
		}

		$upgradeModel->insertUpgradeLog($nextVersionId);
		$lastCompletedVersion = $nextVersionId;
	}
	while (true);

	$upgradeModel->insertUpgradeLog();
}

$cliHelper->printMessage("Rebuilding / importing data...");

/** @var $deferModel XenForo_Model_Deferred */
$deferModel = XenForo_Model::create('XenForo_Model_Deferred');

$installModel->insertDeferredRebuild();

while ($deferModel->runByUniqueKey('installUpgradeRebuild', null, $status))
{
	$cliHelper->printStatus($status);
}

$cliHelper->clearStatus();

if ($upgradeModel->getLatestUpgradeVersionId() === XenForo_Application::$versionId)
{
	$upgradeModel->updateVersion();

	$schemaErrors = $upgradeModel->getDefaultSchemaErrors();
	if ($schemaErrors)
	{
		$cliHelper->printMessage("Upgrade completed but errors were found:");
		foreach ($schemaErrors AS $error)
		{
			$cliHelper->printMessage("\t* $error");
		}
		$cliHelper->printMessage("This is likely caused by an add-on conflict. You may need to restore a backup, remove the offending add-on data from the database, and retry the upgrade. Contact support if you are not sure how to proceed.");
	}
	else
	{
		$cliHelper->printMessage("Upgrade completed successfully!");
	}
}
else
{
	$cliHelper->printMessage("Upgrade failed to complete!");
}