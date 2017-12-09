<?php

namespace App\MemberModule\Presenters;

use App\Authenticator\SsoAuthenticator;
use Nette\Application\BadRequestException;
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


	public function actionSignIn(){
		if ($this->getUser()->isLoggedIn()) {
			if ($this->backlink) $this->restoreRequest($this->backlink);
			$this->redirect('News:');
		}
		//TODO set $backlink to referer

		$this->redirect(':Sign:Sign:sso', ['redirect' => ':Member:Sign:ssoLogin', 'link' => $this->backlink]);
	}

	public function actionSSsoLogin($code, $userId, $signature){
		if ($this->httpRequest->getReferer()->host != $this->httpRequest->url->host)
			throw new BadRequestException('Nesouhlasí doména původu');

		$router = $this->context->getService('rourer');
		$request = new Request(new UrlScript($this->httpRequest->getReferer()->getAbsoluteUrl()));
		$request = $router->match($request);

		if($request->getPresenterName() !== ':Sign:Sing'){
			throw new BadRequestException('Nesouhlasí místo původu');
		}

		try{
			$this->ssoAuthenticator->login($userId, $code, $signature);
		}
		catch (AuthenticationException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('default');
		}

		$this->getUser()->setExpiration('6 hours', IUserStorage::CLEAR_IDENTITY, TRUE);

		$this->getUser()->getIdentity()->date_last = $this->userService->getLastLoginByUserId($userId);
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