<?php

class XenForo_ControllerPublic_Index extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		return $this->responseReroutePath(XenForo_Link::getIndexRoute());
	}
}