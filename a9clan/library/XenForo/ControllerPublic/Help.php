<?php

class XenForo_ControllerPublic_Help extends XenForo_ControllerPublic_Abstract
{
	protected $_pagesCache = null;

	public function actionIndex()
	{
		$pageName = $this->_input->filterSingle('page_name', XenForo_Input::STRING);
		if ($pageName !== '')
		{
			$actionName = str_replace(array('-', '/'), ' ', strtolower($pageName));
			$actionName = str_replace(' ', '', ucwords($actionName));
			if (strtolower($actionName) != 'index')
			{
				if (method_exists($this, "action$actionName"))
				{
					return $this->responseReroute(__CLASS__, $actionName);
				}

				$pageName = trim($pageName, '/ ');

				return $this->_handleHelpPage($pageName);
			}
		}

		$helpModel = $this->_getHelpModel();
		$pages = $helpModel->getHelpPages();
		$this->_pagesCache = $helpModel->preparePages($pages);

		$viewParams = array(
			'tosUrl' => XenForo_Dependencies_Public::getTosUrl(),
			'pages' => $this->_pagesCache
		);

		return $this->_getWrapper('',
			$this->responseView('XenForo_ViewPublic_Help_Index', 'help_index', $viewParams)
		);
	}

	protected function _handleHelpPage($pageName)
	{
		$page = $this->_getHelpModel()->getHelpPageByName($pageName);
		if (!$page)
		{
			return $this->responseError(new XenForo_Phrase('requested_page_not_found'), 404);
		}

		$this->canonicalizeRequestUrl(XenForo_Link::buildPublicLink('help', $page));

		$helpModel = $this->_getHelpModel();

		$viewParams = array(
			'page' => $helpModel->preparePage($page),
			'templateName' => $helpModel->getHelpPageTemplateName($page['page_id'])
		);
		$response = $this->responseView('XenForo_ViewPublic_Help_Page', 'help_page', $viewParams);

		if (!empty($page['callback_class']) && !empty($page['callback_method']))
		{
			call_user_func_array(array($page['callback_class'], $page['callback_method']), array($this, &$response));
		}

		return $this->_getWrapper($pageName, $response);
	}

	public function actionSmilies()
	{
		/* @var $smilieModel XenForo_Model_Smilie */
		$smilieModel = $this->getModelFromCache('XenForo_Model_Smilie');

		$smilieCategories = $smilieModel->getAllSmiliesCategorized();

		$viewParams = array(
			'smilieCategories' => $smilieModel->prepareCategorizedSmiliesForList($smilieCategories)
		);

		return $this->_getWrapper('smilies',
			$this->responseView('XenForo_ViewPublic_Help_Smilies', 'help_smilies', $viewParams)
		);
	}

	public function actionBbCodes()
	{
		/** @var XenForo_Model_BbCode $bbCodeModel */
		$bbCodeModel = $this->getModelFromCache('XenForo_Model_BbCode');

		$bbCodes = $bbCodeModel->getActiveBbCodes();
		foreach ($bbCodes AS $key => $bbCode)
		{
			if (!$bbCode['example'])
			{
				unset($bbCodes[$key]);
			}
		}

		$viewParams = array(
			'mediaSites' => $bbCodeModel->getAllBbCodeMediaSites(),
			'bbCodes' => $bbCodeModel->prepareBbCodes($bbCodes)
		);

		return $this->_getWrapper('bbCodes',
			$this->responseView('XenForo_ViewPublic_Help_BbCodes', 'help_bb_codes', $viewParams)
		);
	}

	public function actionTrophies()
	{
		/* @var $trophyModel XenForo_Model_Trophy */
		$trophyModel = $this->getModelFromCache('XenForo_Model_Trophy');

		$viewParams = array(
			'trophies' => $trophyModel->prepareTrophies($trophyModel->getAllTrophies())
		);

		return $this->_getWrapper('trophies',
			$this->responseView('XenForo_ViewPublic_Help_Trophies', 'help_trophies', $viewParams)
		);
	}

	public function actionTerms()
	{
		$options = XenForo_Application::get('options');

		if (!$options->tosUrl['type'])
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('index')
			);
		}
		else if ($options->tosUrl['type'] == 'custom')
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				$options->tosUrl['custom']
			);
		}

		return $this->_getWrapper('terms',
			$this->responseView('XenForo_ViewPublic_Help_Terms', 'help_terms')
		);
	}

	public function actionCookies()
	{
		return $this->_getWrapper('cookies',
			$this->responseView('XenForo_ViewPublic_Help_Cookies', 'help_cookies')
		);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('viewing_help');
	}

	protected function _getWrapper($selected, XenForo_ControllerResponse_View $subView)
	{
		if ($this->_pagesCache !== null)
		{
			$pages = $this->_pagesCache;
		}
		else
		{
			$helpModel = $this->_getHelpModel();
			$pages = $helpModel->preparePages($helpModel->getHelpPages());
		}

		$viewParams = array(
			'selected' => $selected,
			'tosUrl' => XenForo_Dependencies_Public::getTosUrl(),
			'pages' => $pages
		);

		$wrapper = $this->responseView('XenForo_ViewPublic_Help_Wrapper', 'help_wrapper', $viewParams);
		$wrapper->subView = $subView;

		return $wrapper;
	}

	protected function _assertViewingPermissions($action)
	{
	}

	/**
	 * @return XenForo_Model_Help
	 */
	protected function _getHelpModel()
	{
		return $this->getModelFromCache('XenForo_Model_Help');
	}
}