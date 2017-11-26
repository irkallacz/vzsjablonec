<?php

namespace App\MemberModule\Presenters;

use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Mail\IMailer;
use Nette\Utils\DateTime;
use Nette\Security as NS;
use Nette\Utils\Strings;
use App\Model\UserService;

class SignPresenter extends BasePresenter {

	/** @var UserService  @inject */
	public $userService;

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

	public function signInFormSubmitted(Form $form) {
		try {
			$values = $form->getValues();

			$this->getUser()->setExpiration('6 hours', TRUE);
			$this->getUser()->login($values->mail, $values->password);

			$userId = $this->getUser()->getId();

			$this->getUser()->getIdentity()->date_last = $this->userService->getLastLoginByUserId($userId);
			$this->userService->addUserLogin($userId, new DateTime());

			if ($this->backlink) $this->restoreRequest($this->backlink);
			else $this->redirect('News:default');

		} catch (NS\AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}

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

	public function forgotPassFormSubmitted(Form $form) {
		$values = $form->getValues();
		$values->mail = Strings::lower($values->mail);

		$member = $this->userService->getUserByEmail($values->mail);

		if (!$member) $form->addError('E-mail nenalezen');
		else {
			$session = $this->userService->addPasswordSession($member->id);
			$this->backlink = '';
			$this->sendRestoreMail($member, $session);
			$this->flashMessage('Na Váši e-mailovou adresu byly odeslány údaje pro změnu hesla');

			$this->redirect('Sign:in');
		}
	}

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