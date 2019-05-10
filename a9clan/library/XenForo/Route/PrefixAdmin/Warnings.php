<?php

/**
 * Route prefix handler for warnings in the admin control panel.
 */
class XenForo_Route_PrefixAdmin_Warnings implements XenForo_Route_Interface
{
	/**
	 * Match a specific route for an already matched prefix.
	 *
	 * @see XenForo_Route_Interface::match()
	 */
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		if (preg_match('#^action/(.*)$#i', $routePath, $match))
		{
			$action = 'action' . $router->resolveActionWithIntegerParam($match[1], $request, 'warning_action_id');
		}
		else
		{
			$action = $router->resolveActionWithIntegerParam($routePath, $request, 'warning_definition_id');
		}
		return $router->getRouteMatch('XenForo_ControllerAdmin_Warning', $action, 'userWarnings');
	}

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 *
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		if (preg_match('#^action/(.*)$#i', $action, $match))
		{
			return XenForo_Link::buildBasicLinkWithIntegerParam(
				"$outputPrefix/action", $match[1], $extension, $data, 'warning_action_id'
			);
		}
		else
		{
			return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'warning_definition_id', 'title');
		}
	}
}