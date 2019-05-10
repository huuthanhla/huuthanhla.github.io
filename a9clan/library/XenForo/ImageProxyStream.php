<?php

/**
 * Class to wrap around the xf-image-proxy stream handler
 */
class XenForo_ImageProxyStream
{
	/**
	 * File pointer
	 *
	 * @var resource
	 */
	protected $_resource;

	/**
	 * Stream URI passed in
	 *
	 * @var string
	 */
	protected $_path;

	/**
	 * Length of file written
	 *
	 * @var int
	 */
	protected $_length = 0;

	/**
	 * Maximum size of file (in bytes) allowed to be cached.
	 * Files larger than this will not be retrieved or served.
	 *
	 * @var int
	 */
	protected static $_maxSize = 0;

	/**
	 * True if registered as wrapper
	 *
	 * @var bool
	 */
	protected static $_registered = false;

	/**
	 * Array of meta data related to the request (including error, etc)
	 *
	 * @var array
	 */
	protected static $_metadata = array();

	public function stream_cast($castAs)
	{
		return $this->_resource;
	}

	public function stream_close()
	{
		fclose($this->_resource);
	}

	public function stream_eof()
	{
		return feof($this->_resource);
	}

	public function stream_flush()
	{
		return fflush($this->_resource);
	}

	public function stream_open($path, $mode, $options, &$opened_path)
	{
		$filePath = self::getTempFile($path);
		self::$_metadata[$path] = array(
			'length' => 0,
			'image' => null
		);

		$fileDir = dirname($filePath);
		if (!XenForo_Helper_File::createDirectory($fileDir, true))
		{
			return false;
		}

		$this->_path = $path;
		$this->_resource = fopen($filePath, $mode, false);
		return (bool)$this->_resource;
	}

	public function stream_read($count)
	{
		return fread($this->_resource, $count);
	}

	public function stream_seek($offset, $whence)
	{
		return fseek($this->_resource, $offset, $whence);
	}

	public function stream_tell()
	{
		return ftell($this->_resource);
	}

	public function stream_truncate($size)
	{
		return ftruncate($this->_resource, $size);
	}

	public function stream_write($data)
	{
		if (!empty(self::$_metadata[$this->_path]['error']))
		{
			return false;
		}

		$addLength = strlen($data);
		if (self::$_maxSize && $this->_length + $addLength > self::$_maxSize)
		{
			self::$_metadata[$this->_path]['error'] = 'too_large';
			return false;
		}

		$success = fwrite($this->_resource, $data);
		if ($success)
		{
			$this->_length += $addLength;
			self::$_metadata[$this->_path]['length'] = $this->_length;

			if ($this->_length > 150000 && !isset(self::$_metadata[$this->_path]['image']))
			{
				if (!self::_runImageCheck($this->_path))
				{
					return false;
				}
			}
		}

		return $success;
	}

	public function unlink($path)
	{
		return unlink(self::getTempFile($path));
	}

	public function url_stat($path, $flags)
	{
		$statPath = self::getTempFile($path);
		return ($flags & STREAM_URL_STAT_QUIET ? @stat($statPath) : stat($statPath));
	}

	/**
	 * Gets the path to the written file for a stream URI
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public static function getTempFile($path)
	{
		$path = preg_replace('#^[a-z0-9_-]+://#i', '', $path);
		return XenForo_Helper_File::getTempDir() . '/' . strtr($path, '/\\.', '---') . '.temp';
	}

	/**
	 * Checks whether what has been grabbed appears to be an image.
	 * Writes to meta data if available for path
	 *
	 * @param string $path
	 * @param null|string $error Error details returned by reference
	 *
	 * @return bool
	 */
	protected static function _runImageCheck($path, &$error = null)
	{
		$filePath = self::getTempFile($path);

		$fileSize = filesize($filePath);
		if ($fileSize < 30)
		{
			$imageSize = false;
		}
		else
		{
			$imageSize = getimagesize($filePath);
		}

		if (!$imageSize)
		{
			$error = 'not_image';
		}
		else
		{
			switch ($imageSize[2])
			{
				case IMAGETYPE_GIF:
				case IMAGETYPE_PNG:
				case IMAGETYPE_JPEG:
					break;

				default:
					$error = 'invalid_type';
			}
		}

		if (isset(self::$_metadata[$path]))
		{
			self::$_metadata[$path]['image'] = $imageSize;
			if ($error)
			{
				self::$_metadata[$path]['error'] = $error;
			}
		}

		return ($error ? false : true);
	}

	/**
	 * Gets the metadata for a path
	 *
	 * @param string $path
	 *
	 * @return null|array
	 */
	public static function getMetaData($path)
	{
		if (!isset(self::$_metadata[$path]))
		{
			return null;
		}

		$metadata = self::$_metadata[$path];

		if (!isset($metadata['image']))
		{
			self::_runImageCheck($path);
		}

		// need to refetch as the image check may change it
		return self::$_metadata[$path];
	}

	/**
	 * Registers the xf-image-proxy stream wrapper
	 */
	public static function register()
	{
		if (self::$_registered)
		{
			return;
		}

		stream_wrapper_register('xf-image-proxy', __CLASS__);
		self::$_registered = true;
	}

	/**
	 * @param integer $size
	 */
	public static function setMaxSize($size)
	{
		self::$_maxSize = intval($size);
	}

	/**
	 * @return int
	 */
	public static function getMaxSize()
	{
		return self::$_maxSize;
	}
}