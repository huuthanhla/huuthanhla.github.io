<?php

/**
 * Model for attachments.
 *
 * @package XenForo_Attachment
 */
class XenForo_Model_Attachment extends XenForo_Model
{
	/**
	 * @var integer Join user table to fetch attachment options
	 */
	const FETCH_USER = 0x01;

	public static $dataColumns =
		'data.filename, data.file_size, data.file_hash, data.width, data.height, data.thumbnail_width, data.thumbnail_height';

	/**
	 * Get attachments (and limited data info) by the given content IDs.
	 *
	 * @param string $contentType
	 * @param array $contentIds
	 *
	 * @return array Format: [attachment id] => info
	 */
	public function getAttachmentsByContentIds($contentType, array $contentIds)
	{
		return $this->fetchAllKeyed('
			SELECT attachment.*,
				' . self::$dataColumns . '
			FROM xf_attachment AS attachment
			INNER JOIN xf_attachment_data AS data ON
				(data.data_id = attachment.data_id)
			WHERE attachment.content_type = ?
				AND attachment.content_id IN (' . $this->_getDb()->quote($contentIds) . ')
			ORDER BY attachment.content_id, attachment.attach_date
		', 'attachment_id', $contentType);
	}

	/**
	 * Gets the attachments (along with limited data info) that belong to the given content ID.
	 *
	 * @param string $contentType
	 * @param integer $contentId
	 *
	 * @return array Format: [attachment id] => info
	 */
	public function getAttachmentsByContentId($contentType, $contentId)
	{
		return $this->getAttachmentsByContentIds($contentType, array($contentId));
	}

	/**
	 * Gets all attachments (with limited data info) that have the specified temp hash.
	 *
	 * @param string $tempHash
	 *
	 * @return array Format: [attachment id] => info
	 */
	public function getAttachmentsByTempHash($tempHash)
	{
		if (strval($tempHash) === '')
		{
			return array();
		}

		return $this->fetchAllKeyed('
			SELECT attachment.*,
				' . self::$dataColumns . '
			FROM xf_attachment AS attachment
			INNER JOIN xf_attachment_data AS data ON
				(data.data_id = attachment.data_id)
			WHERE attachment.temp_hash = ?
			ORDER BY attachment.attach_date
		', 'attachment_id', $tempHash);
	}

	/**
	 * Gets the specified attachment by it's ID. Includes some data info.
	 *
	 * @param integer $attachmentId
	 *
	 * @return array|false
	 */
	public function getAttachmentById($attachmentId)
	{
		return $this->_getDb()->fetchRow('
			SELECT attachment.*,
				' . self::$dataColumns . '
			FROM xf_attachment AS attachment
			INNER JOIN xf_attachment_data AS data ON
				(data.data_id = attachment.data_id)
			WHERE attachment.attachment_id = ?
		', $attachmentId);
	}

	/**
	 * Gets the specified attachment data by ID.
	 *
	 * @param integer $dataId
	 *
	 * @return array|false
	 */
	public function getAttachmentDataById($dataId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_attachment_data
			WHERE data_id = ?
		', $dataId);
	}

	/**
	 * Gets attachment data IDs in the specified range. The IDs returned will be those immediately
	 * after the "start" value (not including the start), up to the specified limit.
	 *
	 * @param integer $start IDs greater than this will be returned
	 * @param integer $limit Number of records to return
	 *
	 * @return array List of IDs
	 */
	public function getAttachmentDataIdsInRange($start, $limit)
	{
		$db = $this->_getDb();

		return $db->fetchCol($db->limit('
			SELECT data_id
			FROM xf_attachment_data
			WHERE data_id > ?
			ORDER BY data_id
		', $limit), $start);
	}

	/**
	 * Gets an array of phrases identifying each attachment handler content type
	 *
	 * @return array [$contentType => XenForo_Phrase $phrase]
	 */
	public function getAttachmentHandlerContentTypeNames()
	{
		$phrases = array();

		foreach ($this->_getAttachmentHandlers() AS $contentType => $handler)
		{
			$phrases[$contentType] = new XenForo_Phrase($handler->getContentTypePhraseKey());
		}

		return $phrases;
	}

	/**
	 * Forces the system to cache all available attachment handlers, to avoid multiple queries later
	 *
	 * @return array [contentType => handlerObject]
	 */
	protected function _getAttachmentHandlers()
	{
		$objects = array();

		$classes = $this->getContentTypesWithField('attachment_handler_class');

		foreach ($classes AS $contentType => $class)
		{
			if (!class_exists($class))
			{
				continue;
			}

			$class = XenForo_Application::resolveDynamicClass($class);
			$object = ($class ? new $class() : null);
			$this->setLocalCacheData("attachmentHandler_$contentType", $object);
			$objects[$contentType] = $object;
		}

		return $objects;
	}

	/**
	 * Gets the attachment handler object for a specified content type.
	 *
	 * @param string $contentType
	 *
	 * @return XenForo_AttachmentHandler_Abstract|null
	 */
	public function getAttachmentHandler($contentType)
	{
		if (!$contentType)
		{
			return null;
		}

		$cacheKey = "attachmentHandler_$contentType";
		$object = $this->_getLocalCacheData($cacheKey);
		if ($object === false)
		{
			$class = $this->getContentTypeField($contentType, 'attachment_handler_class');
			if (!class_exists($class))
			{
				return null;
			}

			$class = XenForo_Application::resolveDynamicClass($class);
			$object = ($class ? new $class() : null);
			$this->setLocalCacheData($cacheKey, $object);
		}

		return $object;
	}

	/**
	 * Gets the full path to this attachment's data.
	 *
	 * @param array $data Attachment data info
	 * @param string Internal data path
	 *
	 * @return string
	 */
	public function getAttachmentDataFilePath(array $data, $internalDataPath = null)
	{
		if ($internalDataPath === null)
		{
			$internalDataPath = XenForo_Helper_File::getInternalDataPath();
		}

		return sprintf('%s/attachments/%d/%d-%s.data',
			$internalDataPath,
			floor($data['data_id'] / 1000),
			$data['data_id'],
			$data['file_hash']
		);
	}

	/**
	 * Gets the full path to this attachment's thumbnail.
	 *
	 * @param array $data Attachment data info
	 * @param string External data path
	 *
	 * @return string
	 */
	public function getAttachmentThumbnailFilePath(array $data, $externalDataPath = null)
	{
		if ($externalDataPath === null)
		{
			$externalDataPath = XenForo_Helper_File::getExternalDataPath();
		}

		return sprintf('%s/attachments/%d/%d-%s.jpg',
			$externalDataPath,
			floor($data['data_id'] / 1000),
			$data['data_id'],
			$data['file_hash']
		);
	}

	/**
	 * Gets the URL to this attachment's thumbnail. May be absolute or
	 * relative to the application root directory.
	 *
	 * @param array $data Attachment data info
	 *
	 * @return string
	 */
	public function getAttachmentThumbnailUrl(array $data)
	{
		return sprintf('%s/attachments/%d/%d-%s.jpg',
			XenForo_Application::$externalDataUrl,
			floor($data['data_id'] / 1000),
			$data['data_id'],
			$data['file_hash']
		);
	}

	/**
	 * Gets all attachments (and data) matching the given conditions
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return array
	 */
	public function getAttachments(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareAttachmentConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareAttachmentFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->fetchAllKeyed($this->limitQueryResults(
			'
				SELECT attachment.*, attachment_data.*
					' . $sqlClauses['selectFields'] . '
				FROM xf_attachment AS attachment
				INNER JOIN xf_attachment_data AS attachment_data ON
					(attachment_data.data_id = attachment.data_id)
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereConditions . '
				' . $sqlClauses['orderClause'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'attachment_id');
	}

	/**
	 * Prepare SQL conditions for fetching attachments
	 *
	 * @param array $conditions
	 * @param array $fetchOptions
	 *
	 * @return string
	 */
	public function prepareAttachmentConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['content_type']))
		{
			$sqlConditions[] = 'attachment.content_type = ' . $db->quote($conditions['content_type']);
		}

		if (!empty($conditions['user_id']))
		{
			$sqlConditions[] = 'attachment_data.user_id = ' . $db->quote($conditions['user_id']);
		}

		if (!empty($conditions['start']))
		{
			$sqlConditions[] = 'attachment_data.upload_date >= ' . $db->quote($conditions['start']);
		}

		if (!empty($conditions['end']))
		{
			$sqlConditions[] = 'attachment_data.upload_date <= ' . $db->quote($conditions['end']);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function prepareAttachmentFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		$orderBy = '';

		if (isset($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= ',
					user.*';
				$joinTables .= '
					LEFT JOIN xf_user AS user ON
						(user.user_id = attachment_data.user_id)';
			}
		}

		if (isset($fetchOptions['order']))
		{
			switch ($fetchOptions['order'])
			{
				case 'recent':
					$orderBy = 'attachment_data.upload_date DESC';
					break;

				case 'size':
					$orderBy = 'attachment_data.file_size DESC';
					break;
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables,
			'orderClause' => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}

	public function countAttachments(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareAttachmentConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareAttachmentFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne(
			'
				SELECT COUNT(attachment.attachment_id)
				FROM xf_attachment AS attachment
				INNER JOIN xf_attachment_data AS attachment_data ON
					(attachment_data.data_id = attachment.data_id)
				' . $sqlClauses['joinTables'] . '
				WHERE ' . $whereConditions
		);
	}

	/**
	 * Prepares an attachment for viewing (mainly as a "thumbnail" or similar view).
	 *
	 * @param array $attachment
	 * @param boolean $fetchContentLink If true, will fetch a link to the host content in the 'content_link' key
	 *
	 * @return array
	 */
	public function prepareAttachment(array $attachment, $fetchContentLink = false)
	{
		if ($attachment['thumbnail_width'])
		{
			$attachment['thumbnailUrl'] = $this->getAttachmentThumbnailUrl($attachment);
		}
		else
		{
			$attachment['thumbnailUrl'] = '';
		}

		$attachment['deleteUrl'] = XenForo_Link::buildPublicLink('attachments/delete', $attachment);
		$attachment['viewUrl'] = XenForo_Link::buildPublicLink('attachments', $attachment);

		$attachment['extension'] = strtolower(substr(strrchr($attachment['filename'], '.'), 1));

		if ($fetchContentLink && $contentLink = $this->getContentLink($attachment))
		{
			$attachment['content_link'] = $contentLink;
		}

		return $attachment;
	}

	/**
	 * Prepares a list of attachments.
	 *
	 * @param array $attachments
	 * @param boolean $fetchContentLinks If true, will fetch link to the host content in the 'content_link' key for each attachment
	 *
	 * @return array
	 */
	public function prepareAttachments(array $attachments, $fetchContentLinks = false)
	{
		foreach ($attachments AS &$attachment)
		{
			$attachment = $this->prepareAttachment($attachment, $fetchContentLinks);
		}

		return $attachments;
	}

	/**
	 * Fetches the link to the host content of an attachment
	 *
	 * @param array $attachment
	 * @param array $extraParams
	 * @param boolean $skipPrepend
	 *
	 * @return string|boolean
	 */
	public function getContentLink(array $attachment, array $extraParams = array(), $skipPrepend = false)
	{
		if ($handler = $this->getAttachmentHandler($attachment['content_type']))
		{
			return $handler->getContentLink($attachment, $extraParams, $skipPrepend);
		}

		return false;
	}

	/**
	 * Inserts uploaded attachment data.
	 *
	 * @param XenForo_Upload $file Uploaded attachment info. Assumed to be valid
	 * @param integer $userId User ID uploading
	 * @param array $extra Extra params to set
	 *
	 * @return integer Attachment data ID
	 */
	public function insertUploadedAttachmentData(XenForo_Upload $file, $userId, array $extra = array())
	{
		if ($file->isImage()
			&& XenForo_Image_Abstract::canResize($file->getImageInfoField('width'), $file->getImageInfoField('height'))
		)
		{
			$dimensions = array(
				'width' => $file->getImageInfoField('width'),
				'height' => $file->getImageInfoField('height'),
			);

			$tempThumbFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
			if ($tempThumbFile)
			{
				$image = XenForo_Image_Abstract::createFromFile($file->getTempFile(), $file->getImageInfoField('type'));
				if ($image)
				{
					if ($image->thumbnail(XenForo_Application::get('options')->attachmentThumbnailDimensions))
					{
						$image->output($file->getImageInfoField('type'), $tempThumbFile);
					}
					else
					{
						copy($file->getTempFile(), $tempThumbFile); // no resize necessary, use the original
					}

					$dimensions['thumbnail_width'] = $image->getWidth();
					$dimensions['thumbnail_height'] = $image->getHeight();

					unset($image);
				}
			}
		}
		else
		{
			$tempThumbFile = '';
			$dimensions = array();
		}

		try
		{
			$dataDw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
			$dataDw->bulkSet($extra);
			$dataDw->set('user_id', $userId);
			$dataDw->set('filename', $file->getFileName());
			$dataDw->bulkSet($dimensions);
			$dataDw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_FILE, $file->getTempFile());
			if ($tempThumbFile)
			{
				$dataDw->setExtraData(XenForo_DataWriter_AttachmentData::DATA_TEMP_THUMB_FILE, $tempThumbFile);
			}
			$dataDw->save();
		}
		catch (Exception $e)
		{
			if ($tempThumbFile)
			{
				@unlink($tempThumbFile);
			}

			throw $e;
		}

		if ($tempThumbFile)
		{
			@unlink($tempThumbFile);
		}

		// TODO: add support for "on rollback" behavior

		return $dataDw->get('data_id');
	}

	public function deleteAttachmentData($dataId)
	{
		$dataDw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData', XenForo_DataWriter::ERROR_SILENT);
		$dataDw->setExistingData($dataId);
		$dataDw->delete();
	}

	/**
	 * Inserts a temporary attachment for the specified attachment data.
	 *
	 * @param integer $dataId
	 * @param string $tempHash
	 *
	 * @return integer $attachmentId
	 */
	public function insertTemporaryAttachment($dataId, $tempHash)
	{
		$attachmentDw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
		$attachmentDw->set('data_id', $dataId);
		$attachmentDw->set('temp_hash', $tempHash);
		$attachmentDw->save();

		return $attachmentDw->get('attachment_id');
	}

	/**
	 * Deletes attachments from the specified content IDs.
	 *
	 * @param string $contentType
	 * @param array $contentIds
	 */
	public function deleteAttachmentsFromContentIds($contentType, array $contentIds)
	{
		if (!$contentIds)
		{
			return;
		}

		$db = $this->_getDb();
		$attachments = $db->fetchPairs('
			SELECT attachment_id, data_id
			FROM xf_attachment
			WHERE content_type = ?
				AND content_id IN (' . $db->quote($contentIds) . ')
		', $contentType);

		$this->_deleteAttachmentsFromPairs($attachments);
	}

	/**
	 * Deletes unassociated attachments up to a certain date.
	 *
	 * @param integer $maxDate Maximum timestamp to delete up to
	 */
	public function deleteUnassociatedAttachments($maxDate)
	{
		$attachments = $this->_getDb()->fetchPairs('
			SELECT attachment_id, data_id
			FROM xf_attachment
			WHERE unassociated = 1
				AND attach_date <= ?
		', $maxDate);

		$this->_deleteAttachmentsFromPairs($attachments);
	}

	/**
	 * Helper to delete attachments from a set of pairs [attachment id] => data id.
	 *
	 * @param array $attachments [attachment id] => data id
	 */
	protected function _deleteAttachmentsFromPairs(array $attachments)
	{
		if (!$attachments)
		{
			return;
		}

		$dataCount = array();
		foreach ($attachments AS $dataId)
		{
			if (isset($dataCount[$dataId]))
			{
				$dataCount[$dataId]++;
			}
			else
			{
				$dataCount[$dataId] = 1;
			}
		}

		$db = $this->_getDb();
		$db->delete('xf_attachment',
			'attachment_id IN (' . $db->quote(array_keys($attachments)) . ')'
		);
		foreach ($dataCount AS $dataId => $delta)
		{
			$db->query('
				UPDATE xf_attachment_data
				SET attach_count = IF(attach_count > ?, attach_count - ?, 0)
				WHERE data_id = ?
			', array($delta, $delta, $dataId));
		}
	}

	public function deleteUnusedAttachmentData()
	{
		$attachments = $this->_getDb()->fetchAll('
			SELECT *
			FROM xf_attachment_data
			WHERE attach_count = 0
			LIMIT 1000
		');
		foreach ($attachments AS $attachment)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_AttachmentData');
			$dw->setExistingData($attachment, true);
			$dw->delete();
		}
	}

	/**
	 * Determines if the specified attachment can be viewed. Unassociated attachments
	 * can be viewed if the temp hash is known.
	 *
	 * @param array $attachment
	 * @param string $tempHash
	 * @param array|null $viewingUser Viewing user ref; if null, uses visitor
	 *
	 * @return boolean
	 */
	public function canViewAttachment(array $attachment, $tempHash = '', array $viewingUser = null)
	{
		if (!empty($attachment['temp_hash']) && empty($attachment['content_id']))
		{
			// can view temporary attachments as long as the hash is known
			return ($tempHash === $attachment['temp_hash']);
		}
		else
		{
			$attachmentHandler = $this->getAttachmentHandler($attachment['content_type']);
			return ($attachmentHandler && $attachmentHandler->canViewAttachment($attachment, $viewingUser));
		}
	}

	/**
	 * Determines if the specified attachment can be deleted. Unassociated attachments
	 * can be deleted if the temp hash is known.
	 *
	 * @param array $attachment
	 * @param string $tempHash
	 * @param array|null $viewingUser Viewing user ref; if null, uses visitor
	 *
	 * @return boolean
	 */
	public function canDeleteAttachment(array $attachment, $tempHash = '', array $viewingUser = null)
	{
		if (!empty($attachment['temp_hash']) && empty($attachment['content_id']))
		{
			// can view temporary attachments as long as the hash is known
			return ($tempHash === $attachment['temp_hash']);
		}
		else
		{
			$attachmentHandler = $this->getAttachmentHandler($attachment['content_type']);
			return ($attachmentHandler && $attachmentHandler->canUploadAndManageAttachments($attachment, $viewingUser));
		}
	}

	/**
	 * Logs the viewing of an attachment.
	 *
	 * @param integer $attachmentId
	 */
	public function logAttachmentView($attachmentId)
	{
		$this->_getDb()->query('
			INSERT ' . (XenForo_Application::get('options')->enableInsertDelayed ? 'DELAYED' : '') . ' INTO xf_attachment_view
				(attachment_id)
			VALUES
				(?)
		', $attachmentId);
	}

	/**
	 * Updates attachment views in bulk.
	 */
	public function updateAttachmentViews()
	{
		$db = $this->_getDb();

		$db->query('
			UPDATE xf_attachment
			INNER JOIN (
				SELECT attachment_id, COUNT(*) AS total
				FROM xf_attachment_view
				GROUP BY attachment_id
			) AS xf_av ON (xf_av.attachment_id = xf_attachment.attachment_id)
			SET xf_attachment.view_count = xf_attachment.view_count + xf_av.total
		');

		$db->query('TRUNCATE TABLE xf_attachment_view');
	}

	/**
	 * Fetches attachment constraints
	 *
	 * @return array
	 */
	public function getAttachmentConstraints()
	{
		$options = XenForo_Application::get('options');

		return array(
			'extensions' => preg_split('/\s+/', trim($options->attachmentExtensions)),
			'size' => $options->attachmentMaxFileSize * 1024,
			'width' => $options->attachmentMaxDimensions['width'],
			'height' => $options->attachmentMaxDimensions['height'],
			'count' => $options->attachmentMaxPerMessage
		);
	}
}