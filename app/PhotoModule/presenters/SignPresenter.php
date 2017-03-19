<?php

namespace App\PhotoModule\Presenters;

use App\Model\GalleryService;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Nette\Security;


class SignPresenter extends BasePresenter{
	/** @persistent */
	public $backlink = '';

	/**
	 * @var GalleryService @inject
     */
	public $galleryService;

	public function actionOut(){
		$this->getUser()->logout();
		$this->flashMessage('Byl jste odhlášen');
		$this->redirect('Album:default');
	}

	public function renderIn(){
		$this->template->backlink = $this->backlink;
	}

	/**
	 * Sign in form component factory.
	 * @return Form
	 */
	protected function createComponentSignInForm(){
		$form = new Form;
		$form->addText('username', 'Přihlašovací jméno:')
			->setAttribute('autofocus')
			->setRequired('Vyplňte přihlašovací jméno');

		$form->addPassword('password', 'Heslo:')
			->setRequired('Vyplňte heslo');

		$form->addSubmit('send', 'Přihlásit');
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->onSuccess[] = [$this, 'signInFormSubmitted'];
		return $form;
	}

	public function signInFormSubmitted(Form $form){
		try {
			$values = $form->getValues();

			$this->getUser()->setExpiration('0', TRUE);
			$this->getUser()->login($values->username, $values->password);

			$user_id = $this->getUser()->getId();

			$this->getUser()->getIdentity()->date_last = $this->galleryService->getLastLoginByMemberId($user_id);
			$this->galleryService->addMemberLogin($user_id, new DateTime());

			$this->restoreRequest($this->backlink);
			$this->redirect('Myself:');

		} catch (Security\AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}
	
}
