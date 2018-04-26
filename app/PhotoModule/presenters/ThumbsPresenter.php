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
use Nette\Utils\Strings;

class ThumbsPresenter extends BasePresenter {

	/** @var GalleryService @inject */
	public $galleryService;

	public function renderDefault() {

		$photos = $this->galleryService->getPhotos()
			->where('thumb', NULL)
			->order('album_id, id')
			->limit(100);

		$this->template->items = [];

		foreach ($photos as $photo) {
			$thumbDir	=	self::PHOTO_DIR . DIRECTORY_SEPARATOR . 	self::THUMB_DIR . DIRECTORY_SEPARATOR .	$photo->album_id . DIRECTORY_SEPARATOR;
			$fileDir	=	self::PHOTO_DIR . DIRECTORY_SEPARATOR .												$photo->album_id . DIRECTORY_SEPARATOR;

			$image = Image::fromFile(WWW_DIR . '/' . $fileDir . $photo->filename);

			// zachovani pruhlednosti u PNG
			$image->alphaBlending(FALSE);
			$image->saveAlpha(TRUE);
			$image->resize(150, 100, Image::EXACT)
				->sharpen();

			$filename = pathinfo($photo->filename, PATHINFO_FILENAME);
			$filename = Strings::webalize($filename) . '.jpg';

			//if (!file_exists(WWW_DIR . '/' .$thumbDir)) mkdir(WWW_DIR . '/' .$thumbDir);

			$image->save(WWW_DIR . '/' . $thumbDir . $filename, 80, Image::JPEG);
			$photo->update(['thumb' => $filename]);

			$this->template->items[] = $thumbDir . $filename;
		}
	}

	public function renderPrepare() {
		$albums = $this->galleryService->getAlbums();

		$this->template->items = [];

		foreach ($albums as $album) {
			$dirname = WWW_DIR . DIRECTORY_SEPARATOR . self::PHOTO_DIR . DIRECTORY_SEPARATOR . self::THUMB_DIR . DIRECTORY_SEPARATOR . $album->id . DIRECTORY_SEPARATOR;
			if (!file_exists($dirname)) {
				mkdir($dirname, 0755);
				$this->template->items[] = $dirname;
			}
		}

		$this->setView('default');
	}

}