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

	/** @var GalleryService @inject*/
	public $galleryService;

	public function renderDefault(){

		$photos = $this->galleryService->getPhotos()
			->where('thumb', NULL)
			->order('album_id, id')
			->limit(30);

		$this->template->items = [];

		foreach ($photos as $photo){
			$thumbDir 	=	self::photoDir 	.'/thumbs/'	.$photo->album_id . '/';
			$fileDir 	= 	self::photoDir 	.'/' 			.$photo->album_id . '/';
			$thumbDir	=	self::PHOTO_DIR . DIRECTORY_SEPARATOR . 	self::THUMB_DIR . DIRECTORY_SEPARATOR .	$photo->album_id . DIRECTORY_SEPARATOR;
			$fileDir	=	self::PHOTO_DIR . DIRECTORY_SEPARATOR .												$photo->album_id . DIRECTORY_SEPARATOR;

			$image = Image::fromFile(WWW_DIR . '/' . $fileDir . $photo->filename);

			// zachovani pruhlednosti u PNG
			$image->alphaBlending(FALSE);
			$image->saveAlpha(TRUE);
			$image->resize(150, 100,Image::EXACT)
				->sharpen();

			$filename = pathinfo($photo->filename, PATHINFO_FILENAME);
			$filename = Strings::webalize($filename).'.jpg';

			if (!file_exists(WWW_DIR . '/' .$thumbDir)) mkdir(WWW_DIR . '/' .$thumbDir);

			$image->save(WWW_DIR . '/' . $thumbDir . $filename, 80, Image::JPEG);
			$photo->update(['thumb' => $filename]);

			$this->template->items[] = $thumbDir . $filename;
		}
	}

}