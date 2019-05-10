<?php

class XenForo_SabreDav_RootDirectory extends Sabre_DAV_Directory
{
	const PUBLIC_TEMPLATES = 'Public_Templates';
	const ADMIN_TEMPLATES = 'Admin_Templates';
	const EMAIL_TEMPLATES = 'Email_Templates';

	public function getChildren()
	{
		return array(
			$this->getChild(self::PUBLIC_TEMPLATES),
			$this->getChild(self::ADMIN_TEMPLATES),
			$this->getChild(self::EMAIL_TEMPLATES),
		);
	}

	public function getChild($name)
	{
		switch ($name)
		{
			case self::PUBLIC_TEMPLATES: return new XenForo_SabreDav_Directory_TemplateStyles();
			case self::ADMIN_TEMPLATES: return new XenForo_SabreDav_Directory_AdminTemplates();
			case self::EMAIL_TEMPLATES: return new XenForo_SabreDav_Directory_EmailTemplateTypes();
			default: return false;
		}
	}

	/**
	 * Returns the name of the node
	 *
	 * @return string
	 */
	public function getName()
	{
		return '';
	}
}