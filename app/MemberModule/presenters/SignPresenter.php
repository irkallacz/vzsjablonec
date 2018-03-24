<?php

namespace App\MemberModule\Presenters;

use App\Authenticator\SsoAuthenticator;
use Nette\Application\BadRequestException;
use Nette\Application\IRouter;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Security\AuthenticationException;
use Nette\Security\IUserStorage;
use Nette\Utils\DateTime;
use App\Model\UserService;
use Tracy\Debugger;

class SignPresenter extends BasePresenter {

	/** @var Request @inject */
	public $httpRequest;

	/** @var UserService @inject */
	public $userService;

	/** @var SsoAuthenticator @inject */
	public $ssoAuthenticator;

	/** @persistent */
	public $backlink = '';


	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function actionSignIn() {
		if ($this->getUser()->isLoggedIn()) {
			if ($this->backlink) $this->restoreRequest($this->backlink);
			$this->redirect('News:');
		}
		//TODO set $backlink to referer

		$this->redirect(':Sign:Sign:sso', ['redirect' => ':Member:Sign:ssoLogin', 'link' => $this->backlink]);
	}

	/**
	 * @param string $code
	 * @param int $userId
	 * @param int $timestamp
	 * @param string $signature
	 * @throws BadRequestException
	 * @throws \Nette\Application\AbortException
	 */
	public function actionSSsoLogin(string $code, int $userId, int $timestamp, string $signature) {
		if ($this->httpRequest->getReferer()->host != $this->httpRequest->url->host)
			throw new BadRequestException('Nesouhlasí doména původu');

		/** @var IRouter $router */
		$router = $this->context->getService('router');
		$request = new Request(new UrlScript($this->httpRequest->getReferer()->getAbsoluteUrl()));
		$request = $router->match($request);

		if ($request->getPresenterName() !== ':Sign:Sing') {
			throw new BadRequestException('Nesouhlasí místo původu');
		}

		try {
			$this->ssoAuthenticator->login($userId, $code, $timestamp, $signature);
		} catch (AuthenticationException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('default');
		}

		$this->getUser()->setExpiration('6 hours', IUserStorage::CLEAR_IDENTITY, TRUE);

		$dateLast = $this->userService->getLastLoginByUserId($userId);
		$this->getUser()->getIdentity()->date_last = $dateLast ? $dateLast : new DateTime();
		$this->userService->addMemberLogin($userId);

		if ($this->backlink) $this->restoreRequest($this->backlink);
		else $this->redirect('News:default');
	}


	public function actionOut() {
		$this->getUser()->logout();
		$this->flashMessage('Byl jste odhlášen');
		$this->redirect('in');
	}
}