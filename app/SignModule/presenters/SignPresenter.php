<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 04.12.2017
 * Time: 12:41
 */

namespace App\SignModule\Presenters;

use App\Model\MessageService;
use App\Model\UserService;
use App\SignModule\StateCryptor;
use App\Template\LatteFilters;
use Nette\Application\BadRequestException;
use Nette\Http\Request;
use Nette\Security\AuthenticationException;
use Nette\Security\IUserStorage;
use Nette\Security\Passwords;
use Nette\Utils\DateTime;
use Nette\Utils\Random;
use Nette\Utils\Strings;
use Nette\Utils\Arrays;
use App\Authenticator\CredentialsAuthenticator;
use App\Authenticator\EmailAuthenticator;
use App\Authenticator\SsoAuthenticator;
use Nette\Application\UI\Form;
use Nette\InvalidArgumentException;
use Vencax\FacebookLogin;
use Vencax\GoogleLogin;
use Tracy\Debugger;


class SignPresenter extends BasePresenter {

	const REDIRECTS = [':Member:Sign:ssoLogIn', ':Photo:Sign:ssoLogIn'];

	/** @var Request @inject */
	public $httpRequest;

	/** @var UserService @inject */
	public $userService;

	/** @var MessageService @inject */
	public $messageService;

	/** @var GoogleLogin @inject */
	public $googleLogin;

	/** @var FacebookLogin @inject */
	public $facebookLogin;

	/** @var CredentialsAuthenticator @inject */
	public $credentialsAuthenticator;

	/** @var EmailAuthenticator @inject */
	public $emailAuthenticator;

	/** @var SsoAuthenticator @inject */
	public $ssoAuthenticator;

	/** @var StateCryptor @inject */
	public $stateCryptor;

	/** @persistent */
	public $backlink = '';


	public function renderIn() {
		if ($this->backlink) {
			$googleState = $this->stateCryptor->encryptState($this->backlink, 'google');
			$facebookState = $this->stateCryptor->encryptState($this->backlink, 'facebook');
			$this->googleLogin->setState($googleState);
			$this->facebookLogin->setState($facebookState);
		}

		$this->template->googleLoginUrl = $this->googleLogin->getLoginUrl();
		$this->template->googleLastLogin = $this->googleLogin->isThisServiceLastLogin();

		$this->template->facebookLoginUrl = $this->facebookLogin->getLoginUrl();
		$this->template->facebookLastLogin = $this->facebookLogin->isThisServiceLastLogin();
	}


	public function actionGoogleLogin(string $code, string $state = NULL) {
		try {
			if ($state) $this->backlink = $this->stateCryptor->decryptState($state, 'google');
			$me = $this->googleLogin->getMe($code);
			$this->emailAuthenticator->login($me->email);
			$this->afterLogin(UserService::LOGIN_METHOD_GOOGLE);
		} catch (AuthenticationException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('in');
		}
	}

	public function actionFacebookLogin(string $state = NULL) {
		try {
			if ($state) $this->backlink = $this->stateCryptor->decryptState($state, 'facebook');
			$me = $this->facebookLogin->getMe([FacebookLogin::ID, FacebookLogin::EMAIL]);
			$email = Arrays::get($me, 'email');
			$this->emailAuthenticator->login($email);
			$this->afterLogin(UserService::LOGIN_METHOD_FACEBOOK);
		} catch (InvalidArgumentException $e) {
			$this->flashMessage('Pravděpodobně jste aplikaci VZS JBC na Facebooku odebrali právo přistupovat k vašemu emailu. Odeberte aplikaci a znovu se pokuste přihlásit.', 'error');
			$this->redirect('in');
		} catch (AuthenticationException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('in');
		}
	}

	/**
	 * Sign in form component factory.
	 * @return Form
	 */
	protected function createComponentSignInForm() {
		$form = new Form;
		$form->addText('mail', 'Email:', 30)
			->setAttribute('autofocus')
			->setRequired('Vyplňte váš email')
			->setType('email')
			->addRule(FORM::EMAIL, 'Vyplňte správnou e-mailovou adresu');

		$form->addPassword('password', 'Heslo:', 30)
			->setRequired('Vyplňte heslo');

		$form->addSubmit('send', 'Přihlásit');
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->onSuccess[] = [$this, 'signInFormSubmitted'];
		return $form;
	}

	/**
	 * @param Form $form
	 */
	public function signInFormSubmitted(Form $form) {
		try {
			$values = $form->getValues();
			$this->credentialsAuthenticator->login($values->mail, $values->password);
			$this->afterLogin(UserService::LOGIN_METHOD_PASSWORD);

		} catch (AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}

	private function afterLogin(int $loginMethod = UserService::LOGIN_METHOD_PASSWORD) {
		$userId = $this->getUser()->getId();
		$this->getUser()->setExpiration('6 hours', IUserStorage::CLEAR_IDENTITY, TRUE);
		$this->getUser()->getIdentity()->login_method_id = $loginMethod;

		$this->userService->addUserLogin($userId, $loginMethod);

		if ($this->backlink) $this->restoreRequest($this->backlink);
		else $this->redirect('Sign:default');
	}

	/**
	 * @return Form
	 */
	protected function createComponentForgotPassForm() {
		$form = new Form;
		$form->addText('mail', 'Email:', 30)
			->setRequired('Vyplňte váš email')
			->setType('email')
			->addRule(FORM::EMAIL, 'Vyplňte správnou e-mailovou adresu');

		$form->addSubmit('send', 'Odeslat');
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->onSuccess[] = [$this, 'forgotPassFormSubmitted'];

		$renderer = $form->getRenderer();
		$renderer->wrappers['controls']['container'] = NULL;
		$renderer->wrappers['pair']['container'] = NULL;
		$renderer->wrappers['label']['container'] = NULL;
		$renderer->wrappers['control']['container'] = NULL;

		return $form;
	}

	/**
	 * @param Form $form
	 */
	public function forgotPassFormSubmitted(Form $form) {
		$values = $form->getValues();
		$values->mail = Strings::lower($values->mail);

		$member = $this->userService->getUserByEmail($values->mail);

		if ($member) {
			if (!$this->userService->haveActivePasswordSession($member->id)) {
				$next = $this->messageService->getNextSendTime();
				$minutes = intval($next->diff(new DateTime())->i) + 40;
				$session = $this->userService->addPasswordSession($member->id, "$minutes MINUTE");
				$this->backlink = '';
				$this->addRestoreMail($member, $session);

				$this->flashMessage('Na Váši e-mailovou adresu bude '.LatteFilters::timeAgoInWords($next).' odeslán odkaz pro změnu hesla');
				$this->redirect('in');

			} $form->addError('Na tomto účtu je již aktivní jiná obnova hesla');
		} else $form->addError('E-mail nenalezen');
	}

	/**
	 * @param $pubkey
	 * @throws BadRequestException
	 */
	public function renderRestorePassword(string $pubkey) {
		$session = $this->userService->getPasswordSession($pubkey);

		if (!$session) {
			throw new BadRequestException('Neplatný identifikator session');
		}

		$member = $this->userService->getUserById($session->member_id);

		if ((!$member) or (is_null($member->role))) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		$date_end = $session->date_end;
		$this->template->date_end = $date_end;
		$this->template->time_remain = date_create()->diff($date_end);

		$this['restorePasswordForm']->setDefaults($member);
	}

	/**
	 * @return Form
	 */
	protected function createComponentRestorePasswordForm() {
		$form = new Form;

		$form->addPassword('password', 'Nové heslo:', 20)
			->addCondition(Form::FILLED)
			->addRule(Form::PATTERN, 'Heslo musí mít alespoň 8 znaků, musí obsahovat číslice, malá a velká písmena', '^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,15}$');

		$form->addPassword('confirm', 'Potvrzení hesla:', 20)
			->setRequired(TRUE)
			->addRule(Form::EQUAL, 'Zadaná hesla se neschodují', $form['password'])
			->addCondition(Form::FILLED)
			->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaků', 8);

		$form->addSubmit('ok', 'Uložit');

		$form->addProtection('Vypšela ochrana formuláře');

		$form->onSuccess[] = [$this, 'restorePasswordFormSubmitted'];
		return $form;
	}

	/**
	 * @param Form $form
	 * @throws BadRequestException
	 */
	public function restorePasswordFormSubmitted(Form $form) {
		$values = $form->getValues();
		$pubkey = $this->getParameter('pubkey');

		if (!$pubkey) {
			throw new BadRequestException('Neplatný identifikator session');
		} else {
			$session = $this->userService->getPasswordSession($pubkey);
			if (!$session) {
				throw new BadRequestException('Neplatný identifikator session');
			} else {
				$member = $this->userService->getUserById($session->member_id);
				if ((!$member) or (is_null($member->role))) {
					throw new BadRequestException('Uživatel nenalezen');
				} else {
					$hash = Passwords::hash($values->password);
					$member->update(['hash' => $hash]);
					$session->delete();
					$this->flashMessage('Vaše heslo bylo změněno');
				}
			}
		}

		$this->redirect('in');
	}

	public function actionOut() {
		$this->getUser()->logout();
		$this->flashMessage('Byl jste odhlášen');
		$this->redirect('in');
	}

	public function actionSso(string $redirect, string $link = '') {
		if (!in_array($redirect, self::REDIRECTS))
			throw new BadRequestException('Redirect nemá povolenou hodnotu');

//		if (($this->httpRequest->getReferer())and($this->httpRequest->getReferer()->host != $this->httpRequest->url->host))
//			throw new BadRequestException('Nesouhlasí doména původu');

		if ($this->getUser()->isLoggedIn()) {
			$code = $this->generateCode();
			$timestamp = time();
			$signature = $this->ssoAuthenticator->getSignature($this->user->getIdentity(), $code, $timestamp);

			$this->redirect($redirect, ['code' => $code, 'userId' => $this->user->id, 'timestamp' => $timestamp, 'signature' => $signature, 'backlink' => $link]);
		} else {
			$this->backlink = $this->storeRequest();
			$this->redirect('in');
		}
	}

	private function generateCode(){
		return Random::generate();
	}
}