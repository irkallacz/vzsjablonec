<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 04.12.2017
 * Time: 12:41
 */

namespace App\AccountModule\Presenters;

use App\AccountModule\StateCryptor;
use App\Model\MessageService;
use App\Model\UserService;
use App\Template\LatteFilters;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Database\IRow;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Security\AuthenticationException;
use Nette\Security\IUserStorage;
use Nette\Security\Passwords;
use Nette\Utils\DateTime;
use Nette\Utils\JsonException;
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


	/**
	 * @throws AbortException
	 */
	public function actionIn() {
		if ($this->getUser()->isLoggedIn()) {
			if ($this->backlink) $this->restoreRequest($this->backlink);
			$this->redirect('default');
		}
	}

	public function actionDefault() {
		if (($this->getUser()->isLoggedIn())and($this->backlink)) {
			$this->restoreRequest($this->backlink);
		}
	}

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


	/**
	 * @param string $code
	 * @param string|NULL $state
	 * @throws AbortException
	 */
	public function actionGoogleLogin(string $code, string $state = NULL) {
		if ($state) $this->backlink = $this->stateCryptor->decryptState($state, 'google');

		try {
			$me = $this->googleLogin->getMe($code);
		} catch (\Exception $e) {
			$this->flashMessage('Přihlášení ne nezdařilo', 'error');
			$this->redirect('in');
		}

		try {
			$this->emailAuthenticator->login($me->email);
			$this->afterLogin(UserService::LOGIN_METHOD_GOOGLE);
		} catch (AuthenticationException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('in');
		}

	}

	/**
	 * @param string|NULL $state
	 * @throws AbortException
	 */
	public function actionFacebookLogin(string $state = NULL) {
		if ($state) $this->backlink = $this->stateCryptor->decryptState($state, 'facebook');

		try {
			$me = $this->facebookLogin->getMe([FacebookLogin::ID, FacebookLogin::EMAIL]);
		} catch (\Exception $e) {
			$this->flashMessage('Přihlášení ne nezdařilo', 'error');
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('in');
		}

		try {
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
		$values = $form->getValues();

		try {
			$this->credentialsAuthenticator->login($values->mail, $values->password);
			$this->afterLogin(UserService::LOGIN_METHOD_PASSWORD);
		} catch (AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}

	/**
	 * @param int $loginMethod
	 * @throws AbortException
	 */
	private function afterLogin(int $loginMethod = UserService::LOGIN_METHOD_PASSWORD) {
		$userId = $this->getUser()->getId();
		$this->getUser()->setExpiration('6 hours', IUserStorage::CLEAR_IDENTITY, TRUE);
		$this->getUser()->getIdentity()->login_method_id = $loginMethod;

		$this->userService->addUserLogin($userId, $loginMethod);

		if ($this->backlink) $this->restoreRequest($this->backlink);
		$this->redirect('Sign:default');
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
	 * @param IRow|ActiveRow $user
	 * @param IRow|ActiveRow $session
	 */
	public function addRestoreMail(IRow $user, IRow $session) {
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../../presenters/templates/Mail/restorePassword.latte');
		$template->session = $session;

		$message = new MessageService\Message();
		$message->setType(MessageService\Message::PASSWORD_RESET_TYPE);
		$message->setSubject('Obnova hesla');
		$message->setText($template);
		$message->setAuthor($user->id);
		$message->addRecipient($user->id);
		$message->setParameters(['user_id' => $user->id,'session_id' => $session->id]);

		$this->messageService->addMessage($message);

	}


	/**
	 * @param string $pubkey
	 * @throws BadRequestException
	 */
	public function renderRestorePassword(string $pubkey) {
		$session = $this->userService->getPasswordSession($pubkey);

		if (!$session) {
			throw new BadRequestException('Neplatný identifikator session');
		}

		$member = $this->userService->getUserById($session->user_id);

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
			->setAttribute('autofocus')
			->setRequired('Vyplňte prosím heslo')
			->addCondition(Form::FILLED)
			->addRule(Form::PATTERN, 'Heslo musí mít alespoň 8 znaků, musí obsahovat číslice, malá a velká písmena', '^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,15}$');

		$form->addPassword('confirm', 'Potvrzení hesla:', 20)
			->setRequired('Vyplňte prosím změnu hesla')
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
				$member = $this->userService->getUserById($session->user_id);
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

	/**
	 * @param string $code
	 * @param string $redirect
	 * @param string $link
	 * @throws BadRequestException
	 * @throws AbortException
	 * @throws JsonException
	 */
	public function actionSso(string $code, string $redirect, string $link = '') {
		if (!in_array($redirect, self::REDIRECTS))
			throw new BadRequestException('Redirect nemá povolenou hodnotu');

//		if (($this->httpRequest->getReferer())and($this->httpRequest->getReferer()->host != $this->httpRequest->url->host))
//			throw new BadRequestException('Nesouhlasí doména původu');

		if ($this->getUser()->isLoggedIn()) {
			$timestamp = time();
			$signature = $this->ssoAuthenticator->getSignature($this->user->getIdentity(), $code, $timestamp);

			$this->redirect($redirect, ['code' => $code, 'userId' => $this->user->id, 'timestamp' => $timestamp, 'signature' => $signature, 'backlink' => $link]);
		} else {
			$backlink = $this->storeRequest();
			$this->redirect('in', ['backlink' => $backlink]);
		}
	}

}
