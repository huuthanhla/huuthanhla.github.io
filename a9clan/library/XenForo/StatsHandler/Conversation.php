<?php

class XenForo_StatsHandler_Conversation extends XenForo_StatsHandler_Abstract
{
	public function getStatsTypes()
	{
		return array(
			'conversation' => new XenForo_Phrase('conversations'),
			'conversation_message' => new XenForo_Phrase('conversation_messages')
		);
	}

	public function getData($startDate, $endDate)
	{
		$conversations = $this->_getDb()->fetchPairs(
			$this->_getBasicDataQuery('xf_conversation_master', 'start_date'),
			array($startDate, $endDate)
		);

		$conversationMessages = $this->_getDb()->fetchPairs(
			$this->_getBasicDataQuery('xf_conversation_message', 'message_date'),
			array($startDate, $endDate)
		);

		return array(
			'conversation' => $conversations,
			'conversation_message' => $conversationMessages
		);
	}
}