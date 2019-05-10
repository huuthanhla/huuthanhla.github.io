<?php

class XenForo_Deferred_AttachmentThumb extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'batch' => 100,
			'position' => 0
		), $data);

		/* @var $attachmentModel XenForo_Model_Attachment */
		$attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');

		$s = microtime(true);

		$dataIds = $attachmentModel->getAttachmentDataIdsInRange($data['position'], $data['batch']);
		if (sizeof($dataIds) == 0)
		{
			return false;
		}

		foreach ($dataIds AS $dataId)
		{
			$data['position'] = $dataId;

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData', XenForo_DataWriter::ERROR_SILENT);
			if ($dw->setExistingData($dataId)
				&& $dw->get('width')
				&& XenForo_Image_Abstract::canResize($dw->get('width'), $dw->get('height'))
			)
			{
				$attach = $dw->getMergedData();
				$attachFile = $attachmentModel->getAttachmentDataFilePath($attach);
				$imageInfo = @getimagesize($attachFile);
				if ($imageInfo)
				{
					$image = XenForo_Image_Abstract::createFromFile($attachFile, $imageInfo[2]);
					if ($image)
					{
						if ($image->thumbnail(XenForo_Application::get('options')->attachmentThumbnailDimensions))
						{
							ob_start();
							$image->output($imageInfo[2]);
							$thumbData = ob_get_contents();
							ob_end_clean();
						}
						else
						{
							// no resize necessary, use the original
							$thumbData = file_get_contents($attachFile);
						}

						$dw->set('thumbnail_width', $image->getWidth());
						$dw->set('thumbnail_height', $image->getHeight());
						$dw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_THUMB_DATA, $thumbData);
						try
						{
							$dw->save();
						}
						catch (Exception $e)
						{
							XenForo_Error::logException($e, false, "Thumb rebuild for #$dataId: ");
						}

						unset($image);
					}
				}
			}

			if ($targetRunTime && microtime(true) - $s > $targetRunTime)
			{
				break;
			}
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('attachment_thumbnails');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}