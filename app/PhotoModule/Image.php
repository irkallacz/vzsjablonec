<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 26.9.2018
 * Time: 16:53
 */

namespace App\PhotoModule;

use Nette\SmartObject;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Tracy\Debugger;

/**
 * Class Image
 * @package App\PhotoModule
 */
class Image extends \Imagick {

	use SmartObject;

	/** @var int */
	const THUMB_WIDTH = 150;

	/** @var int*/
	const THUMB_HEIGHT = 100;

	/** @var int */
	const SIZE_THRESHOLD = 2;

	/** @var string */
	private $imageDir;

	/** @var string */
	private $thumbDir;

	/** @var string */
	private $filename;

	/** @var int */
	private $albumId;

	/**
	 * Image constructor.
	 * @param int $albumId
	 * @param string $filename
	 * @param string $imageDir
	 * @param string $thumbDir
	 */
	public function __construct(int $albumId, string $filename, string $imageDir, string $thumbDir) {
		parent::__construct(realpath($imageDir."/$albumId/".$filename));
		$this->albumId = $albumId;
		$this->filename = $filename;
		$this->imageDir = $imageDir;
		$this->thumbDir = $thumbDir;
	}

	/**
	 * @return float
	 */
	public function getSize(){
		return filesize($this->getImageFilename()) / (1024 * 1024);
	}

	public function getFilename(){
		return $this->filename;
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
	 * @return bool
	 */
	public function save(){
		if (!$this->haveCopy()) $this->makeCopy();

		$this->setImageFormat('jpg');
		$this->setImageCompression(\Imagick::COMPRESSION_JPEG);
		$this->setImageCompressionQuality(95);
		return $this->writeImage($this->getImageFilename());
	}

	public function getThumbName(){
		$thumbName = pathinfo($this->filename, PATHINFO_FILENAME);
		$thumbName = Strings::webalize($thumbName).'.jpg';

		return $thumbName;
	}

	/**
	 * @param int $albumId
	 * @return string
	 * @throws \ImagickException
	 */
	public function generateThumbnail(){
		$this->cropThumbnailImage(self::THUMB_WIDTH, self::THUMB_HEIGHT);

		$thumbName = $this->getThumbName();
		$this->writeImage($this->thumbDir. '/'. $this->albumId . '/' . $thumbName);

		return $thumbName;
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

	/**
	 * @return DateTime|NULL
	 */
	public function getExifDateTime(){
		$ext = strtolower(pathinfo($this->getImageFilename(), PATHINFO_EXTENSION));

		if (($ext == 'jpg')or($ext == 'jpeg')) {
			$exif = exif_read_data($this->getImageFilename());
			if (array_key_exists('FileDateTime', $exif)) $datetime = DateTime::from($exif['FileDateTime']);
			if (array_key_exists('DateTime', $exif)) $datetime = Datetime::from($exif['DateTime']);
			if (array_key_exists('DateTimeOriginal', $exif)) $datetime = Datetime::from($exif['DateTimeOriginal']);
			if ($datetime === FALSE) $datetime = NULL;
		}else $datetime = NULL;

		return $datetime;
	}
}
