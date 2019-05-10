<?php

class XenForo_Importer_vBulletin4x extends XenForo_Importer_vBulletin
{
	public static function getName()
	{
		return 'vBulletin 4.x';
	}

	public function stepAttachments($start, array $options)
	{
		$options = array_merge(array(
			'path' => isset($this->_config['attachmentPath']) ? $this->_config['attachmentPath'] : '',
			'limit' => 50,
			'max' => false
		), $options);

		$sDb = $this->_sourceDb;
		$prefix = $this->_prefix;

		/* @var $model XenForo_Model_Import */
		$model = $this->_importModel;

		if ($options['max'] === false)
		{
			$options['max'] = $sDb->fetchOne('
				SELECT MAX(attachmentid)
				FROM ' . $prefix . 'attachment
			');
		}

		$contentId = $sDb->fetchOne('SELECT contenttypeid FROM ' . $prefix .'contenttype WHERE class = \'Post\'');

		$attachments = $sDb->fetchAll($sDb->limit(
			'
				SELECT attachment.attachmentid, attachment.userid, attachment.dateline,
					attachment.filename, attachment.counter, attachment.contentid, attachment.filedataid,
					filedata.userid AS filedata_userid
				FROM ' . $prefix . 'attachment AS attachment
				INNER JOIN ' . $prefix . 'filedata AS filedata ON (filedata.filedataid = attachment.filedataid)
				WHERE attachment.attachmentid > ' . $sDb->quote($start) . '
					AND attachment.contenttypeid = ' . $sDb->quote($contentId) . '
					AND attachment.state = \'visible\'
				ORDER BY attachment.attachmentid
			', $options['limit']
		));
		if (!$attachments)
		{
			return true;
		}

		$next = 0;
		$total = 0;

		$userIdMap = $model->getUserIdsMapFromArray($attachments, 'userid');

		$postIdMap = $model->getPostIdsMapFromArray($attachments, 'contentid');
		$posts = $model->getModelFromCache('XenForo_Model_Post')->getPostsByIds($postIdMap);

		foreach ($attachments AS $attachment)
		{
			$next = $attachment['attachmentid'];

			$newPostId = $this->_mapLookUp($postIdMap, $attachment['contentid']);
			if (!$newPostId)
			{
				continue;
			}

			if (!$options['path'])
			{
				$fData = $sDb->fetchOne('
					SELECT filedata
					FROM ' . $prefix . 'filedata
					WHERE filedataid = ' . $sDb->quote($attachment['filedataid'])
				);
				if ($fData === '')
				{
					continue;
				}

				$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				if (!$attachFile || !@file_put_contents($attachFile, $fData))
				{
					continue;
				}

				$isTemp = true;
			}
			else
			{
				$attachFileOrig = "$options[path]/" . implode('/', str_split($attachment['filedata_userid'])) . "/$attachment[filedataid].attach";
				if (!file_exists($attachFileOrig))
				{
					continue;
				}

				$attachFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
				copy($attachFileOrig, $attachFile);

				$isTemp = true;
			}

			$success = $model->importPostAttachment(
				$attachment['attachmentid'],
				$this->_convertToUtf8($attachment['filename']),
				$attachFile,
				$this->_mapLookUp($userIdMap, $attachment['userid'], 0),
				$newPostId,
				$attachment['dateline'],
				array('view_count' => $attachment['counter']),
				array($this, 'processAttachmentTags'),
				$posts[$newPostId]['message']
			);
			if ($success)
			{
				$total++;
			}

			if ($isTemp)
			{
				@unlink($attachFile);
			}
		}

		$this->_session->incrementStepImportTotal($total);

		return array($next, $options, $this->_getProgressOutput($next, $options['max']));
	}
}