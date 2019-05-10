<?php

class XenForo_Helper_Hash
{
	/**
	 * Return an array containing the MD5sum of every file with the given $fileExtensions
	 * within the given $path, excluding any directories matching $excludeDirs.
	 *
	 * @param string $path
	 * @param array $fileExtensions
	 * @param array $excludeDirs
	 *
	 * @return array [filepath => md5, ...]
	 */
	public static function hashDirectory($path, array $fileExtensions, array $exclude = array())
	{
		$fileHashes = array();

		if ($handle = @opendir($path))
		{
			while ($file = readdir($handle))
			{
				if ($file{0} != '.')
				{
					$filePath = "$path/$file";

					if (!in_array($filePath, $exclude))
					{
						if (is_dir($filePath))
						{
							$fileHashes = array_merge($fileHashes, self::hashDirectory($filePath, $fileExtensions, $exclude));
						}
						else if (in_array(strrchr($file, '.'), $fileExtensions))
						{
							$fileHashes[preg_replace('#^\./#', '', $filePath)] = self::getFileContentsHash(
								file_get_contents($filePath)
							);
						}
					}
				}
			}
			closedir($handle);
		}

		return $fileHashes;
	}

	/**
	 * Compares the hashes of a list of files with what is actually on the disk.
	 *
	 * @param array $hashes [file] => hash
	 *
	 * @return array List of errors, [file] => missing or mismatch
	 */
	public static function compareHashes(array $hashes)
	{
		$cwd = getcwd();
		chdir(XenForo_Application::getInstance()->getRootDir());

		$errors = array();

		foreach ($hashes AS $file => $hash)
		{
			if (file_exists($file))
			{
				if (XenForo_Helper_Hash::getFileContentsHash(file_get_contents($file)) != $hash)
				{
					$errors[$file] = 'mismatch';
				}
			}
			else
			{
				$errors[$file] = 'missing';
			}
		}

		chdir($cwd);

		return $errors;
	}

	/**
	 * Hashes the content of a file in a line-ending agnostic way.
	 *
	 * @param string $contents Contents of file
	 *
	 * @return string Hash of contents
	 */
	public static function getFileContentsHash($contents)
	{
		$contents = str_replace("\r", '', $contents);
		return md5($contents);
	}

	/**
	 * Returns the text of a PHP class called $className that includes a static method
	 * called getSums(), which returns an the single-dimension associative array $fileHashes.
	 *
	 * @param string $className
	 * @param array $fileHashes
	 *
	 * @return string
	 */
	public static function getHashClassCode($className, array $fileHashes)
	{
		return '<?php

class ' . $className . '
{
	public static function getHashes()
	{
		return ' . var_export($fileHashes, true) . ';
	}
}';
	}
}