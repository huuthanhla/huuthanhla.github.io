<?php

class XenForo_Model_Deferred extends XenForo_Model
{
	public function getDeferredById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_deferred
			WHERE deferred_id = ?
		', $id);
	}

	public function getDeferredByKey($key)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_deferred
			WHERE unique_key = ?
		', $this->_getSafeUniqueKey($key));
	}

	protected function _getSafeUniqueKey($key)
	{
		if (is_string($key) && strlen($key) > 50)
		{
			return md5($key);
		}

		return $key;
	}

	public function getRunnableDeferreds($manual = false)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_deferred
			WHERE trigger_date <= ?
				AND manual_execute = ' . ($manual ? 1 : 0) . '
			ORDER BY trigger_date
		', 'deferred_id', XenForo_Application::$time);
	}

	public function countRunnableDeferreds($manual = false)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_deferred
			WHERE trigger_date <= ?
				AND manual_execute = ' . ($manual ? 1 : 0) . '
		', XenForo_Application::$time);
	}

	public function getStoppedManualDefers()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_deferred
			WHERE trigger_date <= ?
				AND manual_execute = 1
			ORDER BY trigger_date
		', 'deferred_id', XenForo_Application::$time - 60);
	}

	protected static $_uniqueDefers = array();

	public function defer($class, array $data, $uniqueKey = null, $manual = false, $triggerDate = null)
	{
		$runner = XenForo_Deferred_Abstract::create($class);
		if (!$runner)
		{
			return false;
		}

		if (!$triggerDate)
		{
			$triggerDate = XenForo_Application::$time;
		}
		if (!$uniqueKey)
		{
			$uniqueKey = null;
		}

		$manual = ($manual ? 1 : 0);

		$db = $this->_getDb();

		if ($uniqueKey)
		{
			$uniqueHash = "$uniqueKey-$triggerDate" . ($data ? '-' . md5(serialize($data)) : '');
			if (isset(self::$_uniqueDefers[$uniqueHash]))
			{
				return self::$_uniqueDefers[$uniqueHash];
			}
		}
		else
		{
			$uniqueHash = false;
		}

		$db->query('
			INSERT INTO xf_deferred
				(execute_class, execute_data, unique_key, manual_execute, trigger_date)
			VALUES
				(?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				execute_class = VALUES(execute_class),
				execute_data = VALUES(execute_data),
				manual_execute = VALUES(manual_execute),
				trigger_date = VALUES(trigger_date)
		', array($class, serialize($data),  $this->_getSafeUniqueKey($uniqueKey), $manual, $triggerDate));

		$id = $db->lastInsertId();

		if (!$manual)
		{
			$this->updateNextDeferredTime();
			XenForo_Application::$autoDeferredIds[] = $id;
		}
		else
		{
			XenForo_Application::$manualDeferredIds[] = $id;
		}

		if ($uniqueHash)
		{
			self::$_uniqueDefers[$uniqueHash] = $id;
		}

		return $id;
	}

	public function resetUniqueDeferInserts()
	{
		self::$_uniqueDefers = array();
	}

	public function updateNextDeferredTime()
	{
		$date = intval($this->_getDb()->fetchOne('
			SELECT MIN(trigger_date)
			FROM xf_deferred
			WHERE manual_execute = 0
		'));

		$this->_getDataRegistryModel()->set('deferredRun', $date);

		return $date;
	}

	public function setNextDeferredTime($time)
	{
		$time = intval($time);
		if ($time <= 0)
		{
			return false;
		}

		$this->_getDataRegistryModel()->set('deferredRun', $time);

		return true;
	}

	public function deleteDeferredById($id)
	{
		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);
		$value = $db->delete('xf_deferred', 'deferred_id = ' . $db->quote($id));
		XenForo_Db::commit($db);

		return $value;
	}

	public function cancelFirstRunnableDeferred($manual = true)
	{
		$defers = $this->getRunnableDeferreds($manual);
		if (!$defers)
		{
			return false;
		}

		$deferred = reset($defers);
		$runner = XenForo_Deferred_Abstract::create($deferred['execute_class']);
		if (!$runner)
		{
			return false;
		}

		if (!$runner->canCancel())
		{
			return false;
		}

		$this->deleteDeferredById($deferred['deferred_id']);

		return $deferred;
	}

	protected static $_shutdownRegistered = false;
	protected static $_runningDeferred = false;

	public static function shutdownHandleFatalDeferred()
	{
		if (!self::$_runningDeferred)
		{
			return;
		}

		// if we get a fatal error from a manual deferred, reinsert it so a refresh can catch it

		$deferred = self::$_runningDeferred;
		try
		{
			XenForo_Db::rollbackAll();

			if ($deferred['manual_execute'])
			{
				XenForo_Application::defer(
					$deferred['execute_class'], unserialize($deferred['execute_data']), $deferred['unique_key'], $deferred['manual_execute'], $deferred['trigger_date'], true
				);
			}
		}
		catch (Exception $e) {}
	}

	public function runDeferred(array $deferred, $targetRunTime, &$status, &$canCancel)
	{
		$this->resetUniqueDeferInserts();

		$canCancel = false;

		if (!$this->deleteDeferredById($deferred['deferred_id']))
		{
			// already being run
			return false;
		}

		$runner = XenForo_Deferred_Abstract::create($deferred['execute_class']);
		if (!$runner)
		{
			return false;
		}

		$data = unserialize($deferred['execute_data']);

		if (!self::$_shutdownRegistered)
		{
			self::$_shutdownRegistered = true;
			register_shutdown_function(array(__CLASS__, 'shutdownHandleFatalDeferred'));
		}

		self::$_runningDeferred = $deferred;

		try
		{
			$output = $runner->execute($deferred, $data, $targetRunTime, $status);
			self::$_runningDeferred = false;
		}
		catch (Exception $e)
		{
			self::$_runningDeferred = false;

			// transactions are likely from the manual runner, so we need to roll them back
			// as they probably won't be committed
			XenForo_Db::rollbackAll();

			if ($deferred['manual_execute'])
			{
				// reinsert and throw so a refresh will catch it
				XenForo_Application::defer(
					$deferred['execute_class'], $data, $deferred['unique_key'], $deferred['manual_execute'], $deferred['trigger_date'], true
				);

				throw $e;
			}
			else
			{
				// log and ignore
				XenForo_Error::logException($e, false);
				$output = false;
				$status = "$deferred[execute_class] threw exception. See error log."; // TODO: phrase?
			}
		}

		if ($output === 'exit')
		{
			// this is for debugging - restore to previous state
			XenForo_Db::rollbackAll();
			XenForo_Application::defer(
				$deferred['execute_class'], $data, $deferred['unique_key'], $deferred['manual_execute'], $deferred['trigger_date'], true
			);
			exit;
		}
		else if (is_array($output))
		{
			$canCancel = $runner->canCancel();

			return XenForo_Application::defer(
				$deferred['execute_class'], $output, $deferred['unique_key'], $deferred['manual_execute'], $deferred['trigger_date'], true
			);
		}
		else
		{
			return false;
		}
	}

	public function runByUniqueKey($uniqueKey, $targetRunTime = null, &$status = '', &$canCancel = null)
	{
		$deferred = $this->getDeferredByKey($uniqueKey);
		if (!$deferred)
		{
			return false;
		}

		$continued = $this->_runInternal(array($deferred), $targetRunTime, $status, $canCancel);
		return $continued ? reset($continued) : false;
	}

	public function runById($id, $targetRunTime = null, &$status = '', &$canCancel = null)
	{
		$deferred = $this->getDeferredById($id);
		if (!$deferred)
		{
			return false;
		}

		$continued = $this->_runInternal(array($deferred), $targetRunTime, $status, $canCancel);
		return $continued ? reset($continued) : false;
	}

	public function run($manual = false, $targetRunTime = null, &$status = '', &$canCancel = null)
	{
		$runnable = $this->getRunnableDeferreds($manual);
		$continued = $this->_runInternal($runnable, $targetRunTime, $status, $canCancel);

		if (!$manual)
		{
			$nextRun = $this->updateNextDeferredTime();
			if ($nextRun && $nextRun <= time())
			{
				$continued = true;
			}
			if (!$nextRun)
			{
				XenForo_Application::defer('Cron', array(), 'cron', false, time() + 300);
			}
		}

		return $continued;
	}

	protected function _runInternal(array $runnable, $targetRunTime = null, &$status = '', &$canCancel = null)
	{
		if ($targetRunTime === null)
		{
			$targetRunTime = XenForo_Application::getConfig()->rebuildMaxExecution;
		}

		if ($targetRunTime < 0)
		{
			$targetRunTime = 0;
		}
		else if ($targetRunTime > 0 && $targetRunTime < 2)
		{
			$targetRunTime = 2;
		}

		$continued = array();
		$limitTime = ($targetRunTime > 0);
		$startTime = microtime(true);

		foreach ($runnable AS $deferred)
		{
			if ($limitTime)
			{
				$remainingTime = $targetRunTime - (microtime(true) - $startTime);
				if ($remainingTime < 1)
				{
					// ran out of time - have some pick up later
					$continued[] = $deferred['deferred_id'];
					continue;
				}
			}
			else
			{
				$remainingTime = 0;
			}

			$continue = $this->runDeferred($deferred, $remainingTime, $status, $canCancel);
			if ($continue)
			{
				$continued[] = $continue;
			}
		}

		return $continued;
	}
}