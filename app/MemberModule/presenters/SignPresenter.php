<?php

namespace App\MemberModule\Presenters;

use Nette\Database\UniqueConstraintViolationException;
use Nette\Security\AuthenticationException;
use App\Authenticator\SsoAuthenticator;
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

	public function actionDefault() {
		if (($this->getUser()->isLoggedIn())and($this->backlink)) $this->restoreRequest($this->backlink);
	}

	public function renderDefault() {
		$this->template->backlink = $this->backlink;
	}

	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function actionIn() {
		if ($this->getUser()->isLoggedIn()) {
			if ($this->backlink) $this->restoreRequest($this->backlink);
			$this->redirect('News:');
		}

		$code = $this->ssoAuthenticator->generateCode();
		$this->redirect(':Account:Sign:sso', ['code' => $code, 'redirect' => ':Member:Sign:ssoLogIn', 'link' => $this->backlink]);
	}

	/**
	 * @param string $code
	 * @param int $userId
	 * @param int $timestamp
	 * @param string $signature
	 * @throws \Nette\Application\AbortException
	 */
	public function actionSsoLogIn(string $code, int $userId, int $timestamp, string $signature) {
		if ($this->getUser()->isLoggedIn()) $this->redirect('News:');

		try {
			$this->ssoAuthenticator->login($userId, $code, $timestamp, $signature, UserService::MODULE_MEMBER);
		} catch (AuthenticationException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('default');
		} catch (UniqueConstraintViolationException $e){
			$this->flashMessage('Duplikátní příhlášení');
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