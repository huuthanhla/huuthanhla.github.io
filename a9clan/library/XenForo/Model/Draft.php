<?php

class XenForo_Model_Draft extends XenForo_Model
{
	public function getDraftById($id)
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM xf_draft
			WHERE draft_id = ?
		", $id);
	}

	public function getDraftByUserKey($key, $userId)
	{
		return $this->_getDb()->fetchRow("
			SELECT *
			FROM xf_draft
			WHERE draft_key = ? AND user_id = ?
		", array($key, $userId));
	}

	public function saveDraft($key, $message, array $extraData = array(), array $viewingUser = null, $lastUpdate = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$message = trim($message);
		if (!$viewingUser['user_id'] || !strlen($message))
		{
			return false;
		}

		if (!$lastUpdate)
		{
			$lastUpdate = XenForo_Application::$time;
		}

		$this->_getDb()->query("
			INSERT INTO xf_draft
				(draft_key, user_id, last_update, message, extra_data)
			VALUES
				(?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				last_update = VALUES(last_update),
				message = VALUES(message),
				extra_data = VALUES(extra_data)
		", array($key, $viewingUser['user_id'], $lastUpdate, $message, serialize($extraData)));

		return true;
	}

	public function deleteDraft($key, array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$q = $this->_getDb()->query("
			DELETE FROM xf_draft
			WHERE draft_key = ?
				AND user_id = ?
		", array($key, $viewingUser['user_id']));

		return $q->rowCount();
	}

	public function pruneDrafts($cutOff = null)
	{
		$draftOption = XenForo_Application::getOptions()->saveDrafts;

		if ($cutOff === null)
		{
			if (empty($draftOption['enabled']))
			{
				$cutOff = XenForo_Application::$time;
			}
			else
			{
				$cutOff = XenForo_Application::$time - $draftOption['lifetime'] * 3600;
			}
		}

		$db = $this->_getDb();
		return $db->delete('xf_draft', 'last_update < ' . $db->quote($cutOff));
	}
}