<?php

/**
* Filters routing back to the expected format
*
* This class never returns a route match with a controller and action specified.
*
* @package XenForo_Mvc
*/
class XenForo_Route_Filter implements XenForo_Route_Interface
{
	/**
	* Attempts to match the routing path. See {@link XenForo_Route_Interface} for further details.
	*
	* @param string $routePath Routing path
	* @param Zend_Controller_Request_Http $request Request object
	* @param XenForo_Router $router Routing object
	*
	* @return XenForo_RouteMatch|bool
	*/
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		if (!XenForo_Application::isRegistered('routeFiltersIn'))
		{
			return false;
		}

		$filters = XenForo_Application::get('routeFiltersIn');
		if (!$filters)
		{
			return false;
		}

		foreach ($filters AS $filter)
		{
			list($from, $to) = XenForo_Link::translateRouteFilterToRegex(
				urldecode($filter['replace_route']), urldecode($filter['find_route'])
			);

			$newRoutePath = preg_replace($from, $to, $routePath);
			if ($newRoutePath != $routePath)
			{
				$match = $router->getRouteMatch();
				$match->setModifiedRoutePath($newRoutePath);
				return $match;
			}
		}

		return false;
	}
}