<?php

class XenForo_Option_TemplateFiles
{
	/**
	 * Updates the build date of styles after the value of the minifyCss option changes.
	 *
	 * @param boolean $option
	 * @param XenForo_DataWriter $dw
	 * @param string $fieldName
	 *
	 * @return boolean
	 */
	public static function verifyOption(&$option, XenForo_DataWriter $dw, $fieldName)
	{
		if ($dw->isInsert())
		{
			return true; // don't need to do anything
		}

		if ($option)
		{
			XenForo_Model::create('XenForo_Model_Template')->writeTemplateFiles(false, false);
		}
		else
		{
			XenForo_Model::create('XenForo_Model_Template')->deleteTemplateFiles();
		}

		return true;
	}
}