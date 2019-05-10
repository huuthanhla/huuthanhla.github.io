<?php

/**
 * Session object.
 *
 * @package XenForo_Core
 */
class XenForo_Session
{
	/**
	 * Cache object. If specified, the session will be stored here instead of the DB.
	 *
	 * @var Zend_Cache_Core
	 */
	protected $_cache = null;

	/**
	 * DB object. If no cache is specified, the session will be stored in the DB.
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_db = null;

	/**
	 * Session configuration. See constructor.
	 *
	 * @var array
	 */
	protected $_config = array();

	/**
	 * Session identifier. An md5 hash.
	 *
	 * @var string
	 */
	protected $_sessionId = '';

	/**
	 * Array of arbitrary session information.
	 *
	 * @var string
	 */
	protected $_session = array();

	/**
	 * Determines whether the data inside the session has changed (and needs
	 * to be resaved).
	 *
	 * @var boolean
	 */
	protected $_dataChanged = false;

	/**
	 * True if the session already exists. Becomes true after a session is saved.
	 *
	 * @var boolean
	 */
	protected $_sessionExists = false;

	/**
	 * True if the session has been saved on this request.
	 *
	 * @var boolean
	 */
	protected $_saved = false;

	/**
	 * Search engine domains (excluding TLD)
	 *
	 * @var array
	 */
	protected $_searchDomains = array(
		'alltheweb',
		'altavista',
		'ask',
		'bing',
		'dogpile',
		'excite',
		'google',
		'lycos',
		'mamma',
		'metacrawler',
		'search',
		'webcrawler',
		'yahoo',
	);

	/**
	 * Known robot user agent substrings. Key is user agent substring, value is robot key name.
	 *
	 * There's a great list here: http://user-agent-string.info/list-of-ua/bots
	 *
	 * @var array
	 */
	protected $_knownRobots = array(
		'archive.org_bot' => 'archive.org',
		'baiduspider' => 'baidu',
		'bingbot' => 'bing',
		'facebookexternalhit' => 'facebookextern',
		'googlebot' => 'google',
		'ia_archiver' => 'alexa',
		'magpie-crawler' => 'brandwatch',
		'mediapartners-google' => 'google-adsense',
		'mj12bot' => 'mj12',
		'msnbot' => 'msnbot',
		'proximic' => 'proximic',
		'scoutjet' => 'scoutjet',
		'sogou web spider' => 'sogou',
		'yahoo! slurp' => 'yahoo',
		'yandex' => 'yandex',

		/*'crawler',
		'php/',
		'zend_http_client',*/
	);

	/**
	 * Maps an robot key to info about it.
	 *
	 * @var array
	 */
	protected $_robotMap = array(
		'alexa' => array(
			'title' => 'Alexa',
			'link' => 'http://www.alexa.com/help/webmasters',
		),
		'archive.org' => array(
			'title' => 'Internet Archive',
			'link' => 'http://www.archive.org/details/archive.org_bot'
		),
		'baidu' => array(
			'title' => 'Baidu',
			'link' => 'http://www.baidu.com/search/spider.htm'
		),
		'bing' => array(
			'title' => 'Bing',
			'link' => 'http://www.bing.com/bingbot.htm'
		),
		'brandwatch' => array(
			'title' => 'Brandwatch',
			'link' => 'http://www.brandwatch.com/how-it-works/gathering-data/'
		),
		'facebookextern' => array(
			'title' => 'Facebook',
			'link' => 'http://www.facebook.com/externalhit_uatext.php'
		),
		'google' => array(
			'title' => 'Google',
			'link' => 'https://support.google.com/webmasters/answer/182072'
		),
		'google-adsense' => array(
			'title' => 'Google AdSense',
			'link' => 'https://support.google.com/webmasters/answer/182072'
		),
		'mj12' => array(
			'title' => 'Majestic-12',
			'link' => 'http://majestic12.co.uk/bot.php',
		),
		'msnbot' => array(
			'title' => 'MSN',
			'link' => 'http://search.msn.com/msnbot.htm'
		),
		'proximic' => array(
			'title' => 'Proximic',
			'link' => 'http://www.proximic.com/info/spider.php'
		),
		'scoutjet' => array(
			'title' => 'Blekko',
			'link' => 'http://www.scoutjet.com/',
		),
		'sogou' => array(
			'title' => 'Sogou',
			'link' => 'http://www.sogou.com/docs/help/webmasters.htm#07'
		),
		'unknown' => array(
			'title' => 'Unknown',
			'link' => ''
		),
		'yahoo' => array(
			'title' => 'Yahoo',
			'link' => 'http://help.yahoo.com/help/us/ysearch/slurp'
		),
		'yandex' => array(
			'title' => 'Yandex',
			'link' => 'http://help.yandex.com/search/?id=1112030'
		)
	);

	/**
	 * Constructor.
	 *
	 * @param array $config Config elements to override default.
	 * @param Zend_Cache_Core|null $cache
	 * @param Zend_Db_Adapter_Abstract|null $db
	 */
	public function __construct(array $config = array(), Zend_Cache_Core $cache = null, Zend_Db_Adapter_Abstract $db = null)
	{
		if (empty($config['admin']))
		{
			$defaultConfig = array(
				'table' => 'xf_session',
				'cacheName' => 'session',
				'cookie' => 'session',
				'lifetime' => 3600
			);
		}
		else
		{
			$defaultConfig = array(
				'table' => 'xf_session_admin',
				'cacheName' => 'session_admin',
				'cookie' => 'session_admin',
				'lifetime' => (XenForo_Application::debugMode() ? 86400 : 3600) // longer lifetime in debug mode to get in the way less
			);
			unset($config['admin']);
		}
		$defaultConfig['ipv4CidrMatch'] = 24;
		$defaultConfig['ipv6CidrMatch'] = 64;

		$this->_config = array_merge($defaultConfig, $config);

		if (!$cache)
		{
			if (XenForo_Application::get('config')->cache->cacheSessions)
			{
				$cache = XenForo_Application::getCache();
			}
		}
		if ($cache)
		{
			$this->_cache = $cache;
		}

		if (!$db)
		{
			$db = XenForo_Application::getDb();
		}
		$this->_db = $db;
	}

	/**
	 * Starts running the public session handler. This will automatically log in the user via
	 * cookies if needed, and setup the visitor object. The session will be registered in the
	 * registry.
	 *
	 * @param Zend_Controller_Request_Http|null $request
	 *
	 * @return XenForo_Session
	 */
	public static function startPublicSession(Zend_Controller_Request_Http $request = null)
	{
		if (!$request)
		{
			$request = new Zend_Controller_Request_Http();
		}

		$session = self::getPublicSession($request);
		XenForo_Application::set('session', $session);

		$options = $session->getAll();

		$cookiePrefix = XenForo_Application::get('config')->cookie->prefix;
		$cookieStyleId = $request->getCookie($cookiePrefix . 'style_id');
		$cookieLanguageId = $request->getCookie($cookiePrefix . 'language_id');

		$options['languageId'] = $cookieLanguageId;

		$permTest = $session->get('permissionTest');
		if ($permTest && !empty($permTest['user_id']))
		{
			$options['permissionUserId'] = $permTest['user_id'];
		}

		$visitor = XenForo_Visitor::setup($session->get('user_id'), $options);

		if ($visitor['user_id'] && $session->get('password_date') && $session->get('password_date') != $visitor['password_date'])
		{
			$session->changeUserId(0);
			$visitor = XenForo_Visitor::setup(0, $options);
		}

		if (!$visitor['user_id'])
		{
			if ($request->isPost())
			{
				$guestUsername = $request->get('_guestUsername');
				if (is_string($guestUsername))
				{
					$session->set('guestUsername', $guestUsername);
				}
			}

			$guestUsername = $session->get('guestUsername');
			if (is_string($guestUsername))
			{
				$visitor['username'] = $guestUsername;
			}
		}
		if ($cookieStyleId)
		{
			$visitor['style_id'] = $cookieStyleId;
		}

		if ($session->get('previousActivity') === false)
		{
			$session->set('previousActivity', $visitor['last_activity']);
		}

		return $session;
	}

	/**
	 * This simply gets public session, from cookies if necessary.
	 *
	 * @param Zend_Controller_Request_Http $request
	 *
	 * @return XenForo_Session
	 */
	public static function getPublicSession(Zend_Controller_Request_Http $request)
	{
		$class = XenForo_Application::resolveDynamicClass('XenForo_Session');
		/** @var $session XenForo_Session */
		$session = new $class();
		$session->start();

		if (!$session->sessionExists())
		{
			$cookiePrefix = XenForo_Application::get('config')->cookie->prefix;
			$userCookie = $request->getCookie($cookiePrefix . 'user');

			if ($userCookie)
			{
				/** @var $userModel XenForo_Model_User */
				$userModel = XenForo_Model::create('XenForo_Model_User');
				if ($userId = $userModel->loginUserByRememberCookie($userCookie))
				{
					$user = $userModel->getUserById($userId, array('join' => XenForo_Model_User::FETCH_USER_FULL));
					$userModel->setUserRememberCookie($user['user_id']);
					$session->userLogin($user['user_id'], $user['password_date']);
				}
				else
				{
					XenForo_Helper_Cookie::deleteCookie('user', true);
				}
			}

			if (!empty($_SERVER['HTTP_USER_AGENT']))
			{
				$session->set('userAgent', $_SERVER['HTTP_USER_AGENT']);
				$session->set('robotId', $session->getRobotId($_SERVER['HTTP_USER_AGENT']));
			}

			if (!empty($_SERVER['HTTP_REFERER']))
			{
				$session->set('referer', $_SERVER['HTTP_REFERER']);
				$session->set('fromSearch', $session->isSearchReferer($_SERVER['HTTP_REFERER']));
			}
		}

		return $session;
	}

	/**
	 * Starts the admin session and sets up the visitor.
	 *
	 * @param Zend_Controller_Request_Http|null $request
	 *
	 * @return XenForo_Session
	 */
	public static function startAdminSession(Zend_Controller_Request_Http $request = null)
	{
		$class = XenForo_Application::resolveDynamicClass('XenForo_Session');
		/** @var $session XenForo_Session */
		$session = new $class(array('admin' => true));
		$session->start();
		XenForo_Application::set('session', $session);

		$visitor = XenForo_Visitor::setup($session->get('user_id'));

		if ($visitor['user_id'] && $session->get('password_date') && $session->get('password_date') != $visitor['password_date'])
		{
			$session->changeUserId(0);
			$visitor = XenForo_Visitor::setup(0);
		}

		return $session;
	}

	/**
	 * Starts the session running.
	 *
	 * @param string|null Session ID. If not provided, read from cookie.
	 * @param string|null IP address in one of various formats, for limiting access. If null, grabbed automatically.
	 */
	public function start($sessionId = null, $ipAddress = null)
	{
		if (!headers_sent())
		{
			header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
			header('Cache-control: private, max-age=0');
		}

		if ($sessionId === null)
		{
			if (isset($_POST['_xfSessionId']) && is_string($_POST['_xfSessionId']))
			{
				$sessionId = $_POST['_xfSessionId'];
			}
			else
			{
				$cookie = XenForo_Application::get('config')->cookie->prefix . $this->_config['cookie'];
				$sessionId = (isset($_COOKIE[$cookie]) ? $_COOKIE[$cookie] : '');
			}
			
			$sessionId = is_string($sessionId) ? $sessionId : '';
		}

		if ($ipAddress == null)
		{
			$ipAddress = XenForo_Helper_Ip::getBinaryIp();
		}
		else
		{
			$ipAddress = XenForo_Helper_Ip::convertIpStringToBinary($ipAddress);
		}

		$this->_setup($sessionId, $ipAddress);
	}

	/**
	 * Sets up the session.
	 *
	 * @param string $sessionId Session ID to look up, if one exists
	 * @param string|false $ipAddress IP address in binary format or false, for access limiting.
	 * @param array|null $defaultSession If no session can be found, uses this as the default session value
	 */
	protected function _setup($sessionId = '', $ipAddress = false, array $defaultSession = null)
	{
		$sessionId = strval($sessionId);

		if ($sessionId)
		{
			$session = $this->getSessionFromSource($sessionId);
			if ($session && !$this->sessionMatchesIp($session, $ipAddress))
			{
				$session = false;
			}
		}
		else
		{
			$session = false;
		}

		if (!is_array($session))
		{
			if ($defaultSession === null)
			{
				$defaultSession = array('sessionStart' => XenForo_Application::$time);
			}

			// if this is changed, change validation in getSessionFromSource
			$sessionId = md5(XenForo_Application::generateRandomString(16, true));
			$session = $defaultSession;
			$sessionExists = false;
		}
		else
		{
			$sessionExists = true;
		}

		if (!isset($session['ip']))
		{
			$session['ip'] = $ipAddress;
		}

		$this->_session = $session;
		$this->_sessionId = $sessionId;
		$this->_sessionExists = $sessionExists;

		if (!$sessionExists)
		{
			$this->generateSessionCsrf();
		}
	}

	/**
	 * Generates a session-specific CSRF token.
	 *
	 * @return string
	 */
	public function generateSessionCsrf()
	{
		$csrf = XenForo_Application::generateRandomString(16);
		$this->set('sessionCsrf', $csrf);

		return $csrf;
	}

	/**
	 * Deletes the current session. The session cookie will be removed as well.
	 */
	public function delete()
	{
		if ($this->_sessionExists)
		{
			$this->deleteSessionFromSource($this->_sessionId);
			if (!headers_sent())
			{
				XenForo_Helper_Cookie::deleteCookie($this->_config['cookie'], true);
			}
		}

		$this->_session = array();
		$this->_dataChanged = false;
		$this->_sessionId = '';
		$this->_sessionExists = false;
		$this->_saved = false;
	}

	/**
	 * Saves the current session. If a session is being created, the session cookie will be created.
	 */
	public function save()
	{
		if (!$this->_sessionId || $this->_saved)
		{
			return;
		}

		if (!$this->_sessionExists)
		{
			$this->saveSessionToSource($this->_sessionId, false);

			if (!headers_sent())
			{
				XenForo_Helper_Cookie::setCookie($this->_config['cookie'], $this->_sessionId, 0, true);
			}
		}
		else
		{
			$this->saveSessionToSource($this->_sessionId, true);
		}

		$this->_sessionExists = true;
		$this->_saved = true;
		$this->_dataChanged = false;
	}

	/**
	 * Maintains the current session values (if desired), but changes the session ID.
	 * Use this when the context (eg, user ID) of a session changes.
	 *
	 * @param boolean $keepExisting If true, keeps the existing info; if false, session data is removed
	 */
	public function regenerate($keepExisting = true)
	{
		if ($this->_sessionExists)
		{
			$this->deleteSessionFromSource($this->_sessionId);
		}

		$this->_setup('', $this->get('ip'), ($keepExisting ? $this->_session : null));
	}

	/**
	 * Gets the session ID.
	 *
	 * @return string
	 */
	public function getSessionId()
	{
		return $this->_sessionId;
	}

	/**
	 * Checks whether or not the specified key exists in the session data.
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	public function isRegistered($key)
	{
		return isset($this->_session[$key]);
	}

	/**
	 * Gets the specified data from the session.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get($key)
	{
		return (isset($this->_session[$key]) ? $this->_session[$key] : false);
	}

	/**
	 * Gets all data from the session.
	 *
	 * @return array
	 */
	public function getAll()
	{
		return array_merge($this->_session, array('session_id' => $this->getSessionId()));
	}

	/**
	 * Sets the specified data into the session. Can't be called after saving.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value)
	{
		if ($this->_saved)
		{
			throw new XenForo_Exception('The session has been saved and is now read-only.');
		}

		$this->_session[$key] = $value;
		$this->_dataChanged = true;
	}

	/**
	 * Removes the specified data from the session.
	 *
	 * @param string $key
	 */
	public function remove($key)
	{
		if ($this->_saved)
		{
			throw new XenForo_Exception('The session has been saved and is now read-only.');
		}

		unset($this->_session[$key]);
		$this->_dataChanged = true;
	}

	/**
	 * Changes the user ID of the session and automatically regenerates it to prevent session hijacking.
	 *
	 * @param integer $userId
	 * @param boolean $keepExisting If true, keeps existing session info; usually want this to be false to ensure things are built as needed
	 */
	public function changeUserId($userId, $keepExisting = false)
	{
		$this->regenerate($keepExisting);
		$this->set('user_id', intval($userId));
	}

	/**
	 * Indicates a login as a user and sets up a password date in the session
	 * for an extra layer of security (invalidates the session when the password changes).
	 *
	 * @param integer $userId
	 * @param integer $passwordDate
	 */
	public function userLogin($userId, $passwordDate)
	{
		$this->changeUserId($userId);
		if ($passwordDate)
		{
			$this->set('password_date', $passwordDate);
		}
	}

	/**
	 * True if the session exists (existed in previous request or has been saved).
	 *
	 * @return boolean
	 */
	public function sessionExists()
	{
		return $this->_sessionExists;
	}

	/**
	 * True if the session has been saved in this request (and thus write locked).
	 *
	 * @return boolean
	 */
	public function saved()
	{
		return $this->_saved;
	}

	/**
	 * Determines if the existing session matches the given IP address. Looks
	 * for the session's IP in the ip key. If not found, check passes.
	 *
	 * @param array $session
	 * @param string|false $ipAddress IP address as binary or false to prevent IP check
	 *
	 * @return boolean
	 */
	public function sessionMatchesIp(array $session, $ipAddress)
	{
		if (!isset($session['ip']) || empty($session['ip']) || empty($ipAddress))
		{
			return true; // no IP to check against
		}

		if (strlen($ipAddress) == 4)
		{
			$cidr = intval($this->_config['ipv4CidrMatch']);
		}
		else
		{
			$cidr = intval($this->_config['ipv6CidrMatch']);
		}

		if ($cidr <= 0)
		{
			return true; // IP check disabled
		}

		return XenForo_Helper_Ip::ipMatchesCidrRange($ipAddress, $session['ip'], $cidr);
	}

	/**
	 * Gets the specified session data from the source.
	 *
	 * @param string $sessionId
	 *
	 * @return array|false
	 */
	public function getSessionFromSource($sessionId)
	{
		if (!preg_match('/^[a-f0-9]{32}$/', $sessionId))
		{
			// doesn't correspond with approach in _setup
			return false;
		}

		if ($this->_cache)
		{
			$data = $this->_cache->load($this->_getSessionCacheName($sessionId));
		}
		else
		{
			$data = $this->_db->fetchOne('
				SELECT session_data
				FROM ' . $this->_config['table'] . '
				WHERE session_id = ?
					AND expiry_date >= ?
			', array($sessionId, XenForo_Application::$time));
		}

		if (!$data)
		{
			return false;
		}
		else
		{
			$data = unserialize($data);
			return (is_array($data) ? $data : false);
		}
	}

	/**
	 * Deletes the specified session from the source.
	 *
	 * @param string $sessionId
	 */
	public function deleteSessionFromSource($sessionId)
	{
		if ($this->_cache)
		{
			$this->_cache->remove($this->_getSessionCacheName());
		}
		else
		{
			$this->_db->delete($this->_config['table'],
				'session_id = ' . $this->_db->quote($sessionId)
			);
		}
	}

	public function saveSessionToSource($sessionId, $isUpdate)
	{
		if ($this->_cache)
		{
			// same behavior on insert and updated
			$this->_cache->save(
				serialize($this->_session),
				$this->_getSessionCacheName($sessionId),
				array(), $this->_config['lifetime']
			);
		}
		else if ($isUpdate)
		{
			// db update
			$data = array(
				'expiry_date' => XenForo_Application::$time + $this->_config['lifetime']
			);

			if ($this->_dataChanged)
			{
				$data['session_data'] = serialize($this->_session);
			}

			$this->_db->update($this->_config['table'], $data, 'session_id = ' . $this->_db->quote($sessionId));
		}
		else
		{
			// db insert
			$this->_db->insert($this->_config['table'], array(
				'session_id' => $sessionId,
				'session_data' => serialize($this->_session),
				'expiry_date' => XenForo_Application::$time + $this->_config['lifetime']
			));
		}
	}

	/**
	 * Deletes all sessions that have expired.
	 */
	public function deleteExpiredSessions()
	{
		// Leave this running when cached sessions are enabled to clear out the table on change.
		// After one run, it won't need to remove anything.
		$this->_db->delete($this->_config['table'],
			'expiry_date < ' . XenForo_Application::$time
		);
	}

	/**
	 * Checks whether or not the referer is a search engine.
	 *
	 * @param string $referer
	 *
	 * @return string|boolean
	 */
	public function isSearchReferer($referer)
	{
		$url = @parse_url($referer);

		if ($url && !empty($url['host']))
		{
			$url['host'] = strtolower($url['host']);

			if ($url['host'] == XenForo_Application::$host)
			{
				return false;
			}

			if (in_array($url['host'], $this->_searchDomains))
			{
				return $url['host'];
			}

			if (preg_match('#((^|\.)(' . implode('|', array_map('preg_quote', $this->_searchDomains)) . ')(\.co)?\.[a-z]{2,})$#i', $url['host'], $match))
			{
				return $match[3];
			}
		}

		return false;
	}

	/**
	 * Checks whether or not the user agent is a known robot.
	 *
	 * @param string $userAgent
	 *
	 * @return string
	 */
	public function getRobotId($userAgent)
	{
		$bots = $this->_knownRobots;

		if (preg_match('#(' . implode('|', array_map('preg_quote', array_keys($bots))) . ')#i', strtolower($userAgent), $match))
		{
			return $bots[$match[1]];
		}

		return '';
	}

	/**
	 * @param string $robotId
	 *
	 * @return bool|array
	 */
	public function getRobotInfo($robotId)
	{
		if (!$robotId)
		{
			return false;
		}
		else if (isset($this->_robotMap[$robotId]))
		{
			return $this->_robotMap[$robotId];
		}
		else
		{
			return $this->_robotMap['unknown'];
		}
	}

	protected function _getSessionCacheName($sessionId = null)
	{
		$sessionId = ($sessionId === null ? $this->_sessionId : $sessionId);
		return $this->_config['cacheName'] . '_' . $sessionId;
	}
}