<?php

/**
* Reroute controller response. This will cause the request to internally be
* redirected to the named route path. The user will not be made aware of
* this redirection.
*
* @package XenForo_Mvc
*/
class XenForo_ControllerResponse_ReroutePath extends XenForo_ControllerResponse_Abstract
{
	/**
	* Path to reroute to
	*
	* @var string
	*/
	public $path = '';
}