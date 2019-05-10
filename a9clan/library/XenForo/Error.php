<?php

/**
 * Helpers for handling exceptional error conditions. The handlers here
 * are generally assumed to be used for fatal errors.
 *
 * @package XenForo_Error
 */
abstract class XenForo_Error
{
	public static function noControllerResponse(XenForo_RouteMatch $routeMatch, Zend_Controller_Request_Http $request)
	{
		@header('Content-Type: text/html; charset=utf-8', true, 500);

		if (XenForo_Application::debugMode())
		{
			echo 'Failed to get controller response and reroute to error handler ('
				. $routeMatch->getControllerName() . '::action' . $routeMatch->getAction() . ')';

			if ($request->getParam('_exception'))
			{
				echo self::getExceptionTrace($request->getParam('_exception'));
			}
		}
		else
		{
			echo self::_getPhrasedTextIfPossible(
				'An unexpected error occurred. Please try again later.',
				'unexpected_error_occurred'
			);
		}
	}

	public static function noViewRenderer(Zend_Controller_Request_Http $request)
	{
		@header('Content-Type: text/html; charset=utf-8', true, 500);
		echo "Failed to get view renderer. No default was provided.";
	}

	protected static $_ignorePendingUpgrade = false;

	public static function setIgnorePendingUpgrade($ignore)
	{
		self::$_ignorePendingUpgrade = $ignore;
	}

	public static function unexpectedException(Exception $e)
	{
		$upgradePending = false;
		try
		{
			$db = XenForo_Application::getDb();
			if ($db->isConnected())
			{
				$dbVersionId = @$db->fetchOne("SELECT option_value FROM xf_option WHERE option_id = 'currentVersionId'");
				if ($dbVersionId && $dbVersionId != XenForo_Application::$versionId)
				{
					$upgradePending = true;
				}
			}
		}
		catch (Exception $ignore) {}

		$isInstalled = file_exists(XenForo_Helper_File::getInternalDataPath() . '/install-lock.php');
		$ignoreUpgradePending = (!$isInstalled || self::$_ignorePendingUpgrade);

		if (XenForo_Application::debugMode() || $ignoreUpgradePending)
		{
			$showTrace = true;
		}
		else if (XenForo_Visitor::hasInstance())
		{
			$showTrace = XenForo_Visitor::getInstance()->is_admin;
		}
		else
		{
			$showTrace = false;
		}

		@header('Content-Type: text/html; charset=utf-8', true, 500);

		if ($upgradePending && !$ignoreUpgradePending)
		{
			echo self::_getPhrasedTextIfPossible(
				'The board is currently being upgraded. Please check back later.',
				'board_currently_being_upgraded'
			);
		}
		else if (!empty($showTrace))
		{
			echo self::getExceptionTrace($e);
		}
		else if ($e instanceof Zend_Db_Exception)
		{
			$message = $e->getMessage();

			echo self::_getPhrasedTextIfPossible(
				'An unexpected database error occurred. Please try again later.',
				'unexpected_database_error_occurred'
			);
			echo "\n<!-- " . htmlspecialchars($message) . " -->";
		}
		else
		{
			echo self::_getPhrasedTextIfPossible(
				'An unexpected error occurred. Please try again later.',
				'unexpected_error_occurred'
			);
		}
	}

	protected static function _getPhrasedTextIfPossible($fallbackText, $phraseName, array $params = array())
	{
		$output = false;

		ini_set('display_errors', true);

		if (XenForo_Application::isRegistered('db') && XenForo_Application::getDb()->isConnected())
		{
			try
			{
				$phrase = new XenForo_Phrase($phraseName, $params);
				$output = $phrase->render();
			}
			catch (Exception $e) {}
		}

		if ($output === false || $output === $phraseName)
		{
			$output = $fallbackText;
		}

		return $output;
	}

	public static function logException(Exception $e, $rollbackTransactions = true, $messagePrefix = '')
	{
		try
		{
			$db = XenForo_Application::getDb();
			if ($db->getConnection())
			{
				if ($rollbackTransactions)
				{
					@XenForo_Db::rollbackAll($db);
				}

				$dbVersionId = @$db->fetchOne("SELECT option_value FROM xf_option WHERE option_id = 'currentVersionId'");
				if ($dbVersionId && $dbVersionId != XenForo_Application::$versionId)
				{
					// do not log errors when an upgrade is pending
					return;
				}

				if (!file_exists(XenForo_Helper_File::getInternalDataPath() . '/install-lock.php'))
				{
					// install hasn't finished yet, don't write
					return;
				}

				$rootDir = XenForo_Application::getInstance()->getRootDir();
				$file = $e->getFile();
				if (strpos($file, $rootDir) === 0)
				{
					$file = substr($file, strlen($rootDir));
					if (strlen($file) && ($file[0] == '/' || $file[0] == '\\'))
					{
						$file = substr($file, 1);
					}
				}

				$requestPaths = XenForo_Application::get('requestPaths');
				$request = array(
					'url' => $requestPaths['fullUri'],
					'_GET' => $_GET,
					'_POST' => $_POST
				);

				// don't log passwords
				foreach ($request['_POST'] AS $key => &$value)
				{
					if (strpos($key, 'password') !== false || $key == '_xfToken')
					{
						$value = '********';
					}
				}

				$db->insert('xf_error_log', array(
					'exception_date' => XenForo_Application::$time,
					'user_id' => XenForo_Visitor::hasInstance() ? XenForo_Visitor::getUserId() : null,
					'ip_address' => XenForo_Helper_Ip::getBinaryIp(),
					'exception_type' => get_class($e),
					'message' => $messagePrefix . $e->getMessage(),
					'filename' => $file,
					'line' => $e->getLine(),
					'trace_string' => $e->getTraceAsString(),
					'request_state' => serialize($request)
				));
			}
		}
		catch (Exception $e) {}
	}

	/**
	 * Logs a debugging message into the server error log, provided debug mode is enabled.
	 * Arguments are identical to sprintf.
	 *
	 * @param string $message Message, with formatting like sprintf. Other arguments are passed in.
	 */
	public static function debug($message)
	{
		if (!XenForo_Application::debugMode())
		{
			return;
		}

		$args = func_get_args();

		self::logException(
			new Exception(call_user_func_array('sprintf', $args)),
			false
		);
	}

	/**
	 * Logs an error message into the server error log.
	 * Arguments are identical to sprintf.
	 *
	 * @param string $message Message, with formatting like sprintf. Other arguments are passed in.
	 */
	public static function logError($message)
	{
		if (!XenForo_Application::debugMode())
		{
			return;
		}

		$args = func_get_args();

		self::logException(
			new Exception(call_user_func_array('sprintf', $args)),
			false
		);
	}

	public static function getExceptionTrace(Exception $e)
	{
		$cwd = str_replace('\\', '/', getcwd());

		if (PHP_SAPI == 'cli')
		{
			$file = str_replace("$cwd/library/", '', $e->getFile());
			$trace = str_replace("$cwd/library/", '', $e->getTraceAsString());

			return PHP_EOL . "An exception occurred: {$e->getMessage()} in {$file} on line {$e->getLine()}" . PHP_EOL . $trace . PHP_EOL;
		}

		$traceHtml = '';

		foreach ($e->getTrace() AS $traceEntry)
		{
			$function = (isset($traceEntry['class']) ? $traceEntry['class'] . $traceEntry['type'] : '') . $traceEntry['function'];
			if (isset($traceEntry['file']))
			{
				$file = str_replace("$cwd/library/", '', str_replace('\\', '/', $traceEntry['file']));
			}
			else
			{
				$file = '';
			}
			$traceHtml .= "\t<li><b class=\"function\">" . htmlspecialchars($function) . "()</b>" . (isset($traceEntry['file']) && isset($traceEntry['line']) ? ' <span class="shade">in</span> <b class="file">' . $file . "</b> <span class=\"shade\">at line</span> <b class=\"line\">$traceEntry[line]</b>" : '') . "</li>\n";
		}

		$message = htmlspecialchars($e->getMessage());
		$file = htmlspecialchars($e->getFile());
		$line = $e->getLine();

		return "<p>An exception occurred: $message in $file on line $line</p><ol>$traceHtml</ol>";
	}
}