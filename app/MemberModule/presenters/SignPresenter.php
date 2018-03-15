<?php

namespace App\MemberModule\Presenters;

use App\Authenticator\CredentialsAuthenticator;
use App\Authenticator\EmailAuthenticator;
use App\MemberModule\Forms\UserFormFactory;
use App\Model\MessageService;
use App\Template\LatteFilters;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
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

	/** @var IMailer @inject */
	public $mailer;

	/** @var UserFormFactory @inject */
	public $userFormFactory;

	/** @persistent */
	public $backlink = '';


	/**
	 *
	 */
	public function startup() {
		parent::startup();
		if ($this->getUser()->isLoggedIn() and ($this->getAction() != 'out')) {
			if ($this->backlink) $this->restoreRequest($this->backlink);
			$this->redirect('News:');
		}
	}

	/**
	 *
	 */
	public function renderIn() {
		if ($this->backlink) {
			$this->googleLogin->setState($this->backlink);
			$this->facebookLogin->setState($this->backlink);
		}

		$this->template->googleLoginUrl = $this->googleLogin->getLoginUrl();
		$this->template->googleLastLogin = $this->googleLogin->isThisServiceLastLogin();

		$this->template->facebookLoginUrl = $this->facebookLogin->getLoginUrl();
		$this->template->facebookLastLogin = $this->facebookLogin->isThisServiceLastLogin();
	}


	/**
	 * @param string $code
	 * @param string|NULL $state
	 */
	public function actionGoogleLogin(string $code, string $state = NULL) {
		try {
			if ($state) $this->backlink = $state;
			$me = $this->googleLogin->getMe($code);
			$this->emailAuthenticator->login($me->email);
			$this->afterLogin(UserService::LOGIN_METHOD_GOOGLE);
		} catch (NS\AuthenticationException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('in');
		}
	}

	/**
	 * @param string|NULL $state
	 */
	public function actionFacebookLogin(string $state = NULL) {
		try {
			if ($state) $this->backlink = $state;
			$me = $this->facebookLogin->getMe([FacebookLogin::ID, FacebookLogin::EMAIL]);
			$email = Arrays::get($me, 'email');
			$this->emailAuthenticator->login($email);
			$this->afterLogin(UserService::LOGIN_METHOD_FACEBOOK);
		} catch (InvalidArgumentException $e) {
			$this->flashMessage('Pravděpodobně jste aplikaci VZS JBC na Facebooku odebrali právo přistupovat k vašemu emailu. Odeberte aplikaci a znovu se pokuste přihlásit.', 'error');
			$this->redirect('in');
		} catch (NS\AuthenticationException $e) {
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

		} catch (NS\AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}

	/**
	 * @param int $method
	 */
	private function afterLogin($method = UserService::LOGIN_METHOD_PASSWORD) {
		$userId = $this->getUser()->getId();
		$this->getUser()->setExpiration('6 hours', NS\IUserStorage::CLEAR_IDENTITY, TRUE);

		$dateLast = $this->userService->getLastLoginByUserId($userId);
		$this->getUser()->getIdentity()->date_last = $dateLast ? $dateLast : new DateTime();
		$this->userService->addUserLogin($userId, new DateTime(), $method);

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

		/** @var Form $form */
		$form = $this['restorePasswordForm'];
		$form->setDefaults($member);
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

	/**
	 * @return Form
	 */
	protected function createComponentRegisterForm() {
		$form = $this->userFormFactory->create();

		$form['mail']->caption = 'E-mail';
		$form['telefon']->caption = 'Telefon';

		$form['mail2']->caption = 'Druhý e-mail';
		$form['mail2']->setOption('description','(na rodiče atd...)');

		$form['telefon2']->caption = 'Druhý telefon';
		$form['telefon2']->setOption('description','(na rodiče atd...)');

		$form->addGroup('');
		$form->addAntiSpam('notSpam')
			->setLockTime(5)
			->setResendTime(NULL);

		$form->addSubmit('ok', 'Odeslat');

		$form->onValidate[] = function (Form $form){
			$values = $form->getValues();
			if (($values->date_born->diff(date_create())->y < 18)and((!$values->mail2)or(!$values->telefon2))) {
				$form->addError('U dětí je potřeba vyplnit i e-mail a telefon rodičů');
			}
			if (!$values->notSpam) {
				$form->addError('Vypadá to, že se jedná o SPAM, zkuste vyplnit formulář znovu');
			}
		};

		$form->onSuccess[] = function (Form $form){
			$values = $form->getValues();
			$now = new DateTime;

			if ($values->date_born->diff($now)->y < 18) {
				$values->send_to_second = TRUE;
			}

			unset($values->notSpam);

			$values->date_add = $now;

			$user = $this->userService->addUser($values, UserService::DELETED_LEVEL);
			$this->flashMessage('Zánam byl uložen, čekejte prosím na e-mail od administrátora');

			$this->addRegistrationMail($user);

			$this->redirect('in');
		};

		return $form;
	}

	/**
	 * @param IRow|ActiveRow $user
	 */
	private function addRegistrationMail(IRow $user){
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../templates/Mail/newRegistration.latte');
		$template->user = $user;

		$message = new MessageService\Message(MessageService\Message::REGISTRATION_NEW_TYPE);
		$message->setSubject('Nová registrace uživatele');
		$message->setText($template);
		$message->setAuthor($user->id);
		$message->setRecipients($this->userService->getUsers(UserService::ADMIN_LEVEL));
		$message->setParameters(['user_id' => $user->id]);

		$this->messageService->addMessage($message);
	}

	/**
	 *
	 */
	public function actionOut() {
		$this->getUser()->logout();
		$this->flashMessage('Byl jste odhlášen');
		$this->redirect('in');
	}
}