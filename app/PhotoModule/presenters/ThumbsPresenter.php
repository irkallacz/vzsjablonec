<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 14.4.2018
 * Time: 16:28
 */

namespace App\PhotoModule\Presenters;

use App\Model\GalleryService;
use Nette\Utils\Image;
use Nette\Utils\ImageException;
use Nette\Utils\Strings;
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
				$filename = $this->getThumbName($photo->filename, $photo->album_id);
				$this->galleryService->updatePhoto($photo->id, ['thumb' => $filename]);
				$this->template->items[] = $photo->album_id . '/' . $filename;
			} catch (ImageException $e){
				Debugger::log($photo->id . ' '. $photo->album_id . '/'. $photo->filename);
			}
		}
	}

	public function renderPrepare() {
		$albums = $this->galleryService->getAlbums();

		$this->template->items = [];

		foreach ($albums as $album) {
			$dirname = WWW_DIR . '/' . self::PHOTO_DIR . '/' . self::THUMB_DIR . '/' . $album->id . '/';
			if (!file_exists($dirname)) {
				mkdir($dirname, 0755);
				$this->template->items[] = $dirname;
			}
		}

		$this->setView('default');
	}

}