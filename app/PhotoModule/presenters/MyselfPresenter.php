<?php

namespace App\PhotoModule\Presenters;

use App\Model\GalleryService;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Nette\Utils\Html;


/**
 * Class MyselfPresenter
 * @package App\PhotoModule\Presenters
 * @allow(member)
 */
class MyselfPresenter extends BasePresenter {

	/** @var GalleryService @inject */
	public $gallery;

	/**
	 *
	 */
	public function renderDefault() {
		$user_id = $this->getUser()->getId();

		/** @var DateTime $date_last*/
		$date_last = $this->getUser()->getIdentity()->date_last;

		$albums = $this->gallery->getAlbums()->order('date_add DESC')->where('user_id', $user_id);

		$pocet = $this->gallery->getAlbumsPhotosCount()->where('album.user_id', $user_id);
		$pocet = $pocet->fetchPairs('id', 'pocet');

		$newAlbums = $this->gallery->getAlbumNews($date_last);
		$newPhotos = $this->gallery->getPhotoNews($date_last);

		$this->template->albums = $albums;
		$this->template->pocet = $pocet;

		$this->template->newAlbums = $newAlbums;
		$this->template->newPhotos = $newPhotos;

		$this->template->date_now = new DateTime();
		$this->template->date_last = $date_last;
	}


	/**
	 * @allow(member)
	 */
	public function handleAddAlbum() {
		$this->template->showAlbumForm = true;
		if ($this->presenter->isAjax()) {
			$this->redrawControl('albumForm');
		}
	}

	/**
	 * @return Form
	 * @allow(member)
	 */
	protected function createComponentAlbumForm() {
		$form = new Form;

		$form->addText('name', 'Název', 30, 50)
			->addFilter(['\Nette\Utils\Strings', 'firstUpper'])
			->setRequired('Vyplňte %label')
			->setAttribute('autofocus');

		$form->addText('date', 'Datum', 10)
			->setRequired('Vyplňte datum začátku akce')
			->setType('date')
			->setDefaultValue(date_create()->format('Y-m-d'))
			->addRule(Form::PATTERN, 'Datum musí být ve formátu RRRR-MM-DD', '[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
			->setAttribute('class', 'date')
			->caption = Html::el('acronym')->setText('Datum')->title('Datum by mělo přibližně odpovídat času, kdy byli fotky pořízeny. 
Když neznáte datum akce, nebo datum není důležité, nechte výchozý hodnotu.');

		$form->addTextArea('text', 'Popis', 30);

		$form->addSubmit('save', 'Ulož');

		$form->onSuccess[] = [$this, 'albumFormSubmitted'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @allow(member)
	 */
	public function albumFormSubmitted(Form $form) {
		$values = $form->getValues();
		$datum = new Datetime();
		$values->date_update = $datum;
		$values->date_add = $datum;
		$values->slug = Strings::webalize($values->name);

		$values->user_id = $this->getUser()->getId();

		$album = $this->gallery->addAlbum($values);
		mkdir(WWW_DIR . '/' . self::photoDir . '/' . $album->id, 0755);
		mkdir(WWW_DIR . '/' . self::photoDir . '/thumb/' . $album->id, 0755);
		
		$this->flashMessage('Album bylo přidáno');

		$this->redirect('Album:view', $album->id . '-' . $values->slug);
	}
}