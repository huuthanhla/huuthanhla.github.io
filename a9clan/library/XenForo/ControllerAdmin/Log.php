<?php

class XenForo_ControllerAdmin_Log extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('viewLogs');
	}

	public function actionServerError()
	{
		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);
		if ($id)
		{
			$entry = $this->_getLogModel()->getServerErrorLogById($id);
			if (!$entry)
			{
				return $this->responseError(new XenForo_Phrase('requested_server_error_log_entry_not_found'), 404);
			}

			$entry['requestState'] = unserialize($entry['request_state']);

			$viewParams = array(
				'entry' => $entry
			);
			return $this->responseView('XenForo_ViewAdmin_Log_ServerErrorView', 'log_server_error_view', $viewParams);
		}
		else
		{
			$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
			$perPage = 20;

			$viewParams = array(
				'entries' => $this->_getLogModel()->getServerErrorLogs(array(
					'page' => $page,
					'perPage' => $perPage
				)),

				'page' => $page,
				'perPage' => $perPage,
				'total' => $this->_getLogModel()->countServerErrors()
			);
			return $this->responseView('XenForo_ViewAdmin_Log_ServerError', 'log_server_error', $viewParams);
		}
	}

	public function actionServerErrorDelete()
	{
		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);
		$entry = $this->_getLogModel()->getServerErrorLogById($id);
		if (!$entry)
		{
			return $this->responseError(new XenForo_Phrase('requested_server_error_log_entry_not_found'), 404);
		}

		if ($this->isConfirmedPost())
		{
			$this->_getLogModel()->deleteServerErrorLog($id);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('logs/server-error')
			);
		}
		else
		{
			$viewParams = array(
				'entry' => $entry
			);
			return $this->responseView('XenForo_ViewAdmin_Log_ServerErrorDelete', 'log_server_error_delete', $viewParams);
		}
	}

	public function actionServerErrorClear()
	{
		if ($this->isConfirmedPost())
		{
			$this->_getLogModel()->clearServerErrorLog();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('logs/server-error')
			);
		}
		else
		{
			$viewParams = array();
			return $this->responseView('XenForo_ViewAdmin_Log_ServerErrorDelete', 'log_server_error_clear', $viewParams);
		}
	}

	public function actionSpamTrigger()
	{
		/** @var XenForo_Model_SpamPrevention $spamPreventionModel */
		$spamPreventionModel = $this->getModelFromCache('XenForo_Model_SpamPrevention');

		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);
		if ($id)
		{
			$entry = $spamPreventionModel->getSpamTriggerLogById($id);
			if (!$entry)
			{
				return $this->responseError(new XenForo_Phrase('requested_log_entry_not_found'), 404);
			}

			$entry['requestState'] = unserialize($entry['request_state']);

			$viewParams = array(
				'entry' => $spamPreventionModel->prepareSpamTriggerLog($entry)
			);
			return $this->responseView('XenForo_ViewAdmin_Log_SpamTriggerView', 'log_spam_trigger_view', $viewParams);
		}

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;

		$entries = $spamPreventionModel->getSpamTriggerLogs(array(), array(
			'page' => $page,
			'perPage' => $perPage
		));

		$viewParams = array(
			'entries' => $spamPreventionModel->prepareSpamTriggerLogs($entries),

			'page' => $page,
			'perPage' => $perPage,
			'total' => $spamPreventionModel->countSpamTriggerLogs()
		);
		return $this->responseView('XenForo_ViewAdmin_Log_SpamTrigger', 'log_spam_trigger', $viewParams);
	}

	public function actionAdmin()
	{
		$this->assertSuperAdmin();

		$logModel = $this->_getLogModel();

		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);
		if ($id)
		{
			$entry = $logModel->getAdminLogById($id);
			if (!$entry)
			{
				return $this->responseError(new XenForo_Phrase('requested_log_entry_not_found'), 404);
			}

			$entry['requestData'] = json_decode($entry['request_data'], true);

			$viewParams = array(
				'entry' => $logModel->prepareAdminLogEntry($entry)
			);
			return $this->responseView('XenForo_ViewAdmin_Log_AdminView', 'log_admin_view', $viewParams);
		}
		else
		{
			$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
			$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
			$perPage = 20;

			$pageParams = array();
			if ($userId)
			{
				$pageParams['user_id'] = $userId;
			}

			$entries = $logModel->getAdminLogEntries($userId, array('page' => $page, 'perPage' => $perPage));

			$viewParams = array(
				'entries' => $logModel->prepareAdminLogEntries($entries),
				'total' => $logModel->countAdminLogEntries($userId),
				'page' => $page,
				'perPage' => $perPage,
				'pageParams' => $pageParams,

				'logUsers' => $logModel->getUsersWithAdminLogs(),
				'userId' => $userId
			);

			return $this->responseView('XenForo_ViewAdmin_Log_Admin', 'log_admin', $viewParams);
		}
	}

	public function actionModerator()
	{
		$logModel = $this->_getLogModel();

		$id = $this->_input->filterSingle('id', XenForo_Input::UINT);
		if ($id)
		{
			$entry = $logModel->getModeratorLogById($id);
			if (!$entry)
			{
				return $this->responseError(new XenForo_Phrase('requested_log_entry_not_found'), 404);
			}

			$viewParams = array(
				'entry' => $logModel->prepareModeratorLogEntry($entry)
			);
			return $this->responseView('XenForo_ViewAdmin_Log_ModeratorView', 'log_moderator_view', $viewParams);
		}
		else
		{
			$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
			$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
			$perPage = 20;

			$pageParams = array();
			if ($userId)
			{
				$pageParams['user_id'] = $userId;
			}

			$entries = $logModel->getModeratorLogEntries($userId, array('page' => $page, 'perPage' => $perPage));

			$viewParams = array(
				'entries' => $logModel->prepareModeratorLogEntries($entries),
				'total' => $logModel->countModeratorLogEntries($userId),
				'page' => $page,
				'perPage' => $perPage,
				'pageParams' => $pageParams,

				'logUsers' => $logModel->getUsersWithModeratorLogs(),
				'userId' => $userId
			);

			return $this->responseView('XenForo_ViewAdmin_Log_Moderator', 'log_moderator', $viewParams);
		}
	}

	public function actionLinkProxy()
	{
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;

		$url = $this->_input->filterSingle('url', XenForo_Input::STRING);
		$sortOrder = $this->_input->filterSingle('order', XenForo_Input::STRING);

		$conditions = array(
			'url' => $url
		);

		$viewParams = array(
			'links' => $this->_getLinkProxyModel()->getLinkProxyLogs($conditions, array(
				'page' => $page,
				'perPage' => $perPage,
				'order' => $sortOrder
			)),
			'page' => $page,
			'perPage' => $perPage,
			'total' => $this->_getLinkProxyModel()->countLinkProxyItems($conditions),

			'url' => $url,
			'sortOrder' => $sortOrder
		);

		return $this->responseView('XenForo_ViewAdmin_Log_LinkCounter', 'log_link_proxy', $viewParams);
	}

	public function actionImageProxy()
	{
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 10;

		$proxyModel = $this->_getImageProxyModel();

		$url = $this->_input->filterSingle('url', XenForo_Input::STRING);
		$sortOrder = $this->_input->filterSingle('order', XenForo_Input::STRING);

		$conditions = array(
			'url' => $url
		);

		$viewParams = array(
			'images' => $proxyModel->prepareImages($proxyModel->getImageProxyLogs($conditions, array(
				'page' => $page,
				'perPage' => $perPage,
				'order' => $sortOrder
			))),
			'page' => $page,
			'perPage' => $perPage,
			'total' => $proxyModel->countImageProxyItems($conditions),

			'url' => $url,
			'sortOrder' => $sortOrder
		);

		return $this->responseView('XenForo_ViewAdmin_Log_ImageProxy', 'log_image_proxy', $viewParams);
	}

	public function actionImageProxyViewImage()
	{
		$image = $this->_getImageOrFallback();

		$viewParams = array('image' => $image);

		return $this->responseView('XenForo_ViewAdmin_Log_ImageProxyViewImage', 'log_image_proxy_view_image', $viewParams);
	}

	public function actionImageProxyView()
	{
		$image = $this->_getImageOrFallback();

		$viewParams = array('image' => $image);

		$this->_routeMatch->setResponseType('raw');

		return $this->responseView('XenForo_ViewAdmin_Log_ImageProxyView', '', $viewParams);
	}

	protected function _getImageOrFallback($imageId = null)
	{
		if ($imageId === null)
		{
			$imageId = $this->_input->filterSingle('image_id', XenForo_Input::UINT);
		}

		$image = $this->_getImageProxyModel()->getImageById($imageId);
		if ($image)
		{
			$image = $this->_getImageProxyModel()->prepareImage($image);
			if (!$image['use_file'])
			{
				$image = false;
			}
		}

		if (!$image)
		{
			$image = $this->_getImageProxyModel()->getPlaceHolderImage();
		}

		return $image;
	}

	public function actionImageProxyRecache()
	{
		$url = $this->_input->filterSingle('url', XenForo_Input::STRING);

		$image = $this->_getImageProxyModel()->getImage($url, true);
		$image = $this->_getImageProxyModel()->prepareImage($image);
		if (!$image['use_file'])
		{
			$image['pruned'] = 0;
		}

		$viewParams = array('image' => $image);

		return $this->responseView('XenForo_ViewAdmin_Log_ImageProxyRecache', 'log_image_proxy_item', $viewParams);
	}

	public function actionUserChangeLog()
	{
		// general change log for all users, by date

		$editUser = array();
		$conditions = array();
		$pageNavParams = array();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;

		$input = $this->_input->filter(array(
			'edit_user_id' => XenForo_Input::UINT,
			'username' => XenForo_Input::STRING
		));

		/* @var $userModel XenForo_Model_User */
		$userModel = $this->getModelFromCache('XenForo_Model_User');

		if (!empty($input['username']))
		{
			$editUser = $this->getRecordOrError(
				$input['username'], $userModel, 'getUserByName',
				'requested_user_not_found'
			);
		}
		else if (!empty($input['edit_user_id']))
		{
			$editUser = $this->getRecordOrError(
				$input['edit_user_id'], $userModel, 'getUserById',
				'requested_user_not_found'
			);
		}

		if (!empty($editUser))
		{
			$conditions['edit_user_id'] = $editUser['user_id'];
			$pageNavParams['edit_user_id'] = $editUser['user_id'];
		}

		/** @var XenForo_Model_UserChangeLog $userChangeModel */
		$userChangeModel = $this->getModelFromCache('XenForo_Model_UserChangeLog');

		$logs = $userChangeModel->getChangeLogs($conditions, array(
			'page' => $page,
			'perPage' => $perPage
		));

		$viewParams = array(
			'logs' => $logs,
			'editUser' => $editUser,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $userChangeModel->countChangeLogs($conditions),
			'pageNavParams' => $pageNavParams,
		);

		return $this->responseView('XenForo_ViewAdmin_Log_UserChangeLog', 'user_change_log', $viewParams);
	}

	public function actionEmailBounces()
	{
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 20;

		$bounceModel = $this->_getEmailBounceModel();
		$conditions = array();

		$bounces = $bounceModel->getEmailBounceLogs($conditions, array(
			'page' => $page,
			'perPage' => $perPage
		));
		$totalBounces = $bounceModel->countEmailBounceLogs($conditions);

		$viewParams = array(
			'page' => $page,
			'perPage' => $perPage,

			'bounces' => $bounces,
			'totalBounces' => $totalBounces
		);
		return $this->responseView('XenForo_ViewAdmin_Log_EmailBounce', 'email_bounce_log', $viewParams);
	}

	public function actionEmailBouncesView()
	{
		$bounceId = $this->_input->filterSingle('bounce_id', XenForo_Input::UINT);
		$bounce = $this->_getEmailBounceModel()->getEmailBounceLogById($bounceId);
		if (!$bounce)
		{
			return $this->responseError(new XenForo_Phrase('requested_page_not_found'), 404);
		}

		$this->_routeMatch->setResponseType('raw');

		$viewParams = array(
			'bounce' => $bounce
		);
		return $this->responseView('XenForo_ViewAdmin_Log_EmailBounceView', '', $viewParams);
	}

	public function actionSitemap()
	{
		/** @var XenForo_Model_Sitemap $sitemapModel */
		$sitemapModel = $this->getModelFromCache('XenForo_Model_Sitemap');

		$viewParams = array(
			'logs' => $sitemapModel->getSitemapHistory()
		);
		return $this->responseView('XenForo_ViewAdmin_Log_Sitemap', 'sitemap_log', $viewParams);
	}

	/**
	 * @return XenForo_Model_Log
	 */
	protected function _getLogModel()
	{
		return $this->getModelFromCache('XenForo_Model_Log');
	}

	/**
	 * @return XenForo_Model_LinkProxy
	 */
	protected function _getLinkProxyModel()
	{
		return $this->getModelFromCache('XenForo_Model_LinkProxy');
	}

	/**
	 * @return XenForo_Model_ImageProxy
	 */
	protected function _getImageProxyModel()
	{
		return $this->getModelFromCache('XenForo_Model_ImageProxy');
	}

	/**
	 * @return XenForo_Model_EmailBounce
	 */
	protected function _getEmailBounceModel()
	{
		return $this->getModelFromCache('XenForo_Model_EmailBounce');
	}
}