<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 19.02.2019
 * Time: 18:58
 */

namespace App\PhotoModule;


use Echo511\Plupload\Entity\Upload;
use Nette\Database\IRow;
use Nette\Database\Table\ActiveRow;

final class ImageService {

	/** @var string */
	private $imageDir;

	/** @var string */
	private $thumbDir;

	/** @var string */
	private $wwwDir;

	/**
	 * ImageService constructor.
	 * @param string $imageDir
	 * @param string $thumbDir
	 * @param string $wwwDir
	 */
	public function __construct(string $imageDir, string $thumbDir, string $wwwDir)
	{
		$this->imageDir = $imageDir;
		$this->thumbDir = $thumbDir;
		$this->wwwDir = realpath($wwwDir);
	}


	public function getPath(int $albumId){
		return $this->imageDir . '/' . $albumId;
	}

	public function getThumbPath(int $albumId){
		return $this->thumbDir . '/' . $albumId;
	}

	/**
	 * @param IRow|ActiveRow $photo
	 * @return string
	 */
	public function getPathFromPhoto(IRow $photo){
		return $this->getPath($photo->album_id) . '/' . $photo->filename;
	}


	/**
	 * @param int $albumId
	 * @param string $filename
	 * @return Image
	 */
	public function createImage(int $albumId, string $filename){
		return new Image($albumId, $filename, $this->wwwDir . '/' . $this->imageDir, $this->wwwDir . '/' .$this->thumbDir);
	}

	/**
	 * @param IRow|ActiveRow $photo
	 * @return Image
	 */
	public function createImageFromPhoto(IRow $photo){
		return $this->createImage($photo->album_id, $photo->filename);
	}

	/**
	 * @param int $albumId
	 * @param string $filename
	 * @return Image
	 */
	public function createImageFromUpload(int $albumId, Upload $upload){
		$filename = $upload->getName();
		$filePath = $this->wwwDir . '/' .$this->getPath($albumId) . '/' . $filename;
		$upload->move($filePath);

		return $this->createImage($albumId, $filename);
	}

}