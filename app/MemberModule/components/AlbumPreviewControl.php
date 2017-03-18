<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 1.12.2016
 * Time: 21:41
 */

use Nette\Application\UI\Control;

class AlbumPreviewControl extends Control{

    /**
     * @var GalleryService;
     */
    private $galleryService;

    /**
     * AlbumPreviewControl constructor.
     * @param GalleryService $galleryService
     */
    public function __construct(GalleryService $galleryService){
        parent::__construct();
        $this->galleryService = $galleryService;
    }

    public function render($id){
        $album = $this->galleryService->getAlbumById($id);
        $photos = $album ? $album->related('photo')->order('order, date_add') : NULL;

        $this->template->setFile(__DIR__ . '/AlbumPreviewControl.latte');
        $this->template->album = $album;
        
        if ($photos){
        	$this->template->pocet = $photos->count();
        	$this->template->photos = $photos->limit(5);
        }

        $photoDir = 'albums';
        $photoUri = $this->presenter->link('//:Photo:News:');

        LayoutHelpers::$thumbDirUri = 'albums/thumbs';

        $this->template->addFilter('thumb', 'LayoutHelpers::thumb');
        $this->template->addFilter('timeAgoInWords', 'Helpers::timeAgoInWords');


        $this->template->photoDir = $photoDir;
        $this->template->photoUri = $photoUri;

        $this->template->render();
    }
}