<?php

class XenForo_ControllerAdmin_Attachment extends XenForo_ControllerAdmin_Abstract
{
	protected function _preDispatch($action)
	{
		$this->assertAdminPermission('attachment');
	}

	public function actionIndex()
	{
		if ($this->_input->inRequest('delete_selected'))
		{
			return $this->responseReroute(__CLASS__, 'delete');
		}

		$input = $this->_getFilterParams();

		$dateInput = $this->_input->filter(array(
			'start' => XenForo_Input::DATE_TIME,
			'end' => array(XenForo_Input::DATE_TIME, 'dayEnd' => true),
		));

		$attachmentModel = $this->_getAttachmentModel();

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$perPage = 50;

		$pageParams = array();
		if ($input['mode'])
		{
			$pageParams['mode'] = $input['mode'];
		}
		if ($input['start'])
		{
			$pageParams['start'] = $input['start'];
		}
		if ($input['end'])
		{
			$pageParams['end'] = $input['end'];
		}
		if ($input['content_type'])
		{
			$pageParams['content_type'] = $input['content_type'];
		}

		$userId = 0;
		if ($input['username'])
		{
			if ($user = $this->getModelFromCache('XenForo_Model_User')->getUserByName($input['username']))
			{
				$userId = $user['user_id'];
				$pageParams['username'] = $input['username'];
			}
			else
			{
				$input['username'] = '';
			}
		}

		$conditions = array(
			'content_type' => $input['content_type'],
			'user_id' => $userId,
			'start' => $dateInput['start'],
			'end' => $dateInput['end'],
		);
		$fetchOptions = array(
			'page' => $page,
			'perPage' => $perPage,
			'join' => XenForo_Model_Attachment::FETCH_USER
		);

		switch ($input['mode'])
		{
			case 'size':
				$fetchOptions['order'] = 'size';
				break;

			case 'recent';
			default:
				$input['mode'] = 'recent';
				$fetchOptions['order'] = 'recent';
				break;
		}

		$attachments = $attachmentModel->getAttachments($conditions, $fetchOptions);

		$viewParams = array(
			'contentTypes' => $attachmentModel->getAttachmentHandlerContentTypeNames(),

			'attachments' => $attachmentModel->prepareAttachments($attachments, true),

			'mode' => $input['mode'],
			'contentType' => $input['content_type'],
			'username' => $input['username'],
			'start' => $input['start'],
			'end' => $input['end'],

			'datePresets' => XenForo_Helper_Date::getDatePresets(),

			'page' => $page,
			'perPage' => $perPage,
			'pageParams' => $pageParams,
			'total' => $attachmentModel->countAttachments($conditions)
		);

		return $this->responseView('XenForo_ControllerAdmin_Attachment_List', 'attachment_list', $viewParams);
	}

	public function actionDelete()
	{
		$filterParams = $this->_getFilterParams();

		$attachmentIds = $this->_input->filterSingle('attachment_ids', array(XenForo_Input::UINT, 'array' => true));

		if ($attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT))
		{
			$attachmentIds[] = $attachmentId;
		}

		if ($this->isConfirmedPost())
		{
			// delete specified attachments

			foreach ($attachmentIds AS $attachmentId)
			{
				$dw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
				$dw->setExistingData($attachmentId);
				$dw->delete();
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('attachments', null, $filterParams)
			);
		}
		else
		{
			// show confirmation dialogue

			$viewParams = array(
				'attachmentIds' => $attachmentIds,
				'filterParams' => $filterParams
			);

			if (count($attachmentIds) == 1)
			{
				list($attachmentId) = $attachmentIds;
				$viewParams['attachment'] = $this->_getAttachmentModel()->getAttachmentById($attachmentId);
			}

			return $this->responseView('XenForo_ViewAdmin_Attachment_Delete', 'attachment_delete', $viewParams);
		}
	}

	/**
	 * This is taken almost verbatim from XenForo_ControllerPublic_Attachment::actionIndex,
	 * but has the permission check removed in order to allow admin-viewing
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionView()
	{
		$attachmentModel = $this->_getAttachmentModel();

		$attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);
		$attachment = $attachmentModel->getAttachmentById($attachmentId);
		if (!$attachment)
		{
			return $this->responseError(new XenForo_Phrase('requested_attachment_not_found'), 404);
		}

		$tempHash = $this->_input->filterSingle('temp_hash', XenForo_Input::STRING);

		/*if (!$attachmentModel->canViewAttachment($attachment, $tempHash))
		{
			return $this->responseNoPermission();
		}*/

		$filePath = $attachmentModel->getAttachmentDataFilePath($attachment);
		if (!file_exists($filePath) || !is_readable($filePath))
		{
			return $this->responseError(new XenForo_Phrase('attachment_cannot_be_shown_at_this_time'));
		}

		$eTag = $this->_request->getServer('HTTP_IF_NONE_MATCH');
		if ($eTag && $eTag == '"' . $attachment['attach_date'] . '"')
		{
			$this->_routeMatch->setResponseType('raw');
			return $this->responseView('XenForo_ViewAdmin_Attachment_View304');
		}

		$this->_routeMatch->setResponseType('raw');

		$viewParams = array(
			'attachment' => $attachment,
			'attachmentFile' => $filePath
		);

		return $this->responseView('XenForo_ViewAdmin_Attachment_View', '', $viewParams);
	}

	protected function _getFilterParams()
	{
		return $this->_input->filter(array(
			'mode' => XenForo_Input::STRING,
			'content_type' => XenForo_Input::STRING,
			'username' => XenForo_Input::STRING,
			'start' => XenForo_Input::STRING,
			'end' => XenForo_Input::STRING
		));
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}
}