<?php

namespace App\PhotoModule\Presenters;

use App\Model\GalleryService;
use App\Model\MemberService;

class NewsPresenter extends BasePresenter {

	/** @var GalleryService @inject */
	public $gallery;

	/** @var MemberService @inject */
	public $member;

	public function renderDefault() {
		$albums = $this->gallery->getAlbums()->order('date_add DESC')->limit(5);
		$photos = $this->gallery->getPhotos()->order('rand()')->limit(10);

		if (!$this->getUser()->isLoggedIn()) {
			$albums->where('visible', TRUE);
			$photos->where('photo.visible', TRUE)->where('album.visible', TRUE);
		} else $this->template->member = $this->member->getMembersArray(FALSE);

		$this->template->albums = $albums;
		$this->template->photos = $photos;
	}

}