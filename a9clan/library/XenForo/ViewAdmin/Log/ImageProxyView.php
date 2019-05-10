<?php

/**
 * View to handle displaying an attachment.
 *
 * @package XenForo_ImageProxy
 */
class XenForo_ViewAdmin_Log_ImageProxyView extends XenForo_ViewAdmin_Base
{
	public function renderRaw()
	{
		$image = $this->_params['image'];

		$imageTypes = array(
			'image/gif',
			'image/jpeg',
			'image/pjpeg',
			'image/png'
		);

		if (in_array($image['mime_type'], $imageTypes))
		{
			$this->_response->setHeader('Content-type', $image['mime_type'], true);
			$this->setDownloadFileName($image['file_name'], true);
		}
		else
		{
			$this->_response->setHeader('Content-type', 'application/octet-stream', true);
			$this->setDownloadFileName($image['file_name']);
		}

		$this->_response->setHeader('ETag', '"' . $image['fetch_date'] . '"', true);
		if ($image['file_size'])
		{
			$this->_response->setHeader('Content-Length', $image['file_size'], true);
		}
		$this->_response->setHeader('X-Content-Type-Options', 'nosniff');

		return new XenForo_FileOutput($image['file_path']);
	}
}