<?php

/**
 * Search controller.
 *
 * @package XenForo_Search
 */
class XenForo_ControllerPublic_Search extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Displays a form to do an advanced search.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$search = array(
			'child_nodes' => true,
			'order' => 'date'
		);

		$searchId = $this->_input->filterSingle('search_id', XenForo_Input::UINT);
		if ($searchId)
		{
			if ($this->_input->filterSingle('searchform', XenForo_Input::UINT))
			{
				$params = $this->_input->filter(array(
					'q' => XenForo_Input::STRING,
					't' => XenForo_Input::STRING,
					'o' => XenForo_Input::STRING,
					'g' => XenForo_Input::UINT,
					'c' => XenForo_Input::ARRAY_SIMPLE
				));

				// allow this to pass through for the search type check later
				$this->_request->setParam('type', $params['t']);

				$users = '';

				if (!empty($params['c']['user']))
				{
					foreach ($this->_getUserModel()->getUsersByIds($params['c']['user']) AS $user)
					{
						$users .= $user['username'] . ', ';
					}
					$users = substr($users, 0, -2);
				}

				if (!empty($params['c']['node']))
				{
					$nodes = array_fill_keys(explode(' ', $params['c']['node']), true);
				}
				else
				{
					$nodes = array();
				}

				if (!empty($params['c']['date']))
				{
					$date = XenForo_Locale::date(intval($params['c']['date']), 'picker');
				}
				else
				{
					$date = '';
				}

				if (!empty($params['c']['user_content']))
				{
					$userContent = $params['c']['user_content'];
				}
				else
				{
					$userContent = '';
				}

				$search = array_merge($search, array(
					'keywords' => $params['q'],
					'title_only' => !empty($params['c']['title_only']),
					'users' => $users,
					'user_content' => $userContent,
					'date' => $date,
					'nodes' => $nodes,
					'child_nodes' => empty($nodes),
					'order' => $params['o'],
					'group_discussion' => $params['g'],
					'existing' => true
				));
			}
			else
			{
				return $this->responseReroute(__CLASS__, 'results');
			}
		}

		if (!XenForo_Visitor::getInstance()->canSearch())
		{
			throw $this->getNoPermissionResponseException();
		}

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		if ($nodeId)
		{
			$search['nodes'][$nodeId] = true;
		}

		$viewParams = array(
			'supportsRelevance' => XenForo_Search_SourceHandler_Abstract::getDefaultSourceHandler()->supportsRelevance(),
			'nodes' => $this->_getNodeModel()->getViewableNodeList(null, true),
			'search' => (empty($search) ? array() : $search)
		);

		$searchType = $this->_input->filterSingle('type', XenForo_Input::STRING);
		if ($searchType)
		{
			$typeHandler = $this->_getSearchModel()->getSearchDataHandler($searchType);
			if ($typeHandler)
			{
				$viewParams['searchType'] = $searchType;

				$response = $typeHandler->getSearchFormControllerResponse($this, $this->_input, $viewParams);
				if ($response)
				{
					return $response;
				}
			}
		}

		$viewParams['searchType'] = '';

		return $this->responseView('XenForo_ViewPublic_Search_Form', 'search_form', $viewParams);
	}

	/**
	 * Performs a search.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSearch()
	{
		// note: intentionally not post-only

		if (!XenForo_Visitor::getInstance()->canSearch())
		{
			throw $this->getNoPermissionResponseException();
		}

		$input = $this->_input->filter(array(
			'keywords' => XenForo_Input::STRING,
			'title_only' => XenForo_Input::UINT,
			'date' => XenForo_Input::DATE_TIME,
			'users' => XenForo_Input::STRING,
			'nodes' => array(XenForo_Input::UINT, 'array' => true),
			'child_nodes' => XenForo_Input::UINT,
			'user_content' => XenForo_Input::STRING,

			'order' => XenForo_Input::STRING,
			'group_discussion' => XenForo_Input::UINT
		));
		$input['type'] = $this->_handleInputType($input);

		if (!$input['order'])
		{
			$input['order'] = 'date';
		}

		$origKeywords = $input['keywords'];
		$input['keywords'] = XenForo_Helper_String::censorString($input['keywords'], null, ''); // don't allow searching of censored stuff

		$visitorUserId = XenForo_Visitor::getUserId();
		$searchModel = $this->_getSearchModel();

		$constraints = $searchModel->getGeneralConstraintsFromInput($input, $errors);
		if ($errors)
		{
			return $this->responseError($errors);
		}

		if (!$input['type'] && $input['keywords'] === ''
			&& count($constraints) == 1
			&& !empty($constraints['user']) && count($constraints['user']) == 1
		)
		{
			// we're searching for messages by a single user
			$this->_request->setParam('user_id', reset($constraints['user']));
			return $this->responseReroute(__CLASS__, 'member');
		}

		if ($input['keywords'] === '' && empty($constraints['user']))
		{
			// must have keyword or user constraint
			return $this->responseError(new XenForo_Phrase('please_specify_search_query_or_name_of_member'));
		}

		$typeHandler = null;
		if ($input['type'])
		{
			if (is_array($input['type']))
			{
				$typeInfo = $input['type'];
				list($input['type'], $contentInfo) = each($input['type']);
				list($contentType, $contentId) = each($contentInfo);
			}

			$typeHandler = $searchModel->getSearchDataHandler($input['type']);
			if ($typeHandler)
			{
				$constraints = array_merge($constraints,
					$typeHandler->getTypeConstraintsFromInput($this->_input)
				);
			}
			else
			{
				$input['type'] = '';
			}
		}

		$search = $searchModel->getExistingSearch(
			$input['type'], $input['keywords'], $constraints, $input['order'], $input['group_discussion'], $visitorUserId
		);

		if (!$search)
		{
			$searcher = new XenForo_Search_Searcher($searchModel);

			if ($typeHandler)
			{
				$results = $searcher->searchType(
					$typeHandler, $input['keywords'], $constraints, $input['order'], $input['group_discussion']
				);

				$userResults = array();
			}
			else
			{
				$results = $searcher->searchGeneral($input['keywords'], $constraints, $input['order']);

				if ($this->_getUserModel()->canViewMemberList())
				{
					$userResults = $this->_getUserSearch($input['keywords']);
				}
				else
				{
					$userResults = array();
				}
			}

			if (!$results && !$userResults)
			{
				return $this->getNoSearchResultsResponse($searcher);
			}

			$warnings = $searcher->getErrors() + $searcher->getWarnings();

			$search = $searchModel->insertSearch(
				$results, $input['type'], $origKeywords, $constraints, $input['order'], $input['group_discussion'], $userResults,
				$warnings, $visitorUserId
			);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('search', $search),
			''
		);
	}

	/**
	 * Handles the special case of 'type' being submitted as type[post][thread_id] = x,
	 * In which case, the input should be translated as type=post, thread_id=x
	 *
	 * @return string
	 */
	protected function _handleInputType(array &$input = array())
	{
		if ($this->_input->inRequest('type'))
		{
			$typeParam = $this->_request->get('type');

			if (is_array($typeParam) && !empty($typeParam))
			{
				list($type, $typeExtra) = each($typeParam);

				if (is_array($typeExtra)) {
					foreach ($typeExtra AS $paramName => $paramValue)
					{
						if (!empty($paramName) && !empty($paramValue))
						{
							$paramNameClean = XenForo_Input::rawFilter($paramName, XenForo_Input::STRING);

							$this->_request->setParam($paramNameClean, $paramValue);

							if (isset($input[$paramNameClean]))
							{
								$input[$paramNameClean] = $paramValue;
							}
						}
					}
				}

				return XenForo_Input::rawFilter($type, XenForo_Input::STRING);
			}
			else
			{
				return $this->_input->filterSingle('type', XenForo_Input::STRING);
			}
		}

		return '';
	}

	/**
	 * Searches for recent content by the specified member.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMember()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$user = $this->_getUserModel()->getUserById($userId);
		if (!$user)
		{
			return $this->responseError(new XenForo_Phrase('requested_member_not_found'), 404);
		}

		$searchModel = $this->_getSearchModel();
		$maxDate = $this->_input->filterSingle('before', XenForo_Input::UINT);

		$content = $this->_input->filterSingle('content', XenForo_Input::STRING);
		if ($content)
		{
			$constraints = array(
				'user' => array($userId),
				'content' => $content,
				'title_only' => true
			);
			if ($maxDate)
			{
				$constraints['date_max'] = $maxDate;
			}

			$searcher = new XenForo_Search_Searcher($searchModel);
			$results = $searcher->searchGeneral('', $constraints, 'date');
		}
		else
		{
			$searchModel = $this->_getSearchModel();
			$searcher = new XenForo_Search_Searcher($searchModel);

			$results = $searcher->searchUser($userId, $maxDate);
		}

		if (!$results)
		{
			return $this->getNoSearchResultsResponse($searcher);
		}

		$search = $searchModel->insertSearch($results, 'user', '', array('user_id' => $userId, 'content' => $content ? $content : false), 'date', false);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('search', $search),
			''
		);
	}

	/**
	 * Displays the results of a search.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionResults()
	{
		$searchModel = $this->_getSearchModel();

		$searchId = $this->_input->filterSingle('search_id', XenForo_Input::UINT);
		$searchQuery = $this->_input->filterSingle('q', XenForo_Input::STRING);

		$search = $searchModel->getSearchById($searchId);

		if (!$search)
		{
			$rerunSearch = true;
		}
		else if ($search['user_id'] != XenForo_Visitor::getUserId())
		{
			if ($search['search_query'] === '' || $search['search_query'] !== $searchQuery)
			{
				// just browsing searches without having query
				return $this->responseError(new XenForo_Phrase('requested_search_not_found'), 404);
			}

			$rerunSearch = true;
		}
		else
		{
			$rerunSearch = false;
		}

		if ($rerunSearch)
		{
			$rerunInput = $this->_input->filter(array(
				'q' => XenForo_Input::STRING,
				't' => XenForo_Input::STRING,
				'o' => XenForo_Input::STRING,
				'g' => XenForo_Input::UINT,
				'c' => XenForo_Input::ARRAY_SIMPLE
			));
			$rerun = array(
				'search_query' => $rerunInput['q'],
				'search_type' => $rerunInput['t'],
				'search_order' => $rerunInput['o'],
				'search_grouping' => $rerunInput['g'],
			);

			$newSearch = $this->rerunSearch($rerun, $rerunInput['c']);
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('search', $newSearch)
			);
		}

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$perPage = XenForo_Application::get('options')->searchResultsPerPage;

		$pageResultIds = $searchModel->sliceSearchResultsToPage($search, $page, $perPage);
		$results = $searchModel->getSearchResultsForDisplay($pageResultIds);
		if (!$results)
		{
			return $this->getNoSearchResultsResponse($search);
		}

		$resultStartOffset = ($page - 1) * $perPage + 1;
		$resultEndOffset = ($page - 1) * $perPage + count($results['results']);

		if ($search['search_type'] == 'user'
			&& $search['result_count'] > $perPage
			&& $resultEndOffset >= $search['result_count']
		)
		{
			// user search on last page (with more than one page)
			$last = end($results['results']);
			$userSearchMaxDate = $results['handlers'][$last[XenForo_Model_Search::CONTENT_TYPE]]->getResultDate($last['content']);
		}
		else
		{
			$userSearchMaxDate = false;
		}

		$ignoredNames = array();
		foreach ($results['results'] AS $result)
		{
			$content = $result['content'];
			if (!empty($content['isIgnored']) && !empty($content['user_id']) && !empty($content['username']))
			{
				$ignoredNames[$content['user_id']] = $content['username'];
			}
		}

		$pageHandlers = $results['handlers'];
		$modType = $this->_input->filterSingle('mod', XenForo_Input::STRING);

		$groupedInlineModOptions = $searchModel->setupInlineModerationForSearchResults($results['results'], $pageHandlers);
		$supportedInlineModTypes = array();
		foreach ($groupedInlineModOptions AS $inlineModType => $options)
		{
			if ($options)
			{
				$config = $results['handlers'][$inlineModType]->getInlineModConfiguration();
				if ($config)
				{
					$supportedInlineModTypes[$inlineModType] = $config;
				}
				else
				{
					unset($groupedInlineModOptions[$inlineModType]);
				}
			}
			else
			{
				unset($groupedInlineModOptions[$inlineModType]);
			}
		}

		if ($modType && isset($supportedInlineModTypes[$modType]))
		{
			$activeInlineMod = $supportedInlineModTypes[$modType];
			$inlineModOptions = $groupedInlineModOptions[$modType];
		}
		else
		{
			$activeInlineMod = null;
			$inlineModOptions = array();
			$modType = '';
			$inlineModForceEnable = false;
		}

		$viewParams = array(
			'search' => $searchModel->prepareSearch($search),
			'results' => $results,
			'ignoredNames' => $ignoredNames,

			'modType' => $modType,
			'supportedInlineModTypes' => $supportedInlineModTypes,
			'activeInlineMod' => $activeInlineMod,
			'inlineModOptions' => $inlineModOptions,

			'resultStartOffset' => $resultStartOffset,
			'resultEndOffset' => $resultEndOffset,

			'page' => $page,
			'perPage' => $perPage,
			'totalResults' => $search['result_count'],
			'nextPage' => ($resultEndOffset < $search['result_count'] ? ($page + 1) : 0),

			'userSearchMaxDate' => $userSearchMaxDate,
		);

		return $this->responseView('XenForo_ViewPublic_Search_Results', 'search_results', $viewParams);
	}

	/**
	 * Reruns the given search. If errors occur, a response exception will be thrown.
	 *
	 * @param array $search Search info (does not need search_id, constraints, results, or warnings)
	 * @param array $constraints Array of search constraints
	 *
	 * @return array New search
	 */
	public function rerunSearch(array $search, array $constraints)
	{
		if (!XenForo_Visitor::getInstance()->canSearch())
		{
			throw $this->getNoPermissionResponseException();
		}

		$visitorUserId = XenForo_Visitor::getUserId();
		$searchModel = $this->_getSearchModel();

		$existingSearch = $searchModel->getExistingSearch(
			$search['search_type'], $search['search_query'], $constraints,
			$search['search_order'], $search['search_grouping'], $visitorUserId
		);
		if ($existingSearch)
		{
			return $existingSearch;
		}

		$typeHandler = null;
		if ($search['search_type'])
		{
			$typeHandler = $searchModel->getSearchDataHandler($search['search_type']);
		}

		$searcher = new XenForo_Search_Searcher($searchModel);

		$userResults = array();

		if ($typeHandler)
		{
			$results = $searcher->searchType(
				$typeHandler, $search['search_query'], $constraints,
				$search['search_order'], $search['search_grouping']
			);

			$userResults = array();
		}
		else
		{
			$search['search_type'] = '';

			$results = $searcher->searchGeneral(
				$search['search_query'], $constraints, $search['search_order']
			);

			$userResults = $this->_getUserSearch($search['search_query']);
		}

		if (!$results && !$userResults)
		{
			throw $this->responseException($this->getNoSearchResultsResponse($searcher));
		}

		return $searchModel->insertSearch(
			$results, $search['search_type'], $search['search_query'], $constraints,
			$search['search_order'], $search['search_grouping'], $userResults,
			$searcher->getWarnings(), $visitorUserId
		);
	}

	/**
	 * Performs a simple search for users with names matching the given query string as a prefix
	 *
	 * @param string $queryString
	 *
	 * @return array User IDs
	 */
	protected function _getUserSearch($queryString)
	{
		if (XenForo_Application::get('options')->searchUsersWithContent && utf8_strlen($queryString) >= 2)
		{
			$users = $this->_getUserModel()->getUsers(
				array('username' => array($queryString , 'r'), 'user_state' => 'valid', 'is_banned' => 0),
				array('limit' => 10)
			);

			return array_keys($users);
		}

		return array();
	}

	/**
	 * Handles the response behavior when there are no search results.
	 *
	 * @param XenForo_Search_Searcher|array $search
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function getNoSearchResultsResponse($search)
	{
		if ($search instanceof XenForo_Search_Searcher)
		{
			$errors = $search->getErrors();
			if ($errors)
			{
				return $this->responseError($errors);
			}
		}
		else if (is_array($search) && !empty($search['user_results']))
		{
			$viewParams = array('search' => $this->_getSearchModel()->prepareSearch($search));

			return $this->responseView('XenForo_ViewPublic_Search_UserResults', 'search_results_users_only', $viewParams);
		}

		return $this->responseMessage(new XenForo_Phrase('no_results_found'));
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('searching');
	}

	/**
	 * Gets the specified search for the specified permission combination, or throws an error.
	 * A valid search with out
	 *
	 * @param integer $searchId
	 * @param string $searchQuery Text being searched for; prevents browsing of search terms
	 *
	 * @return array
	 */
	protected function _getSearchOrError($searchId, $searchQuery)
	{
		$searchModel = $this->_getSearchModel();

		$search = $searchModel->getSearchById($searchId);
		if (!$search || $search['search_query'] !== $searchQuery)
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_search_not_found'), 404));
		}

		return $searchModel->prepareSearch($search);
	}

	/**
	 * @return XenForo_Model_Search
	 */
	protected function _getSearchModel()
	{
		return $this->getModelFromCache('XenForo_Model_Search');
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}