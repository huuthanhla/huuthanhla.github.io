<?php

/**
 * View to handle displaying an attachment.
 *
 * @package XenForo_ImageProxy
 */
class XenForo_ViewPublic_ImageProxy_View extends XenForo_ViewPublic_Base
{
	public function renderRaw()
	{
		$image = $this->_params['image'];

		$filename = basename($image['url']);

		$extension = XenForo_Helper_File::getFileExtension($filename);
		$imageTypes = array(
			'gif' => 'image/gif',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpe' => 'image/jpeg',
			'png' => 'image/png'
		);

		if (in_array($extension, array_keys($imageTypes)))
		{
			$this->_response->setHeader('Content-type', $imageTypes[$extension], true);
			$this->setDownloadFileName($filename, true);
		}
		else
		{
			$this->_response->setHeader('Content-type', 'application/octet-stream', true);
			$this->setDownloadFileName($filename);
		}

		$this->_response->setHeader('ETag', '"' . $image['fetch_date'] . '"', true);
		$this->_response->setHeader('Content-Length', $image['file_size'], true);
		$this->_response->setHeader('X-Content-Type-Options', 'nosniff');

		return new XenForo_FileOutput($image['file_path']);
	}
}