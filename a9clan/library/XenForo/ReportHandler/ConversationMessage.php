<?php

/**
 * Handler for reported conversation messages.
 *
 * @package XenForo_Report
 */
class XenForo_ReportHandler_ConversationMessage extends XenForo_ReportHandler_Abstract
{
	/**
	 * Gets report details from raw array of content (eg, a post record).
	 *
	 * @see XenForo_ReportHandler_Abstract::getReportDetailsFromContent()
	 */
	public function getReportDetailsFromContent(array $content)
	{
		/* @var $conversationModel XenForo_Model_Conversation */
		$conversationModel = XenForo_Model::create('XenForo_Model_Conversation');

		$message = $conversationModel->getConversationMessageById($content['message_id']);
		if (!$message)
		{
			return array(false, false, false);
		}

		if (empty($content['conversation']))
		{
			$content['conversation'] = $conversationModel->getConversationMasterById($message['conversation_id']);
		}

		$conversation = XenForo_Application::arrayFilterKeys($content['conversation'], array
		(
			'conversation_id', 'title', 'start_date',
			'user_id', 'username', 'reply_count'
		));

		return array(
			$content['message_id'],
			$content['user_id'],
			array(
				'username' => $content['username'],
				'message' => $content['message'],
				'conversation' => $conversation,
				'recipients' => $conversationModel->getConversationRecipients($conversation['conversation_id'])
			)
		);
	}

	/**
	 * Gets the visible reports of this content type for the viewing user.
	 *
	 * @see XenForo_ReportHandler_Abstract:getVisibleReportsForUser()
	 */
	public function getVisibleReportsForUser(array $reports, array $viewingUser)
	{
		/* @var $conversationModel XenForo_Model_Conversation */
		$conversationModel = XenForo_Model::create('XenForo_Model_Conversation');

		foreach ($reports AS $reportId => $report)
		{
			$conversation = unserialize($report['content_info']);

			$message = array(
				'message_id' => $report['content_id'],
				'user_id' => $report['user_id'],
			) + $conversation;

			if (!$conversationModel->canManageReportedMessage($message, $conversation, $errorPhraseKey, $viewingUser))
			{
				unset($reports[$reportId]);
			}
		}

		return $reports;
	}

	/**
	 * Gets the title of the specified content.
	 *
	 * @see XenForo_ReportHandler_Abstract:getContentTitle()
	 */
	public function getContentTitle(array $report, array $contentInfo)
	{
		return new XenForo_Phrase('conversation_message_in_x', array('title' => $contentInfo['conversation']['title']));
	}

	/**
	 * Gets the link to the specified content.
	 *
	 * @see XenForo_ReportHandler_Abstract::getContentLink()
	 */
	public function getContentLink(array $report, array $contentInfo)
	{
		// we can't have non-participants view a conversation at this point, so don't provide a link
		// maybe check if the visitor has permission to view the conversation and build the link then?
		return '';

		return XenForo_Link::buildPublicLink('conversations/message',
			array(
				'conversation_id' => $contentInfo['conversation']['conversation_id'],
				'title' => $contentInfo['conversation']['title']
			),
			array(
				'message_id' => $report['content_id']
			));
	}

	/**
	 * A callback that is called when viewing the full report.
	 *
	 * @see XenForo_ReportHandler_Abstract::viewCallback()
	 */
	public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
	{
		$parser = XenForo_BbCode_Parser::create(
			XenForo_BbCode_Formatter_Base::create('Base', array('view' => $view))
		);

		return $view->createTemplateObject('report_conversation_message_content', array(
			'report' => $report,
			'content' => $contentInfo,
			'bbCodeParser' => $parser
		));
	}
}