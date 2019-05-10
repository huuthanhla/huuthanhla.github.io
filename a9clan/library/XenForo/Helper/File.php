<?php

/**
 * Helper for file-related functions.
 *
 * @package XenForo_Helper
 */
abstract class XenForo_Helper_File
{
	/**
	 * Recursively creates directories until the full path is created.
	 *
	 * @param string $path Directory path to create
	 * @param boolean $createIndexHtml If true, creates an index.html file in the created directory
	 *
	 * @return boolean True on success
	 */
	public static function createDirectory($path, $createIndexHtml = false)
	{
		$path = rtrim($path, DIRECTORY_SEPARATOR);
		$path .= DIRECTORY_SEPARATOR;

		if (file_exists($path) && is_dir($path))
		{
			return true;
		}

		$path = str_replace('\\', '/', $path);
		$path = rtrim($path, '/');
		$parts = explode('/', $path);
		$pathPartCount = count($parts);
		$partialPath = '';

		$rootDir = XenForo_Application::getInstance()->getRootDir();

		// find the "lowest" part that exists (and is a dir)...
		for ($i = $pathPartCount - 1; $i >= 0; $i--)
		{
			$partialPath = implode('/', array_slice($parts, 0, $i + 1));
			if ($partialPath == $rootDir)
			{
				return false; // can't go above the root dir
			}

			if (file_exists($partialPath))
			{
				if (!is_dir($partialPath))
				{
					return false;
				}
				else
				{
					break;
				}
			}
		}
		if ($i < 0)
		{
			return false;
		}

		$i++; // skip over the last entry (as it exists)

		// ... now create directories for anything below it
		for (; $i < $pathPartCount; $i++)
		{
			$partialPath .= '/' . $parts[$i];
			if (!file_exists($partialPath))
			{
				try
				{
					if (!mkdir($partialPath))
					{
						return false;
					}
				}
				catch (Exception $e)
				{
					if (strpos($e->getMessage(), 'File exists') !== false)
					{
						// this means the directory already exists - race condition, we're ok
					}
					else
					{
						throw $e;
					}
				}
			}

			XenForo_Helper_File::makeWritableByFtpUser($partialPath);

			if ($createIndexHtml)
			{
				$fp = @fopen($partialPath . '/index.html', 'w');
				if ($fp)
				{
					fwrite($fp, ' ');
					fclose($fp);

					XenForo_Helper_File::makeWritableByFtpUser($partialPath . '/index.html');
				}
			}
		}

		return true;
	}

	/**
	 * Gets a file's extension in lower case. This only includes the last
	 * extension (eg, x.tar.gz -> gz).
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	public static function getFileExtension($filename)
	{
		return strtolower(substr(strrchr($filename, '.'), 1));
	}

	/**
	 * Gets the name of a temporary directory where files can be written.
	 *
	 * @return string
	 */
	public static function getTempDir()
	{
		return self::getInternalDataPath() . '/temp';
	}

	protected static $_chmodDirectory = null;
	protected static $_chmodFile = null;

	/**
	 * Ensures that the specified file can be written to by the FTP user.
	 * This generally doesn't need to do anything if PHP is running via
	 * some form of suexec. It's primarily needed when running as apache.
	 *
	 * @param string $file
	 * @return boolean
	 */
	public static function makeWritableByFtpUser($file)
	{
		if (self::$_chmodDirectory === null)
		{
			if (XenForo_Application::isRegistered('config'))
			{
				$chmod = XenForo_Application::get('config')->chmodWritableValue;
				if ($chmod)
				{
					self::$_chmodDirectory = XenForo_Application::get('config')->chmodWritableValue | 0111;
					self::$_chmodFile = XenForo_Application::get('config')->chmodWritableValue;
				}
			}

			if (!self::$_chmodFile)
			{
				$selfWritable = null;
				if (PHP_SAPI == 'cli')
				{
					$uid = false;
					if (function_exists('posix_getuid'))
					{
						$uid = @posix_getuid();
					}
					else
					{
						$tempFile = tempnam(self::getTempDir(), 'xf');
						if ($tempFile)
						{
							$uid = fileowner($tempFile);
							@unlink($tempFile);
						}
					}

					if ($uid !== false)
					{
						$lock = self::getInternalDataPath() . '/install-lock.php';
						if (file_exists($lock))
						{
							// if we're running as who created the lock file,
							// then the web access is likely running with the same user
							$selfWritable = ($uid == fileowner($lock));
						}
						else if ($uid == 0)
						{
							// if we're root, just force w+w
							$selfWritable = false;
						}
					}
				}

				if ($selfWritable === null)
				{
					$selfWritable = @is_writable(__FILE__);
				}

				if ($selfWritable)
				{
					// writable - probably owned by ftp user already
					self::$_chmodDirectory = 0755;
					self::$_chmodFile = 0644;
				}
				else
				{
					// not writable, so file is probably owned by "nobody", need to w+w it
					self::$_chmodDirectory = 0777;
					self::$_chmodFile = 0666;
				}
			}
		}

		if (@is_readable($file))
		{
			return @chmod($file, @is_file($file) ? self::$_chmodFile : self::$_chmodDirectory);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Performs a cross-stream/device safe rename by falling back
	 * to a copy+delete if needed.
	 *
	 * @param string $source
	 * @param string $destination
	 *
	 * @return bool
	 */
	public static function safeRename($source, $destination)
	{
		try
		{
			if (is_uploaded_file($source))
			{
				$success = move_uploaded_file($source, $destination);
			}
			else
			{
				$success = rename($source, $destination);
			}
		}
		catch (Exception $e)
		{
			// possibly a perm problem, but may be an issue moving across streams, so copy+delete instead
			$success = false;
		}

		if (!$success)
		{
			$success = copy($source, $destination);
			if ($success)
			{
				@unlink($source);
			}
		}

		if ($success)
		{
			XenForo_Helper_File::makeWritableByFtpUser($destination);
		}

		return $success;
	}

	/**
	 * Gets the path to the internal data directory (internal_data).
	 * This directory can be moved above the web root.
	 *
	 * @return string Absolute path
	 */
	public static function getInternalDataPath()
	{
		if (!XenForo_Application::isRegistered('config'))
		{
			$path = 'internal_data';
		}
		else
		{
			$path = XenForo_Application::get('config')->internalDataPath;
		}

		if (preg_match('#^(/|\\\\|[a-z0-9]+:)#i', $path))
		{
			// absolute path
			return $path;
		}
		else
		{
			return XenForo_Application::getInstance()->getRootDir() . '/' . $path;
		}
	}

	/**
	 * Gets the path to the external data directory (data)
	 * This directory can NOT be moved above the web root.
	 *
	 * @return string Absolute path
	 */
	public static function getExternalDataPath()
	{
		if (!XenForo_Application::isRegistered('config'))
		{
			$path = 'data';
		}
		else
		{
			$path = XenForo_Application::get('config')->externalDataPath;
		}

		if (preg_match('#^(/|\\\\|[a-z0-9]+:)#i', $path))
		{
			// absolute path
			return $path;
		}
		else
		{
			return XenForo_Application::getInstance()->getRootDir() . '/' . $path;
		}
	}

	/**
	 * Method for writing out a file log.
	 *
	 * @param string $logName 'foo' will write to {internalDataPath}/foo.log
	 * @param string $logEntry The string to write into the log. Line break not required.
	 * @param boolean $append Append the log entry to the end of the existing log. Otherwise, start again.
	 *
	 * @return boolean True on successful log write
	 */
	public static function log($logName, $logEntry, $append = true)
	{
		$logName = preg_replace('/[^a-z0-9._-]/i', '', $logName);

		if ($fp = @fopen(self::getInternalDataPath() . '/' . $logName . '.log', ($append ? 'a' : 'w')))
		{
			fwrite($fp, date('Y-m-d H:i:s') . ' ' . $logEntry . "\n");
			fclose($fp);

			return true;
		}

		return false;
	}
}