<?php

namespace PhotoModule;

use Nette\Application\UI\Form;
use Nette\DateTime;
use Nette\Image;
use Nette\Utils\Strings;
use Nette\Database\SqlLiteral;
use Nette\Diagnostics\Debugger;
use Echo511\Plupload;
use Nette\Http\FileUpload;
use lsolesen\pel\PelJpeg;

class AlbumPresenter extends BasePresenter{

	 /** @var \GalleryService @inject*/
	public $gallery;

	/** @var \MemberService @inject*/
	public $members;

    protected function startup(){
        parent::startup();
	    \Kdyby\Extension\Forms\Replicator\Replicator::register();
    }


	public function getAlbumById($slug){
		$id = $this->getIdFromSlug($slug);

		$album = $this->gallery->getAlbumById($id);

		if ((!$album)or($slug != $album->id.'-'.$album->slug)){
			$this->flashMessage('Zadané album neexistuje','error');
			$this->redirect('default');
		}

		return $album;
	}

	public function renderDefault(){
		$albums = $this->gallery->getAlbums()->order('date DESC');
		$pocet = $this->gallery->getAlbumsPhotosCount();

		if (!$this->getUser()->isLoggedIn()){
			$albums->where('visible',TRUE);
			$pocet->where('album.visible',TRUE)->where(':photo.visible',TRUE);
		}else $this->template->member = $this->members->getMembersArray(FALSE);

		$this->template->albums = $albums;
		$this->template->pocet = $pocet->fetchPairs('id','pocet');
	}

	public function renderUsers(){
		$this->checkUserLoggin();

		$albums = $this->gallery->getAlbums()->order('member_id, date DESC');

		$membersAlbums = $albums->fetchPairs('member_id','id');

		$member = $this->members->getMembersArray(FALSE);
		asort($member);

		$membersAlbums = array_intersect_key($member, $membersAlbums);

		$pocet = $this->gallery->getAlbumsPhotosCount();
		$pocet = $pocet->fetchPairs('id','pocet');

		$this->template->albums = $albums;
		$this->template->pocet = $pocet;
		$this->template->member = $member;
		$this->template->membersAlbums = $membersAlbums;
	}

	public function renderView($slug,$pubkey = null){
		$album = $this->getAlbumById($slug);
		$this->template->album = $album;

		$pubkeyCheck = $pubkey === $album->pubkey;

		if ((!$album->visible)and(!(($this->getUser()->isLoggedIn())or($pubkeyCheck)))) $this->checkUserLoggin();

		$photos = $this->gallery->getPhotosByAlbumId($album->id)->order('order, date_add');

		$member = $this->members->getMemberById($album->member_id);

		if (!(($this->getUser()->isLoggedIn())or($pubkeyCheck))) $photos->where('visible',TRUE);

		$this->template->photos = $photos;
		$this->template->slug = $slug;
		$this->template->member = $member;

		$this->registerTexy();
	}

	public function renderEdit($slug, $order = 'order'){
		$this->checkUserLoggin();

		$album = $this->getAlbumById($slug);
		$this->template->album = $album;

		if (!(($album->member_id == $this->getUser()->getId())or($this->getUser()->isInRole($this->name)))) {
			$this->flashMessage('Nemáte právo toho album upravovat','error');
			$this->redirect('view',$slug);
		}

		$photos = $this->gallery->getPhotosByAlbumId($album->id)
			->order($order);

		$this->template->photos = $photos;
		$this->template->slug = $slug;
		$this->template->order = $order;

		$form = $this['superForm'];
		if (!$form->isSubmitted()) {
			foreach ($photos as $photo) {
				$form['photos'][$photo->id]['text']->setDefaultValue($photo->text);
				$form['photos'][$photo->id]['text']->setAttribute('data-date', $photo->date_taken ? $photo->date_taken->format('d.m.Y H:i:s') : NULL);
				$form['photos'][$photo->id]['text']->setAttribute('data-title', $photo->text);
			}

			$form->setDefaults($album);
			$form['date']->setValue($album->date->format('Y-m-d'));
		}
	}

	public function renderAdd($slug){
		$this->checkUserLoggin();

		$album = $this->getAlbumById($slug);
		$this->template->album = $album;
		$this->template->slug = $slug;
	}

	public function actionSetAlbumVisibility($slug, $visible = FALSE){
		$this->checkUserLoggin();

		$id = $this->getIdFromSlug($slug);

		if (!$this->getUser()->isInRole($this->name)) {
			$this->flashMessage('Nemáte právo měnit viditelnost alba','error');
			$this->redirect('view',$slug);
		}

		$this->gallery->getAlbumById($id)->update(array('visible'=>$visible));

		$text = $visible ? null : 'ne';
		$this->flashMessage('Album bylo označeno jako '.$text.'viditelné pro veřenost');

		$this->redirect('Album:view',$slug);
	}

	public function actionDeleteAlbum($id){
	   $this->checkUserLoggin();

	   $album = $this->gallery->getAlbumById($id);

	   if (!(($album->member_id==$this->getUser()->getId())or($this->getUser()->isInRole($this->name)))) {
		$this->flashMessage('Nemáte právo toto album smazat','error');
		$this->redirect('view',$id.'-'.$album->slug);
	   }

	   $this->gallery->getPhotosByAlbumId($album->id)->delete();
	   $album->delete();

	   $this->flashMessage('Album bylo smazáno');
	   $this->redirect('Myself:default');
	}

	public function createComponentPlupload(){
		$uploader = new Plupload\Rooftop();
		$uploader->disableMagic();

		$uploader->setWwwDir(WWW_DIR) // Full path to your frontend directory
				 ->setBasePath($this->template->basePath) // BasePath provided by Nette
				 ->setTempLibsDir(WWW_DIR . '/js'); // Full path to the location of plupload libs (js, css)

		$uploader->createSettings()
				 ->setRuntimes(array('html5','flash')) // Available: gears, flash, silverlight, browserplus, html5
				 ->setMaxFileSize('3mb')
				 ->setMaxChunkSize('1mb'); // What is chunk you can find here: http://www.plupload.com/documentation.php
				 //->setFlashSwfUrl('/js/plupload.flash.swf');

		$uploader->createUploader()
				 ->setTempUploadsDir(TEMP_DIR . '/uploads') // Where should be placed temporaly files
				 ->setToken('vzs') // Resolves file names collisions in temp directory
				 ->setOnSuccess(array($this, 'pluploadSubmbited')); // Callback when upload is successful: returns Nette\Http\FileUpload

		return $uploader->getComponent();
	}

	public function pluploadSubmbited(FileUpload $file){
		$slug = (string) $this->getParameter('slug');
		$id = $this->getIdFromSlug($slug);

		$datum = new DateTime();

		$name = $file->getName();

		if ($file->isImage()) {
			if ($file->isOk()) {
				$filename = self::photoDir.'/'.$id.'/'.$name;
				$filepath = WWW_DIR.'/'. $filename;
				$file->move($filepath);

				$values = [
				  'filename' => $name,
				  'album_id' => $id,
				  'date_add' => $datum
				];

				$exif = exif_read_data($filepath);
				if (array_key_exists('DateTime', $exif)) {
					$datetime = new Datetime($exif['DateTime']);
					if ($datetime != FALSE) $values['date_taken'] = $datetime;
				}

				$this->gallery->addPhoto($values);

				\LayoutHelpers::$thumbDirUri = 'albums/thumbs';
				\LayoutHelpers::thumb($filename,150,100);
			}else{
				$this->flashMessage('Soubor '.$file->name.' nebyl v pořádnu nahrán');
				$this->redirect('edit',$slug);
			}
		}else{
			$this->flashMessage('Soubor '.$file->name.' není obrázek');
			$this->redirect('edit',$slug);
		}
	}

	protected function createComponentSuperForm(){
		$form = new Form;

		$form->addText('name','Název',30,50)
			->setRequired('Vyplňte %label');

		$form->addText('date', 'Datum', 10,10)
			->setRequired('Vyplňte datum začátku akce')
			->setType('date')
			->setDefaultValue(date_create()->format('Y-m-d'))
			->addRule(Form::PATTERN, 'Datum musí být ve formátu RRRR-MM-DD', '[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
			->setAttribute('class','date');

		$form->addSelect('member_id','Uživatel',$this->members->getMembersArray(FALSE));

		$form->addCheckBox('show_date','Upravit datum pořízení')
			->setDefaultValue(FALSE)
			->setAttribute('onclick', 'swapTitle(this)');

		$form->addTextArea('text','Popis',30);

		$photos = $form->addDynamic('photos', function (\Nette\Forms\Container $photo) {
			$photo->addText('text', 'Popis', 30, 50);
			$photo->addCheckBox('selected')->setAttribute('class','select');
		}, 0);

		$form->addSubmit('save', 'uložit změny')
			->onClick[] =  callback($this, 'SuperFormSave');

		$form->addSubmit('delete', 'vymazat vybrané')
			->onClick[] =  callback($this, 'SuperFormDelete');

		$form->addSubmit('visible', 'změnit viditelnost')
			->onClick[] =  callback($this, 'SuperFormVisible');

		$form->addSubmit('turnLeft', 'otočit o 90° doleva')
			->onClick[] =  callback($this, 'SuperFormTurnLeft');

		$form->addSubmit('turnRight', 'otočit o 90° doprava')
			->onClick[] =  callback($this, 'SuperFormTurnRight');


		return $form;
	}

	public function superFormSave(){
		$slug = (string) $this->params['slug'];
		$id = (int) $this->getIdFromSlug($slug);

		$values = $this['superForm']->getValues();
		$values->date_update = new Datetime();
		$values->text = self::nullString($values->text);
		$values->slug = Strings::webalize($values->name);

		$show_date = $values->show_date;
		unset($values->show_date);

		$photos = $values->photos;
		unset($values->photos);

		$this->gallery->getAlbumById($id)->update($values);
		$order = array_flip(array_keys(iterator_to_array($photos)));

		foreach ($photos as $key => $photo) {
			$update = ['order' => $order[$key]];

			if ($show_date) {
				$datetime = $photo->text ? date_create($photo->text) : NULL;
			if ($datetime == FALSE) $datetime = NULL;
				$update['date_taken'] = $datetime;
			}
			else $update['text'] = self::nullString($photo->text);

			$this->gallery->getPhotoById($key)->update($update);
		}

		$this->flashMessage('Album bylo upraveno');
		$this->redirect('view',$id.'-'.$values->slug);
	}

	private function getSuperFromSelected(){
		$photos = $values = $this['superForm']->getValues()->photos;

		if (!$photos) {
			$slug = (string) $this->params['slug'];
			$this->flashMessage('Musíte vybrat nějaká fotografie','error');
			$this->redirect('edit',$slug);
		}

		$selected = array();
		foreach ($photos as $key => $photo) {
			if ($photo->selected) $selected[] = (int) $key;
		}

		if (empty($selected)) {
			$slug = (string) $this->params['slug'];
			$this->flashMessage('Musíte vybrat nějaké fotografie','error');
			$this->redirect('edit',$slug);
		}

		return $selected;
	}

	public function superFormDelete(){
		$selected = $this->getSuperFromSelected();

		$this->gallery->deletePhotos($selected);

		$slug = (string) $this->params['slug'];
		$this->flashMessage('Bylo smazáno '.count($selected).' fotografií');
		$this->redirect('view',$slug);
	}

	private function superFormImagesTurn($angle){
		$selected = $this->getSuperFromSelected();

		$photos = $this->gallery->getPhotos()->where('id',$selected);

		foreach ($photos as $photo) {
			$filename = self::photoDir.'/'.$photo->album_id.'/'.$photo->filename;

			$inputExifFile = new PelJpeg($filename);
			$exif = $inputExifFile->getExif();
			unset($inputExifFile);

			$image =  Image::fromFile($filename);
			$image->rotate($angle,0);
			$image->save($filename, 100, Image::JPEG);

			if ($exif){
				$outputExifFile = new PelJpeg($filename);
				$outputExifFile->setExif($exif);
				$outputExifFile->saveFile($filename);
			}
		}

		return $selected;
	}

	public function superFormTurnLeft(){
		$selected = $this->superFormImagesTurn(90);

		$slug = (string) $this->params['slug'];
		$this->flashMessage('Doleva bylo otočeno '.count($selected).' fotografií');
		$this->redirect('edit',$slug);
	}

	 public function superFormTurnRight(){
		$selected = $this->superFormImagesTurn(-90);

		$slug = (string) $this->params['slug'];
		$this->flashMessage('Doprava bylo otočeno '.count($selected).' fotografií');
		$this->redirect('edit',$slug);
	}

	public function superFormVisible(){
		$selected = $this->getSuperFromSelected();

		$this->gallery->getPhotos()->where('id',$selected)->update(['visible' => new SqlLiteral('NOT(`visible`)')]);

		$slug = (string) $this->params['slug'];
		$this->flashMessage('Bylo změněna viditelnost '.count($selected).' fotografií');
		$this->redirect('view',$slug);
	}
}