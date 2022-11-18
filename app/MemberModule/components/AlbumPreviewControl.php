<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 1.12.2016
 * Time: 21:41
 */

namespace App\MemberModule\Components;

use Nette\Application\UI\Control;
use App\Model\ImageService;
use App\Model\GalleryService;

class AlbumPreviewControl extends Control {

	/**@var GalleryService; */
	private $galleryService;

	/**@var ImageService; */
	private $imageService;

	/**
	 * AlbumPreviewControl constructor.
	 * @param GalleryService $galleryService
	 * @param ImageService $imageService
	 */
	public function __construct(GalleryService $galleryService, ImageService $imageService)
	{
		parent::__construct();
		$this->galleryService = $galleryService;
		$this->imageService = $imageService;
	}

	/**
	 * @param int $id
	 */
	public function render(int $id) {
		$album = $this->galleryService->getAlbumById($id);
		$photos = $album ? $album->related('album_photos')->order('order, created_at') : NULL;

		$this->template->setFile(__DIR__ . '/AlbumPreviewControl.latte');
		$this->template->album = $album;

		if ($photos) {
			$this->template->pocet = $photos->count();
			$this->template->photos = $photos->limit(5);
		}

		$this->template->imageService = $this->imageService;

		$this->template->render();
	}
}