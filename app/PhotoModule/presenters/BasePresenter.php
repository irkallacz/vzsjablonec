<?php

namespace App\PhotoModule\Presenters;

use App\PhotoModule\ImageService;
use App\Template\TemplateProperty;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Presenter;
use Nette\Database\IRow;
use Nette\Utils\ArrayHash;

/**
 * @property-read TemplateProperty|\Nette\Bridges\ApplicationLatte\Template $template
 */
abstract class BasePresenter extends Presenter {

	/** @var ImageService @inject */
	public $imageService;

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

		$this->template->addFilter('thumb', function (IRow $photo){
			if ($photo->thumb) {
				$thumb = $photo->thumb;
			}else {
				try {
					$image = $this->imageService->createImageFromPhoto($photo);
					$thumb = $image->generateThumbnail();
					$image->clear();

					$this->galleryService->updatePhoto($photo->id, ['thumb' => $thumb]);
				} catch (\Exception $e) {
					return $this->imageService->getPath($photo->album_id) . '/' . $photo->filename;
				}
			}
			return $this->imageService->getThumbPath($photo->album_id) . '/' . $thumb;
		});

		$this->template->addFilter('image', function (iRow $photo){
			return $this->imageService->getPathFromPhoto($photo);
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
