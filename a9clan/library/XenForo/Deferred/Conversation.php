<?php

class XenForo_Deferred_Conversation extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'batch' => 100,
			'position' => 0
		), $data);

		/* @var $conversationModel XenForo_Model_Conversation */
		$conversationModel = XenForo_Model::create('XenForo_Model_Conversation');

		$ids = $conversationModel->getConversationIdsInRange($data['position'], $data['batch']);
		if (sizeof($ids) == 0)
		{
			return false;
		}

		foreach ($ids AS $id)
		{
			$data['position'] = $id;

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($id))
			{
				$dw->rebuildRecipients();
				$dw->save();
			}
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('conversations');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}