<?php

if (!defined('XENFORO_AUTOLOADER_SETUP')) { die('No access.'); }

/**
* Base XenForo application class. Sets up the environment as necessary and acts as the
* registry for the application. Can broker to the autoload as well.
*
* @package XenForo_Core
*/
class XenForo_Application extends Zend_Registry
{
	const URL_ID_DELIMITER = '.';

	/**
	 * Current printable and encoded versions. These are used for visual output
	 * and installation/upgrading.
	 *
	 * @var string
	 * @var integer
	 */
	public static $version = '1.4.3';
	public static $versionId = 1040370; // abbccde = a.b.c d (alpha: 1, beta: 3, RC: 5, stable: 7, PL: 9) e

	/**
	 * JavaScript cache buster variable
	 *
	 * @var string
	 */
	public static $jsVersion = '';

	/**
	 * jQuery version currently in use. See XenForo_Dependencies_Public::getJquerySource()
	 *
	 * @var string
	 */
	public static $jQueryVersion = '1.11.0';

	/**
	* Path to directory containing the application's configuration file(s).
	*
	* @var string
	*/
	protected $_configDir = '.';

	/**
	* Path to applications root directory. Specific directories will be looked for within this.
	*
	* @var string
	*/
	protected $_rootDir = '.';

	/**
	* Stores whether the application has been initialized yet.
	*
	* @var boolean
	*/
	protected $_initialized = false;

	/**
	* Un-used lazy loaders for the registry. When a lazy loader is called, it
	* is removed from the list. Key is the index and value is an array:
	*    0 => callback
	*    1 => array of arguments
	*
	* @var array
	*/
	protected $_lazyLoaders = array();

	/**
	 * If true, any PHP errors/warnings/notices that come up will be handled
	 * by our error handler. Otherwise, they will be deferred to any previously
	 * registered handler (probably PHP's).
	 *
	 * @var boolean
	 */
	protected static $_handlePhpError = true;

	/**
	 * Controls whether the application is in debug mode.
	 *
	 * @var boolean
	 */
	protected static $_debug;

	/**
	 * Cache of random data. String of binary characters.
	 *
	 * @var string
	 */
	protected static $_randomData = '';

	/**
	 * Cache of dynamic inheritance classes and what they resolve to.
	 *
	 * @var array
	 */
	protected static $_classCache = array();

	/**
	 * Unix timestamp representing the current webserver date and time.
	 * This should be used whenever 'now' needs to be referred to.
	 *
	 * @var integer
	 */
	public static $time = 0;

	/**
	 * Hostname of the server
	 *
	 * @var string
	 */
	public static $host = 'localhost';

	/**
	 * Are we using SSL?
	 *
	 * @var boolean
	 */
	public static $secure = false;

	/**
	 * Value we can use as a sentinel to stand for variable integer values
	 *
	 * @var string
	 */
	public static $integerSentinel = '{{sentinel}}';

	/**
	 * Relative path to the thumbnails / avatars (etc.) directory from the base installation directory.
	 * Must be web accessible and server-writable.
	 * Examples 'data', 'foo/bar/data', '../path/to/thingy'.
	 *
	 * @var string
	 */
	public static $externalDataPath = 'data';

	/**
	 * URL to the thumbnails /avatars (etc.) directory. Can be relative or absolute, but must
	 * point to the web-accessible location referred-to by $externalDataPath.
	 *
	 * @var string
	 */
	public static $externalDataUrl = 'data';

	/**
	 * URL to the location where XenForo's Javascript directories are located.
	 * Can be absolute or relative.
	 *
	 * @var string
	 */
	public static $javaScriptUrl = 'js';

	/**
	 * Provides some configuration options to the initialization process.
	 *
	 * @var array
	 */
	protected static $_initConfig = array(
		'undoMagicQuotes' => true,
		'setMemoryLimit' => true,
		'resetOutputBuffering' => true
	);

	/**
	* Begin the application. This causes the environment to be setup as necessary.
	*
	* @param string Path to application configuration directory. See {@link $_configDir}.
	* @param string Path to application root directory. See {@link $_rootDir}.
	* @param boolean True to load default data (config, DB, etc)
	*/
	public function beginApplication($configDir = '.', $rootDir = '.', $loadDefaultData = true)
	{
		if ($this->_initialized)
		{
			return;
		}

		if (!defined('PHP_VERSION_ID'))
		{
			$version = explode('.', PHP_VERSION);
			define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
		}

		if (self::$_initConfig['undoMagicQuotes'] && function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
		{
			self::undoMagicQuotes($_GET);
			self::undoMagicQuotes($_POST);
			self::undoMagicQuotes($_COOKIE);
			self::undoMagicQuotes($_REQUEST);
		}
		if (function_exists('get_magic_quotes_runtime') && get_magic_quotes_runtime())
		{
			@set_magic_quotes_runtime(false);
		}

		if (self::$_initConfig['setMemoryLimit'])
		{
			self::setMemoryLimit(64 * 1024 * 1024);
		}

		ignore_user_abort(true);

		if (self::$_initConfig['resetOutputBuffering'])
		{
			@ini_set('output_buffering', false);
			@ini_set('zlib.output_compression', 0);

			// see http://bugs.php.net/bug.php?id=36514
			// and http://xenforo.com/community/threads/53637/
			if (!@ini_get('output_handler'))
			{
				$level = ob_get_level();
				while ($level)
				{
					@ob_end_clean();
					$newLevel = ob_get_level();
					if ($newLevel >= $level)
					{
						break;
					}
					$level = $newLevel;
				}
			}
		}

		error_reporting(E_ALL | E_STRICT & ~8192);
		set_error_handler(array('XenForo_Application', 'handlePhpError'));
		set_exception_handler(array('XenForo_Application', 'handleException'));
		register_shutdown_function(array('XenForo_Application', 'handleFatalError'));

		//@ini_set('pcre.backtrack_limit', 1000000);

		date_default_timezone_set('UTC');

		self::$time = time();

		self::$host = (empty($_SERVER['HTTP_HOST']) ? '' : $_SERVER['HTTP_HOST']);

		self::$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');

		require(XenForo_Autoloader::getInstance()->autoloaderClassToFile('Lgpl_utf8'));

		$this->_configDir = $configDir;
		$this->_rootDir = $rootDir;
		$this->addLazyLoader('requestPaths', array($this, 'loadRequestPaths'));

		if ($loadDefaultData)
		{
			$this->loadDefaultData();
		}

		// this is a minor hack as people sometimes set _SERVER[HTTPS] in config.php,
		// so the value may have changed compared to above
		self::$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');

		$this->_initialized = true;
	}

	/**
	 * Loads the default data for the application (config, DB, options, etc).
	 */
	public function loadDefaultData()
	{
		$config = $this->loadConfig();
		self::set('config', $config);
		self::setDebugMode($config->debug);
		self::$jsVersion = substr(md5(self::$versionId . $config->jsVersion), 0, 8);
		self::$externalDataPath = (string)$config->externalDataPath;
		self::$externalDataUrl = (string)$config->externalDataUrl;
		self::$javaScriptUrl = (string)$config->javaScriptUrl;

		$this->addLazyLoader('db', array($this, 'loadDb'), $config->db);
		$this->addLazyLoader('cache', array($this, 'loadCache'), $config->cache);
		$this->addLazyLoader('options', array($this, 'loadOptions'));
		$this->addLazyLoader('simpleCache', array($this, 'loadSimpleCache'));
	}

	/**
	* Helper function to initialize the application.
	*
	* @param string Path to application configuration directory. See {@link $_configDir}.
	* @param string Path to application root directory. See {@link $_rootDir}.
	* @param boolean True to load default data (config, DB, etc)
	* @param array Changes to the initialization process
	*/
	public static function initialize($configDir = '.', $rootDir = '.', $loadDefaultData = true, array $initChanges = array())
	{
		self::setClassName(__CLASS__);
		self::changeInitConfig($initChanges);
		self::getInstance()->beginApplication($configDir, $rootDir, $loadDefaultData);
	}

	/**
	 * Merges changes into the init configuration.
	 *
	 * @param array $changes
	 */
	public static function changeInitConfig(array $changes)
	{
		if ($changes)
		{
			self::$_initConfig = array_merge(self::$_initConfig, $changes);
		}
	}

	/**
	 * Handler for set_error_handler to convert notices, warnings, and other errors
	 * into exceptions.
	 *
	 * @param integer $errorType Type of error (one of the E_* constants)
	 * @param string $errorString
	 * @param string $file
	 * @param integer $line
	 */
	public static function handlePhpError($errorType, $errorString, $file, $line)
	{
		if (!self::$_handlePhpError)
		{
			return false;
		}

		if ($errorType & error_reporting())
		{
			$trigger = true;
			if (!self::debugMode())
			{
				if (
					(defined('E_DEPRECATED') && $errorType & E_DEPRECATED)
					|| (defined('E_USER_DEPRECATED') && $errorType & E_USER_DEPRECATED))
				{
					$trigger = false;
				}
				else if (
					$errorType & E_NOTICE
					|| $errorType & E_USER_NOTICE
					|| $errorType & E_STRICT
				)
				{
					$trigger = false;
					$e = new ErrorException($errorString, 0, $errorType, $file, $line);
					XenForo_Error::logException($e, false);
				}
			}

			if ($trigger)
			{
				throw new ErrorException($errorString, 0, $errorType, $file, $line);
			}
		}
	}

	/**
	 * Disables our PHP error handler, in favor of a previously registered one
	 * (or the default PHP error handler).
	 */
	public static function disablePhpErrorHandler()
	{
		self::$_handlePhpError = false;
	}

	/**
	 * Enables our PHP error handler.
	 */
	public static function enablePhpErrorHandler()
	{
		self::$_handlePhpError = true;
	}

	/**
	 * Default exception handler.
	 *
	 * @param Exception $e
	 */
	public static function handleException(Exception $e)
	{
		XenForo_Error::logException($e);
		XenForo_Error::unexpectedException($e);
	}

	/**
	 * Try to log fatal errors so that debugging is easier.
	 */
	public static function handleFatalError()
	{
		$error = @error_get_last();
		if (!$error)
		{
			return;
		}

		if (empty($error['type']) || !($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)))
		{
			return;
		}

		try
		{
			XenForo_Error::logException(
				new ErrorException("Fatal Error: " . $error['message'], $error['type'], 1, $error['file'], $error['line'])
			);
		}
		catch (Exception $e) {}
	}

	/**
	 * Returns true if the application is in debug mode.
	 *
	 * @return boolean
	 */
	public static function debugMode()
	{
		return self::$_debug;
	}

	/**
	 * Sets the debug mode value.
	 *
	 * @param boolean $debug
	 */
	public static function setDebugMode($debug)
	{
		self::$_debug = (boolean)$debug;

		if (self::$_debug)
		{
			@ini_set('display_errors', true);
		}
	}

	/**
	 * Determines whether we should try to write to the development files.
	 *
	 * @return boolean
	 */
	public static function canWriteDevelopmentFiles()
	{
		return (self::debugMode() && XenForo_Application::get('config')->development->directory);
	}

	/**
	 * Resolves dynamic, run time inheritance for the specified class.
	 * The classes to be loaded for this base class are grabbed via the event.
	 * These classes must inherit from from XFCP_x, which is a non-existant
	 * class that is dynamically created, inheriting from the correct class
	 * as needed.
	 *
	 * If a fake base is needed when the base class doesn't exist, and there
	 * are no classes extending it, false will still be returned! This prevents
	 * an unnecessary eval.
	 *
	 * @param string $class Name of class
	 * @param string $type Type of class (for determining event to fire)
	 * @param string|false $fakeBase If the specified class doesn't exist, an alternative base can be specified
	 *
	 * @return false|string False or name of class to instantiate
	 */
	public static function resolveDynamicClass($class, $type = '', $fakeBase = false)
	{
		if (!$class)
		{
			return false;
		}

		if (!XenForo_Application::autoload($class))
		{
			if ($fakeBase)
			{
				$fakeNeeded = true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			$fakeNeeded = false;
		}

		if (!empty(self::$_classCache[$class]))
		{
			return self::$_classCache[$class];
		}

		$createClass = $class;

		$extend = array();
		XenForo_CodeEvent::fire('load_class', array($class, &$extend), $class);
		if ($type)
		{
			XenForo_CodeEvent::fire('load_class_' . $type, array($class, &$extend), $class);
		}

		if ($fakeNeeded)
		{
			if (!$extend)
			{
				return false;
			}

			eval('class ' . $class . ' extends ' . $fakeBase . ' {}');
		}

		if ($extend)
		{
			try
			{
				foreach ($extend AS $dynamicClass)
				{
					if (preg_match('/[;,$\/#"\'\.()]/', $dynamicClass))
					{
						continue;
					}

					// XenForo Class Proxy, in case you're wondering
					$proxyClass = 'XFCP_' . $dynamicClass;
					$namespaceEval = '';

					$nsSplit = strrpos($dynamicClass, '\\');
					if ($nsSplit !== false && $ns = substr($dynamicClass, 0, $nsSplit))
					{
						$namespaceEval = "namespace $ns; ";
						$proxyClass = 'XFCP_' . substr($dynamicClass, $nsSplit + 1);
						$createClass = '\\' . $createClass;
					}

					eval($namespaceEval . 'class ' . $proxyClass . ' extends ' . $createClass . ' {}');
					XenForo_Application::autoload($dynamicClass);
					$createClass = $dynamicClass;
				}
			}
			catch (Exception $e)
			{
				self::$_classCache[$class] = $class;
				throw $e;
			}
		}

		self::$_classCache[$class] = $createClass;
		return $createClass;
	}

	/**
	 * Takes a class that may be dynamically extended and resolves it
	 * back to the root.
	 *
	 * @param string|object $class Class name or object
	 *
	 * @return string
	 */
	public static function resolveDynamicClassToRoot($class)
	{
		if (is_object($class))
		{
			$class = get_class($class);
		}

		$finalClass = $class;
		while (is_subclass_of($finalClass, "XFCP_$finalClass"))
		{
			$finalClass = get_parent_class("XFCP_$finalClass");
			if (!$finalClass)
			{
				return $class;
			}
		}

		return $finalClass;
	}

	/**
	 * Resets the dynamic resolution class cache, in case the listeners/settings
	 * have changed within a request.
	 */
	public function resetDynamicClassCache()
	{
		self::$_classCache = array();
	}

	/**
	* Gets the path to the configuration directory.
	*
	* @return string
	*/
	public function getConfigDir()
	{
		return $this->_configDir;
	}

	/**
	* Gets the path to the application root directory.
	*
	* @return string
	*/
	public function getRootDir()
	{
		return $this->_rootDir;
	}

	/**
	* Load the configuration file. Mixes in over top of the default values. Provided
	* a default is specified in {@link loadDefaultConfig}, all elements available
	* to the config will always be defined. Non-default elements may still be defined
	* in the loaded configuration.
	*
	* @return Zend_Config
	*/
	public function loadConfig()
	{
		if (file_exists($this->_configDir . '/config.php'))
		{
			$defaultConfig = $this->loadDefaultConfig();

			$config = array();
			require($this->_configDir . '/config.php');

			$outputConfig = new Zend_Config(array(), true);
			$outputConfig->merge($defaultConfig)
			             ->merge(new Zend_Config($config))
			             ->setReadOnly();
			return $outputConfig;
		}
		else
		{
			if (XenForo_Model::create('XenForo_Install_Model_Install')->isInstalled())
			{
				// TODO: ideally, we want a better way to display a fatal error like this
				echo "Couldn't load library/config.php file.";
				exit;
			}
			else
			{
				header('Location: install/index.php');
				exit;
			}
		}
	}

	/**
	* Load the default configuration. User-specified versions will override this.
	*
	* @return Zend_Config
	*/
	public function loadDefaultConfig()
	{
		return new Zend_Config(array(
			'db' => array(
				'adapter' => 'mysqli',
				'host' => 'localhost',
				'port' => '3306',
				'username' => '',
				'password' => '',
				'dbname' => '',
				'adapterNamespace' => 'Zend_Db_Adapter'
			),
			'cache' => array(
				'enabled' => false,
				'cacheSessions' => false,
				'frontend' => 'core',
				'frontendOptions' => array(
					'caching' => true,
					'cache_id_prefix' => 'xf_'
				),
				'backend' => 'file',
				'backendOptions' => array(
					'file_name_prefix' => 'xf_'
				)
			),
			'debug' => false,
			'enableListeners' => true,
			'development' => array(
				'directory' => '', // relative to the configuration directory
				'default_addon' => ''
			),
			'superAdmins' => '1',
			'globalSalt' => 'vxfdotvn',
			'jsVersion' => '',
			'cookie' => array(
				'prefix' => 'xf_',
				'path' => '/',
				'domain' => ''
			),
			'enableMail' => true,
			'enableMailQueue' => true,
			'internalDataPath' => 'internal_data',
			'externalDataPath' => 'data',
			'externalDataUrl' => 'data',
			'javaScriptUrl' => 'js',
			'checkVersion' => true,
			'enableGzip' => true,
			'enableContentLength' => true,
			'adminLogLength' => 60, // number of days to keep admin log entries
			'chmodWritableValue' => 0,
			'rebuildMaxExecution' => 8,
			'passwordIterations' => 10,
			'enableTemplateModificationCallbacks' => true,
			'enableClickjackingProtection' => true,
			'maxImageResizePixelCount' => 20000000
		));
	}

	/**
	* Load the database object.
	*
	* @param Zend_Configuration Configuration to use
	*
	* @return Zend_Db_Adapter_Abstract
	*/
	public function loadDb(Zend_Config $dbConfig)
	{
		$db = Zend_Db::factory($dbConfig->adapter,
			array(
				'host' => $dbConfig->host,
				'port' => $dbConfig->port,
				'username' => $dbConfig->username,
				'password' => $dbConfig->password,
				'dbname' => $dbConfig->dbname,
				'adapterNamespace' => $dbConfig->adapterNamespace,
				'charset' => 'utf8'
			)
		);

		switch (get_class($db))
		{
			case 'Zend_Db_Adapter_Mysqli':
				$db->getConnection()->query("SET @@session.sql_mode='STRICT_ALL_TABLES'");
				break;
			case 'Zend_Db_Adapter_Pdo_Mysql':
				$db->getConnection()->exec("SET @@session.sql_mode='STRICT_ALL_TABLES'");
				break;
		}

		if (self::debugMode())
		{
			$db->setProfiler(true);
		}

		return $db;
	}

	/**
	* Load the cache object.
	*
	* @param Zend_Configuration Configuration to use
	*
	* @return Zend_Cache_Core|Zend_Cache_Frontend|false
	*/
	public function loadCache(Zend_Config $cacheConfig)
	{
		if (!$cacheConfig->enabled)
		{
			return false;
		}

		return Zend_Cache::factory(
		    $cacheConfig->frontend,
		    $cacheConfig->backend,
		    $cacheConfig->frontendOptions->toArray(),
		    $cacheConfig->backendOptions->toArray()
		);
	}

	/**
	* Loads the list of options from the cache if possible and rebuilds
	* it from the DB if necessary.
	*
	* @return XenForo_Options
	*/
	public function loadOptions()
	{
		$options = XenForo_Model::create('XenForo_Model_DataRegistry')->get('options');
		if (!is_array($options))
		{
			$options = XenForo_Model::create('XenForo_Model_Option')->rebuildOptionCache();
		}

		$optionsObj = new XenForo_Options($options);
		self::setDefaultsFromOptions($optionsObj);

		return $optionsObj;
	}

	/**
	 * Setup necessary system defaults based on the options.
	 *
	 * @param XenForo_Options $options
	 */
	public static function setDefaultsFromOptions(XenForo_Options $options)
	{
		if ($options->useFriendlyUrls)
		{
			XenForo_Link::useFriendlyUrls(true);
		}
		if ($options->romanizeUrls)
		{
			XenForo_Link::romanizeTitles(true);
		}
		if ($options->indexRoute && preg_match('/^[a-z0-9-]/i', $options->indexRoute))
		{
			XenForo_Link::setIndexRoute($options->indexRoute);
		}

		self::$jsVersion = substr(md5(self::$jsVersion . $options->jsLastUpdate), 0, 8);
	}

	/**
	 * Loads the request paths from a default request object.
	 *
	 * @return array
	 */
	public function loadRequestPaths()
	{
		return self::getRequestPaths(new Zend_Controller_Request_Http());
	}

	/**
	 * Gets the request paths from the specified request object.
	 *
	 * @param Zend_Controller_Request_Http $request
	 *
	 * @return array Keys: basePath, host, protocol, fullBasePath, requestUri
	 */
	public static function getRequestPaths(Zend_Controller_Request_Http $request)
	{
		$basePath = $request->getBasePath();
		if ($basePath === '' || substr($basePath, -1) != '/')
		{
			$basePath .= '/';
		}

		$host = $request->getServer('HTTP_HOST');
		if (!$host)
		{
			$host = $request->getServer('SERVER_NAME');
			$serverPort = intval($request->getServer('SERVER_PORT'));
			if ($serverPort && $serverPort != 80 && $serverPort != 443)
			{
				$host .= ':' . $serverPort;
			}
		}

		$protocol = ($request->isSecure() ? 'https' : 'http');

		$requestUri = $request->getRequestUri();

		return array(
			'basePath' => $basePath,
			'host' => $host,
			'protocol' => $protocol,
			'fullBasePath' => $protocol . '://' . $host . $basePath,
			'requestUri' => $requestUri,
			'fullUri' => $protocol . '://' . $host . $requestUri
		);
	}

	/**
	* Add a lazy loader to the application registry. This lazy loader callback
	* will be called if the specified index is not in the registry.
	*
	* The 3rd argument and on will be passed to the lazy loader callback.
	*
	* @param string   Index to assign lazy loader to
	* @param callback Callback to call when triggered
	*/
	public function addLazyLoader($index, $callback)
	{
		if (!is_callable($callback, true))
		{
			throw new Zend_Exception("Invalid callback for lazy loading '$index'");
		}

		$arguments = array_slice(func_get_args(), 2);

		$this->_lazyLoaders[$index] = array($callback, $arguments);
	}

	/**
	* Removes the lazy loader from the specified index.
	*
	* @param string Index to remove from
	*
	* @return boolean
	*/
	public function removeLazyLoader($index)
	{
		if (isset($this->_lazyLoaders[$index]))
		{
			unset($this->_lazyLoaders[$index]);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Loads simple cache data from the source.
	 *
	 * @return array
	 */
	public function loadSimpleCache()
	{
		$return = XenForo_Model::create('XenForo_Model_DataRegistry')->get('simpleCache');
		return (is_array($return) ? $return : array());
	}

	/**
	 * Gets the specified simple cache data. The simple cache is for data that you want
	 * available on on pages, but don't need to special rebuild behaviors for.
	 *
	 * @param string $key
	 *
	 * @return mixed|false False if not in the cache
	 */
	public static function getSimpleCacheData($key)
	{
		$cache = self::get('simpleCache');
		return (isset($cache[$key]) ? $cache[$key] : false);
	}

	/**
	 * Sets the specified simple cache data. This data will be persisted over pages
	 * indefinitely. Values of false will remove the cache data.
	 *
	 * @param string $key
	 * @param mixed $value If false, the specified cache key is removed
	 */
	public static function setSimpleCacheData($key, $value)
	{
		$old = $cache = self::get('simpleCache');

		if ($value === false)
		{
			unset($cache[$key]);
		}
		else
		{
			$cache[$key] = $value;
		}

		if ($cache !== $old)
		{
			XenForo_Model::create('XenForo_Model_DataRegistry')->set('simpleCache', $cache);
			self::set('simpleCache', $cache);
		}
	}

	/**
	* Execute lazy loader for an index if there is one. The loaded data is returned
	* via a reference parameter, not the return value of the method. The return
	* value is true only if the lazy loader was executed.
	*
	* Once called, the data is set to the registry and the lazy loader is removed.
	*
	* @param string Index to lazy load
	* @param mixed  By ref; data returned by lazy loader
	*
	* @return boolean True if a lazy loader was called
	*/
	public function lazyLoad($index, &$return)
	{
		if (isset($this->_lazyLoaders[$index]))
		{
			$lazyLoader = $this->_lazyLoaders[$index];

			$return = call_user_func_array($lazyLoader[0], $lazyLoader[1]);

			$this->offsetSet($index, $return);
			$this->removeLazyLoader($index);

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* getter method, basically same as offsetGet().
	*
	* This method can be called from an object of type Zend_Registry, or it
	* can be called statically.  In the latter case, it uses the default
	* static instance stored in the class.
	*
	* @param string $index - get the value associated with $index
	* @return mixed
	* @throws Zend_Exception if no entry is registerd for $index.
	*/
	public static function get($index)
	{
		/** @var $instance XenForo_Application */
		$instance = self::getInstance();

		if (!$instance->offsetExists($index))
		{
			if ($instance->lazyLoad($index, $return))
			{
				return $return;
			}
			else
			{
				throw new Zend_Exception("No entry is registered for key '$index'");
			}
		}

		return $instance->offsetGet($index);
	}

	/**
	 * Attempts to get the specified index. If it cannot be found, the callback
	 * is called and the result from the callback is set into the registry for that
	 * index.
	 *
	 * @param string $index Index to look for
	 * @param callback $callback Callback function to call if not found
	 * @param array $args Arguments to pass to callback
	 *
	 * @return mixed
	 */
	public static function getWithFallback($index, $callback, array $args = array())
	{
		if (self::isRegistered($index))
		{
			return self::get($index);
		}
		else
		{
			$result = call_user_func_array($callback, $args);
			self::set($index, $result);
			return $result;
		}
	}

	/**
	* Helper method to autoload a class. Could simply call the autoloader directly
	* but this method is recommended to reduce dependencies.
	*
	* @param string $class Class to load
	*
	* @return boolean
	*/
	public static function autoload($class)
	{
		return XenForo_Autoloader::getInstance()->autoload($class);
	}

	/**
	* Helper method to remove the result of magic_quotes_gpc being applied to the
	* input super globals
	*
	* @param array The array to have slashes stripped, this is passed by reference
	* @param integer Recursion depth to prevent malicious use
	*/
	public static function undoMagicQuotes(&$array, $depth = 0)
	{
		if ($depth > 10 || !is_array($array))
		{
			return;
		}

		foreach ($array AS $key => $value)
		{
			if (is_array($value))
			{
				self::undoMagicQuotes($array[$key], $depth + 1);
			}
			else
			{
				$array[$key] = stripslashes($value);
			}

			if (is_string($key))
			{
				$new_key = stripslashes($key);
				if ($new_key != $key)
				{
					$array[$new_key] = $array[$key];
					unset($array[$key]);
				}
			}
		}
	}

	/**
	 * Gzips the given content if the browser supports it.
	 *
	 * @param string $content Content to gzip; this will be modified if necessary
	 *
	 * @return array List of HTTP headers to add
	 */
	public static function gzipContentIfSupported(&$content)
	{
		if (@ini_get('output_handler'))
		{
			return array();
		}

		if (!function_exists('gzencode') || empty($_SERVER['HTTP_ACCEPT_ENCODING']))
		{
			return array();
		}

		if (!is_string($content))
		{
			return array();
		}

		if (!self::get('config')->enableGzip)
		{
			return array();
		}

		$headers = array();

		if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)
		{
			$headers[] = array('Content-Encoding', 'gzip', true);
			$headers[] = array('Vary', 'Accept-Encoding', false);

			$content = gzencode($content, 1);
		}

		return $headers;
	}

	/**
	 * Returns a version of the input $data that contains only the array keys defined in $keys
	 *
	 * Example: arrayFilterKeys(array('a' => 1, 'b' => 2, 'c' => 3), array('b', 'c'))
	 * Returns: array('b' => 2, 'c' => 3)
	 *
	 * @param array $data
	 * @param array $keys
	 *
	 * @return array $data
	 */
	public static function arrayFilterKeys(array $data, array $keys)
	{
		// this version will not warn on undefined indexes: return array_intersect_key($data, array_flip($keys));

		$array = array();

		foreach ($keys AS $key)
		{
			$array[$key] = $data[$key];
		}

		return $array;
	}

	/**
	 * This is a simplified version of a function similar to array_merge_recursive. It is
	 * designed to recursively merge associative arrays (maps). If each array shares a key,
	 * that key is recursed and the child keys are merged.
	 *
	 * This function does not handle merging of non-associative arrays (numeric keys) as
	 * a special case.
	 *
	 * More than 2 arguments may be passed if desired.
	 *
	 * @param array $first
	 * @param array $second
	 *
	 * @return array
	 */
	public static function mapMerge(array $first, array $second)
	{
		$args = func_get_args();
		unset($args[0]);

		foreach ($args AS $arg)
		{
			if (!is_array($arg) || !$arg)
			{
				continue;
			}
			foreach ($arg AS $key => $value)
			{
				if (is_array($value) && isset($first[$key]) && is_array($first[$key]))
				{
					$first[$key] = self::mapMerge($first[$key], $value);
				}
				else
				{
					$first[$key] = $value;
				}
			}
		}

		return $first;
	}

	public static function mapDiff(array $array1, array $array2)
	{
		$diff = array();

		foreach ($array1 AS $key => $value)
		{
			if (
				!array_key_exists($key, $array2) // not in the other
				|| (is_array($value) && !is_array($array2[$key])) // different type
				|| (!is_array($value) && $value !== $array2[$key]) // not equal
			)
			{
				$diff[$key] = $value;
			}
			else if (is_array($value)) // $array2[$key] will be an array as well
			{
				$result = self::mapDiff($value, $array2[$key]);
				if ($result)
				{
					$diff[$key] = $result;
				}
			}
		}

		return $diff;
	}

	public static function arrayColumn($array, $column, $index = null)
	{
		if (function_exists('array_column'))
		{
			return array_column($array, $column, $index);
		}

		$output = array();
		foreach ($array AS $row)
		{
			if ($column === null)
			{
				$value = $row;
			}
			else if (array_key_exists($column, $row))
			{
				$value = $row[$column];
			}
			else
			{
				continue;
			}

			if ($index === null || !array_key_exists($index, $row))
			{
				$output[] = $value;
			}
			else
			{
				$output[$row[$index]] = $value;
			}
		}

		return $output;
	}

	/**
	 * Parses a query string (x=y&a=b&c[]=d) into a structured array format.
	 *
	 * Note that this can handle very long query strings, but it has problems
	 * if there are conflicting elements that split the "chunks" that are made
	 * internally. Workaround this using distinct keys for each input whenever possible.
	 *
	 * @param string $string
	 *
	 * @return array
	 */
	public static function parseQueryString($string)
	{
		$max = intval(@ini_get('max_input_vars'));
		if ($max && substr_count($string, '&') >= $max)
		{
			$string = preg_replace_callback('/(?<=^|&)([^=&]+)(\\[\\]|%5B%5D)/U',
				array('XenForo_Application', 'parseQueryStringCallback'),
				$string
			);
			self::$_qsPartCounter = array();

			$chunks = array_chunk(explode('&', $string), $max, true);

			$output = array();
			foreach ($chunks AS $chunk)
			{
				parse_str(implode('&', $chunk), $values);
				$output = self::mapMerge($output, $values);
			}
		}
		else
		{
			parse_str($string, $output);
		}

		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
		{
			XenForo_Application::undoMagicQuotes($output);
		}

		return $output;
	}

	protected static $_qsPartCounter = array();

	public static function parseQueryStringCallback(array $match)
	{
		$key = $match[1];
		if (!isset(self::$_qsPartCounter[$key]))
		{
			self::$_qsPartCounter[$key] = 0;
		}

		$output = $key . '[' . self::$_qsPartCounter[$key] . ']';
		self::$_qsPartCounter[$key]++;

		return $output;
	}

	protected static $_randomState;

	/**
	 * Generates a psuedo-random string of the specified length.
	 *
	 * @param integer $length
	 * @param boolean $raw If true, raw binary is returned, otherwise modified base64
	 *
	 * @return string
	 */
	public static function generateRandomString($length, $raw = false)
	{
		$mixInternal = false;

		while (strlen(self::$_randomData) < $length)
		{
			if (function_exists('openssl_random_pseudo_bytes')
				&& (substr(PHP_OS, 0, 3) != 'WIN' || version_compare(phpversion(), '5.3.4', '>='))
			)
			{
				self::$_randomData .= openssl_random_pseudo_bytes($length);
				$mixInternal = true;
			}
			else if (function_exists('mcrypt_create_iv') && version_compare(phpversion(), '5.3.0', '>='))
			{
				self::$_randomData .= mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
				$mixInternal = true;
			}
			else if (substr(PHP_OS, 0, 3) != 'WIN'
				&& @file_exists('/dev/urandom') && @is_readable('/dev/urandom')
				&& $fp = @fopen('/dev/urandom', 'r')
			)
			{
				if (function_exists('stream_set_read_buffer'))
				{
					stream_set_read_buffer($fp, 0);
				}

				self::$_randomData .= fread($fp, $length);
				fclose($fp);
				$mixInternal = true;
			}
			else
			{
				self::$_randomData .= self::generateInternalRandomValue();
			}
		}

		$return = substr(self::$_randomData, 0, $length);
		self::$_randomData = substr(self::$_randomData, $length);

		// have seen situations where duplicates may be read(!?!) so mix
		// in another source
		if ($mixInternal)
		{
			$final = '';
			foreach (str_split($return, 16) AS $i => $part)
			{
				$internal = uniqid(mt_rand());
				if ($i % 2 == 0)
				{
					$final .= md5($part . $internal, true);
				}
				else
				{
					$final .= md5($internal . $part, true);
				}
			}

			$return = substr($final, 0, $length);
		}

		if ($raw)
		{
			return $return;
		}

		// modified base64 to be more URL safe (roughly in rfc4648)
		return substr(strtr(base64_encode($return), array(
			'=' => '',
			"\r" => '',
			"\n" => '',
			'+' => '-',
			'/' => '_'
		)), 0, $length);
	}

	/**
	 * Generates a random number using internal methods only.
	 *
	 * @return string
	 */
	public static function generateInternalRandomValue()
	{
		if (!self::$_randomState)
		{
			self::$_randomState = md5(memory_get_usage() . getmypid()
				. serialize($_ENV) . serialize($_SERVER) . mt_rand() . microtime(), true);
		}

		$data = md5(uniqid(mt_rand(), true) . memory_get_usage() . microtime() . self::$_randomState, true);
		self::$_randomState = substr($data, 0, 8);

		return $data;
	}

	public static function getCopyrightHtml()
	{
		return '<a href="http://xenforo.com" class="concealed">Forum software by XenForo&trade; <span>&copy;2010-2014 XenForo Ltd.</span></a>';
	}

	public static $allowManualDeferred = false;
	public static $manualDeferredIds = array();
	public static $autoDeferredIds = array();

	/**
	 * Inserts a deferred runner entry
	 *
	 * @param string $class Class name to run
	 * @param array $data Data to pass to runner
	 * @param null|string $uniqueKey Unique identifier (prevents duplicate inserts, if provided)
	 * @param bool $manual If true, this will only be run by the manual runner. Supported only for entries triggered where manual deferreds are allowed (ACP
	 * @param null|integer $triggerDate If specified, only allows triggering after this time
	 * @param bool $forceAllowManual If true, force allow manual defers
	 *
	 * @return bool|int
	 */
	public static function defer($class, array $data, $uniqueKey = null, $manual = false, $triggerDate = null, $forceAllowManual = false)
	{
		if (!self::$allowManualDeferred && !$forceAllowManual)
		{
			$manual = false;
		}

		return XenForo_Model::create('XenForo_Model_Deferred')->defer(
			$class, $data, $uniqueKey, $manual, $triggerDate
		);
	}

	protected static $_memoryLimit = null;

	/**
	 * Sets the memory limit. Will not shrink the limit.
	 *
	 * @param integer $limit Limit must be given in integer (byte) format.
	 *
	 * @return bool True if the limit was set (or already bigger)
	 */
	public static function setMemoryLimit($limit)
	{
		$limit = intval($limit);
		$currentLimit = self::getMemoryLimit();

		if ($limit == -1 || ($limit > $currentLimit && $currentLimit >= 0))
		{
			$success = @ini_set('memory_limit', $limit);
			if ($success)
			{
				self::$_memoryLimit = $limit;
			}

			return $success;
		}

		return true; // already big enough
	}

	public static function increaseMemoryLimit($amount)
	{
		$amount = intval($amount);
		if ($amount <= 0)
		{
			return false;
		}

		$currentLimit = self::getMemoryLimit();
		if ($currentLimit < 0)
		{
			return true;
		}

		return self::setMemoryLimit($currentLimit + $amount);
	}

	/**
	 * Gets the current memory limit.
	 *
	 * @return int
	 */
	public static function getMemoryLimit()
	{
		if (self::$_memoryLimit === null)
		{
			$curLimit = @ini_get('memory_limit');
			if ($curLimit === false)
			{
				// reading failed, so we have to treat it as unlimited - unlikely to be able to change anyway
				$curLimit = -1;
			}
			else
			{
				switch (substr($curLimit, -1))
				{
					case 'g':
					case 'G':
						$curLimit *= 1024;
						// fall through

					case 'm':
					case 'M':
						$curLimit *= 1024;
						// fall through

					case 'k':
					case 'K':
						$curLimit *= 1024;
				}
			}

			self::$_memoryLimit = intval($curLimit);
		}

		return self::$_memoryLimit;
	}

	/**
	 * Attempts to determine the current available amount of memory.
	 * If there is no memory limit
	 *
	 * @return int
	 */
	public static function getAvailableMemory()
	{
		$limit = self::getMemoryLimit();
		if ($limit < 0)
		{
			return PHP_INT_MAX;
		}

		$used = memory_get_usage();
		$available = $limit - $used;

		return ($available < 0 ? 0 : $available);
	}

	/**
	 * @return Zend_Db_Adapter_Abstract
	 */
	public static function getDb()
	{
		return self::get('db');
	}

	/**
	 * @return XenForo_FrontController
	 */
	public static function getFc()
	{
		return self::get('fc');
	}

	/**
	 * @return XenForo_Session
	 */
	public static function getSession()
	{
		return self::get('session');
	}

	/**
	 * @return Zend_Config
	 */
	public static function getConfig()
	{
		return self::get('config');
	}

	/**
	 * @return XenForo_Options
	 */
	public static function getOptions()
	{
		return self::get('options');
	}

	/**
	 * @return Zend_Cache_Core|boolean
	 */
	public static function getCache()
	{
		return self::get('cache');
	}
}