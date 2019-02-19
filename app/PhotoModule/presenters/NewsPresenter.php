<?php

namespace App\PhotoModule\Presenters;

use App\Model\GalleryService;
use App\Model\UserService;

class NewsPresenter extends BasePresenter {

	/** @var GalleryService @inject */
	public $galleryService;

	/** @var UserService @inject */
	public $userService;

	/**
	 *
	 */
	public function renderDefault() {
		$albums = $this->galleryService->getAlbums()
			->order('date_add DESC')
			->limit(5);

		$photos = $this->galleryService->getPhotos()
			->order('rand()')
			->limit(10);

		if (!$this->getUser()->isLoggedIn()) {
			$albums->where('visible', TRUE);
			$photos->where('album_photo.visible', TRUE)->where('album.visible', TRUE);
		}

		$this->template->albums = $albums;
		$this->template->photos = $photos;
	}

}