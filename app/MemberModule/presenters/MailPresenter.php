<?php

namespace MemberModule;

use Nette\Application\UI\Form;

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

		$form = $this['mailForm'];
    	//if (!$form->isSubmitted()) {    
	        foreach ($this->memberService->getMembers()->order('surname, name') as $member) {
	            $form['users'][$member->id]['mail']
	            	->setAttribute('data-mail',$member->mail)
	            	->setAttribute('class','member')
	            	->caption = $member->surname.' '.$member->name;
	        }
    		$this->template->pocet = ceil(count($form['users']->values)/3);
    	//}
  	}

	public function renderAkce($id,$organizator=FALSE){
		$form = $this['mailForm'];
    	//if (!$form->isSubmitted()) {    
	        $array = array();
	        foreach ($this->akceService->getMembersByAkceId($id,$organizator) as $member) {

	            $form['users'][$member->id]['mail']
	            	->setAttribute('data-mail',$member->mail)
	            	->setDefaultValue(TRUE)
	            	->caption = $member->surname.' '.$member->name;
	        	
	        	$array[] = $member->mail;
	        }	
    		
    		$form['to']->setDefaultValue(implode(',',$array));
    		
    		$this->template->isAkce = TRUE;

    		$this->template->pocet = ceil(count($form['users']->values)/3);
    		$this->setView('default');
    	//}
  	}

	protected function createComponentMailForm(){
		$form = new Form;
		
		$form->addText('to', 'Příjemci', 50)
      		->setAttribute('readonly')
      		->setAttribute('class', 'max')
      		->setRequired('Musíte vybrat alespoň jednoho příjemce');
		
		$form->addButton('open', '  ')
    		->setAttribute('class', 'buttonLike myfont')
    		->setAttribute('onclick', 'adresy()');

		$form->addDynamic('users', function (\Nette\Forms\Container $container) {
	        $container->addCheckBox('mail', 'jmeno')
	        	->setDefaultValue(FALSE);
	    });

		$form->addText('subject', 'Předmět', 50)
      		->setRequired('Vyplňte %label')
      		->setAttribute('class', 'max');

    	$form->addUpload('file','Příloha')
    		->setAttribute('class', 'max')
    		->addCondition(Form::FILLED)    			
        		->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 16 MB.',16 * 1024 * 1024 /* v bytech */);

    	$form->addTextArea('text', 'Text e-mailu:', 45)
      		->setRequired('Vyplňte %label')
      		->setAttribute('class', 'max');
      		//->setAttribute('class','texyla');	

        $form->addSubmit('ok', 'Odeslat');
		$form->onSuccess[] = callback($this, 'mailFormSubmitted');

    	return $form;
	}

	public function mailFormSubmitted(Form $form){	
		$akce_id = (int) $this->getParameter('id');

		$values = $form->getValues();
		
		$memberList = array_keys(iterator_to_array($form['users']->values));

		$sender = $this->memberService->getMemberById($this->getUser()->getId());
		$members = $this->memberService->getMembers()->where('id',$memberList);

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

		$mail->setSubject('[VZS_info] '.$values->subject)
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