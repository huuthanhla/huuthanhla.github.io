<?php

/**
 * Model that represents data in the data registry system. This system
 * is a generally permanent cache of data that can be read from the
 * cache system or out of the database.
 *
 * Data stored here will be automatically serialized and unserialized
 * as it is retrieved.
 *
 * @package XenForo_Core
 */
class XenForo_Model_DataRegistry extends XenForo_Model
{
	/**
	 * Gets the named item.
	 *
	 * @param string $itemName
	 *
	 * @return mixed|null Value of the entry or null if it couldn't be found
	 */
	public function get($itemName)
	{
		$cacheItem = $this->_getCacheEntryName($itemName);

		$cache = $this->_getCache(true);

		$cacheData = ($cache ? $cache->load($cacheItem) : false);
		if ($cacheData !== false)
		{
			return unserialize($cacheData);
		}

		$data = $this->_getFromDb($itemName);

		if ($data !== false)
		{
			if ($cache)
			{
				$cache->save($data, $cacheItem, array(), 86400);
			}
			return unserialize($data);
		}
		else
		{
			return null;
		}
	}

	/**
	 * Internal function to get the value of an item directly out of the DB,
	 * ignoring the cache settings.
	 *
	 * @param string $itemName
	 *
	 * @return string|false Serialized value or false if not found
	 */
	protected function _getFromDb($itemName)
	{
		return $this->_getDb()->fetchOne('
			SELECT data_value
			FROM xf_data_registry
			WHERE data_key = ?
		', $itemName);
	}

	/**
	 * Gets multiple entries from the registry at once.
	 *
	 * @param array $itemNames List of item names
	 *
	 * @return array Format: [item name] => value, or null if it couldn't be found
	 */
	public function getMulti(array $itemNames)
	{
		if (!$itemNames)
		{
			return array();
		}

		$cache = $this->_getCache(true);
		$dbItemNames = $itemNames;
		$data = array();

		foreach ($itemNames AS $k => $itemName)
		{
			$cacheData = ($cache ? $cache->load($this->_getCacheEntryName($itemName)) : false);
			if ($cacheData !== false)
			{
				$data[$itemName] = $cacheData;
				unset($dbItemNames[$k]);
			}
		}

		if ($dbItemNames)
		{
			$dbData = $this->_getMultiFromDb($dbItemNames);
			$data += $dbData;

			if ($cache)
			{
				foreach ($dbData AS $itemName => $dataValue)
				{
					$cache->save($dataValue, $this->_getCacheEntryName($itemName));
				}
			}
		}

		foreach ($itemNames AS $itemName)
		{
			if (!isset($data[$itemName]))
			{
				$data[$itemName] = null;
			}
			else
			{
				$data[$itemName] = unserialize($data[$itemName]);
			}
		}

		return $data;
	}

	/**
	 * Internal function to load multiple data registry values from the DB.
	 *
	 * @param array $itemNames
	 *
	 * @return array Format: [key] => value
	 */
	protected function _getMultiFromDb(array $itemNames)
	{
		if (!$itemNames)
		{
			return array();
		}

		$db = $this->_getDb();

		return $db->fetchPairs('
			SELECT data_key, data_value
			FROM xf_data_registry
			WHERE data_key IN (' . $db->quote($itemNames) . ')
		');
	}

	/**
	 * Sets a data registry value into the DB and updates the cache object.
	 *
	 * @param string $itemName
	 * @param mixed $value
	 */
	public function set($itemName, $value)
	{
		$serialized = serialize($value);

		$this->_getDb()->query('
			INSERT INTO xf_data_registry
				(data_key, data_value)
			VALUES
				(?, ?)
			ON DUPLICATE KEY UPDATE
				data_value = VALUES(data_value)
		', array($itemName, $serialized));

		$cache = $this->_getCache(true);
		if ($cache)
		{
			$cache->save($serialized, $this->_getCacheEntryName($itemName));
		}
	}

	/**
	 * Deletes a data registry value from the DB and cache.
	 *
	 * @param string $itemName
	 */
	public function delete($itemName)
	{
		$db = $this->_getDb();
		$db->delete('xf_data_registry', 'data_key = ' . $db->quote($itemName));

		$cache = $this->_getCache(true);
		if ($cache)
		{
			$cache->remove($this->_getCacheEntryName($itemName));
		}
	}

	/**
	 * Gets the name that will be used in the cache for a given data
	 * registry item.
	 *
	 * @param string $itemName Registry item name
	 *
	 * @return string Cache item name
	 */
	protected function _getCacheEntryName($itemName)
	{
		return 'data_' . $itemName;
	}
}