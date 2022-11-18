<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 22.11.2016
 * Time: 23:09
 */

namespace App\MemberModule\Presenters;

use Nette\Application\AbortException;
use Nette\Application\ForbiddenRequestException;
use Nette\Security\IUserStorage;
use Tracy\Debugger;

abstract class LayerPresenter extends BasePresenter {
	/**
	 *
	 * @throws AbortException
	 */
	protected function startup() {
		parent::startup();

		$this->checkLogin();
	}

	/**
	 * @throws AbortException
	 */
	protected function checkLogin() {
		if (!$this->user->isLoggedIn()) {
			if ($this->user->getLogoutReason() === IUserStorage::INACTIVITY) {
				$this->flashMessage('Byl jste odhlášen z důvodu neaktivity. Přihlaste se prosím znovu.');
			}
			$backlink = $this->storeRequest();
			$this->redirect('Sign:in', ['backlink' => $backlink]);
		}
	}

	/**
	 * @param $element
	 * @throws ForbiddenRequestException
	 * @throws AbortException
	 */
	public function checkRequirements($element) {
		parent::checkRequirements($element);

		$this->checkLogin();

		if ($element->hasAnnotation('allow')) {
			$role = $element->getAnnotation('allow');
			if (!$this->getUser()->isInRole($role)) {
				throw new ForbiddenRequestException('Na tuto akci nemáte právo');
			}
		}
	}

}
