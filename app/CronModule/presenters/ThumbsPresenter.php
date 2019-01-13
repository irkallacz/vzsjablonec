<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 14.4.2018
 * Time: 16:28
 */

namespace App\CronModule\Presenters;

use App\Model\GalleryService;
use App\PhotoModule\Image;
use Tracy\Debugger;

class ThumbsPresenter extends BasePresenter {

	/** @var GalleryService @inject */
	public $galleryService;

	public function renderDefault() {

		$photos = $this->galleryService->getPhotos()
			->where('thumb', NULL)
			->order('date_add DESC')
			->limit(100);

		$this->template->items = [];

		foreach ($photos as $photo) {
			try{
				$filename = WWW_DIR . '../photo/' . Image::PHOTO_DIR . '/' . Image::THUMB_DIR . '/' . $photo->album_id . '/' . $photo->filename;
				$image = new Image($filename);
				$thumbname = $image->generateThumbnail($photo->album_id);
				$image->clear();

				$this->galleryService->updatePhoto($photo->id, ['thumb' => $thumbname]);
				$this->template->items[] = $photo->album_id . '/' . $thumbname;
			} catch (\Exception $e){
				Debugger::log($photo->id . ' '. $photo->album_id . '/'. $photo->filename);
			}
		}
	}

	public function renderPrepare() {
		$albums = $this->galleryService->getAlbums();

		$this->template->items = [];

		foreach ($albums as $album) {
			$dirname = WWW_DIR . '/' . Image::PHOTO_DIR . '/' . Image::THUMB_DIR . '/' . $album->id . '/';
			if (!file_exists($dirname)) {
				mkdir($dirname, 0755);
				$this->template->items[] = $dirname;
			}
		}

		$this->setView('default');
	}

}