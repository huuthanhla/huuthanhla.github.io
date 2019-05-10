<?php

/**
* Concrete renderer for HTML output.
*
* @package XenForo_Mvc
*/
class XenForo_ViewRenderer_HtmlPublic extends XenForo_ViewRenderer_Abstract
{
	protected $_contentTemplate = '';

	/**
	 * Constructor
	 * @see XenForo_ViewRenderer_Abstract::__construct()
	 */
	public function __construct(XenForo_Dependencies_Abstract $dependencies, Zend_Controller_Response_Http $response, Zend_Controller_Request_Http $request)
	{
		parent::__construct($dependencies, $response, $request);
		$this->_response->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
	}

	/**
	* Renders an error.
	* @see XenForo_ViewRenderer_Abstract::renderError()
	*
	* @param string|array $error
	*
	* @return string|false
	*/
	public function renderError($error)
	{
		if (!is_array($error))
		{
			$error = array($error);
		}

		$this->_contentTemplate = 'error';

		return $this->createTemplateObject('error', array('error' => $error));
	}

	/**
	 * Renders a message.
	 *
	 * @see XenForo_ViewRenderer_Abstract::renderMessage()
	 */
	public function renderMessage($message)
	{
		$this->_contentTemplate = 'message_page';

		return $this->createTemplateObject('message_page', array('message' => $message));
	}

	/**
	* Renders a view.
	* @see XenForo_ViewRenderer_Abstract::renderView()
	*/
	public function renderView($viewName, array $params = array(), $templateName = '', XenForo_ControllerResponse_View $subView = null)
	{
		$this->_contentTemplate = $templateName;

		if ($subView)
		{
			if ($templateName)
			{
				$this->preloadTemplate($templateName);
			}
			$params['_subView'] = $this->renderSubView($subView);
		}

		$viewOutput = $this->renderViewObject($viewName, 'Html', $params, $templateName);
		if ($viewOutput === null)
		{
			if (!$templateName)
			{
				return false;
			}
			else
			{
				return $this->createTemplateObject($templateName, $params);
			}
		}
		else
		{
			return $viewOutput;
		}
	}

	/**
	* Renders the container.
	* @see XenForo_ViewRenderer_Abstract::renderContainer()
	*
	* @param string
	* @param array
	*
	* @return string
	*/
	public function renderContainer($contents, array $params = array())
	{
		$params['contentTemplate'] = $this->_contentTemplate;
		$params['debugMode'] = XenForo_Application::debugMode();
		$params['serverTimeInfo'] = XenForo_Locale::getDayStartTimestamps();

		if (!empty($params['extraTabs']))
		{
			foreach ($params['extraTabs'] AS &$group)
			{
				foreach ($group AS &$extraTab)
				{
					if (!empty($extraTab['linksTemplate']))
					{
						$extraTab['linksTemplate'] = $this->createTemplateObject($extraTab['linksTemplate'], $extraTab + $params);
					}
				}
			}
		}

		$templateName = (!empty($params['containerTemplate']) ? $params['containerTemplate'] : 'PAGE_CONTAINER');
		$template = $this->createTemplateObject($templateName, $params);

		if ($contents instanceof XenForo_Template_Abstract)
		{
			$contents = $contents->render();
		}

		$containerData = $this->_dependencies->getExtraContainerData();

		$containerData['notices'] = $this->_getNoticesContainerParams($template, $containerData);

		$template->setParams($containerData);
		$template->setParam('contents', $contents);
		$template->setParam('noH1', (isset($containerData['h1']) && $containerData['h1'] === ''));

		if ($params['debugMode'])
		{
			$template->setParams(XenForo_Debug::getDebugTemplateParams());
		}

		$rendered = $template->render();

		$rendered = $this->replaceRequiredExternalPlaceholders($template, $rendered);

		$language = XenForo_Visitor::getInstance()->getLanguage();
		if (isset($language['text_direction']) && $language['text_direction'] == 'RTL')
		{
			$rendered = XenForo_Template_Helper_RightToLeft::replaceRtlEntities($rendered);
		}

		return $rendered;
	}

	/**
	* Data that should be preloaded for the container. Templates/phrases may be
	* accidentally (or intentionally) rendered in the view or before the container
	* is set to be rendered. Preloading data here can allow all the data to be fetched
	* at once.
	*/
	protected function _preloadContainerData()
	{
		$this->preloadTemplate('page_nav');
	}

	/**
	* Fallback for rendering an "unrepresentable" message.
	* @see XenForo_ViewRenderer_Abstract::renderUnrepresentable()
	*
	* @return string
	*/
	public function renderUnrepresentable()
	{
		return $this->renderError(new XenForo_Phrase('requested_page_is_unrepresentable_in_html'));
	}

	/**
	 * Fetches all notices applicable to the visiting user
	 *
	 * @param XenForo_Template_Abstract $template
	 * @param array $containerData
	 *
	 * @return array
	 */
	protected function _getNoticesContainerParams(XenForo_Template_Abstract $template, array $containerData)
	{
		$notices = array();

		foreach ($this->_dependencies->notices AS $param => $noticeKey)
		{
			if ($template->getParam($param))
			{
				$notices[$noticeKey] = array(
					'title' => new XenForo_Phrase($noticeKey),
					'message' => $template->create($noticeKey, $template->getParams()),
					'wrap' => true,
					'dismissible' => false
				);
			}
		}

		if (XenForo_Application::get('options')->enableNotices)
		{
			if (XenForo_Application::isRegistered('notices'))
			{
				$user = XenForo_Visitor::getInstance()->toArray();

				if (XenForo_Application::isRegistered('session'))
				{
					$dismissedNotices = XenForo_Application::getSession()->get('dismissedNotices');
				}

				if (!isset($dismissedNotices) || !is_array($dismissedNotices))
				{
					$dismissedNotices = array();
				}

				// handle style overrides
				$visitorStyle = $template->getParam('visitorStyle');
				if (!empty($visitorStyle))
				{
					$user['style_id'] = $visitorStyle['style_id'];
				}

				$noticeList = XenForo_Application::get('notices');

				foreach (XenForo_Application::get('notices') AS $noticeId => $notice)
				{
					if (in_array($noticeId, $dismissedNotices)
						|| !XenForo_Helper_Criteria::userMatchesCriteria($notice['user_criteria'], true, $user)
						|| !XenForo_Helper_Criteria::pageMatchesCriteria($notice['page_criteria'], true, $template->getParams(), $containerData))
					{
						unset($noticeList[$noticeId]);
					}
				}

				$noticeTokens = array(
					'{name}' => $user['username'] !== '' ? $user['username'] : new XenForo_Phrase('guest'),
					'{user_id}' => $user['user_id'],
				);

				XenForo_CodeEvent::fire('notices_prepare', array(&$noticeList, &$noticeTokens, $template, $containerData));

				foreach ($noticeList AS $noticeId => $notice)
				{
					$notices[$noticeId] = array(
						'title' => $notice['title'],
						'message' => str_replace(array_keys($noticeTokens), $noticeTokens, $notice['message']),
						'wrap' => $notice['wrap'],
						'dismissible' => ($notice['dismissible'] && XenForo_Visitor::getUserId())
					);
				}
			}
		}

		return $notices;
	}
}