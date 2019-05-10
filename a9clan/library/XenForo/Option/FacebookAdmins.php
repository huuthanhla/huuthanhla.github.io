<?php

abstract class XenForo_Option_FacebookAdmins
{
	/**
	 * Verifies the list of Facebook admin IDs.
	 *
	 * @param array $admins Array of of Facebook user IDs
	 * @param XenForo_DataWriter $dw Calling DW
	 * @param string $fieldName Name of field/option
	 *
	 * @return true
	 */
	public static function verifyOption(array &$admins, XenForo_DataWriter $dw, $fieldName)
	{
		$output = array();

		foreach ($admins AS $admin)
		{
			$admin = trim($admin);

			if ($admin)
			{
				if (!preg_match('/^[0-9]+$/', $admin))
				{
					$dw->error(new XenForo_Phrase('facebook_user_ids_are_integers'), $fieldName);
					return false;
				}

				$output[] = $admin;
			}
		}

		$admins = $output;

		return true;
	}
}