<?php

abstract class XenForo_Deferred_Abstract
{
	abstract public function execute(array $deferred, array $data, $targetRunTime, &$status);

	protected function __construct()
	{
		@set_time_limit(120);
		ignore_user_abort(true);
		XenForo_Application::getDb()->setProfiler(false);
	}

	/**
	 * Controls whether the rebuild can be cancelled manually.
	 *
	 * @return bool
	 */
	public function canCancel()
	{
		return false;
	}

	/**
	 * Controls whether this can be triggered manually with the
	 * end user's own criteria/options. Should be disabled if "unsafe"
	 * or simply undesired.
	 *
	 * @return bool
	 */
	public function canTriggerManually()
	{
		return true;
	}

	/**
	 * @param string $class
	 *
	 * @return XenForo_Deferred_Abstract|bool
	 */
	public static function create($class)
	{
		if (strpos($class, '_') === false)
		{
			$class = 'XenForo_Deferred_' . $class;
		}

		$class = XenForo_Application::resolveDynamicClass($class);
		$object = new $class();
		if (!($object instanceof XenForo_Deferred_Abstract))
		{
			return false;
		}
		return $object;
	}
}