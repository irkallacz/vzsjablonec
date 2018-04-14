<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 1.12.2016
 * Time: 21:41
 */

namespace App\MemberModule\Components;

use Nette\Application\UI\Control;
use App\Model\GalleryService;

class AlbumPreviewControl extends Control {

	/**@var GalleryService; */
	private $galleryService;

	/**
	 * AlbumPreviewControl constructor.
	 * @param GalleryService $galleryService
	 */
	public function __construct(GalleryService $galleryService) {
		parent::__construct();
		$this->galleryService = $galleryService;
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

		$this->template->photoDir = 'albums';
		$this->template->photoUri = $this->presenter->link('//:Photo:News:');

		$this->template->render();
	}
}