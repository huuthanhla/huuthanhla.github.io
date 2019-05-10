<?php

class XenForo_Model_EditHistory extends XenForo_Model
{
	const FETCH_USER = 0x01;

	public function getEditHistoryById($id, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareEditHistoryFetchOptions($fetchOptions);

		return $this->_getDb()->fetchRow('
			SELECT history.*
				' . $joinOptions['selectFields'] . '
			FROM xf_edit_history AS history
			' . $joinOptions['joinTables'] . '
			WHERE history.edit_history_id = ?
		', $id);
	}

	public function getEditHistoryByIds(array $ids, array $fetchOptions = array())
	{
		if (!$ids)
		{
			return array();
		}

		$joinOptions = $this->prepareEditHistoryFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT history.*
				' . $joinOptions['selectFields'] . '
			FROM xf_edit_history AS history
			' . $joinOptions['joinTables'] . '
			WHERE history.edit_history_id IN (' . $this->_getDb()->quote($ids) . ')
		', 'edit_history_id');
	}

	public function getEditHistoryListForContent($contentType, $contentId, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareEditHistoryFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT history.edit_history_id, history.edit_user_id, history.edit_date
				' . $joinOptions['selectFields'] . '
			FROM xf_edit_history AS history
			' . $joinOptions['joinTables'] . '
			WHERE history.content_type = ?
				AND history.content_id = ?
			ORDER BY history.edit_date DESC
		', 'edit_history_id', array($contentType, $contentId));
	}

	public function getEditHistoryByUserSinceDate($userId, $cutOff, array $fetchOptions = array())
	{
		$joinOptions = $this->prepareEditHistoryFetchOptions($fetchOptions);

		return $this->fetchAllKeyed('
			SELECT history.edit_history_id, history.edit_user_id, history.edit_date
				' . $joinOptions['selectFields'] . '
			FROM xf_edit_history AS history
			' . $joinOptions['joinTables'] . '
			WHERE history.edit_user_id = ?
				AND history.edit_date >= ?
			ORDER BY history.edit_date DESC
		', 'edit_history_id', array($userId, $cutOff));
	}

	/**
	 * Prepares join-related fetch options.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys.
	 */
	public function prepareEditHistoryFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					user.*';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(history.edit_user_id = user.user_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	public function deleteEditHistoryForContent($contentType, $contentIds)
	{
		if (!is_array($contentIds))
		{
			$contentIds = array($contentIds);
		}
		if (!$contentIds)
		{
			return 0;
		}

		$db = $this->_getDb();

		return $db->delete('xf_edit_history',
			'content_type = ' . $db->quote($contentType)
			. ' AND content_id IN (' . $db->quote($contentIds) . ')'
		);
	}

	public function pruneEditHistory($cutOff = null)
	{
		if ($cutOff === null)
		{
			if (XenForo_Application::get('options')->editHistory['enabled'])
			{
				$logLength = XenForo_Application::get('options')->editHistory['length'];
				if (!$logLength)
				{
					return 0;
				}
			}
			else
			{
				$logLength = 0;
			}

			$cutOff = XenForo_Application::$time - 86400 * $logLength;
		}

		$db = $this->_getDb();
		return $db->delete('xf_edit_history', 'edit_date < ' . $db->quote($cutOff));
	}

	public function revertToHistoryId($historyId)
	{
		$history = $this->getEditHistoryById($historyId);
		if (!$history)
		{
			return false;
		}

		$handler = $this->getEditHistoryHandler($history['content_type']);
		if (!$handler)
		{
			return false;
		}

		$content = $handler->getContent($history['content_id']);
		if (!$content || !$handler->canViewHistoryAndContent($content))
		{
			return false;
		}

		return $this->revertToHistory($history, $content, $handler);
	}

	public function revertToHistory(array $history, array $content, XenForo_EditHistoryHandler_Abstract $handler)
	{
		$histories = $this->getEditHistoryListForContent($history['content_type'], $history['content_id']);
		$previous = null;
		$useNext = false;
		$count = 0;

		foreach ($histories AS $h)
		{
			if ($h['edit_history_id'] == $history['edit_history_id'])
			{
				$useNext = true;
			}
			else if ($useNext)
			{
				$previous = $h;
				break;
			}

			$count++;
		}

		if ($count && $handler->revertToVersion($content, $count, $history, $previous))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param string $contentType
	 *
	 * @return XenForo_EditHistoryHandler_Abstract
	 */
	public function getEditHistoryHandler($contentType)
	{
		$handlerClass = $this->getContentTypeField($contentType, 'edit_history_handler_class');
		if (!$handlerClass || !class_exists($handlerClass))
		{
			return false;
		}

		$handlerClass = XenForo_Application::resolveDynamicClass($handlerClass);
		return new $handlerClass();
	}
}