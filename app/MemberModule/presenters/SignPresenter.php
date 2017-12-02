<?php

namespace App\MemberModule\Presenters;

use App\Authenticator\CredentialsAuthenticator;
use App\Authenticator\EmailAuthenticator;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\InvalidArgumentException;
use Nette\Mail\IMailer;
use Nette\Utils\Arrays;
use Nette\Utils\DateTime;
use Nette\Security as NS;
use Nette\Utils\Strings;
use App\Model\UserService;
use Vencax\FacebookLogin;
use Vencax\GoogleLogin;
use Tracy\Debugger;

class SignPresenter extends BasePresenter {

	/** @var UserService @inject */
	public $userService;

	/** @var GoogleLogin @inject */
	public $googleLogin;

	/** @var FacebookLogin @inject */
	public $facebookLogin;

	/** @var CredentialsAuthenticator @inject */
	public $credentialsAuthenticator;

	/** @var EmailAuthenticator @inject */
	public $emailAuthenticator;

	/** @var IMailer @inject */
	public $mailer;

	/** @persistent */
	public $backlink = '';


	public function startup() {
		parent::startup();
		if ($this->getUser()->isLoggedIn() and ($this->getAction() != 'out')) {
			if ($this->backlink) $this->restoreRequest($this->backlink);
			$this->redirect('News:');
		}
	}

	public function renderIn(){
		$this->template->googleLoginUrl = $this->googleLogin->getLoginUrl();
		$this->template->googleLastLogin = $this->googleLogin->isThisServiceLastLogin();

		$this->template->facebookLoginUrl = $this->facebookLogin->getLoginUrl();
		$this->template->facebookLastLogin = $this->facebookLogin->isThisServiceLastLogin();
	}


	/**
	 * @param $code
	 */
	public function actionGoogleLogin($code) {
		try {
			$me = $this->googleLogin->getMe($code);
			$this->emailAuthenticator->login($me->email);
			$this->afterLogin();
		}
		catch(NS\AuthenticationException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('in');
		}
	}

	public function actionFacebookLogin() {
		try {
			$me = $this->facebookLogin->getMe([FacebookLogin::ID, FacebookLogin::EMAIL]);
			$email = Arrays::get($me, 'email');
 			$this->emailAuthenticator->login($email);
			$this->afterLogin();
 		}
		catch(InvalidArgumentException $e) {
			$this->flashMessage('Pravděpodobně jste aplikaci VZS JBC na Facebooku odebrali právo přistupovat k vašemu emailu. Odeberte aplikaci a znovu se pokuste přihlásit.', 'error');
			$this->redirect('in');
		}
		catch(NS\AuthenticationException $e) {
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
			$this->afterLogin();

		} catch (NS\AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}

	private function afterLogin(){
		$userId = $this->getUser()->getId();
		$this->getUser()->setExpiration('6 hours', NS\IUserStorage::CLEAR_IDENTITY, TRUE);

		$this->getUser()->getIdentity()->date_last = $this->userService->getLastLoginByUserId($userId);
		$this->userService->addUserLogin($userId, new DateTime());

		if ($this->backlink) $this->restoreRequest($this->backlink);
		else $this->redirect('News:default');
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

		$member = $this->userService->getUserByLogin($values->mail);

		if (!$member) $form->addError('E-mail nenalezen');
		else {
			$session = $this->userService->addPasswordSession($member->id);
			$this->backlink = '';
			$this->sendRestoreMail($member, $session);
			$this->flashMessage('Na Váši e-mailovou adresu byly odeslány údaje pro změnu hesla');

			$this->redirect('Sign:in');
		}
	}

	/**
	 * @param $pubkey
	 * @throws BadRequestException
	 */
	public function renderRestorePassword($pubkey) {
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
					$hash = NS\Passwords::hash($values->password);
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
}