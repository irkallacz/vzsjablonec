<?php

namespace App\PhotoModule\Presenters;

use App\Model\GalleryService;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Nette\Utils\Html;


class MyselfPresenter extends BasePresenter{

    /** @var GalleryService @inject */
    public $gallery;

    public function renderDefault(){
        $this->checkUserLoggin();
        $user_id = $this->getUser()->getId();
        $date_last = $this->getUser()->getIdentity()->date_last;

        $albums = $this->gallery->getAlbums()->order('date_add DESC')->where('member_id',$user_id);

        $pocet = $this->gallery->getAlbumsPhotosCount()->where('member_id',$user_id);
        $pocet = $pocet->fetchPairs('id','pocet');

        $newAlbums = $this->gallery->getAlbumNews($date_last);
        $newPhotos = $this->gallery->getPhotoNews($date_last);

        $this->template->albums = $albums;
        $this->template->pocet = $pocet;

        $this->template->newAlbums = $newAlbums;
        $this->template->newPhotos = $newPhotos;

        $this->template->date_now = new DateTime();
        $this->template->date_last = $date_last;
    }


    public function handleAddAlbum() {        
        $this->template->showAlbumForm = true;
        if ($this->presenter->isAjax()) {    
            $this->redrawControl('albumForm');
        }
     }

    protected function createComponentAlbumForm(){
        $form = new Form;

        $form->addText('name','Název',30,50)
            ->setRequired('Vyplňte %label')
            ->setAttribute('autofocus');
        
        $form->addText('date', 'Datum', 10)
            ->setRequired('Vyplňte datum začátku akce')
            ->setType('date')
            ->setDefaultValue(date_create()->format('Y-m-d'))
            ->addRule(Form::PATTERN, 'Datum musí být ve formátu RRRR-MM-DD', '[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
            ->setAttribute('class','date')
            ->caption = Html::el('acronym')->setText('Datum')->title('Datum by mělo přibližně odpovídat času, kdy byli fotky pořízeny. 
Když neznáte datum akce, nebo datum není důležité, nechte výchozý hodnotu.');
        
        $form->addTextArea('text','Popis',30);

        $form->addSubmit('save', 'Ulož');

        $form->onSuccess[] = [$this, 'albumFormSubmitted'];

        return $form;
    }

    public function albumFormSubmitted(Form $form){
        $values = $form->getValues();
        $datum = new Datetime();
        $values->date_update = $datum;
        $values->date_add = $datum;
        $values->slug = Strings::webalize($values->name);

        $values->member_id = $this->getUser()->getId();

        $album = $this->gallery->addAlbum($values);
        $this->flashMessage('Album bylo přidáno'); 
        mkdir(WWW_DIR .'/'. self::photoDir .'/'. $album->id, 0755);

        $this->redirect('Album:view',$album->id.'-'.$values->slug);
    } 
}