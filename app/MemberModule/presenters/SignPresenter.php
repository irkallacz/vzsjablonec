<?php

namespace App\MemberModule\Presenters;

use App\Authenticator\SsoAuthenticator;
use Nette\Application\BadRequestException;
use Nette\Security\AuthenticationException;
use App\Model\UserService;
use Tracy\Debugger;

class SignPresenter extends BasePresenter {

	/** @var UserService @inject */
	public $userService;

	/** @var SsoAuthenticator @inject */
	public $ssoAuthenticator;

	/** @persistent */
	public $backlink = '';


	public function beforeRender() {
		parent::beforeRender();

		$this->template->mainMenu = [];
	}

	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function actionIn() {
		if ($this->getUser()->isLoggedIn()) {
			if ($this->backlink) $this->restoreRequest($this->backlink);
			$this->redirect('News:');
		}

		$this->redirect(':Account:Sign:sso', ['redirect' => ':Member:Sign:ssoLogIn', 'link' => $this->backlink]);
	}

	/**
	 * @param string $code
	 * @param int $userId
	 * @param int $timestamp
	 * @param string $signature
	 * @throws BadRequestException
	 * @throws \Nette\Application\AbortException
	 */
	public function actionSsoLogIn(string $code, int $userId, int $timestamp, string $signature) {
		if ($this->getUser()->isLoggedIn()) $this->redirect('News:');

		try {
			$this->ssoAuthenticator->login($userId, $code, $timestamp, $signature, UserService::MODULE_MEMBER);
		} catch (AuthenticationException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('default');
		}

		if ($this->backlink) $this->restoreRequest($this->backlink);
		else $this->redirect('News:default');
	}


	public function actionOut() {
		$this->getUser()->logout();
		$this->flashMessage('Byl jste odhlášen');
		$this->setView('default');
	}

}