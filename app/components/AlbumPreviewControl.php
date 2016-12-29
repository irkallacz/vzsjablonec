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
        $photos = $album ? $album->related('photo')->order('order, date_add')->limit(5) : NULL;

        $this->template->setFile(__DIR__.'/AlbumPreviewControl.latte');
        $this->template->album = $album;
        $this->template->photos = $photos;
        $this->template->pocet = count($photos);

        $photoDir = 'albums';
        $photoUri = $this->presenter->link('//:Photo:News:');

        LayoutHelpers::$thumbDirUri = 'albums/thumbs';

        $this->template->registerHelper('thumb', 'LayoutHelpers::thumb');
        $this->template->registerHelper('timeAgoInWords', 'Helpers::timeAgoInWords');


        $this->template->photoDir = $photoDir;
        $this->template->photoUri = $photoUri;

        $this->template->render();
    }
}