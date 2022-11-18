<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 19.02.2019
 * Time: 18:58
 */

namespace App\Model;

/**
 * Class ImageService
 * @package App\PhotoModule
 */
final class ImageService {

	/** @var string */
	private $galleryUrl;

	/** @var string */
	private $albumUrl;

	/** @var string */
	private $albumDir;

	/** @var string */
	private $thumbDir;

	/**
	 * ImageService constructor.
	 * @param string $galleryUrl
	 * @param string $albumUrl
	 * @param string $albumDir
	 * @param string $thumbDir
	 */
	public function __construct(string $galleryUrl, string $albumUrl, string $albumDir, string $thumbDir)
	{
		$this->galleryUrl = $galleryUrl;
		$this->albumUrl = $albumUrl;
		$this->albumDir = $albumDir;
		$this->thumbDir = $thumbDir;
	}

	/**
	 * @param int $albumId
	 * @param string $thumbName
	 * @return string
	 */
	public function getThumbUrl(int $albumId, string $thumbName) {
		return join('/', [$this->galleryUrl, $this->albumDir, $albumId, $this->thumbDir, $thumbName]);
	}

	/**
	 * @return string
	 */
	public function getAlbumUrl(string $slug) {
		return join('/', [$this->galleryUrl, $this->albumUrl, $slug]);
	}




}