<?php

namespace App\PhotoModule\Presenters;

use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Presenter;
use Nette\Database\IRow;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;
use Nette\Utils\Image;
use Nette\Utils\Strings;

/**
 * @property-read \Nette\Bridges\ApplicationLatte\Template|\stdClass $template
 */
abstract class BasePresenter extends Presenter {

	/** @var string */
	const PHOTO_DIR = 'albums';

	/** @var string */
	const THUMB_DIR = 'thumbs';

	/**
	 *
	 */
	protected function afterRender() {
		parent::afterRender();

		if (!$this->context->parameters['productionMode']) {
			$this->template->basePath .= '/photo/';
			$this->template->baseUrl .= '/photo';
		}
	}

	/**
	 *
	 */
	protected function beforeRender() {
		parent::beforeRender();

		$this->template->photoDir = self::PHOTO_DIR;

		$this->template->addFilter('thumb', function ($photo){
			if ($photo->thumb) {
				$thumb = $photo->thumb;
			}else {
				try {
					$thumb = $this->getThumbName($photo->filename, $photo->album_id);
					$this->galleryService->updatePhoto($photo->id, ['thumb' => $thumb]);
				} catch (\Exception $e) {
					return self::PHOTO_DIR  .'/'. $photo->album_id  .'/'. $photo->filename;
				}
			}
			return self::PHOTO_DIR . '/' . self::THUMB_DIR .'/' . $photo->album_id . '/' . $thumb;
		});

		$mainMenu = [
			['title' => 'novinky',		'link' => 'News:',					'current' => 'News:*',				'role' => NULL		],
			['title' => 'alba',			'link' => 'Album:',					'current' => 'Album:*',				'role' => NULL		],
			['title' => 'můj účet',		'link' => 'Myself:',				'current' => 'Myself:*',			'role' => 'member'	],
			['title' => 'rss',			'link' => ':Member:Feed:albums',	'current' => ':Member:Feed:albums',	'role' => 'user'	],
			['title' => 'member',		'link' => ':Member:News:',			'current' => ':Member:News:',		'role' => 'user'	],
			['title' => 'odhlášení',	'link' => 'Sign:out',				'current' => 'Sign:*',				'role' => 'user'	],
			['title' => 'přihlášení',	'link' => 'Sign:in',				'current' => 'Sign:*',				'role' => 'guest'	],
		];

		$this->template->mainMenu = ArrayHash::from($mainMenu);
	}

	/**
	 * @param string $filename
	 * @param int $album_id
	 * @return string
	 * @throws \Nette\Utils\UnknownImageFileException
	 */
	public function getThumbName(string $filename, int $album_id) {
		$image = Image::fromFile(WWW_DIR . '/' . self::PHOTO_DIR . 	'/' . $album_id . '/' . $filename);

		// zachovani pruhlednosti u PNG
		$image->alphaBlending(FALSE);
		$image->saveAlpha(TRUE);
		$image->resize(150, 100,Image::EXACT)
			->sharpen();

		$thumb = pathinfo($filename, PATHINFO_FILENAME);
		$thumb = Strings::webalize($thumb).'.jpg';

		//if (!file_exists(WWW_DIR . '/' .$thumbDir)) mkdir(WWW_DIR . '/' .$thumbDir);

		$image->save(WWW_DIR . '/' . self::PHOTO_DIR . 	'/' . self::THUMB_DIR .'/' . 	$album_id . '/' . $thumb, 80, Image::JPEG);

		return $thumb;
	}

	/**
	 * @param $element
	 * @throws ForbiddenRequestException
	 * @throws \Nette\Application\AbortException
	 */
	public function checkRequirements($element) {
		$this->getUser()->getStorage()->setNamespace('photo');

		if ($element->hasAnnotation('allow')) {
			if ($this->getUser()->isLoggedIn()) {
				$role = $element->getAnnotation('allow');
				if (!$this->getUser()->isInRole($role)) {
					throw new ForbiddenRequestException('Na tuto akci nemáte právo');
				}
			} else {
				if ($this->getUser()->logoutReason === \Nette\Security\IUserStorage::INACTIVITY) {
					$this->flashMessage('Byl jste odhlášen z důvodu dlouhé nečinosti. Přihlaste se prosím znovu.');
				}
				$backlink = $this->storeRequest();
				$this->redirect('Sign:in', ['backlink' => $backlink]);
			}
		}
		parent::checkRequirements($element);
	}
}
