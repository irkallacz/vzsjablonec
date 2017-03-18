<?php

namespace App\PhotoModule\Presenters;

use Nette\Diagnostics\Debugger;
use Nette\Utils\Finder;

class CliPresenter extends BasePresenter{

     /** @var \GalleryService @inject*/
    public $gallery;

    public function renderPhotosTime(){
    	$albums = $this->gallery->getAlbums();

    	$times = [];
    	foreach ($albums as $album) {
	    	foreach ($album->related('photo.album_id') as $photo) {
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

}