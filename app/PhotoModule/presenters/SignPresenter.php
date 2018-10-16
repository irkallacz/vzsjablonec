<?php

namespace App\PhotoModule\Presenters;

use App\Authenticator\SsoAuthenticator;
use App\Model\UserService;
use Nette\Application\BadRequestException;
use Nette\Application\IRouter;
use Nette\Database\UniqueConstraintViolationException;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Security\AuthenticationException;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
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
			$this->redirect('Album:default');
		}
		$code = $this->ssoAuthenticator->generateCode();
		$this->redirect(':Account:Sign:sso', ['code' => $code, 'redirect' => ':Photo:Sign:ssoLogIn', 'link' => $this->backlink]);
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
		if ($this->getUser()->isLoggedIn()) $this->redirect('Album:default');

		try {
			$this->ssoAuthenticator->login($userId, $code, $timestamp, $signature, UserService::MODULE_PHOTO);
		} catch (AuthenticationException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('default');
		} catch (UniqueConstraintViolationException $e){
			$this->flashMessage('Duplikátní příhlášení');
		}

		if ($this->backlink) $this->restoreRequest($this->backlink);
		else {
			$referer = $this->httpRequest->getReferer();
			if ($referer) {
				$httpRequest = new Request(new UrlScript($referer->getAbsoluteUrl()));
				$appRequest = $this->router->match($httpRequest);

				if (($appRequest) and (Strings::startsWith($appRequest->presenterName, 'Photo'))) {
					$code = ':' . $appRequest->presenterName;
					$param = $appRequest->parameters;
					if (array_key_exists('action', $param)) {
						$action = Arrays::pick($param, 'action');
						$code .= ':' . $action;
					}
					$this->redirect($code, $param);
				}

				$this->redirect('Album:default');
			}
		}
	}


	public function actionOut() {
		$this->getUser()->logout();
		$this->flashMessage('Byl jste odhlášen');
		$this->setView('default');
	}
}