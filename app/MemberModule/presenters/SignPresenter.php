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

	/**
	 * @throws \Nette\Application\AbortException
	 */
	private function checkLogin(){
		if ($this->getUser()->isLoggedIn()) {
			if ($this->backlink) $this->restoreRequest($this->backlink);
			$this->redirect('News:');
		}
	}

	/**
	 *
	 */
	public function beforeRender() {
		parent::beforeRender();
		$this->template->backlink = $this->backlink;
	}

	/**
	 * @param bool $logout
	 * @throws \Nette\Application\AbortException
	 */
	public function actionDefault($logout = FALSE){
		$this->checkLogin();
		$this->template->logout = $logout;
	}

	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function actionIn() {
		$this->checkLogin();
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
		$this->checkLogin();

		try {
			$this->ssoAuthenticator->login($userId, $code, $timestamp, $signature, UserService::MODULE_MEMBER);
		} catch (AuthenticationException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('default');
		} catch (UniqueConstraintViolationException $e){
			$this->flashMessage('Duplikátní příhlášení');
		}

		$this->checkLogin();
	}


	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function actionOut() {
		$this->getUser()->logout();
		$this->flashMessage('Byl jste odhlášen');
		$this->redirect('default', ['logout' => TRUE]);
	}

}