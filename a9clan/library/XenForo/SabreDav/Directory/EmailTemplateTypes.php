<?php

class XenForo_SabreDav_Directory_EmailTemplateTypes extends Sabre_DAV_Directory
{
	const MASTER_TEMPLATES = 'Master';
	const CUSTOM_TEMPLATES = 'Customizable';

	public function getChildren()
	{
		return array(
			new XenForo_SabreDav_Directory_EmailTemplates(self::MASTER_TEMPLATES),
			new XenForo_SabreDav_Directory_EmailTemplates(self::CUSTOM_TEMPLATES),
		);
	}

	/**
	 * @see XenForo_SabreDav_Directory_EmailTemplates::getName()
	 * @param string XenForo_SabreDav_Directory_EmailTemplates::MASTER_TEMPLATES or CUSTOM_TEMPLATES
	 */
	public function getChild($directoryName)
	{
		return new XenForo_SabreDav_Directory_EmailTemplates($directoryName);
	}

	public function getName()
	{
		return XenForo_SabreDav_RootDirectory::EMAIL_TEMPLATES;
	}
}