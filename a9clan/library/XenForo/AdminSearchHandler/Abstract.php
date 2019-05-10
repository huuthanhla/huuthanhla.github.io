<?php

abstract class XenForo_AdminSearchHandler_Abstract
{
	/**
	 * Standard approach to caching other model objects for the lifetime of the model.
	 *
	 * @var array
	 */
	protected $_modelCache = array();

	/**
	 * Returns the phrase key of a phrase that describes the search type.
	 *
	 * @return string
	 */
	abstract public function getPhraseKey();

	/**
	 * Perform a search of the ACP for the given search text within the given content type.
	 *
	 * @param string $searchText
	 * @param array $phraseMatches Array of IDs derived from a phrase search for the search text
	 *
	 * @return array
	 */
	abstract public function search($searchText, array $phraseMatches = null);

	/**
	 * Gets the constraints to apply to a phrases search for this type
	 *
	 * @return array|false
	 */
	public function getPhraseConditions()
	{
		return false;
	}

	/**
	 * Creates a template object in which to display the search results.
	 *
	 * @param array $results
	 * @param XenForo_View $view
	 *
	 * @return XenForo_Template_Admin
	 */
	public function renderResults($results, XenForo_View $view)
	{
		return $view->createTemplateObject(
			$this->_getTemplateName(),
			array('results' => $this->_limitResults($results))
		);
	}

	/**
	 * Returns the name of the template used by the rendered results of this search.
	 *
	 * @return string
	 */
	abstract protected function _getTemplateName();

	/**
	 * Slices down the number of results returned
	 *
	 * @param array $results
	 *
	 * @return array
	 */
	protected function _limitResults(array $results)
	{
		return array_slice($results, 0, XenForo_Application::get('options')->adminSearchMaxResults);
	}

	/**
	 * @return XenForo_Model_AdminSearch
	 */
	protected function _getAdminSearchModel()
	{
		return $this->getModelFromCache('XenForo_Model_AdminSearch');
	}

	/**
	 * Gets the specified model object from the cache. If it does not exist,
	 * it will be instantiated.
	 *
	 * @param string $class Name of the class to load
	 *
	 * @return XenForo_Model
	 */
	public function getModelFromCache($class)
	{
		if (!isset($this->_modelCache[$class]))
		{
			$this->_modelCache[$class] = XenForo_Model::create($class);
		}

		return $this->_modelCache[$class];
	}

	/**
	 *
	 */
	public function getAdminPermission()
	{
		return false;
	}

	/**
	 * Insert the data from $item into the Admin Search Index
	 *
	 * @param array $item
	 * @param XenForo_DataWriter $dw
	 *
	 * @return boolean
	 */
	//abstract public function index(array $item, XenForo_DataWriter $dw = null);
}