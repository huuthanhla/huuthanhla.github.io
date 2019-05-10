<?php

/**
 * Model for user group promotions.
 */
class XenForo_Model_UserGroupPromotion extends XenForo_Model
{
	const FETCH_USER_NAME = 0x01;
	const FETCH_PROMOTION_TITLE = 0x02;

	/**
	 * Gets a promotion by its ID.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getPromotionById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_user_group_promotion
			WHERE promotion_id = ?
		', $id);
	}

	/**
	 * Gets promotions matching the specified conditions.
	 *
	 * @param array $conditions
	 *
	 * @return array [promotion id] => info
	 */
	public function getPromotions(array $conditions = array())
	{
		$sqlConditions = array();

		if (isset($conditions['active']))
		{
			$sqlConditions[] = 'promotion.active = ' . ($conditions['active'] ? 1 : 0);
		}

		if (isset($conditions['adminQuickSearch']))
		{
			$sqlConditions[] = 'promotion.title LIKE ' .
				XenForo_Db::quoteLike($conditions['adminQuickSearch'], 'lr', $this->_getDb());
		}

		$whereClause = $this->getConditionsForClause($sqlConditions);

		return $this->fetchAllKeyed('
			SELECT promotion.*
			FROM xf_user_group_promotion AS promotion
			WHERE ' . $whereClause . '
			ORDER BY promotion.title
		', 'promotion_id');
	}

	/**
	 * Gets promotion states for all promotions for the specified user. If there is no
	 * existing promotion state, nothing will be returned.
	 *
	 * @param $userId
	 *
	 * @return array [promotion id] => state
	 */
	public function getPromotionStatesByUserId($userId)
	{
		$results = $this->_getDb()->query('
			SELECT promotion_id, promotion_state
			FROM xf_user_group_promotion_log
			WHERE user_id = ?
		', $userId);

		$output = array();
		while ($result = $results->fetch())
		{
			$output[$result['promotion_id']] = $result['promotion_state'];
		}

		return $output;
	}

	/**
	 * Gets promotion states for all promotions for the specified users. If there is no
	 * existing promotion state, nothing will be returned.
	 *
	 * @param array $userIds
	 *
	 * @return array [user id][promotion id] => state
	 */
	public function getPromotionStatesByUserIds(array $userIds)
	{
		if (!$userIds)
		{
			return array();
		}

		$db = $this->_getDb();

		$results = $db->query('
			SELECT user_id, promotion_id, promotion_state
			FROM xf_user_group_promotion_log
			WHERE user_id IN (' . $db->quote($userIds) . ')
		');

		$output = array();
		while ($result = $results->fetch())
		{
			$output[$result['user_id']][$result['promotion_id']] = $result['promotion_state'];
		}

		return $output;
	}

	/**
	 * Gets promotion log entries matching the specified criteria.
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array [] => entry
	 */
	public function getPromotionLogEntries(array $conditions = array(), array $fetchOptions = array())
	{
		$whereClause = $this->preparePromotionLogEntryConditions($conditions, $fetchOptions);
		$joinOptions = $this->preparePromotionLogEntryFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchAll($this->limitQueryResults(
			'
				SELECT log.*
					' . $joinOptions['selectFields'] . '
				FROM xf_user_group_promotion_log AS log
				' . $joinOptions['joinTables'] . '
				WHERE ' . $whereClause . '
				ORDER BY log.promotion_date DESC
			', $limitOptions['limit'], $limitOptions['offset']
		));
	}

	/**
	 * Counts promotion log entries matching the specified criteria.
	 *
	 * @param array $conditions
	 *
	 * @return integer
	 */
	public function countPromotionLogEntries(array $conditions = array())
	{
		$fetchOptions = array();
		$whereClause = $this->preparePromotionLogEntryConditions($conditions, $fetchOptions);
		$joinOptions = $this->preparePromotionLogEntryFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user_group_promotion_log AS log
			' . $joinOptions['joinTables'] . '
			WHERE ' . $whereClause
		);
	}

	/**
	 * Gets the specified promotion log entry.
	 *
	 * @param int $promotionId
	 * @param int $userId
	 *
	 * @return array|false
	 */
	public function getPromotionLogEntry($promotionId, $userId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_user_group_promotion_log
			WHERE promotion_id = ?
				AND user_id = ?
		', array($promotionId, $userId));
	}

	/**
	 * Prepares a set of conditions to select promotion log entries against.
	 *
	 * @param array $conditions List of conditions.
	 * @param array $fetchOptions The fetch options that have been provided. May be edited if criteria requires.
	 *
	 * @return string Criteria as SQL for where clause
	 */
	public function preparePromotionLogEntryConditions(array $conditions, array &$fetchOptions)
	{
		$db = $this->_getDb();
		$sqlConditions = array();

		if (!empty($conditions['user_id']))
		{
			$sqlConditions[] = 'log.user_id = ' . $db->quote($conditions['user_id']);
		}
		if (!empty($conditions['promotion_id']))
		{
			$sqlConditions[] = 'log.promotion_id = ' . $db->quote($conditions['promotion_id']);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	/**
	 * Prepares join-related fetch options.
	 *
	 * @param array $fetchOptions
	 *
	 * @return array Containing 'selectFields' and 'joinTables' keys.
	 */
	public function preparePromotionLogEntryFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';

		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_USER_NAME)
			{
				$selectFields .= ',
					user.username';
				$joinTables .= '
					INNER JOIN xf_user AS user ON (log.user_id = user.user_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_PROMOTION_TITLE)
			{
				$selectFields .= ',
					promotion.title';
				$joinTables .= '
					INNER JOIN xf_user_group_promotion AS promotion ON (log.promotion_id = promotion.promotion_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}

	public function updatePromotionsForUser(array $user, array $promotionStates = null, array $promotions = null)
	{
		$changes = 0;

		if ($promotions === null)
		{
			$promotions = $this->getPromotions(array(
				'active' => 1
			));
		}
		if (!$promotions)
		{
			return 0;
		}

		if ($promotionStates === null)
		{
			$promotionStates = $this->getPromotionStatesByUserId($user['user_id']);
		}

		foreach ($promotions AS $promotionId => $promotion)
		{
			if (isset($promotionStates[$promotionId]))
			{
				$skip = false;
				switch ($promotionStates[$promotionId])
				{
					case 'manual': // has it, don't take it away
					case 'disabled': // never give it
						$skip = true;
				}
				if ($skip)
				{
					continue;
				}
				$hasPromotion = true;
			}
			else
			{
				$hasPromotion = false;
			}

			if (XenForo_Helper_Criteria::userMatchesCriteria($promotion['user_criteria'], false, $user))
			{
				if (!$hasPromotion)
				{
					$this->promoteUser($promotion, $user['user_id']);
					$changes++;
				}
			}
			else if ($hasPromotion)
			{
				$this->demoteUser($promotion, $user['user_id']);
				$changes++;
			}
		}

		return $changes;
	}

	/**
	 * Gives a user the specified promotion.
	 *
	 * @param array $promotion
	 * @param integer $userId
	 * @param string $state Type of promotion (automatic, manual); this affects automatic demotion
	 */
	public function promoteUser(array $promotion, $userId, $state = 'automatic')
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$this->_getUserModel()->addUserGroupChange(
			$userId, "ugPromotion$promotion[promotion_id]", $promotion['extra_user_group_ids']
		);

		$this->insertPromotionLogEntry($promotion['promotion_id'], $userId, $state);

		XenForo_Db::commit($db);
	}

	/**
	 * Demotes a user (removes them from the promotion).
	 *
	 * @param array $promotion
	 * @param integer $userId
	 * @param boolean $disablePromotion If true, the user will never be given the promotion automatically
	 */
	public function demoteUser(array $promotion, $userId, $disablePromotion = false)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$promotionId = $promotion['promotion_id'];

		$this->_getUserModel()->removeUserGroupChange($userId, "ugPromotion$promotionId");

		if ($disablePromotion)
		{
			$this->insertPromotionLogEntry($promotionId, $userId, 'disabled');
		}
		else
		{
			// allow it to be re-added
			$db->delete('xf_user_group_promotion_log',
				'promotion_id = ' . $db->quote($promotionId) . ' AND user_id = ' . $db->quote($userId)
			);
		}

		XenForo_Db::commit($db);
	}

	/**
	 * Inserts a promotion log entry.
	 *
	 * @param integer $promotionId
	 * @param integer $userId
	 * @param string $state Values: automatic, manual, disabled
	 */
	public function insertPromotionLogEntry($promotionId, $userId, $state = 'automatic')
	{
		$this->_getDb()->query('
			INSERT INTO xf_user_group_promotion_log
				(promotion_id, user_id, promotion_date, promotion_state)
			VALUES
				(?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				promotion_date = VALUES(promotion_date),
				promotion_state = VALUES(promotion_state)
		', array($promotionId, $userId, XenForo_Application::$time, $state));
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}