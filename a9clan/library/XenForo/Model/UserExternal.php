<?php

/**
 * Model for external user authentication (eg, Facebook).
 *
 * @package XenForo_User
 */
class XenForo_Model_UserExternal extends XenForo_Model
{
	/**
	 * Updates the specified provider association for the given user.
	 *
	 * @param string $provider
	 * @param string $providerKey
	 * @param string $userId
	 * @param array $extra Array of extra data to store
	 */
	public function updateExternalAuthAssociation($provider, $providerKey, $userId, array $extra = null)
	{
		$db = $this->_getDb();

		$existing = $this->getExternalAuthAssociation($provider, $providerKey);
		if ($existing && $existing['user_id'] != $userId)
		{
			$this->deleteExternalAuthAssociation($provider, $providerKey, $existing['user_id']);
		}

		$db->query('
			INSERT INTO xf_user_external_auth
				(provider, provider_key, user_id, extra_data)
			VALUES
				(?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				provider = VALUES(provider),
				provider_key = VALUES(provider_key),
				user_id = VALUES(user_id),
				extra_data = VALUES(extra_data)
		', array($provider, $providerKey, $userId, serialize($extra)));

		$this->rebuildExternalAuthCache($userId);
	}

	public function updateExternalAuthAssociationExtra($userId, $provider, array $extra = null)
	{
		$db = $this->_getDb();
		$db->update('xf_user_external_auth',
			array('extra_data' => serialize($extra)),
			'user_id = ' . $db->quote($userId) . ' AND provider = ' . $db->quote($provider)
		);
	}

	/**
	 * Deletes the external auth association for the given key.
	 *
	 * @param string $provider
	 * @param string $providerKey
	 * @param integer|null $userId If null, finds the user that has this key.
	 *
	 * @return boolean
	 */
	public function deleteExternalAuthAssociation($provider, $providerKey, $userId = null)
	{
		if ($userId === null)
		{
			$existing = $this->getExternalAuthAssociation($provider, $providerKey);
			if (!$existing)
			{
				return false;
			}
			$userId = $existing['user_id'];
		}

		$db = $this->_getDb();

		$db->query('
			DELETE FROM xf_user_external_auth
			WHERE provider = ?
				AND provider_key = ?
				AND user_id = ?
		', array($provider, $providerKey, $userId));

		$this->rebuildExternalAuthCache($userId);

		return true;
	}

	/**
	 * Deletes the external auth association for the given user.
	 *
	 * @param string $provider
	 * @param integer $userId
	 *
	 * @return boolean
	 */
	public function deleteExternalAuthAssociationForUser($provider, $userId)
	{
		$db = $this->_getDb();

		$db->query('
			DELETE FROM xf_user_external_auth
			WHERE provider = ?
				AND user_id = ?
		', array($provider, $userId));

		$this->rebuildExternalAuthCache($userId);

		return true;
	}

	/**
	 * Rebuilds the external auth cache for a user.
	 *
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function rebuildExternalAuthCache($userId)
	{
		$cache = array();
		foreach ($this->getExternalAuthAssociationsForUser($userId) AS $provider)
		{
			$cache[$provider['provider']] = $provider['provider_key'];
		}

		$this->_getDb()->update('xf_user_profile', array(
			'external_auth' => serialize($cache)
		), 'user_id = ' . $this->_getDb()->quote($userId));

		return $cache;
	}

	/**
	 * Gets the external auth association record for the specified provider and key.
	 *
	 * @param string $provider
	 * @param string $providerKey
	 *
	 * @return array|false
	 */
	public function getExternalAuthAssociation($provider, $providerKey)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_user_external_auth
			WHERE provider = ?
				AND provider_key = ?
		', array($provider, $providerKey));
	}

	/**
	 * Gets the external auth association record for the specified  provider and user
	 *
	 * @param string $provider
	 * @param string $userId
	 *
	 * @return array|false
	 */
	public function getExternalAuthAssociationForUser($provider, $userId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_user_external_auth
			WHERE provider = ?
				AND user_id = ?
		', array($provider, $userId));
	}

	/**
	 * Gets all external auth assocations for a user.
	 *
	 * @param integer $userId
	 *
	 * @return array
	 */
	public function getExternalAuthAssociationsForUser($userId)
	{
		return $this->fetchAllKeyed("
			SELECT *
			FROM xf_user_external_auth
			WHERE user_id = ?
			ORDER BY provider
		", 'provider', $userId);
	}
}