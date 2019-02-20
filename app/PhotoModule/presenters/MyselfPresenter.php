<?php

namespace App\PhotoModule\Presenters;

use App\Model\GalleryService;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
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
	public $galleryService;

	/**
	 *
	 */
	public function renderDefault() {
		$user_id = $this->getUser()->getId();

		/** @var DateTime $date_last*/
		$date_last = $this->user->identity->date_last;

		$albums = $this->galleryService->getAlbums()
			->where('user_id', $user_id)
			->order('date_add DESC');

		$pocet = $this->galleryService->getAlbumsPhotosCount()
			->where('album.user_id', $user_id)
			->fetchPairs('id', 'pocet');

		$newAlbums = $this->galleryService->getAlbumNews($date_last);
		$newPhotos = $this->galleryService->getPhotoNews($date_last);

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

		/** @var \DateInput $dateInput*/
		$dateInput = $form['date'] = new \DateInput('Datum');
		$dateInput->setRequired('Vyplňte datum začátku akce')
			->setDefaultValue(new DateTime())
			->caption = Html::el('acronym')->setText('Datum')->title('Datum by mělo přibližně odpovídat času, kdy byli fotky pořízeny. 
Když neznáte datum akce, nebo datum není důležité, nechte výchozí hodnotu.');

		$form->addTextArea('text', 'Popis', 30)
			->setNullable();

		$form->addSubmit('save', 'Ulož');

		$form->onSuccess[] = [$this, 'albumFormSubmitted'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @param ArrayHash $values
	 * @allow(member)
	 * @throws AbortException
	 */
	public function albumFormSubmitted(Form $form, ArrayHash $values) {
		$datum = new Datetime();
		$values->date_update = $datum;
		$values->date_add = $datum;
		$values->slug = Strings::webalize($values->name);

		$values->user_id = $this->user->id;

		$album = $this->galleryService->addAlbum($values);

		$album->update(['slug' => $album->id . '-' . $album->slug]);

		mkdir($this->imageService->getAbsolutePath($album->id), 0755);
		mkdir($this->imageService->getAbsoluteThumbPath($album->id), 0755);
		
		$this->flashMessage('Album bylo přidáno');

		$this->redirect('Album:view', $album->slug);
	}
}