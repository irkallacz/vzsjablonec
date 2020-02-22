<?php

namespace App\CronModule\Presenters;

use App\Model\GalleryService;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Finder;

class PhotoPresenter extends BasePresenter{

     /** @var GalleryService @inject*/
    public $gallery;

    public function renderPhotosTime(){
    	$albums = $this->gallery->getAlbums();

    	$times = [];
    	foreach ($albums as $album) {
			/** @var ActiveRow $album*/
    		foreach ($album->related('album_photo.album_id') as $photo) {
				/** @var ActiveRow $photo*/

	    		if (!$photo->date_taken){
		            $exif = exif_read_data('albums/'.$album->id.'/'.$photo->filename);

		            if (array_key_exists('DateTime', $exif)) {
		                $datetime = new \Datetime($exif['DateTime']);
		                $photo->update(['date_taken' => $datetime]);
		                $times[$album->id.'/'.$photo->filename] = $datetime;
		            }
			    }
			}
    	}
    	$this->template->images = $times;
    }

	public function renderDirectories(){
		$this->template->dirs = Finder::findDirectories('*')->exclude('thumbs')->in('./albums');
		$this->template->albums = $this->gallery;
		$this->template->files = Finder::findFiles('*');
	}

	public function renderPhotosHash(){
		$photos = $this->gallery->getPhotos()->where('hash ?', NULL)->order('album_id');

		$files = [];
		foreach ($photos as $photo) {
			$file = 'albums/' . $photo->album_id . '/' . $photo->filename;
			$file = (file_exists($file . '_')) ? $file . '_' : $file;
			$hash = md5_file($file);
			$photo->update(['hash' => $hash]);
			$files[$file] = $hash;
		}
		$this->template->images = $files;
	}


}