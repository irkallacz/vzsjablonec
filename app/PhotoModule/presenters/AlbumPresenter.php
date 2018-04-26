<?php

namespace App\PhotoModule\Presenters;

use App\Model\GalleryService;
use App\Model\UserService;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Nette\Utils\Image;
use Nette\Utils\Strings;
use Nette\Database\SqlLiteral;
use Echo511\Plupload;
use Echo511\Plupload\Entity\UploadQueue;
use Nette\Http\FileUpload;
use lsolesen\pel\PelJpeg;
use Tracy\Debugger;

class AlbumPresenter extends BasePresenter {

	/** @var GalleryService @inject */
	public $gallery;

	/** @var UserService @inject */
	public $userService;

	/** @var \Echo511\Plupload\Control\IPluploadControlFactory @inject */
	public $controlFactory;

	private $offset = 0;

	const LOAD_COUNT = 30;

	/**
	 * @param string $slug
	 * @return IRow|ActiveRow
	 * @throws BadRequestException
	 */
	public function getAlbumById(string $slug) {
		$id = parent::getIdFromSlug($slug);

		$album = $this->gallery->getAlbumById($id);

		if ((!$album) or ($slug != $album->id . '-' . $album->slug)) {
			throw new BadRequestException('Zadané album neexistuje');
		}

		return $album;
	}

	/**
	 *
	 */
	public function renderDefault() {
		$albums = $this->gallery->getAlbums()->order('date DESC');

		if (!$this->getUser()->isLoggedIn()) {
			$albums->where('visible', TRUE);
		}

		$albums->limit(self::LOAD_COUNT, $this->offset);

		$this->template->albums = $albums;
		$this->template->offset = $this->offset + self::LOAD_COUNT;
		$this->template->needMore = ($albums->count() == self::LOAD_COUNT);
	}


	/**
	 * @param int $offset
	 */
	public function handleLoadMore(int $offset) {
		$this->offset = $offset;
		$this->redrawControl();
	}

	/**
	 * @allow(user)
	 */
	public function renderUsers() {
		$users = $this->userService->getUsers(UserService::DELETED_LEVEL)
			->order('surname, name');

		$this->template->users = $users;
	}

	/**
	 * @param string $slug
	 * @param string|NULL $pubkey
	 */
	public function renderView(string $slug, string $pubkey = NULL) {
		$album = $this->getAlbumById($slug);
		$this->template->album = $album;

		$pubkeyCheck = ($pubkey === $album->pubkey);

		if ((!$album->visible) and (!(($this->getUser()->isLoggedIn()) or ($pubkeyCheck)))) {
			$backlink = $this->storeRequest();
			$this->redirect('Sign:in', ['backlink' => $backlink]);
		}

		$photos = $this->gallery->getPhotosByAlbumId($album->id)->order('order, date_add');

		if (!(($this->getUser()->isLoggedIn()) or ($pubkeyCheck))) $photos->where('visible', TRUE);

		$this->template->photos = $photos;
		$this->template->slug = $slug;
	}

	/**
	 * @param string $slug
	 * @param string $order
	 * @allow(member)
	 * @throws ForbiddenRequestException
	 */
	public function renderEdit(string $slug, string $order = 'order') {
		$album = $this->getAlbumById($slug);
		$this->template->album = $album;

		if (!(($album->user_id == $this->getUser()->getId()) or ($this->getUser()->isInRole('admin')))) {
			throw new ForbiddenRequestException('Nemáte právo toho album upravovat');
		}

		$this->template->order = $order;

		if ($order == 'order') $order = $order . ', date_add';

		$photos = $this->gallery->getPhotosByAlbumId($album->id)
			->order($order);

		$this->template->photos = $photos;
		$this->template->slug = $slug;

		/** @var Form $form*/
		$form = $this['superForm'];
		if (!$form->isSubmitted()) {
			$form->setDefaults($album);
			$form['date']->setValue($album->date->format('Y-m-d'));
		}
	}

	/**
	 * @param string $slug
	 * @allow(user)
	 */
	public function renderAdd(string $slug) {
		$album = $this->getAlbumById($slug);
		$this->template->album = $album;
		$this->template->slug = $slug;
	}

	/**
	 * @param string $slug
	 * @param bool|NULL $visible
	 * @allow(admin)
	 */
	public function actionSetAlbumVisibility(string $slug, bool $visible = FALSE) {
		$id = parent::getIdFromSlug($slug);

		$album = $this->gallery->getAlbumById($id);
		$album->update(['visible' => $visible]);

		$text = $visible ? null : 'ne';
		$this->flashMessage('Album bylo označeno jako ' . $text . 'viditelné pro veřenost');

		$this->redirect('Album:view', $slug);
	}

	/**
	 * @param int $id
	 * @allow(member)
	 * @throws ForbiddenRequestException
	 */
	public function actionDeleteAlbum(int $id) {
		$album = $this->gallery->getAlbumById($id);

		if (!(($album->member_id == $this->getUser()->getId()) or ($this->getUser()->isInRole('admin')))) {
			throw new ForbiddenRequestException('Nemáte právo toto album smazat');
		}

		$this->gallery->getPhotosByAlbumId($album->id)->delete();
		$album->delete();

		$this->flashMessage('Album bylo smazáno');
		$this->redirect('Myself:default');
	}

	/**
	 * @return Plupload\Control\PluploadControl
	 * @allow(user)
	 */
	public function createComponentPlupload() {
		$plupload = $this->controlFactory->create();

		$plupload->maxFileSize = '5mb';
		$plupload->maxChunkSize = '1mb';
		$plupload->allowedExtensions = 'jpg,jpeg,gif,png';

		$slug = (string) $this->getParameter('slug');
		$id = parent::getIdFromSlug($slug);

		$plupload->onFileUploaded[] = function (UploadQueue $uploadQueue) use ($id) {
			$upload = $uploadQueue->getLastUpload();

			$name = $upload->getName();
			$filename = self::PHOTO_DIR . '/' . $id . '/' . $name;
			$filepath = WWW_DIR . '/' . $filename;
			$upload->move($filepath);

			$values = [
				'filename' => $name,
				'album_id' => $id,
				'date_add' => new DateTime,
				'user_id' => $this->user->id
			];

			$ext = pathinfo($name, PATHINFO_EXTENSION);

			if (($ext == 'jpg')or($ext == 'jpeg')) {
				$exif = exif_read_data($filepath);
				if (array_key_exists('DateTimeOriginal', $exif)) {
					$datetime = new Datetime($exif['DateTimeOriginal']);
					if ($datetime != FALSE) $values['date_taken'] = $datetime;
				}
			}

			$photo = $this->gallery->addPhoto($values);
			$this->getThumbName($photo);
		};

		$plupload->onUploadComplete[] = function (UploadQueue $uploadQueue) use ($slug) {
			$this->flashMessage('Fotografie byli v pořádku přidány');
			$this->redirect('view', $slug);
		};

		return $plupload;
	}

	/**
	 * @return Form
	 * @allow(member)
	 */
	protected function createComponentSuperForm() {
		$form = new Form;

		$form->addText('name', 'Název', 30, 50)
			->setRequired('Vyplňte %label');

		$form->addText('date', 'Datum', 10, 10)
			->setRequired('Vyplňte datum začátku akce')
			->setType('date')
			->setDefaultValue(date('Y-m-d'))
			->addRule(Form::PATTERN, 'Datum musí být ve formátu RRRR-MM-DD', '[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
			->setAttribute('class', 'date');

		$form->addSelect('member_id', 'Uživatel', $this->userService->getUsersArray(UserService::DELETED_LEVEL));

		$form->addCheckBox('show_date', 'Upravit datum pořízení')
			->setDefaultValue(FALSE)
			->setHtmlId('swap-title');

		$form->addTextArea('text', 'Popis', 30)
			->setNullable();

		$form->addMultiplier('photos', function (\Nette\Forms\Container $photo) {
			$photo->addText('text', 'Popis', 30, 50);
			$photo->addHidden('id');
			$photo->addCheckBox('selected')
				->setAttribute('class', 'select')
				->setDefaultValue(FALSE);
		}, 0);

		$form->addSubmit('save', 'uložit změny')
			->onClick[] = [$this, 'SuperFormSave'];

		$form->addSubmit('delete', 'vymazat vybrané')
			->onClick[] = [$this, 'SuperFormDelete'];

		$form->addSubmit('visible', 'změnit viditelnost')
			->onClick[] = [$this, 'SuperFormVisible'];

		$form->addSubmit('turnLeft', 'otočit o 90° doleva')
			->onClick[] = [$this, 'SuperFormTurnLeft'];

		$form->addSubmit('turnRight', 'otočit o 90° doprava')
			->onClick[] = [$this, 'SuperFormTurnRight'];


		$album = $this->getAlbumById($this->getParameter('slug'));
		$photos = $this->gallery->getPhotosByAlbumId($album->id)->fetchPairs('id');
		$form->setDefaults(['photos' => $photos]);

		return $form;
	}

	/**
	 * @allow(member)
	 */
	public function superFormSave() {
		$slug = (string) $this->params['slug'];
		$id = parent::getIdFromSlug($slug);

		/** @var Form $form*/
		$form = $this['superForm'];
		$values = $form->getValues();
		$values->date_update = new Datetime();
		$values->slug = Strings::webalize($values->name);

		$show_date = $values->show_date;
		unset($values->show_date);

		$photos = $values->photos;
		unset($values->photos);

		$album = $this->gallery->getAlbumById($id);
		$album->update($values);

		foreach ($photos as $order => $photo) {
			$update = ['order' => $order];

			if ($show_date) {
				$datetime = $photo->text ? date_create($photo->text) : NULL;
				if ($datetime == FALSE) $datetime = NULL;
				$update['date_taken'] = $datetime;
			} else $update['text'] = $photo->text;

			$this->gallery->getPhotoById($photo->id)->update($update);
		}

		$this->flashMessage('Album bylo upraveno');
		$this->redirect('view', $id . '-' . $values->slug);
	}

	/**
	 * @return array
	 * @allow(member)
	 */
	private function getSuperFromSelected() {
		$photos = $values = $this['superForm']->getValues()->photos;

		if (!$photos) {
			$slug = (string)$this->params['slug'];
			$this->flashMessage('Musíte vybrat nějaká fotografie', 'error');
			$this->redirect('edit', $slug);
		}

		$selected = [];
		foreach ($photos as $order => $photo) {
			if ($photo->selected) $selected[] = $photo->id;
		}

		if (empty($selected)) {
			$slug = (string)$this->params['slug'];
			$this->flashMessage('Musíte vybrat nějaké fotografie', 'error');
			$this->redirect('edit', $slug);
		}

		return $selected;
	}

	/**
	 * @allow(member)
	 */
	public function superFormDelete() {
		$selected = $this->getSuperFromSelected();

		$this->gallery->deletePhotos($selected);

		$slug = (string)$this->params['slug'];
		$this->flashMessage('Bylo smazáno ' . count($selected) . ' fotografií');
		$this->redirect('view', $slug);
	}

	/**
	 * @param float $angle
	 * @return array
	 * @allow(member)
	 */
	private function superFormImagesTurn(float $angle) {
		$selected = $this->getSuperFromSelected();

		$photos = $this->gallery->getPhotos()->where('id', $selected);

		foreach ($photos as $photo) {
			$filename = self::PHOTO_DIR . '/' . $photo->album_id . '/' . $photo->filename;

			$inputExifFile = new PelJpeg($filename);
			$exif = $inputExifFile->getExif();
			unset($inputExifFile);

			$image = Image::fromFile($filename);
			$image->rotate($angle, 0);
			$image->save($filename, 100, Image::JPEG);

			if ($exif) {
				$outputExifFile = new PelJpeg($filename);
				$outputExifFile->setExif($exif);
				$outputExifFile->saveFile($filename);
			}

			$photo->update(['thumb' => NULL]);
		}

		return $selected;
	}

	/**
	 * @allow(member)
	 */
	public function superFormTurnLeft() {
		$selected = $this->superFormImagesTurn(90);

		$slug = (string) $this->params['slug'];
		$this->flashMessage('Doleva bylo otočeno ' . count($selected) . ' fotografií');
		$this->redirect('edit', $slug);
	}

	/**
	 * @allow(member)
	 */
	public function superFormTurnRight() {
		$selected = $this->superFormImagesTurn(-90);

		$slug = (string) $this->params['slug'];
		$this->flashMessage('Doprava bylo otočeno ' . count($selected) . ' fotografií');
		$this->redirect('edit', $slug);
	}

	/**
	 * @allow(member)
	 */
	public function superFormVisible() {
		$selected = $this->getSuperFromSelected();

		$this->gallery->getPhotos()->where('id', $selected)->update(['visible' => new SqlLiteral('NOT(`visible`)')]);

		$slug = (string)$this->params['slug'];
		$this->flashMessage('Bylo změněna viditelnost ' . count($selected) . ' fotografií');
		$this->redirect('view', $slug);
	}
}