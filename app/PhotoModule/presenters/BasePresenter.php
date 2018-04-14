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
	const photoDir = 'albums';

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

		$this->template->photoDir = self::photoDir;
		$this->template->addFilter('thumb', [$this, 'getThumbName']);

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
	 * @param IRow|ActiveRow $photo
	 * @return string
	 */
	public function getThumbName($photo){
		$thumbDir = self::photoDir . '/thumbs/' . $photo->album_id . '/';
		$fileDir = self::photoDir . '/' . $photo->album_id . '/';

		if ($photo->thumb) {
			$filename = $photo->thumb;
		}else {
			try {
				$image = Image::fromFile(WWW_DIR . '/' . $fileDir . $photo->filename);

				// zachovani pruhlednosti u PNG
				$image->alphaBlending(FALSE);
				$image->saveAlpha(TRUE);
				$image->resize(150, 100,Image::EXACT)
					->sharpen();

				$filename = pathinfo($photo->filename, PATHINFO_FILENAME);
				$filename = Strings::webalize($filename).'.jpg';

				if (!file_exists(WWW_DIR . '/' .$thumbDir)) mkdir(WWW_DIR . '/' .$thumbDir);

				$image->save(WWW_DIR . '/' . $thumbDir . $filename, 80, Image::JPEG);
				$photo->update(['thumb' => $filename]);

			} catch (\Exception $e) {
				return $fileDir . $photo->filename;
			}
		}

		return $thumbDir . $filename;
	}

	/**
	 * @param string $slug
	 * @return int
	 */
	public static function getIdFromSlug(string $slug) {
		return (int) substr($slug, 0, strstr($slug, '-') - 1);
	}

	/**
	 * @param $element
	 * @throws ForbiddenRequestException
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
