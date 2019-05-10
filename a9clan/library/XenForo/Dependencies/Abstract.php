<?php

/**
* Interface for objects that can be passed into the front controller to load all of
* its dependencies.
*
* @package XenForo_Mvc
*/
abstract class XenForo_Dependencies_Abstract
{
	/**
	 * A list of explicit view state changes from the controller. This will be used
	 * to modify how view rendering is done. For example, pages that want an explicit
	 * style ID can set it here.
	 *
	 * @var array Key-value pairs
	 */
	protected $_viewStateChanges = array();

	/**
	 * List of data to pre-load from the data registry. You must process this data
	 * via {@link _handleCustomPreLoadedData()}.
	 *
	 * @var array
	 */
	protected $_dataPreLoadFromRegistry = array();

	/**
	 * A list of template params that will be set in each template by default.
	 * Conflicting params are overridden by specific template params.
	 *
	 * @var array
	 */
	protected $_defaultTemplateParams = array();

	/**
	 * @return XenForo_Router
	 */
	abstract public function getRouter();

	/**
	* Determines if the controller matched by the route can be dispatched. Use this
	* function to ensure, for example, that an admin page only shows an admin controller.
	*
	* @param mixed  Likely a XenForo_Controller object, but not guaranteed
	* @param string Name of the action to call
	*
	* @return boolean
	*/
	abstract public function allowControllerDispatch($controller, $action);

	/**
	* Gets the routing information for a not found error
	*
	* @return array Format: [0] => controller name, [1] => action
	*/
	abstract public function getNotFoundErrorRoute();

	/**
	* Gets the routing information for a server error
	*
	* @return array Format: [0] => controller name, [1] => action
	*/
	abstract public function getServerErrorRoute();

	/**
	 * Gets the name of the base view class for this type.
	 *
	 * @return string
	 */
	abstract public function getBaseViewClassName();

	/**
	* Helper method to create a template object for rendering.
	*
	* @param string Name of the template to be used
	* @param array  Key-value parameters to pass to the template
	*
	* @return XenForo_Template_Abstract
	*/
	abstract public function createTemplateObject($templateName, array $params = array());

	/**
	 * Gets the extra container data from template renders.
	 *
	 * @return array
	 */
	abstract public function getExtraContainerData();

	/**
	* Preloads a template with the template handler for use later.
	*
	* @param string Template name
	*/
	abstract public function preloadTemplate($templateName);

	/**
	 * Routes the request.
	 *
	 * @param Zend_Controller_Request_Http $request
	 * @param string|null $routePath
	 *
	 * @return XenForo_RouteMatch
	 */
	public function route(Zend_Controller_Request_Http $request, $routePath = null)
	{
		return $this->getRouter()->match($request, $routePath);
	}

	/**
	* Creates the view renderer for a specified response type. If an invalid
	* type is specified, false is returned.
	*
	* @param Zend_Controller_Response_Http Response object
	* @param string                        Type of response
	* @param Zend_Controller_Request_Http  Request object
	*
	* @return XenForo_ViewRenderer_Abstract|false
	*/
	public function getViewRenderer(Zend_Controller_Response_Http $response, $responseType, Zend_Controller_Request_Http $request)
	{
		switch ($responseType)
		{
			case 'json':      return new XenForo_ViewRenderer_Json($this, $response, $request);
			case 'json-text': return new XenForo_ViewRenderer_JsonText($this, $response, $request);
			case 'rss':       return new XenForo_ViewRenderer_Rss($this, $response, $request);
			case 'css':       return new XenForo_ViewRenderer_Css($this, $response, $request);
			case 'xml':       return new XenForo_ViewRenderer_Xml($this, $response, $request);
			case 'raw':       return new XenForo_ViewRenderer_Raw($this, $response, $request);
			default:          return false;
		}
	}

	/**
	 * Pre-loads globally required data for the system.
	 */
	public function preLoadData()
	{
		$required = array_merge(
			array(
				'options', 'languages', 'contentTypes', 'codeEventListeners', 'deferredRun', 'simpleCache', 'addOns',
				'defaultStyleProperties', 'routeFiltersIn', 'routeFiltersOut'
			),
			$this->_dataPreLoadFromRegistry
		);
		$dr = new XenForo_Model_DataRegistry(); // this is a slight hack to prevent the class from being cached
		$data = $dr->getMulti($required);

		if (XenForo_Application::get('config')->enableListeners)
		{
			if (!is_array($data['codeEventListeners']))
			{
				$data['codeEventListeners'] = XenForo_Model::create('XenForo_Model_CodeEvent')->rebuildEventListenerCache();
			}
			XenForo_CodeEvent::setListeners($data['codeEventListeners']);
		}

		if (!is_array($data['options']))
		{
			$data['options'] = XenForo_Model::create('XenForo_Model_Option')->rebuildOptionCache();
		}
		$options = new XenForo_Options($data['options']);
		XenForo_Application::setDefaultsFromOptions($options);
		XenForo_Application::set('options', $options);

		if (!is_array($data['languages']))
		{
			$data['languages'] = XenForo_Model::create('XenForo_Model_Language')->rebuildLanguageCache();
		}
		XenForo_Application::set('languages', $data['languages']);

		if (!is_array($data['defaultStyleProperties']))
		{
			$data['defaultStyleProperties'] = XenForo_Model::create('XenForo_Model_StyleProperty')->rebuildPropertyCacheInStyleAndChildren(0, true);
		}
		XenForo_Application::set('defaultStyleProperties', $data['defaultStyleProperties']);

		if (!is_array($data['contentTypes']))
		{
			$data['contentTypes'] = XenForo_Model::create('XenForo_Model_ContentType')->rebuildContentTypeCache();
		}
		XenForo_Application::set('contentTypes', $data['contentTypes']);

		if (!is_int($data['deferredRun']))
		{
			$data['deferredRun'] = XenForo_Model::create('XenForo_Model_Deferred')->updateNextDeferredTime();
		}
		XenForo_Application::set('deferredRun', $data['deferredRun']);

		if (!is_array($data['addOns']))
		{
			$data['addOns'] = XenForo_Model::create('XenForo_Model_AddOn')->rebuildActiveAddOnCache();
		}
		XenForo_Application::set('addOns', $data['addOns']);

		if (!is_array($data['simpleCache']))
		{
			$data['simpleCache'] = array();
			XenForo_Model::create('XenForo_Model_DataRegistry')->set('simpleCache', $data['simpleCache']);
		}
		XenForo_Application::set('simpleCache', $data['simpleCache']);

		if (!is_array($data['routeFiltersIn']) || !is_array($data['routeFiltersOut']))
		{
			$filterCache = XenForo_Model::create('XenForo_Model_RouteFilter')->rebuildRouteFilterCache();
			$data['routeFiltersIn'] = $filterCache['in'];
			$data['routeFiltersOut'] = $filterCache['out'];
		}
		XenForo_Application::set('routeFiltersIn', $data['routeFiltersIn']);
		XenForo_Application::set('routeFiltersOut', $data['routeFiltersOut']);

		$this->_handleCustomPreloadedData($data);

		XenForo_CodeEvent::fire('init_dependencies', array($this, $data));
	}

	/**
	 * Handles the custom data that needs to be preloaded.
	 *
	 * @param array $data Data that was loaded. Unsuccessfully loaded items will have a value of null
	 */
	protected function _handleCustomPreloadedData(array &$data)
	{
	}

	/**
	 * Performs any pre-view rendering setup, such as getting style information and
	 * ensuring the correct data is registered.
	 *
	 * @param XenForo_ControllerResponse_Abstract|null $controllerResponse
	 */
	public function preRenderView(XenForo_ControllerResponse_Abstract $controllerResponse = null)
	{
		if (XenForo_Application::isRegistered('session'))
		{
			/* @var $session XenForo_Session */
			$session = XenForo_Application::get('session');
			$this->_defaultTemplateParams['session'] = $session->getAll();
			$this->_defaultTemplateParams['sessionId'] = $session->getSessionId();
		}
		$this->_defaultTemplateParams['requestPaths'] = XenForo_Application::get('requestPaths');

		$options = XenForo_Application::get('options')->getOptions();
		$options['cookieConfig'] = XenForo_Application::get('config')->cookie->toArray();
		$options['currentVersion'] = XenForo_Application::$version;
		$options['jsVersion'] = XenForo_Application::$jsVersion;

		$visitor = XenForo_Visitor::getInstance();
		$this->_defaultTemplateParams['visitor'] = $visitor->toArray();
		$this->_defaultTemplateParams['visitorLanguage'] = $visitor->getLanguage();
		$this->_defaultTemplateParams['pageIsRtl'] = (isset($this->_defaultTemplateParams['visitorLanguage']['text_direction']) && $this->_defaultTemplateParams['visitorLanguage']['text_direction'] == 'RTL');
		$this->_defaultTemplateParams['xenOptions'] = $options;
		$this->_defaultTemplateParams['xenCache'] = XenForo_Application::get('simpleCache');
		$this->_defaultTemplateParams['xenAddOns'] = XenForo_Application::get('addOns');
		$this->_defaultTemplateParams['serverTime'] = XenForo_Application::$time;
		$this->_defaultTemplateParams['debugMode'] = XenForo_Application::debugMode();
		$this->_defaultTemplateParams['javaScriptSource'] = XenForo_Application::$javaScriptUrl;

		if ($controllerResponse)
		{
			if ($controllerResponse instanceof XenForo_ControllerResponse_View && $controllerResponse->subView)
			{
				$this->_defaultTemplateParams['viewName'] = $controllerResponse->subView->viewName;
			}
			else
			{
				$this->_defaultTemplateParams['viewName'] = $controllerResponse->viewName;
			}
			$this->_defaultTemplateParams['controllerName']   = $controllerResponse->controllerName;
			$this->_defaultTemplateParams['controllerAction'] = $controllerResponse->controllerAction;
		}
	}

	/**
	* Gets the effective set of container params. This includes combining
	* and specific container params with any global ones. For example, a specific
	* container param may refer to the section the page is in, so this function
	* could load the other options that are specific to this section.
	*
	* @param array $params Container params from the controller/view
	* @param Zend_Controller_Request_Http $request
	*
	* @return array
	*/
	public function getEffectiveContainerParams(array $params, Zend_Controller_Request_Http $request)
	{
		if (XenForo_Application::get('config')->checkVersion)
		{
			$params['showUpgradePendingNotice'] = (
				XenForo_Application::debugMode()
				&& XenForo_Application::$versionId != XenForo_Application::get('options')->currentVersionId
			);
		}
		else
		{
			$params['showUpgradePendingNotice'] = false;
		}

		return $params;
	}

	/**
	 * Gets cron-related params for the container.
	 *
	 * @return array
	 */
	protected function _getCronContainerParams()
	{
		if (!XenForo_Application::isRegistered('deferredRun'))
		{
			return array();
		}

		$nextRun = XenForo_Application::get('deferredRun');
		if (!$nextRun || $nextRun >= XenForo_Application::$time)
		{
			return array();
		}

		return array(
			'hasAutoDeferred' => true
		);
	}

	/**
	 * Merge view state changes over any existing states.
	 *
	 * @param array $states Key-value pairs
	 */
	public function mergeViewStateChanges(array $states)
	{
		$this->_viewStateChanges = array_merge($this->_viewStateChanges, $states);
	}

	/**
	 * Fetch the path / URL to the jQuery core library
	 *
	 * @param boolean $forceLocal If true, forces the local version of jQuery
	 *
	 * @return string
	 */
	public static function getJquerySource($forceLocal = false)
	{
		// always serve the local jQuery, just in case the CDN is down
		return 'js/jquery/jquery-' . XenForo_Application::$jQueryVersion . '.min.js';
	}
}