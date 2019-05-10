<?php

class XenForo_SabreDav_File_EmailTemplate extends Sabre_DAV_File
{
	protected $_template = null;
	protected $_templateText = null;
	protected $_title;
	protected $_custom;

	protected static $_emailTemplateModel = null;

	public function __construct(array $template = null, $custom, $title = null)
	{
		if ($template)
		{
			$this->_template = $template;
			$this->_title = $template['title'];
		}
		else
		{
			$this->_title = $title;
		}

		$this->_custom = $custom;
	}

	public function getName()
	{
		if (strpos($this->_title, '.') === false)
		{
			return $this->_title . '.html';
		}
		else
		{
			return $this->_title;
		}
	}

	public function getLastModified()
	{
		return 0;
	}

	public function getETag()
	{
		$templateText = $this->_getTemplateText();
		if ($templateText === false)
		{
			return '"new"';
		}
		else
		{
			return '"' . md5($templateText) . '"';
		}
	}

	public function get()
	{
		$templateText = $this->_getTemplateText();
		if ($templateText === false)
		{
			return '';
		}
		else
		{
			return $templateText;
		}
	}

	public function getSize()
	{
		$templateText = $this->_getTemplateText();
		if ($templateText === false)
		{
			return 0;
		}
		else
		{
			return strlen($templateText);
		}
	}

	public function getContentType()
	{
		if (strpos($this->_title, '.') === false)
		{
			return 'text/html';
		}
		else if (strpos($this->_title, '.css') !== false)
		{
			return 'text/css';
		}
		else
		{
			return null;
		}
	}

	public function put($data)
	{
		if (!$this->_title || $this->_title[0] == '.' || $this->_title == 'Thumbs.db' || $this->_title == 'desktop.ini')
		{
			// don't save files that are likely temporary
			return;
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_EmailTemplate');
		if ($this->_template && $this->_template['custom'] == $this->_custom)
		{
			// only set this as the existing template if it truly exists in this style
			$dw->setExistingData($this->_template);
		}
		else
		{
			throw new XenForo_Exception('Unable to create email templates via WebDAV');
		}

		$dw->set('title', $this->_title);
		$dw->set('body_html', stream_get_contents($data));

		XenForo_SabreDav_ErrorHandler::assertNoErrors($dw, 'save', 'Email template');
		$dw->save();
	}

	public function delete()
	{
		if ($this->_template)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_EmailTemplate');
			$dw->setExistingData($this->_template);

			XenForo_SabreDav_ErrorHandler::assertNoErrors($dw, 'delete', 'Email template');
			$dw->delete();
		}
	}

	public function setName($title)
	{
		if (substr($title, -5) == '.html')
		{
			$title = substr($title, 0, -5);
		}

		if ($this->_template)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_EmailTemplate');
			$dw->setExistingData($this->_template);
			$dw->set('title', $title);

			XenForo_SabreDav_ErrorHandler::assertNoErrors($dw, 'save', 'Email template');
			$dw->save();
		}
	}

	protected function _getTemplateText()
	{
		if ($this->_templateText !== null)
		{
			return $this->_templateText;
		}

		if (!$this->_template)
		{
			$this->_templateText = false;
		}
		else
		{
			$this->_templateText = $this->_template['body_html'];
		}

		return $this->_templateText;
	}

	/**
	 * @return XenForo_Model_EmailTemplate
	 */
	protected static function _getEmailTemplateModel()
	{
		if (!self::$_emailTemplateModel)
		{
			self::$_emailTemplateModel = XenForo_Model::create('XenForo_Model_EmailTemplate');
		}

		return self::$_emailTemplateModel;
	}
}