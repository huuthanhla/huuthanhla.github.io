<?php

/**
 * Model for trophies.
 *
 * @package XenForo_Trophy
 */
class XenForo_Model_Trophy extends XenForo_Model
{
	/**
	 * Gets the named trophy.
	 *
	 * @param integer $id
	 *
	 * @return array|false
	 */
	public function getTrophyById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_trophy
			WHERE trophy_id = ?
		', $id);
	}

	/**
	 * Gets all trophies, ordered by their points (ascending).
	 *
	 * @return array Format: [trophy id] => info
	 */
	public function getAllTrophies()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_trophy
			ORDER BY trophy_points
		', 'trophy_id');
	}

	/**
	 * Gets all trophies that the specified user has earned. Ordered by award date descending.
	 *
	 * @param integer $userId
	 *
	 * @return array Format: [trophy id] => trophy info plus award_date
	 */
	public function getTrophiesForUserId($userId)
	{
		return $this->fetchAllKeyed('
			SELECT trophy.*,
				user_trophy.award_date
			FROM xf_user_trophy AS user_trophy
			INNER JOIN xf_trophy AS trophy ON (trophy.trophy_id = user_trophy.trophy_id)
			WHERE user_trophy.user_id = ?
			ORDER BY user_trophy.award_date DESC
		', 'trophy_id', $userId);
	}

	/**
	 * Counts the number of trophies that have been awarded to the specified user.
	 *
	 * @param integer $userId
	 *
	 * @return integer
	 */
	public function countTrophiesForUserId($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_user_trophy AS user_trophy
			INNER JOIN xf_trophy AS trophy ON (trophy.trophy_id = user_trophy.trophy_id)
			WHERE user_trophy.user_id = ?
		', $userId);
	}

	/**
	 * Prepares a trophy for display.
	 *
	 * @param array $trophy
	 *
	 * @return array
	 */
	public function prepareTrophy(array $trophy)
	{
		$trophy['title'] = new XenForo_Phrase($this->getTrophyTitlePhraseName($trophy['trophy_id']));
		$trophy['description'] = new XenForo_Phrase($this->getTrophyDescriptionPhraseName($trophy['trophy_id']));

		return $trophy;
	}

	/**
	 * Prepares a list of trophies for display.
	 *
	 * @param array $trophies
	 *
	 * @return array
	 */
	public function prepareTrophies(array $trophies)
	{
		foreach ($trophies AS &$trophy)
		{
			$trophy = $this->prepareTrophy($trophy);
		}

		return $trophies;
	}

	/**
	 * Gets information about the default trophy for use when adding
	 * a new trophy. Includes prepared data.
	 *
	 * @return array
	 */
	public function getDefaultTrophy()
	{
		return array(
			'trophy_id' => 0,
			'trophy_points' => 10,

			'user_criteria' => '',
			'userCriteriaList' => array(),
			'title' => '',
			'description' => ''
		);
	}

	/**
	 * Gets the name of a trophy's title phrase.
	 *
	 * @param integer $trophyId
	 *
	 * @return string
	 */
	public function getTrophyTitlePhraseName($trophyId)
	{
		return 'trophy_' . $trophyId . '_title';
	}

	/**
	 * Gets a trophy's master title phrase text.
	 *
	 * @param integer $trophyId
	 *
	 * @return string
	 */
	public function getTrophyMasterTitlePhraseValue($trophyId)
	{
		$phraseName = $this->getTrophyTitlePhraseName($trophyId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Gets the name of a trophy's description phrase.
	 *
	 * @param integer $trophyId
	 *
	 * @return string
	 */
	public function getTrophyDescriptionPhraseName($trophyId)
	{
		return 'trophy_' . $trophyId . '_description';
	}

	/**
	 * Gets a trophy's master description phrase text.
	 *
	 * @param integer $trophyId
	 *
	 * @return string
	 */
	public function getTrophyMasterDescriptionPhraseValue($trophyId)
	{
		$phraseName = $this->getTrophyDescriptionPhraseName($trophyId);
		return $this->_getPhraseModel()->getMasterPhraseValue($phraseName);
	}

	/**
	 * Get all trophies for the specified users.
	 *
	 * @return array Format: [user id][trophy id] => award date
	 */
	public function getUserTrophiesByUserIds(array $userIds)
	{
		if (!$userIds)
		{
			return array();
		}

		$db = $this->_getDb();

		$output = array();
		$userTrophiesResult = $db->query('
			SELECT user_id, trophy_id, award_date
			FROM xf_user_trophy
			WHERE user_id IN (' . $db->quote($userIds) . ')
		');
		while ($userTrophy = $userTrophiesResult->fetch())
		{
			$output[$userTrophy['user_id']][$userTrophy['trophy_id']] = $userTrophy['award_date'];
		}

		return $output;
	}

	/**
	 * Award the specified user with a specific trophy.
	 *
	 * @param array $user
	 * @param string $username
	 * @param array $trophy
	 * @param integer|null $awardDate If null, use current time
	 */
	public function awardUserTrophy(array $user, $username, array $trophy, $awardDate = null)
	{
		if ($awardDate === null)
		{
			$awardDate = XenForo_Application::$time;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		$result = $db->query('
			INSERT IGNORE INTO xf_user_trophy
				(user_id, trophy_id, award_date)
			VALUES
				(?, ?, ?)
		', array($user['user_id'], $trophy['trophy_id'], $awardDate));

		if ($result->rowCount())
		{
			$db->query('
				UPDATE xf_user SET
					trophy_points = trophy_points + ?
				WHERE user_id = ?
			', array($trophy['trophy_points'], $user['user_id']));

			if (XenForo_Model_Alert::userReceivesAlert($user, 'user', 'trophy'))
			{
				XenForo_Model_Alert::alert(
					$user['user_id'],
					$user['user_id'], $username,
					'user', $user['user_id'],
					'trophy',
					array('trophy_id' => $trophy['trophy_id'])
				);
			}
		}

		XenForo_Db::commit($db);
	}

	public function updateTrophyPointsForUser($userId, $points = null)
	{
		if ($points === null)
		{
			$points = $this->recalculateTrophyPointsForUser($userId);
		}

		$this->_getDb()->update('xf_user',
			array('trophy_points' => $points),
			'user_id = ' . $this->_getDb()->quote($userId)
		);

		return $points;
	}

	public function recalculateTrophyPointsForUser($userId)
	{
		return intval($this->_getDb()->fetchOne("
			SELECT SUM(trophy.trophy_points)
			FROM xf_user_trophy AS user_trophy
			INNER JOIN xf_trophy AS trophy ON (user_trophy.trophy_id = trophy.trophy_id)
			WHERE user_trophy.user_id = ?
		", $userId));
	}

	public function updateTrophiesForUser(array $user, array $userTrophies = null, array $trophies = null)
	{
		$awarded = 0;

		if ($trophies === null)
		{
			$trophies = $this->getAllTrophies();
		}
		if (!$trophies)
		{
			return 0;
		}

		if ($userTrophies === null)
		{
			$userTrophies = $this->getTrophiesForUserId($user['user_id']);
		}

		foreach ($trophies AS $trophy)
		{
			if (isset($userTrophies[$trophy['trophy_id']]))
			{
				continue;
			}

			if (XenForo_Helper_Criteria::userMatchesCriteria($trophy['user_criteria'], false, $user))
			{
				$this->awardUserTrophy($user, $user['username'], $trophy);
				$awarded++;
			}
		}

		return $awarded;
	}

	/**
	 * @return XenForo_Model_Phrase
	 */
	protected function _getPhraseModel()
	{
		return $this->getModelFromCache('XenForo_Model_Phrase');
	}

	/**
	 * @return XenForo_Model_Alert
	 */
	protected function _getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}
}