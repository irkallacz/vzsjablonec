<?php

namespace App\MemberModule\Presenters;

use Nette\Application\UI\Form;
use Tracy\Debugger;

class MailPresenter extends LayerPresenter{

	/** @var \MemberService @inject */
	public $memberService;

	/** @var \AkceService @inject */
	public $akceService;

	/** @var \Nette\Mail\IMailer @inject*/
	public $mailer;

	public function renderDefault(){
		if (!$this->getUser()->isInRole($this->name)) {
            $this->flashMessage('Nemáte práva na tuto akci','error');
            $this->redirect('News:');
        }

        $userMails = $this->memberService->getMembers(TRUE)->fetchPairs('id','mail');
        $this->template->userMails = $userMails;

		$form = $this['mailForm'];
    	if (!$form->isSubmitted()) {
    		$this->template->pocet = ceil(count($userMails)/3);
    	}
  	}

	public function renderAkce($id,$organizator=FALSE){
		$form = $this['mailForm'];

		$users = $this->akceService->getMembersByAkceId($id,$organizator);
        $form['to']->setDefaultValue(join(',',$users->fetchPairs('id','mail')));
		$form['users']->setDefaultValue($users->fetchPairs('id','id'));

        $this->template->isAkce = TRUE;

        $this->template->pocet = ceil(count($users)/3);
        $this->setView('default');

  	}

	protected function createComponentMailForm(){
		$form = new Form;
		
		$form->addText('to', 'Příjemci', 50)
      		->setAttribute('readonly')
      		->setAttribute('class', 'max')
      		->setRequired('Musíte vybrat alespoň jednoho příjemce');
		
		$form->addButton('open', '  ')
    		->setAttribute('class', 'buttonLike myfont')
    		->setAttribute('onclick', 'adresy()');

		$form->addCheckboxList('users', 'Příjemci')
			->setItems($this->memberService->getMembersArray());

		$form->addText('subject', 'Předmět', 50)
      		->setRequired('Vyplňte %label')
      		->setAttribute('spellcheck', 'true')
      		->setAttribute('class', 'max');


    	$form->addUpload('file','Příloha')
    		->setAttribute('class', 'max')
    		->addCondition(Form::FILLED)    			
        		->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 16 MB.',16 * 1024 * 1024 /* v bytech */);

    	$form->addTextArea('text', 'Text e-mailu:', 45)
      		->setRequired('Vyplňte %label')
      		->setAttribute('spellcheck', 'true')
      		->setAttribute('class', 'max');
      		
      		//->setAttribute('class','texyla');	

        $form->addSubmit('ok', 'Odeslat');
		$form->onSuccess[] = [$this, 'mailFormSubmitted'];

    	return $form;
	}

	public function mailFormSubmitted(Form $form){	
		$akce_id = (int) $this->getParameter('id');

		$values = $form->getValues();

		$sender = $this->memberService->getMemberById($this->getUser()->getId());
		$members = $this->memberService->getMembers()->where('id', $values->users);

		if (($form['file']->isFilled()) and (!$values->file->isOK())) {
			$form->addError('Chyba při nahrávání souboru');
			$this->redirect('this');
		}

		$mail = $this->getNewMail();
		$mail->addReplyTo($sender->mail, $sender->surname.' '.$sender->name);
		$mail->addBcc($sender->mail, $sender->surname.' '.$sender->name);

		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../templates/Mail/newMail.latte');
		$template->text = $values->text;

		$mail->setSubject('[VZS Jablonec] '.$values->subject)
			->setBody($template);

		foreach ($members as $member)
			$mail->addTo($member->mail, $member->surname.' '.$member->name);

		if (($form['file']->isFilled()) and ($values->file->isOK()))
			$mail->addAttachment($values->file->getSanitizedName(),$values->file->getContents());

		$this->mailer->send($mail);

		$this->flashMessage('Váš mail byl v pořádku odeslán');

		if ($akce_id) $this->redirect('Akce:view',$akce_id); else $this->redirect('Mail:default');
	}
}