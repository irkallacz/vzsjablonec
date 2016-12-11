<?php

namespace MemberModule;

use Nette\Application\UI\Form;
use Nette\DateTime;
use Nette\Security as NS;
use Nette\Utils\Strings;

class SignPresenter extends BasePresenter{

	/** @var \MemberService  @inject*/
	public $memberService;

	/** @var \Nette\Mail\iMailer @inject*/
	public $mailer;

	/** @persistent */
	public $backlink = '';
	
	public function sendLogginMail($member, $password){
	    $template = $this->createTemplate();
	    $template->setFile(__DIR__ . '/../templates/Mail/forgotPassword.latte');
	    $template->member = $member;
		$template->password = $password;

		$mail = $this->getNewMail();

	    $mail->addTo($member->mail,$member->surname.' '.$member->name);
	    $mail->setBody($template);
	    $this->mailer->send($mail);

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

		$form->onSuccess[] = callback($this, 'signInFormSubmitted');
		return $form;
	}

	public function signInFormSubmitted(Form $form){
		try {
			$values = $form->getValues();

			$this->getUser()->setExpiration('0', TRUE);
			$this->getUser()->login($values->username, $values->password);

			$user_id = $this->getUser()->getId();

			$this->getUser()->getIdentity()->date_last = $this->memberService->getLastLoginByMemberId($user_id);
			$this->memberService->addMemberLogin($user_id, new DateTime());

			$this->restoreRequest($this->backlink);
			$this->redirect('News:default');

		} catch (NS\AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}

    protected function createComponentForgotPassForm(){
		$form = new Form;
		$form->addText('mail', 'email:')
			->setRequired('Vyplňte váš email')
			->setType('email')
			->addRule(FORM::EMAIL,'Vyplňte správnou e-mailovou adresu');
                    
		$form->addSubmit('send', 'poslat');

		$form->onSuccess[] = callback($this, 'forgotPassFormSubmitted');
		return $form;
	}

	public function ForgotPassFormSubmitted(Form $form){
		
        $values = $form->getValues();
        $values->mail = Strings::lower($values->mail);

        $member = $this->memberService->getMemberByEmail($values->mail);
        
        if(!$member) $form->addError('E-mail nenalezen');
        else {
        	$password = Strings::random(8);
        	$member->update(['hash' => NS\Passwords::hash($password)]);

        	$this->sendLogginMail($member, $password);
        	$this->flashMessage('Na Váši e-mailovou adresu byly odeslány nové přihlašovací údaje');
        	
        	$this->redirect('Sign:in');
        }
	}

	public function actionOut(){
		$this->getUser()->logout();
		$this->flashMessage('Byl jste odhlášen');
		$this->redirect('in');
	}

}