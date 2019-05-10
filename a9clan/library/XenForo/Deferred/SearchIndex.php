<?php

class XenForo_Deferred_SearchIndex extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$inputHandler = new XenForo_Input($data);
		$input = $inputHandler->filter(array(
			'batch' => XenForo_Input::UINT,
			'start' => XenForo_Input::UINT,
			'extra_data' => XenForo_Input::ARRAY_SIMPLE,
			'delay' => XenForo_Input::UNUM,
			'content_type' => XenForo_Input::STRING,
			'delete_index' => XenForo_Input::UINT
		));

		if ($input['delay'] >= 0.01)
		{
			usleep($input['delay'] * 1000000);
		}

		/* @var $searchModel XenForo_Model_Search */
		$searchModel = XenForo_Model::create('XenForo_Model_Search');
		$searchContentTypes = $searchModel->getSearchContentTypes();

		$extraData = $input['extra_data'];
		if (!isset($extraData['content_types']) || !is_array($extraData['content_types']))
		{
			if ($input['content_type'] && isset($searchContentTypes[$input['content_type']]))
			{
				$extraData['content_types'] = array($input['content_type']);
			}
			else
			{
				$extraData['content_types'] = array_keys($searchContentTypes);
			}
		}
		if (empty($extraData['current_type']))
		{
			$extraData['current_type'] = array_shift($extraData['content_types']);
		}
		if (empty($extraData['type_start']))
		{
			$extraData['type_start'] = 0;
		}

		$originalExtraData = $extraData;

		while (!isset($searchContentTypes[$extraData['current_type']]))
		{
			if (!$extraData['content_types'])
			{
				return false;
			}

			$extraData['current_type'] = array_shift($extraData['content_types']);
		}

		if ($input['delete_index'])
		{
			$source = XenForo_Search_SourceHandler_Abstract::getDefaultSourceHandler();
			$source->deleteIndex($input['content_type'] ? $input['content_type'] : null);
		}

		$searchHandler = $searchContentTypes[$extraData['current_type']];
		if (class_exists($searchHandler))
		{
			$dataHandler = XenForo_Search_DataHandler_Abstract::create($searchHandler);
			$indexer = new XenForo_Search_Indexer();
			$indexer->setIsRebuild(true);

			$nextStart = $dataHandler->rebuildIndex($indexer, $extraData['type_start'], $input['batch']);

			$indexer->finalizeRebuildSet();
		}
		else
		{
			$nextStart = false;
		}

		if ($nextStart === false)
		{
			// move on to next type
			$extraData['current_type'] = '';
			$extraData['type_start'] = 0;
		}
		else
		{
			$extraData['type_start'] = $nextStart;
		}

		$data = array(
			'batch' => $input['batch'],
			'start' => $input['start'] + 1,
			'extra_data' => $extraData,
			'delay' => $input['delay']
		);

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('search_index');
		$text = new XenForo_Phrase($originalExtraData['current_type']);

		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, "$text " . XenForo_Locale::numberFormat($originalExtraData['type_start']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}