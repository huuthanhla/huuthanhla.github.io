<?php

class XenForo_Deferred_Atomic extends XenForo_Deferred_Abstract
{
	public function canTriggerManually()
	{
		return false;
	}

	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'execute' => array(),
			'simple' => false
		), $data);

		if ($data['simple'])
		{
			if (is_string($data['simple']))
			{
				$classes = preg_split('/,\s*/', $data['simple'], -1, PREG_SPLIT_NO_EMPTY);
			}
			else
			{
				$classes = $data['simple'];
			}
			foreach ($classes AS $class)
			{
				$data['execute'][] = array($class, array());
			}
			$data['simple'] = false;
		}

		$startTime = microtime(true);
		$limitTime = ($targetRunTime > 0);

		while ($data['execute'])
		{
			$value = reset($data['execute']);
			$key = key($data['execute']);
			list($class, $classData) = $value;

			$runner = XenForo_Deferred_Abstract::create($class);
			if (!$runner)
			{
				unset($data['execute'][$key]);
				continue;
			}

			if ($limitTime)
			{
				$remainingTime = $targetRunTime - (microtime(true) - $startTime);
				if ($remainingTime < 1)
				{
					// ran out of time - have some pick up later
					break;
				}
			}
			else
			{
				$remainingTime = 0;
			}

			try
			{
				$output = $runner->execute($deferred, $classData, $remainingTime, $status);
			}
			catch (Exception $e)
			{
				if ($deferred['manual_execute'])
				{
					// throw and let it be handled above
					throw $e;
				}
				else
				{
					// log and ignore - need to roll back any transactions opened by this too
					XenForo_Error::logException($e, true);
					$output = false;
					$status = "$class threw exception. See error log."; // TODO: phrase?
				}
			}

			if ($output === 'exit')
			{
				return 'exit';
			}
			else if (is_array($output))
			{
				$data['execute'][$key][1] = $output;
			}
			else
			{
				unset($data['execute'][$key]);
			}
		}

		if (!$data['execute'])
		{
			return false;
		}

		return $data;
	}
}