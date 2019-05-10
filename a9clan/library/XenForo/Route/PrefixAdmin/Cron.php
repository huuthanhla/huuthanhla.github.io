<?php

/**
 * Route prefix handler for cron in the admin control panel.
 *
 * @package XenForo_Cron
 */
class XenForo_Route_PrefixAdmin_Cron implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithStringParam($routePath, $request, 'entry_id');
		return $router->getRouteMatch('XenForo_ControllerAdmin_Cron', $action, 'cronEntries');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		return XenForo_Link::buildBasicLinkWithStringParam($outputPrefix, $action, $extension, $data, 'entry_id');
	}
}