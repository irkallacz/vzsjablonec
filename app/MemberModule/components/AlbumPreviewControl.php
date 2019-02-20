<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 1.12.2016
 * Time: 21:41
 */

namespace App\MemberModule\Components;

use App\PhotoModule\ImageService;
use Nette\Application\UI\Control;
use App\Model\GalleryService;
use Nette\Database\Table\IRow;

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
		$photos = $album ? $album->related('album_photo')->order('order, date_add') : NULL;

		$this->template->setFile(__DIR__ . '/AlbumPreviewControl.latte');
		$this->template->album = $album;

		if ($photos) {
			$this->template->pocet = $photos->count();
			$this->template->photos = $photos->limit(5);
		}

		$this->template->photoUri = $this->presenter->link('//:Photo:News:');

		$this->template->addFilter('thumb', [$this->imageService, 'getThumbPathFromPhoto']);

		$this->template->render();
	}
}