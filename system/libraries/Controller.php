<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Leo Feyer 2005-2013
 * @author     Leo Feyer <https://contao.org>
 * @package    System
 * @license    LGPL
 * @filesource
 */


/**
 * Class Controller
 *
 * Provide methods to manage controllers.
 * @copyright  Leo Feyer 2005-2013
 * @author     Leo Feyer <https://contao.org>
 * @package    Controller
 */
abstract class Controller extends System
{

	/**
	 * Return all languages as array
	 * @param boolean
	 * @return array
	 */
	protected function getLanguages()
	{
		$languages = array();
		include(TL_ROOT . '/system/config/languages.php');

		return $languages;
	}


	/**
	 * Return all languages as array
	 * @param boolean
	 * @return array
	 */
	protected function getCountries()
	{
		$countries = array();
		include(TL_ROOT . '/system/config/countries.php');

		return $countries;
	}


	/**
	 * Resize or crop an image
	 * @param string
	 * @param integer
	 * @param integer
	 * @param string
	 * @return boolean
	 */
	protected function resizeImage($image, $width, $height, $mode='')
	{
		return $this->getImage($image, $width, $height, $mode, $image, true) ? true : false;
	}


	/**
	 * Resize an image
	 * @param string
	 * @param integer
	 * @param integer
	 * @param string
	 * @param string
	 * @param boolean
	 * @return string|null
	 */
	protected function getImage($image, $width, $height, $mode='', $target=null, $force=false)
	{
		if ($image == '')
		{
			return null;
		}

		$image = rawurldecode($image);

		// Check whether the file exists
		if (!file_exists(TL_ROOT . '/' . $image))
		{
			$this->log('Image "' . $image . '" could not be found', 'Controller getImage()', TL_ERROR);
			return null;
		}

		$objFile = new File($image);
		$arrAllowedTypes = trimsplit(',', strtolower($GLOBALS['TL_CONFIG']['validImageTypes']));

		// Check the file type
		if (!in_array($objFile->extension, $arrAllowedTypes))
		{
			$this->log('Image type "' . $objFile->extension . '" was not allowed to be processed', 'Controller getImage()', TL_ERROR);
			return null;
		}

		// No resizing required
		if ($objFile->width == $width && $objFile->height == $height)
		{
			// Return the target image (thanks to Tristan Lins) (see #4166)
			if ($target)
			{
				// Copy the source image if the target image does not exist or is older than the source image
				if (!file_exists(TL_ROOT . '/' . $target) || $objFile->mtime > filemtime(TL_ROOT . '/' . $target))
				{
					$this->import('Files');
					$this->Files->copy($image, $target);
				}

				return $this->urlEncode($target);
			}

			return $this->urlEncode($image);
		}

		// No mode given
		if ($mode == '')
		{
			$mode = 'proportional';
		}

		$strCacheName = 'system/html/' . $objFile->filename . '-' . substr(md5('-w' . $width . '-h' . $height . '-' . $image . '-' . $mode . '-' . $objFile->mtime), 0, 8) . '.' . $objFile->extension;

		// Custom target (thanks to Tristan Lins) (see #4166)
		if ($target && !$force)
		{
			if (file_exists(TL_ROOT . '/' . $target) && $objFile->mtime <= filemtime(TL_ROOT . '/' . $target))
			{
				return $this->urlEncode($target);
			}
		}

		// Regular cache file
		if (file_exists(TL_ROOT . '/' . $strCacheName))
		{
			// Copy the cached file if it exists
			if ($target)
			{
				$this->import('Files');
				$this->Files->copy($strCacheName, $target);
			}

			return $this->urlEncode($strCacheName);
		}

		// HOOK: add custom logic
		if (isset($GLOBALS['TL_HOOKS']['getImage']) && is_array($GLOBALS['TL_HOOKS']['getImage']))
		{
			foreach ($GLOBALS['TL_HOOKS']['getImage'] as $callback)
			{
				$this->import($callback[0]);
				$return = $this->$callback[0]->$callback[1]($image, $width, $height, $mode, $strCacheName, $objFile, $target);

				if (is_string($return))
				{
					return $this->urlEncode($return);
				}
			}
		}

		// Return the path to the original image if the GDlib cannot handle it
		if (!extension_loaded('gd') || !$objFile->isGdImage || $objFile->width > $GLOBALS['TL_CONFIG']['gdMaxImgWidth'] || $objFile->height > $GLOBALS['TL_CONFIG']['gdMaxImgHeight'] || (!$width && !$height) || $width > $GLOBALS['TL_CONFIG']['gdMaxImgWidth'] || $height > $GLOBALS['TL_CONFIG']['gdMaxImgHeight'])
		{
			return $this->urlEncode($image);
		}

		$intPositionX = 0;
		$intPositionY = 0;
		$intWidth = $width;
		$intHeight = $height;

		// Mode-specific changes
		if ($intWidth && $intHeight)
		{
			switch ($mode)
			{
				case 'proportional':
					if ($objFile->width >= $objFile->height)
					{
						unset($height, $intHeight);
					}
					else
					{
						unset($width, $intWidth);
					}
					break;

				case 'box':
					if (round($objFile->height * $width / $objFile->width) <= $intHeight)
					{
						unset($height, $intHeight);
					}
					else
					{
						unset($width, $intWidth);
					}
					break;
			}
		}

		// Resize width and height and crop the image if necessary
		if ($intWidth && $intHeight)
		{
			if (($intWidth * $objFile->height) != ($intHeight * $objFile->width))
			{
				$intWidth = max(round($objFile->width * $height / $objFile->height), 1);
				$intPositionX = -intval(($intWidth - $width) / 2);

				if ($intWidth < $width)
				{
					$intWidth = $width;
					$intHeight = max(round($objFile->height * $width / $objFile->width), 1);
					$intPositionX = 0;
					$intPositionY = -intval(($intHeight - $height) / 2);
				}
			}

			// Advanced crop modes
			switch ($mode)
			{
				case 'left_top':
					$intPositionX = 0;
					$intPositionY = 0;
					break;

				case 'center_top':
					$intPositionX = -intval(($intWidth - $width) / 2);
					$intPositionY = 0;
					break;

				case 'right_top':
					$intPositionX = -intval($intWidth - $width);
					$intPositionY = 0;
					break;

				case 'left_center':
					$intPositionX = 0;
					$intPositionY = -intval(($intHeight - $height) / 2);
					break;

				case 'center_center':
					$intPositionX = -intval(($intWidth - $width) / 2);
					$intPositionY = -intval(($intHeight - $height) / 2);
					break;

				case 'right_center':
					$intPositionX = -intval($intWidth - $width);
					$intPositionY = -intval(($intHeight - $height) / 2);
					break;

				case 'left_bottom':
					$intPositionX = 0;
					$intPositionY = -intval($intHeight - $height);
					break;

				case 'center_bottom':
					$intPositionX = -intval(($intWidth - $width) / 2);
					$intPositionY = -intval($intHeight - $height);
					break;

				case 'right_bottom':
					$intPositionX = -intval($intWidth - $width);
					$intPositionY = -intval($intHeight - $height);
					break;
			}

			$strNewImage = imagecreatetruecolor($width, $height);
		}

		// Calculate the height if only the width is given
		elseif ($intWidth)
		{
			$intHeight = max(round($objFile->height * $width / $objFile->width), 1);
			$strNewImage = imagecreatetruecolor($intWidth, $intHeight);
		}

		// Calculate the width if only the height is given
		elseif ($intHeight)
		{
			$intWidth = max(round($objFile->width * $height / $objFile->height), 1);
			$strNewImage = imagecreatetruecolor($intWidth, $intHeight);
		}

		$arrGdinfo = gd_info();
		$strGdVersion = preg_replace('/[^0-9\.]+/', '', $arrGdinfo['GD Version']);

		switch ($objFile->extension)
		{
			case 'gif':
				if ($arrGdinfo['GIF Read Support'])
				{
					$strSourceImage = imagecreatefromgif(TL_ROOT . '/' . $image);
					$intTranspIndex = imagecolortransparent($strSourceImage);

					// Handle transparency
					if ($intTranspIndex >= 0 && $intTranspIndex < imagecolorstotal($strSourceImage))
					{
						$arrColor = imagecolorsforindex($strSourceImage, $intTranspIndex);
						$intTranspIndex = imagecolorallocate($strNewImage, $arrColor['red'], $arrColor['green'], $arrColor['blue']);
						imagefill($strNewImage, 0, 0, $intTranspIndex);
						imagecolortransparent($strNewImage, $intTranspIndex);
					}
				}
				break;

			case 'jpg':
			case 'jpeg':
				if ($arrGdinfo['JPG Support'] || $arrGdinfo['JPEG Support'])
				{
					$strSourceImage = imagecreatefromjpeg(TL_ROOT . '/' . $image);
				}
				break;

			case 'png':
				if ($arrGdinfo['PNG Support'])
				{
					$strSourceImage = imagecreatefrompng(TL_ROOT . '/' . $image);

					// Handle transparency (GDlib >= 2.0 required)
					if (version_compare($strGdVersion, '2.0', '>='))
					{
						imagealphablending($strNewImage, false);
						$intTranspIndex = imagecolorallocatealpha($strNewImage, 0, 0, 0, 127);
						imagefill($strNewImage, 0, 0, $intTranspIndex);
						imagesavealpha($strNewImage, true);
					}
				}
				break;
		}

		// The new image could not be created
		if (!$strSourceImage)
		{
			imagedestroy($strNewImage);
			$this->log('Image "' . $image . '" could not be processed', 'Controller getImage()', TL_ERROR);
			return null;
		}

		imagecopyresampled($strNewImage, $strSourceImage, $intPositionX, $intPositionY, 0, 0, $intWidth, $intHeight, $objFile->width, $objFile->height);

		// Fallback to PNG if GIF ist not supported
		if ($objFile->extension == 'gif' && !$arrGdinfo['GIF Create Support'])
		{
			$objFile->extension = 'png';
		}

		// Create the new image
		switch ($objFile->extension)
		{
			case 'gif':
				imagegif($strNewImage, TL_ROOT . '/' . $strCacheName);
				break;

			case 'jpg':
			case 'jpeg':
				imagejpeg($strNewImage, TL_ROOT . '/' . $strCacheName, (!$GLOBALS['TL_CONFIG']['jpgQuality'] ? 80 : $GLOBALS['TL_CONFIG']['jpgQuality']));
				break;

			case 'png':
				// Optimize non-truecolor images (see #2426)
				if (version_compare($strGdVersion, '2.0', '>=') && function_exists('imagecolormatch') && !imageistruecolor($strSourceImage))
				{
					// TODO: make it work with transparent images, too
					if (imagecolortransparent($strSourceImage) == -1)
					{
						$intColors = imagecolorstotal($strSourceImage);

						// Convert to a palette image
						// @see http://www.php.net/manual/de/function.imagetruecolortopalette.php#44803
						if ($intColors > 0 && $intColors < 256)
						{
							$wi = imagesx($strNewImage);
							$he = imagesy($strNewImage);
							$ch = imagecreatetruecolor($wi, $he);
							imagecopymerge($ch, $strNewImage, 0, 0, 0, 0, $wi, $he, 100);
							imagetruecolortopalette($strNewImage, false, $intColors);
							imagecolormatch($ch, $strNewImage);
							imagedestroy($ch);
						}
					}
				}

				imagepng($strNewImage, TL_ROOT . '/' . $strCacheName);
				break;
		}

		// Destroy the temporary images
		imagedestroy($strSourceImage);
		imagedestroy($strNewImage);

		// Resize the original image
		if ($target)
		{
			$this->import('Files');
			$this->Files->copy($strCacheName, $target);
			return $this->urlEncode($target);
		}

		// Set the file permissions when the Safe Mode Hack is used
		if ($GLOBALS['TL_CONFIG']['useFTP'])
		{
			$this->import('Files');
			$this->Files->chmod($strCacheName, $GLOBALS['TL_CONFIG']['defaultFileChmod']);
		}

		// Return the path to new image
		return $this->urlEncode($strCacheName);
	}


	/**
	 * Print an article as PDF and stream it to the browser
	 * @param string
	 * @param string
	 */
	protected function printHtmlAsPdf($strHtml, $strTitle='PDF')
	{
		$strHtml = html_entity_decode($strHtml, ENT_QUOTES, $GLOBALS['TL_CONFIG']['characterSet']);

		// Remove form elements and JavaScript links
		$arrSearch = array
		(
			'@<form.*</form>@Us',
			'@<a [^>]*href="[^"]*javascript:[^>]+>.*</a>@Us'
		);

		$strHtml = preg_replace($arrSearch, '', $strHtml);

		// Handle line breaks in preformatted text
		$strHtml = preg_replace_callback('@(<pre.*</pre>)@Us', 'nl2br_callback', $strHtml);

		// Default PDF export using TCPDF
		$arrSearch = array
		(
			'@<span style="text-decoration: ?underline;?">(.*)</span>@Us',
			'@(<img[^>]+>)@',
			'@(<div[^>]+block[^>]+>)@',
			'@[\n\r\t]+@',
			'@<br( /)?><div class="mod_article@',
			'@href="([^"]+)(pdf=[0-9]*(&|&amp;)?)([^"]*)"@'
		);

		$arrReplace = array
		(
			'<u>$1</u>',
			'<br>$1',
			'<br>$1',
			' ',
			'<div class="mod_article',
			'href="$1$4"'
		);

		$strHtml = preg_replace($arrSearch, $arrReplace, $strHtml);

		// TCPDF configuration
		$l['a_meta_dir'] = 'ltr';
		$l['a_meta_charset'] = $GLOBALS['TL_CONFIG']['characterSet'];
		$l['a_meta_language'] = $GLOBALS['TL_LANGUAGE'];
		$l['w_page'] = 'page';

		// Include library
		require_once(TL_ROOT . '/system/config/tcpdf.php');
		require_once(TL_ROOT . '/plugins/tcpdf/tcpdf.php');

		// Create new PDF document
		$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true);

		// Set document information
		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetAuthor(PDF_AUTHOR);
		$pdf->SetTitle($strTitle);
		$pdf->SetSubject($strTitle);

		// Prevent font subsetting (huge speed improvement)
		$pdf->setFontSubsetting(false);

		// Remove default header/footer
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);

		// Set margins
		$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

		// Set auto page breaks
		$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

		// Set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

		// Set some language-dependent strings
		$pdf->setLanguageArray($l);

		// Initialize document and add a page
		$pdf->AddPage();

		// Set font
		$pdf->SetFont(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN);

		// Write the HTML content
		$pdf->writeHTML($strHtml, true, 0, true, 0);

		// Close and output PDF document
		$pdf->lastPage();
		$pdf->Output(standardize(ampersand($strTitle, false)) . '.pdf', 'D');

		// Stop script execution
		exit;
	}


	/**
	 * Generate an image tag and return it as HTML string
	 * @param string
	 * @param string
	 * @param string
	 * @return string
	 */
	protected function generateImage($src, $alt='', $attributes='')
	{
		$src = rawurldecode($src);

		if (!file_exists(TL_ROOT .'/'. $src))
		{
			return '';
		}

		$size = getimagesize(TL_ROOT .'/'. $src);
		return '<img src="' . $this->urlEncode($src) . '" ' . $size[3] . ' alt="' . specialchars($alt) . '"' . (($attributes != '') ? ' ' . $attributes : '') . '>';
	}


	/**
	 * Send a file to the browser so the "save as" dialogue opens
	 * @param string
	 */
	protected function sendFileToBrowser($strFile)
	{
		// Make sure there are no attempts to hack the file system
		if (preg_match('@^\.+@i', $strFile) || preg_match('@\.+/@i', $strFile) || preg_match('@(://)+@i', $strFile))
		{
			header('HTTP/1.1 404 Not Found');
			die('Invalid file name');
		}

		// Check whether the file exists
		if (!file_exists(TL_ROOT . '/' . $strFile))
		{
			header('HTTP/1.1 404 Not Found');
			die('File not found');
		}

		$objFile = new File($strFile);
		$arrAllowedTypes = trimsplit(',', strtolower($GLOBALS['TL_CONFIG']['allowedDownload']));

		if (!in_array($objFile->extension, $arrAllowedTypes))
		{
			header('HTTP/1.1 403 Forbidden');
			die(sprintf('File type "%s" is not allowed', $objFile->extension));
		}

		// Make sure no output buffer is active
		// @see http://ch2.php.net/manual/en/function.fpassthru.php#74080
		while (@ob_end_clean());

		// Prevent session locking (see #2804)
		session_write_close();

		// Open the "save as …" dialogue
		header('Content-Type: ' . $objFile->mime);
		header('Content-Transfer-Encoding: binary');
		header('Content-Disposition: attachment; filename="' . $objFile->basename . '"');
		header('Content-Length: ' . $objFile->filesize);
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Expires: 0');
		header('Connection: close');

		$resFile = fopen(TL_ROOT . '/' . $strFile, 'rb');
		fpassthru($resFile);
		fclose($resFile);

		// HOOK: post download callback
		if (isset($GLOBALS['TL_HOOKS']['postDownload']) && is_array($GLOBALS['TL_HOOKS']['postDownload']))
		{
			foreach ($GLOBALS['TL_HOOKS']['postDownload'] as $callback)
			{
				$this->import($callback[0]);
				$this->$callback[0]->$callback[1]($strFile);
			}
		}

		// Stop script
		exit;
	}


	/**
	 * Return true if a class file exists
	 * @param string
	 * @param boolean
	 * @return boolean
	 */
	protected function classFileExists($strClass, $blnNoCache=false)
	{
		if ($strClass == '')
		{
			return false;
		}

		$this->import('Cache');
		$strKey = __METHOD__ . '-' . $strClass;

		// Try to load from cache
		if (!$blnNoCache)
		{
			// Handle multiple requests for the same class
			if (isset($this->Cache->$strKey))
			{
				return $this->Cache->$strKey;
			}

			$objCache = FileCache::getInstance('classes');

			// Check the file cache
			if (isset($objCache->$strClass))
			{
				$this->Cache->$strKey = $objCache->$strClass;
				return $objCache->$strClass;
			}
		}

		$this->import('Config'); // see ticket #152
		$this->Cache->$strKey = false;

		// Browse all modules
		foreach ($this->Config->getActiveModules() as $strModule)
		{
			$strFile = 'system/modules/' . $strModule . '/' . $strClass . '.php';

			if (file_exists(TL_ROOT . '/' . $strFile))
			{
				// Also store the result in the autoloader cache, so the
				// function does not have to browse the module folders again
				$objAutoload = FileCache::getInstance('autoload');
				$objAutoload->$strClass = $strFile;

				$this->Cache->$strKey = true;
				break;
			}
		}

		// Remember the result
		if (!$blnNoCache)
		{
			$objCache->$strClass = $this->Cache->$strKey;
		}

		return $this->Cache->$strKey;
	}


	/**
	 * Take an array of paths and eliminate nested paths
	 * @param array
	 * @return array
	 */
	protected function eliminateNestedPaths($arrPaths)
	{
		if (!is_array($arrPaths) || empty($arrPaths))
		{
			return array();
		}

		$nested = array();

		foreach ($arrPaths as $path)
		{
			$nested = array_merge($nested, preg_grep('/^' . preg_quote($path, '/') . '\/.+/', $arrPaths));
		}

		return array_values(array_diff($arrPaths, $nested));
	}


	/**
	 * Set a static URL constant and replace the protocol when requested via SSL
	 * @param string
	 * @param string
	 */
	protected function setStaticUrl($name, $url)
	{
		if (defined($name))
		{
			return;
		}

		if ($url == '' || $GLOBALS['TL_CONFIG']['debugMode'])
		{
			define($name, '');
		}
		else
		{
			if ($this->Environment->ssl)
			{
				$url = str_replace('http://', 'https://', $url);
			}

			define($name, $url . TL_PATH . '/');
		}
	}
}

?>