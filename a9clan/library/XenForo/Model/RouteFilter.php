<?php

class XenForo_Model_RouteFilter extends XenForo_Model
{
	public function getRouteFilterById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_route_filter
			WHERE route_filter_id = ?
		', $id);
	}

	public function getRouteFilters($routeType)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_route_filter
			WHERE route_type = ?
			ORDER BY find_route
		', 'route_filter_id', $routeType);
	}

	public function getRouteFiltersForCache()
	{
		$results = $this->_getDb()->query("
			SELECT *
			FROM xf_route_filter
			WHERE route_type = 'public' AND enabled = 1
			ORDER BY LENGTH(replace_route) DESC
		");
		$in = array();
		while ($res = $results->fetch())
		{
			$in[$res['route_filter_id']] = array(
				'find_route' => $res['find_route'],
				'replace_route' => $res['replace_route']
			);
		}

		$results = $this->_getDb()->query("
			SELECT *
			FROM xf_route_filter
			WHERE route_type = 'public'
				AND enabled = 1
				AND url_to_route_only = 0
			ORDER BY prefix, LENGTH(find_route) DESC
		");
		$out = array();
		while ($res = $results->fetch())
		{
			$out[$res['prefix']][$res['route_filter_id']] = array(
				'find_route' => $res['find_route'],
				'replace_route' => $res['replace_route']
			);
		}

		return array(
			'in' => $in,
			'out' => $out
		);
	}

	public function rebuildRouteFilterCache()
	{
		$routeFilters = $this->getRouteFiltersForCache();
		$this->_getDataRegistryModel()->set('routeFiltersIn', $routeFilters['in']);
		$this->_getDataRegistryModel()->set('routeFiltersOut', $routeFilters['out']);

		return $routeFilters;
	}
}