<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 26.9.2018
 * Time: 16:53
 */

namespace App\PhotoModule;

use Nette\SmartObject;
use Nette\Utils\Strings;
use Tracy\Debugger;

class Image extends \Imagick {

	use SmartObject;

	/** @var string */
	const PHOTO_DIR = 'albums';

	/** @var string */
	const THUMB_DIR = 'thumbs';

	/** @var int */
	const THUMB_WIDTH = 150;

	/** @var int*/
	const THUMB_HEIGHT = 100;

	/** @var int */
	const SIZE_THRESHOLD = 2;

	/**
	 * Image constructor.
	 */
	public function __construct(string $filename) {
		parent::__construct(realpath($filename));
	}

	/**
	 * @return float
	 */
	public function getSize(){
		return filesize($this->getImageFilename()) / (1024 * 1024);
	}

	/**
	 * @return bool
	 * @throws \ImagickException
	 */
	public function adaptiveResize(){
		$size = $this->getSize();
		if ($size > self::SIZE_THRESHOLD) {
			//$ratio = round(1 / floor($size / self::SIZE_THRESHOLD), 1);
			//$ratio = min(round((self::SIZE_THRESHOLD) / $size, 1),1);
			$ratio = ($size < 2 * self::SIZE_THRESHOLD) ? 0.75 : 0.5;
			$width = round($this->getImageWidth() * $ratio);
			$this->adaptiveResizeImage($width, 0);
			return TRUE;
		}else {
			return FALSE;
		}
	}

	/**
	 * @return bool
	 */
	public function fixOrientation(){
		switch ($this->getImageOrientation()) {
			case \Imagick::ORIENTATION_TOPRIGHT:
				$this->flopImage();
				$this->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
				return TRUE;
				break;
			case \Imagick::ORIENTATION_BOTTOMRIGHT:
				$this->rotateImage("#000", 180);
				$this->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
				return TRUE;
				break;
			case \Imagick::ORIENTATION_BOTTOMLEFT:
				$this->flopImage();
				$this->rotateImage("#000", 180);
				$this->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
				return TRUE;
				break;
			case \Imagick::ORIENTATION_LEFTTOP:
				$this->flopImage();
				$this->rotateImage("#000", -90);
				$this->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
				return TRUE;
				break;
			case \Imagick::ORIENTATION_RIGHTTOP:
				$this->rotateImage("#000", 90);
				$this->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
				return TRUE;
				break;
			case \Imagick::ORIENTATION_RIGHTBOTTOM:
				$this->flopImage();
				$this->rotateImage("#000", 90);
				$this->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
				return TRUE;
				break;
			case \Imagick::ORIENTATION_LEFTBOTTOM:
				$this->rotateImage("#000", -90);
				$this->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
				return TRUE;
				break;
			default: // Invalid orientation
				return FALSE;
		}
	}

	/**
	 * @param int $degree
	 */
	public function rotate(int $degree){
		$this->rotateimage('#000', $degree);
	}

	/**
	 * @param string $filename
	 * @return bool
	 */
	public function save(string $filename){
		if (!$this->haveCopy()) $this->makeCopy();

		$this->setImageFormat('jpg');
		$this->setImageCompression(\Imagick::COMPRESSION_JPEG);
		$this->setImageCompressionQuality(95);
		return $this->writeImage($filename);
	}

	/**
	 * @param int $albumId
	 * @return string
	 * @throws \ImagickException
	 */
	public function generateThumbnail(int $albumId, string $wwwDir = WWW_DIR){
		$this->cropThumbnailImage(self::THUMB_WIDTH, self::THUMB_HEIGHT);

		$thumbname = pathinfo($this->getImageFilename(), PATHINFO_FILENAME);
		$thumbname = Strings::webalize($thumbname).'.jpg';

		$path = $wwwDir . '/' . self::PHOTO_DIR . 	'/' . self::THUMB_DIR . '/' . $albumId;
		$this->writeImage($path .'/'. $thumbname);

		return $thumbname;
	}

	/**
	 * @return bool
	 */
	public function makeCopy(){
		$filename = $this->getImageFilename();
		return copy($filename, $filename.'_');
	}

	/**
	 * @return bool
	 */
	public function haveCopy(){
		return file_exists($this->getImageFilename().'_');
	}
}
