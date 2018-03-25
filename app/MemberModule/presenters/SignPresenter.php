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

	/** @var IRouter @inject */
	public $router;

	/** @var UserService @inject */
	public $userService;

	/** @var SsoAuthenticator @inject */
	public $ssoAuthenticator;

	/** @persistent */
	public $backlink = '';


	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function actionIn() {
		if ($this->getUser()->isLoggedIn()) {
			if ($this->backlink) $this->restoreRequest($this->backlink);
			$this->redirect('News:');
		}

		$this->redirect(':Sign:Sign:sso', ['redirect' => ':Member:Sign:ssoLogIn', 'link' => $this->backlink]);
	}

	/**
	 * @param string $code
	 * @param int $userId
	 * @param int $timestamp
	 * @param string $signature
	 * @throws BadRequestException
	 * @throws \Nette\Application\AbortException
	 */
	public function actionSSoLogIn(string $code, int $userId, int $timestamp, string $signature) {
		$referer = $this->httpRequest->getReferer();

		if ($referer) {
			if ($referer->host != $this->httpRequest->url->host)
				throw new BadRequestException('Nesouhlasí doména původu');

			$httpRequest = new Request(new UrlScript($referer->getAbsoluteUrl()));
			$appRequest = $this->router->match($httpRequest);

			if (($appRequest)and($appRequest->getPresenterName() !== ':Sign:Sign'))
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
		$this->redirect('default');
	}

}