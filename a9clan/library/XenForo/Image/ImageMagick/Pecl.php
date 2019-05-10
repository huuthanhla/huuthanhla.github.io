<?php

/**
 * Image processor using imagick PECL extension.
 *
 * @package XenForo_Image
 * @author SMV
 */
class XenForo_Image_ImageMagick_Pecl extends XenForo_Image_Abstract
{
	/**
	 * The Imagick object
	 *
	 * @var Imagick
	 */
	protected $_image = null;

	/**
	 * Constructor.
	 *
	 * @param resource $image Imagick object
	 */
	protected function __construct(Imagick $image)
	{
		$this->_setImage($image);
	}

	public function __destruct()
	{
		if ($this->_image) {
			$this->_image->destroy();
		}
	}

	/**
	 * Creates a blank image.
	 *
	 * @param integer $width
	 * @param integer $height
	 *
	 * @return XenForo_Image_Imagemagick_Pecl
	 */
	public static function createImageDirect($width, $height)
	{
		$instance = new Imagick();
		// background colour is transparent with none
		$instance->newImage($width, $height, new ImagickPixel('none'));

		$class = XenForo_Application::resolveDynamicClass(__CLASS__);
		return new $class($instance);
	}

	/**
	 * Creates an image from an existing file.
	 *
	 * @param string $fileName
	 * @param integer $inputType IMAGETYPE_XYZ constant representing image type
	 *
	 * @return XenForo_Image_Imagemagick_Pecl|false
	 */
	public static function createFromFileDirect($fileName, $inputType)
	{
		$invalidType = false;
		try
		{
			switch ($inputType)
			{
				case IMAGETYPE_GIF:
				case IMAGETYPE_JPEG:
				case IMAGETYPE_PNG:
					$image = new Imagick($fileName);
					break;

				default:
					$invalidType = true;
			}
		}
		catch (Exception $e)
		{
			return false;
		}

		if ($invalidType)
		{
			throw new XenForo_Exception('Invalid image type given. Expects IMAGETYPE_XXX constant.');
		}

		$class = XenForo_Application::resolveDynamicClass(__CLASS__);
		return new $class($image);
	}

	/**
	 * Thumbnails the image.
	 *
	 * @see XenForo_Image_Abstract::thumbnail()
	 */
	public function thumbnail($maxWidth, $maxHeight = 0)
	{
		if ($maxWidth < 10)
		{
			$maxWidth = 10;
		}
		if ($maxHeight < 10)
		{
			$maxHeight = $maxWidth;
		}

		if ($this->_width < $maxWidth && $this->_height < $maxHeight)
		{
			return false;
		}

		$ratio = $this->_width / $this->_height;

		$maxRatio = ($maxWidth / $maxHeight);

		if ($maxRatio > $ratio)
		{
			$width = max(1, $maxHeight * $ratio);
			$height = $maxHeight;
		}
		else
		{
			$width = $maxWidth;
			$height = max(1, $maxWidth / $ratio);
		}

		try
		{
			foreach ($this->_image AS $frame)
			{
				$frame->thumbnailImage($width, $height, true);
				$frame->setImagePage($frame->getImageWidth(), $frame->getImageHeight(), 0, 0);
			}
			$this->_updateDimensionCache();
		}
		catch (Exception $e)
		{
			return false;
		}
		return true;
	}

	/**
	 * Produces a thumbnail of the current image whose shorter side is the specified length
	 *
	 * @see XenForo_Image_Abstract::thumbnailFixedShorterSide
	 */
	public function thumbnailFixedShorterSide($shortSideLength)
	{
		if ($shortSideLength < 10)
		{
			$shortSideLength = 10;
		}

		$scaleUp = false;
		$ratio = $this->_width / $this->_height;
		if ($ratio > 1) // landscape
		{
			$width = ceil($shortSideLength * $ratio);
			$height = $shortSideLength;
			$scaleUp = ($this->_height < $height);
		}
		else
		{
			$width = $shortSideLength;
			$height = ceil(max(1, $shortSideLength / $ratio));
			$scaleUp = ($this->_width < $width);
		}

		// imagick module < 3 or ImageMagick < 6.3.2 don't support the 4th thumbnailImage param
		$oldImagick = version_compare(phpversion('imagick'), '3', '<');

		$version = $this->_image->getVersion();
		if (preg_match('#ImageMagick (\d+\.\d+\.\d+)#i', $version['versionString'], $match))
		{
			if (version_compare($match[1], '6.3.2', '<'))
			{
				$oldImagick = true;
			}
		}

		try
		{
			foreach ($this->_image AS $frame)
			{
				if ($scaleUp)
				{
					$frame->resizeImage($width, $height, Imagick::FILTER_QUADRATIC, .5, true);
				}
				else if ($oldImagick)
				{
					$frame->thumbnailImage($width, $height, true);
				}
				else
				{
					$frame->thumbnailImage($width, $height, true, true);
				}
				$frame->setImagePage($width, $height, 0, 0);
			}

			$this->_updateDimensionCache();
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Crops the image.
	 *
	 * @see XenForo_Image_Abstract::crop()
	 */
	public function crop($x, $y, $width, $height)
	{
		foreach ($this->_image AS $frame)
		{
			$frame->cropImage($width, $height, $x, $y);
			$frame->setImagePage($frame->getImageWidth(), $frame->getImageHeight(), 0, 0);
		}
		$this->_updateDimensionCache();
	}

	/**
	 * Rotates the image clockwise
	 *
	 * @see XenForo_Image_Abstract::rotate()
	 */
	public function rotate($angle)
	{
		foreach ($this->_image AS $frame)
		{
			$frame->rotateImage(new ImagickPixel('none'), $angle);
		}

		$this->_updateDimensionCache();
	}

	/**
	 * Flips the image
	 *
	 * @see XenForo_Image_Abstract::flip()
	 */
	public function flip($mode)
	{
		foreach ($this->_image AS $frame)
		{
			switch ($mode)
			{
				case self::FLIP_HORIZONTAL:
					$frame->flopImage();
					break;

				case self::FLIP_VERTICAL:
					$frame->flipImage();
					break;

				case self::FLIP_BOTH:
					$frame->flopImage();
					$frame->flipImage();
					break;

				default:
					return;
			}
		}

		$this->_updateDimensionCache();
	}

	/**
	 * Outputs the image.
	 *
	 * @see XenForo_Image_Abstract::output()
	 */
	public function output($outputType, $outputFile = null, $quality = 85)
	{
		$this->_image->stripImage();

		// NULL means output directly
		switch ($outputType)
		{
			case IMAGETYPE_GIF:
				if (is_callable(array($this->_image, 'optimizeimagelayers')))
				{
					$this->_image->optimizeimagelayers();
				}
				$success = $this->_image->setImageFormat('gif');
				break;
			case IMAGETYPE_JPEG:
				$success = $this->_image->setImageFormat('jpeg')
					&& $this->_image->setImageCompression(Imagick::COMPRESSION_JPEG)
					&& $this->_image->setImageCompressionQuality($quality);
				break;
			case IMAGETYPE_PNG:
				$success = $this->_image->setImageFormat('png');
				break;

			default:
				throw new XenForo_Exception('Invalid output type given. Expects IMAGETYPE_XXX constant.');
		}

		try
		{
			if ($success)
			{
				if (!$outputFile)
				{
					echo $this->_image->getImagesBlob();
				}
				else
				{
					$success = $this->_image->writeImages($outputFile, true);
				}
			}
		}
		catch (ImagickException $e)
		{
			return false;
		}
		return $success;
	}

	/**
	 * Sets the internal Imagick object
	 *
	 * @param resource $image
	 */
	protected function _setImage(Imagick $image)
	{
		$this->_image = $image->coalesceImages();
		$this->_updateDimensionCache();
	}

	/**
	* Update the cached dimension information from
	* the internal Imagick object
	*
	*/
	protected function _updateDimensionCache()
	{
		$this->_width = $this->_image->getImageWidth();
		$this->_height = $this->_image->getImageHeight();
	}
}
