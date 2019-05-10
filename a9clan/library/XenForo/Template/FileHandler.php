<?php

class XenForo_Template_FileHandler
{
	protected static $_instance;

	protected $_path = null;

	protected function __construct()
	{
		$this->_path = XenForo_Helper_File::getInternalDataPath() . '/templates';
	}

	public static function getInstance()
	{
		if (!self::$_instance)
		{
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Get the file name of the specified template
	 *
	 * @param string $title
	 * @param integer $styleId
	 * @param integer $languageId
	 *
	 * @return string
	 */
	public static function get($title, $styleId, $languageId)
	{
		return self::getInstance()->_getFileName($title, $styleId, $languageId);
	}

	/**
	 * Save the specified template
	 *
	 * @param string $title
	 * @param integer $styleId
	 * @param integer $languageId
	 * @param string $template
	 *
	 * @return string $filename
	 */
	public static function save($title, $styleId, $languageId, $template)
	{
		return self::getInstance()->_saveTemplate($title, $styleId, $languageId, '<?php if (!class_exists(\'XenForo_Application\', false)) die(); ' . $template);
	}

	/**
	 * Delete the specified template(s)
	 *
	 * Each parameter can be passed as
	 * -	a scalar (to match that parameter)
	 * -	null (to use a wildcard for that parameter)
	 * -	an array of scalars (to match multiple specific items)
	 *
	 * @param string|array|null $title
	 * @param integer|array|null $styleId
	 * @param string|array|null $languageId
	 */
	public static function delete($title, $styleId, $languageId)
	{
		self::getInstance()->_deleteTemplate($title, $styleId, $languageId);
	}

	protected function _createTemplateDirectory()
	{
		if (!is_dir($this->_path))
		{
			if (XenForo_Helper_File::createDirectory($this->_path))
			{
				return XenForo_Helper_File::makeWritableByFtpUser($this->_path);
			}
			else
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * @see XenForo_Template_FileHandler::save
	 */
	protected function _saveTemplate($title, $styleId, $languageId, $template)
	{
		$this->_createTemplateDirectory();
		$fileName = $this->_getFileName($title, $styleId, $languageId);

		file_put_contents($fileName, $template);
		XenForo_Helper_File::makeWritableByFtpUser($fileName);
		$this->_postTemplateChange($fileName, 'write');

		return $fileName;
	}

	/**
	 * @see XenForo_Template_FileHandler::delete
	 */
	protected function _deleteTemplate($title, $styleId, $languageId)
	{
		$this->_createTemplateDirectory();

		$title = $this->_prepareWildcard($title);
		$styleId = $this->_prepareWildcard($styleId);
		$languageId = $this->_prepareWildcard($languageId);

		foreach ($title AS $_title)
		{
			foreach ($styleId AS $_styleId)
			{
				foreach ($languageId AS $_languageId)
				{
					$files = glob($this->_getFileName($_title, $_styleId, $_languageId));

					if (is_array($files))
					{
						foreach ($files AS $file)
						{
							@unlink($file);
							$this->_postTemplateChange($file, 'delete');
						}
					}
				}
			}
		}
	}

	protected function _postTemplateChange($file, $action)
	{
		XenForo_CodeEvent::fire('template_file_change', array($file, $action), $file);
	}

	/**
	 * Takes a parameter for the filename and turns it into an array of parameters
	 *
	 * @param mixed $item
	 *
	 * @return array
	 */
	protected function _prepareWildcard($item)
	{
		if (is_null($item))
		{
			return array('*');
		}
		else if (!is_array($item))
		{
			return array($item);
		}
		else
		{
			return $item;
		}
	}

	/**
	 * Prepares a glob-friendly filename or wildcard for the specified template(s)
	 *
	 * @param string $title
	 * @param integer $styleId
	 * @param integer $languageId
	 *
	 * @return string
	 */
	protected function _getFileName($title, $styleId, $languageId)
	{
		if ($title !== '*')
		{
			$title = preg_replace('/[^a-z0-9_\.-]/i', '', $title);
		}

		if ($styleId !== '*')
		{
			$styleId = intval($styleId);
		}

		if ($languageId != '*')
		{
			$languageId = intval($languageId);
		}

		return sprintf('%s/S.%s,L.%s,%s.php', $this->_path, $styleId, $languageId, $title);
	}
}