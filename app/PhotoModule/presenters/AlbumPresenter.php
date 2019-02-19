<?php

namespace App\PhotoModule\Presenters;

use App\Model\GalleryService;
use App\Model\UserService;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Forms\Controls\BaseControl;
use Nette\InvalidArgumentException;
use Nette\Utils\Arrays;
use Nette\Utils\DateTime;
use Nette\Database\SqlLiteral;
use Echo511\Plupload;
use Echo511\Plupload\Entity\UploadQueue;
use Tracy\Debugger;

class AlbumPresenter extends BasePresenter {

	/** @var GalleryService @inject */
	public $galleryService;

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
	public function getAlbumBySlug(string $slug) {
		$album = $this->galleryService->getAlbumBySlug($slug);

		if ((!$album)) {
			throw new BadRequestException('Zadané album neexistuje');
		}

		return $album;
	}
	/**
	 *
	 */
	public function renderDefault() {
		$albums = $this->galleryService->getAlbums()->order('date DESC');

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
	 * @throws BadRequestException
	 * @throws AbortException
	 */
	public function renderView(string $slug, string $pubkey = NULL) {
		$album = $this->getAlbumBySlug($slug);
		$this->template->album = $album;

		$pubkeyCheck = ($pubkey === $album->pubkey);

		if ((!$album->visible) and (!(($this->getUser()->isLoggedIn()) or ($pubkeyCheck)))) {
			$backlink = $this->storeRequest();
			$this->redirect('Sign:in', ['backlink' => $backlink]);
		}

		$photos = $this->galleryService->getPhotosByAlbumId($album->id)->order('order, date_add');

		if (!(($this->getUser()->isLoggedIn()) or ($pubkeyCheck))) $photos->where('visible', TRUE);

		$this->template->photos = $photos;
		$this->template->slug = $slug;
	}

	/**
	 * @param string $slug
	 * @param string $order
	 * @allow(member)
	 * @throws ForbiddenRequestException
	 * @throws BadRequestException
	 */
	public function renderEdit(string $slug, string $order = 'order') {
		$album = $this->getAlbumBySlug($slug);
		$this->template->album = $album;

		if (!(($album->user_id == $this->getUser()->getId()) or ($this->getUser()->isInRole('admin')))) {
			throw new ForbiddenRequestException('Nemáte právo toho album upravovat');
		}

		$this->template->order = $order;

		if ($order == 'order') $order = $order . ', date_add';

		$photos = $this->galleryService->getPhotosByAlbumId($album->id)->order($order);

		$this->template->photos = $photos;
		$this->template->albumDir = $this->imageService->getPath($album->id);
		$this->template->slug = $slug;

		/** @var Form $form*/
		$form = $this['photoForm'];
		if (!$form->isSubmitted()) {
			$album = $album->toArray();
			$member = Arrays::pick($album,'user_id');

			try{
				$form['user_id']->setDefaultValue($member);
			}catch (InvalidArgumentException $e){
				$this->flashMessage('Některé již neplatné hodnoty byly vynechány', 'error');
			}

			$form->setDefaults($album);
		}
	}

	/**
	 * @param string $slug
	 * @allow(user)
	 * @throws BadRequestException
	 */
	public function renderAdd(string $slug) {
		$album = $this->getAlbumBySlug($slug);
		$this->template->album = $album;
		$this->template->slug = $slug;
	}

	/**
	 * @param string $slug
	 * @param bool|NULL $visible
	 * @allow(admin)
	 * @throws AbortException
	 */
	public function actionSetAlbumVisibility(string $slug, bool $visible = FALSE) {
		$album = $this->galleryService->getAlbumBySlug($slug);

		$album->update(['visible' => $visible]);

		$text = $visible ? null : 'ne';
		$this->flashMessage('Album bylo označeno jako ' . $text . 'viditelné pro veřenost');

		$this->redirect('Album:view', $slug);
	}

	/**
	 * @param int $id
	 * @allow(member)
	 * @throws ForbiddenRequestException
	 * @throws AbortException
	 */
	public function actionDeleteAlbum(int $id) {
		$album = $this->galleryService->getAlbumById($id);

		if (!(($album->user_id == $this->getUser()->getId()) or ($this->getUser()->isInRole('admin')))) {
			throw new ForbiddenRequestException('Nemáte právo toto album smazat');
		}

		$this->galleryService->getPhotosByAlbumId($album->id)->delete();
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

		$plupload->maxChunkSize = '1mb';
		$plupload->allowedExtensions = 'jpg,jpeg,gif,png';

		$slug = (string) $this->getParameter('slug');
		$album = $this->galleryService->getAlbumBySlug($slug);
		$albumId = $album->id;

		$plupload->onFileUploaded[] = function (UploadQueue $uploadQueue) use ($albumId) {
			$upload = $uploadQueue->getLastUpload();
			$image = $this->imageService->createImageFromUpload($albumId, $upload);

			$datetime = $image->getExifDateTime();

			$resize = $image->adaptiveResize();
			$rotation = $image->fixOrientation();

			if (($resize)or($rotation)) $image->save();

			$thumbname = $image->generateThumbnail();
			$filename = $image->getFilename();
			$image->clear();

			$order = $this->galleryService->getPhotosCount($albumId);

			$values = [
				'filename' => $filename,
				'album_id' => $albumId,
				'date_add' => new DateTime,
				'user_id' => $this->user->id,
				'thumb' => $thumbname,
				'order' => $order,
				'date_taken' => $datetime
			];

			$photo = $this->galleryService->addPhoto($values);
		};

		$plupload->onUploadComplete[] = function (UploadQueue $uploadQueue) {
			$this->flashMessage('Fotografie byli v pořádku přidány');
			$this->redrawControl('flash');
		};

		return $plupload;
	}

	/**
	 * @return Form
	 * @allow(member)
	 */
	protected function createComponentPhotoForm() {
		$slug = $this->getParameter('slug');
		$album = $this->galleryService->getAlbumBySlug($slug);

		$form = new Form;

		$form->addText('name', 'Název', 50, 50)
			->setRequired('Vyplňte %label');

		$form->addText('slug', 'Slug', 50, 50)
			->setRequired('Vyplňte %label')
			->addRule(function (BaseControl $item) use ($album){
				$new = $this->galleryService->getAlbumBySlug($item->getValue());
				return !(($new)and($new->id != $album->id));
			}, 'Url Alba musí být unikátní');

		/** @var \DateInput $dateInput*/
		$dateInput = $form['date'] = new \DateInput('Datum');
		$dateInput->setRequired('Vyplňte datum začátku akce')
			->setDefaultValue(new DateTime());

		$form->addSelect('user_id', 'Uživatel', $this->userService->getUsersArray($this->user->isInRole('admin') ? UserService::DELETED_LEVEL : UserService::MEMBER_LEVEL));

		$form->addCheckBox('show_date', 'Upravit datum pořízení')
			->setDefaultValue(FALSE)
			->setHtmlId('swap-title');

		$form->addTextArea('text', 'Popis', 30)
			->setAttribute('placeholder','Popis alba')
			->setNullable();

		$form->addTextArea('private', 'Jen pro členy', 30)
			->setAttribute('placeholder', 'Text viditelný jen pro přihlášené')
			->setNullable();

		$form->addMultiplier('photos', function (\Nette\Forms\Container $photo) {
			$photo->addText('text', 'Popis', 30, 50)
				->setNullable();
			$photo->addHidden('id');
			$photo->addCheckBox('selected')
				->setAttribute('class', 'select')
				->setDefaultValue(FALSE);
		}, 0);

		$form->addSubmit('save', 'uložit změny')
			->onClick[] = [$this, 'photoFormSave'];

		$form->addSubmit('delete', 'vymazat vybrané')
			->onClick[] = [$this, 'photoFormDelete'];

		$form->addSubmit('visible', 'změnit viditelnost')
			->onClick[] = [$this, 'photoFormVisible'];

		$form->addSubmit('thumbnail', 'nové miniatury')
			->onClick[] = [$this, 'generateThumbnail'];

		$form->addSubmit('turnLeft', 'otočit o 90° doleva')
			->onClick[] = [$this, 'photoFormTurnLeft'];

		$form->addSubmit('turnRight', 'otočit o 90° doprava')
			->onClick[] = [$this, 'photoFormTurnRight'];

		$photos = $this->galleryService->getPhotosByAlbumId($album->id)->fetchPairs('id');
		$form->setDefaults(['photos' => $photos]);

		return $form;
	}

	/**
	 * @allow(member)
	 * @throws AbortException
	 */
	public function photoFormSave() {
		/** @var Form $form*/
		$form = $this['photoForm'];
		$values = $form->getValues();
		$values->date_update = new Datetime();

		$show_date = $values->show_date;
		unset($values->show_date);

		$images = $values->photos;
		unset($values->photos);

		$slug = $this->getParameter('slug');
		$album = $this->galleryService->getAlbumBySlug($slug);
		$album->update($values);

		$photos = $this->galleryService->getPhotosByAlbumId($album->id)->fetchPairs('id');

		foreach ($images as $order => $photo) {
			$update = [];
			$order = (int) $order;

			if ($photos[$photo->id]['order'] != $order) $update['order'] = $order;

			if ($show_date) {
				$datetime = $photo->text ? date_create($photo->text) : NULL;
				if ($datetime == FALSE) $datetime = NULL;
				if ($photos[$photo->id]['date_taken'] != $datetime) $update['date_taken'] = $datetime;
			} else {
				if ($photos[$photo->id]['text'] != $photo->text) $update['text'] = $photo->text;
			}

			if (!empty($update)) $this->galleryService->updatePhoto($photo->id, $update);
		}

		$this->flashMessage('Album bylo upraveno');
		$this->redirect('view', $album->slug);
	}

	/**
	 * @return array
	 * @allow(member)
	 * @throws AbortException
	 */
	private function getPhotoFromSelectedPhotos() {
		$photos = $values = $this['photoForm']->getValues()->photos;

		if (!$photos) {
			$this->flashMessage('Musíte vybrat nějaké fotografie', 'error');
			$this->redirect('this');
		}

		$selected = [];
		foreach ($photos as $order => $photo) {
			if ($photo->selected) $selected[] = $photo->id;
		}

		if (empty($selected)) {
			$this->flashMessage('Musíte vybrat nějaké fotografie', 'error');
			$this->redirect('this');
		}

		return $selected;
	}

	/**
	 * @allow(member)
	 * @throws \ImagickException
	 * @throws AbortException
	 */
	public function generateThumbnail() {
		$selected = $this->getPhotoFromSelectedPhotos();
		$photos = $this->galleryService->getPhotos()->where('id', $selected);

		foreach ($photos as $photo) {
			$image = $this->imageService->createImageFromPhoto($photo);
			$image->generateThumbnail();
			$image->clear();
		}

		$slug = $this->getParameter('slug');

		$this->flashMessage('Bylo vytvořeno ' . count($selected) . ' náhledů');
		$this->redirect('view', $slug);
	}

	/**
	 * @allow(member)
	 * @throws AbortException
	 */
	public function photoFormDelete() {
		$selected = $this->getPhotoFromSelectedPhotos();

		$this->galleryService->deletePhotos($selected);

		$slug = $this->getParameter('slug');
		$this->flashMessage('Bylo smazáno ' . count($selected) . ' fotografií');
		$this->redirect('view', $slug);
	}

	/**
	 * @param int $degree
	 * @return array
	 * @allow(member)
	 * @throws AbortException
	 */
	private function photoFormImagesTurn(int $degree) {
		$selected = $this->getPhotoFromSelectedPhotos();

		$photos = $this->galleryService->getPhotos()->where('id', $selected);

		foreach ($photos as $photo) {
			$image = $this->imageService->createImageFromPhoto($photo);
			$image->rotate($degree);
			$image->save();

			try {
				$thumb = $image->generateThumbnail();
				$this->galleryService->updatePhoto($photo->id, ['thumb' => $thumb]);
			} catch (\Exception $e){
				$this->galleryService->updatePhoto($photo->id, ['thumb' => NULL]);
			}

			$image->clear();
		}

		return $selected;
	}

	/**
	 * @allow(member)
	 * @throws AbortException
	 */
	public function photoFormTurnLeft() {
		$selected = $this->photoFormImagesTurn(-90);

		$this->flashMessage('Doleva bylo otočeno ' . count($selected) . ' fotografií');
		$this->redirect('this');
	}

	/**
	 * @allow(member)
	 * @throws AbortException
	 */
	public function photoFormTurnRight() {
		$selected = $this->photoFormImagesTurn( 90);

		$this->flashMessage('Doprava bylo otočeno ' . count($selected) . ' fotografií');
		$this->redirect('this');
	}

	/**
	 * @allow(member)
	 * @throws AbortException
	 */
	public function photoFormVisible() {
		$selected = $this->getPhotoFromSelectedPhotos();

		$this->galleryService->getPhotos()->where('id', $selected)->update(['visible' => new SqlLiteral('NOT(`visible`)')]);

		$slug = $this->getParameter('slug');
		$this->flashMessage('Byla změněna viditelnost ' . count($selected) . ' fotografií');
		$this->redirect('view', $slug);
	}
}