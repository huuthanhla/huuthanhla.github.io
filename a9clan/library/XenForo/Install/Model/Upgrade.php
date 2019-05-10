<?php

class XenForo_Install_Model_Upgrade extends XenForo_Model
{
	public function insertUpgradeLog($versionId = null, $type = 'upgrade', $userId = null)
	{
		if ($versionId === null)
		{
			$versionId = XenForo_Application::$versionId;
		}

		if ($userId === null)
		{
			$userId = XenForo_Visitor::getUserId();
		}

		$this->_getDb()->query('
			INSERT IGNORE INTO xf_upgrade_log
				(version_id, completion_date, user_id, log_type)
			VALUES
				(?, ?, ?, ?)
		', array($versionId, XenForo_Application::$time, $userId, $type));
	}

	public function updateVersion()
	{
		$this->getModelFromCache('XenForo_Model_Option')->updateOptions(
			array('currentVersionId' => XenForo_Application::$versionId)
		);
	}

	public function getLatestUpgradeVersionId()
	{
		return $this->_getDb()->fetchOne('
			SELECT MAX(version_id)
			FROM xf_upgrade_log
		');
	}

	public function getRemainingUpgradeVersionIds($lastCompletedVersion)
	{
		$searchDir = XenForo_Application::getInstance()->getRootDir() . '/library/XenForo/Install/Upgrade';

		$upgrades = array();
		foreach (glob($searchDir . '/*.php') AS $file)
		{
			$file = basename($file);

			switch($file)
			{
				case '1010031-100b1.php': // this was badly named - make sure it's always skipped so the right one runs
					continue 2;
			}

			$versionId = intval($file);
			if (!$versionId)
			{
				continue;
			}

			$upgrades[] = $versionId;
		}

		sort($upgrades, SORT_NUMERIC);

		foreach ($upgrades AS $key => $upgrade)
		{
			if ($upgrade > $lastCompletedVersion)
			{
				return array_slice($upgrades, $key);
			}
		}

		return array();
	}

	public function getNextUpgradeVersionId($lastCompletedVersion)
	{
		$upgrades = $this->getRemainingUpgradeVersionIds($lastCompletedVersion);
		return reset($upgrades);
	}

	public function getNewestUpgradeVersionId()
	{
		$upgrades = $this->getRemainingUpgradeVersionIds(0);
		return end($upgrades);
	}

	public function getUpgrade($versionId)
	{
		$versionId = intval($versionId);
		if (!$versionId)
		{
			throw new XenForo_Exception('No upgrade version ID specified.');
		}

		$searchDir = XenForo_Application::getInstance()->getRootDir() . '/library/XenForo/Install/Upgrade';

		$matches = glob($searchDir . '/' . $versionId . '*.php');
		foreach ($matches AS $file)
		{
			$file = basename($file);
			if (intval($file) == $versionId)
			{
				require($searchDir . '/' . $file);
				$class = 'XenForo_Install_Upgrade_' . intval($file);

				return new $class();
			}
		}

		throw new XenForo_Exception('Could not find the specified upgrade.');
	}

	public function setupVisitorFromUpgradeCookie()
	{
		$cookie = XenForo_Helper_Cookie::getCookie('upgrade');
		if (!$cookie)
		{
			return false;
		}

		/** @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');

		$userId = $userModel->loginUserByRememberCookie($cookie);
		if ($userId)
		{
			XenForo_Visitor::setup($userId);
		}

		return $userId;
	}

	public function setUpgradeCookie($userId)
	{
		/** @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');

		$value = $userModel->getUserRememberKeyForCookie($userId);
		if (!$value)
		{
			return false;
		}

		return XenForo_Helper_Cookie::setCookie('upgrade', $value, 0, true);
	}

	public function getDefaultSchemaErrors()
	{
		if (class_exists('XenForo_Install_Data_FileSums'))
		{
			$fileLookup = XenForo_Install_Data_FileSums::getHashes();
		}
		else
		{
			$fileLookup = null;
		}

		return $this->runSchemaCompare(
			'XenForo_DataWriter',
			XenForo_Autoloader::getInstance()->getRootDir() . '/XenForo/DataWriter',
			$fileLookup
		);
	}

	public function runSchemaCompare($dwClassPrefix, $dwClassDir, array $fileLookup = null)
	{
		$db = $this->_getDb();
		$tables = array_fill_keys($db->fetchCol('SHOW TABLES'), true);
		$errors = array();

		$dwClasses = $this->_findSchemaClasses($dwClassPrefix, $dwClassDir, $fileLookup);
		foreach ($dwClasses AS $class)
		{
			if (!class_exists($class))
			{
				continue;
			}

			$reflection = new ReflectionClass($class);
			if (!$reflection->isInstantiable() || !$reflection->isSubclassOf('XenForo_DataWriter'))
			{
				continue;
			}

			$dw = XenForo_DataWriter::create($class);
			foreach ($dw->getFields() AS $table => $fields)
			{
				if (!isset($tables[$table]))
				{
					$errors[$table] = "Table $table missing.";
					continue;
				}

				$columns = $this->fetchAllKeyed('
					SHOW COLUMNS FROM ' . $db->quoteIdentifier($table) . '
				', 'Field');

				foreach (array_keys($fields) AS $field)
				{
					if (!isset($columns[$field]))
					{
						$errors["$table.$field"] = "Column $table.$field missing.";
					}
				}
			}
		}

		return $errors;
	}

	protected function _findSchemaClasses($classPrefix, $searchDir, array $fileLookup = null)
	{
		$searchDir = rtrim($searchDir, '/\\');
		$dir = opendir($searchDir);
		if (!$dir)
		{
			return array();
		}

		$output = array();
		while (($entry = readdir($dir)) !== false)
		{
			if ($entry == '.' || $entry == '..')
			{
				continue;
			}

			$fullPath = "$searchDir/$entry";

			if ($fileLookup !== null)
			{
				$testFile = str_replace(XenForo_Autoloader::getInstance()->getRootDir(), 'library', $fullPath);
				if (!isset($fileLookup[$testFile]))
				{
					// this file doesn't exist any more - likely a left over from a previous version
					continue;
				}
			}

			if (preg_match('#^([a-z0-9_]+)\.php$#i', $entry, $match))
			{
				$output[] = $classPrefix . '_' . $match[1];
			}
			else if (is_dir($fullPath))
			{
				$output = array_merge($output,
					$this->_findSchemaClasses("{$classPrefix}_{$entry}", $fullPath)
				);
			}
		}

		return $output;
	}

	public function isCliUpgradeRecommended()
	{
		$existingVersion = $this->_getDb()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'currentVersionId'
		");
		if ($existingVersion)
		{
			$diff = floor(XenForo_Application::$versionId / 10000) - floor($existingVersion / 10000);
			if ($diff == 0)
			{
				// upgrading in the same branch (1.3.0 -> 1.3.1 for example). Web upgrader should be fine in general
				return false;
			}
		}

		$totals = $this->_getDb()->fetchOne("
			SELECT data_value
			FROM xf_data_registry
			WHERE data_key = 'boardTotals'
		");
		if (!$totals)
		{
			return false;
		}

		$totals = @unserialize($totals);
		if (!$totals)
		{
			return false;
		}

		if (!empty($totals['messages']) && $totals['messages'] >= 500000)
		{
			return true;
		}

		if (!empty($totals['users']) && $totals['users'] >= 50000)
		{
			return true;
		}

		return false;
	}

	public function getCliCommand()
	{
		$rootDir = XenForo_Autoloader::getInstance()->getRootDir();
		$filePath = str_replace('/', DIRECTORY_SEPARATOR, $rootDir . '/XenForo/Install/run-upgrade.php');

		$filePath = @escapeshellarg($filePath);
		if (!$filePath)
		{
			// I've seen servers disable this function...
			return false;
		}

		return 'php ' . $filePath;
	}
}