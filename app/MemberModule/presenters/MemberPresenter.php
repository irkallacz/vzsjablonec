<?php

namespace App\MemberModule\Presenters;

use App\Model\MemberService;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Form;
use Nette\Mail\IMailer;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;
use Nette\Utils\Image;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class MemberPresenter extends LayerPresenter{

	/** @var MemberService @inject */
	public $memberService;

	/** @var IMailer @inject*/
	public $mailer;

	public function actionUpdateCsv(){
		if (($handle = fopen('members_update.csv', 'r')) !== FALSE) {
			while (($data = fgetcsv($handle, 0, ",",'"')) !== FALSE) {
				$array = [
					'surname'	=> $data[1],
					'name' 		=> $data[2],
					'date_born'	=> date_create($data[3]),
					'mesto' 	=> $data[4],
					'ulice' 	=> $data[5],
					'mail' 		=> $data[6],
					'telefon' 	=> $data[7]
				];

				$member = $this->memberService->getMemberById($data[0]);
				
				if ($member) {
					$member->update($array);    	
					$this->flashMessage('Záznam "'.$member->surname.' '.$member->name.'" aktualizován');
				}
	    	}
		}
		
		$this->redirect('default');
  	}

	public function renderDefault($q = null){
		$members = $this->memberService->getMembers()->order('surname, name');
		
		if ($q) {
			$members->where('MATCH(name, surname, zamestnani, mesto, ulice, mail, telefon, text) AGAINST (? IN BOOLEAN MODE)',$q);
			$this['memberSearchForm']['search']->setDefaultValue($q);
		}
		
		$this->template->members = $members;
  	}

  	public function renderView($id){
		$member = $this->memberService->getMemberById($id);

		if (!$member) {
			$this->flashMessage('Záznam nenalezen','error');
        	$this->redirect('default',$id);
        }

		$this->template->narozeni = $member->date_born->diff(date_create());

		$this->template->member = $member;

		$this->template->fileExists = file_exists(WWW_DIR.'/img/portrets/'.$id.'.jpg');

		$this->registerTexy();

		$this->template->title = $member->surname .' '. $member->name;
  	}
 

	public function actionVcfArchive(){
		$zip = new \ZipArchive;
		$zip->open(WWW_DIR.'/archive.zip', \ZIPARCHIVE::CREATE | \ZIPARCHIVE::OVERWRITE);

		$template = $this->createTemplate();
		$template->setFile(APP_DIR .'/MemberModule/templates/Member.vcf.latte');
		$template->archive = TRUE;

		foreach ($this->memberService->getMembers()->order('surname, name') as $member) {
			$template->member = $member;
			$s = (string) $template;
			//$s = iconv('utf-8','cp1250',$s);
			$zip->addFromString(Strings::toAscii($member->surname).' '.Strings::toAscii($member->name).'.vcf',$s);
		}
		
		$zip->close();

		$response = new FileResponse(
            WWW_DIR.'/archive.zip',
            'member-archive.zip',
            'application/zip'
        );

        $this->sendResponse($response);
	}
	
	public function renderCsv(){
		$this->template->members = $this->memberService->getMembers()->order('surname, name');

		$httpResponse = $this->context->getByType('Nette\Http\Response');
		$httpResponse->setHeader('Content-Disposition','attachment; filename="members.csv"');
	}
	
	public function actionDelete($id){
		if (!$this->getUser()->isInRole($this->name)) {
            	$this->flashMessage('Nemáte práva na tuto akci','error');
            	$this->redirect('view',$id);
        }

		$member = $this->memberService->getMemberById($id);
		
		if (!$member) {
			$this->flashMessage('Záznam nenalezen','error');
        	$this->redirect('default',$id);
        }

		$member->update(['active' => 0]);

		$this->flashMessage('Člen byl úspěšně smazán');
		$this->redirect('default');
  	}

	public function renderVcf($id){
    	$member = $this->memberService->getMemberById($id);	
		
		if (!$member) {
			$this->flashMessage('Záznam nenalezen','error');
        	$this->redirect('default',$id);
        }

  		$this->template->member = $member;

		$httpResponse = $this->context->getByType('Nette\Http\Response');
	    $httpResponse->setHeader('Content-Disposition','attachment; filename="'.$member->surname.' '.$member->name.'.vcf"');
	}

	public function renderEdit($id){
    	$form = $this['memberForm'];
    	$member = $this->memberService->getMemberById($id);

    	if (!$member) {
			$this->flashMessage('Záznam nenalezen','error');
        	$this->redirect('default',$id);
        }
        
    	if ((!$this->getUser()->isInRole($this->name))and($member->id!=$this->getUser()->getId())) {
            	$this->flashMessage('Nemáte práva na tuto akci','error');
            	$this->redirect('view',$id);
        }	

 		$form['name']->setAttribute('readonly');
  		$form['surname']->setAttribute('readonly');

  		if (!$this->getUser()->isInRole($this->getName())) unset($form['date_add']);

  		$form->setDefaults($member);

  		unset($this['memberForm']['sendMail']);
  		$this->template->title = $member->surname .' '. $member->name;
  	}

	public function renderAdd(){
    	if (!$this->getUser()->isInRole($this->name)) {
            $this->flashMessage('Nemáte práva na tuto akci','error');
            $this->redirect($this->name.':');
        }

    	unset($this['memberForm']['password']);
    	unset($this['memberForm']['confirm']);
		unset($this['memberForm']['image']);
		unset($this['memberForm']['text']);

		$this->setView('edit');
	}

	public function actionProfile(){
		$id = $this->getUser()->getId();
		$this->redirect('Member:view',$id);
	}

	public function sendLogginMail($member, $session){
	    $template = $this->createTemplate();
	    $template->setFile(__DIR__ . '/../templates/Mail/newMember.latte');
		$template->session = $session;

		$mail = $this->getNewMail();

		$mail->addTo($member->mail, $member->surname.' '.$member->name);
	    $mail->setSubject('[VZS Jablonec] Vítejte v informačním systému VZS Jablonec nad Nisou');
	    $mail->setHTMLBody($template);

	    $this->mailer->send($mail);
  	}

	protected function createComponentUploadMembersForm(){
		$form = new Form;
		
        $form->addUpload('file')
        	->addRule(Form::MIME_TYPE,'Uploadovaný soubor můsí být ve formátu .csv',
        	'text/comma-separated-values, text/csv, application/csv, application/excel, application/vnd.ms-excel, application/vnd.msexcel, text/anytext');
        $form->addSubmit('ok', '')->setAttribute('class','iconic');

		$form->onSuccess[] = [$this, 'uploadMembersFormSubmitted'];

    return $form;
	}

	public function uploadMembersFormSubmitted(Form $form){
		$values = $form->getValues();

		$this->redirect('Member:default',$values->search);
	}

	protected function createComponentMemberSearchForm(){
		$form = new Form;
		$form->addText('search', null, 30)
      		->setType('search')
      		->setRequired('Vyplňte hledanou frázi')
      		->getControlPrototype()
      			->title = 'Vyhledá v seznamu hledanou frázi';

        $form->addSubmit('ok', '')
        	->setAttribute('class','myfont');

		$form->onSuccess[] = [$this, 'memberSearchFormSubmitted'];

    return $form;
	}

	public function memberSearchFormSubmitted(Form $form){
		$values = $form->getValues();

		$this->redirect('Member:default',$values->search);
	}

	public function uniqueValidator($item){
		$id = (int) $this->getParameter('id');
		return (bool) !($this->memberService->getMembers(FALSE)->select('id')->where($item->name, $item->value)->where('id != ?',$id)->fetch());
	}
	
	public function currentPassValidator($item,$arg){
		return Passwords::verify($item->value,$arg);
	}

	protected function createComponentMemberForm(){		
		$form = new Form;
		
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->addGroup('Osobní data');

		$form->addText('name', 'Jméno', 30)
			->setAttribute('spellcheck', 'true')      		
      		->setRequired('Vyplňte %label');

		$form->addText('surname', 'Příjmení', 30)
			->setAttribute('spellcheck', 'true')	
      		->setRequired('Vyplňte %label');

		$form['date_born'] = new \DateInput('Datum narození');
		$form['date_born']->setRequired('Vyplňte datum narození')
			->setDefaultValue(new DateTime());

		$form->addText('zamestnani', 'Zaměstnání/Škola', 30)
			->setAttribute('spellcheck', 'true')
      		->setRequired('Vyplňte %label');

        $form->addGroup('Přihlašovací údaje');

		$form->addPassword('password', 'Nové heslo', 20)
      		->addCondition(Form::FILLED)
      			->addRule(Form::PATTERN,'Heslo musí mít alespoň 8 znaků, musí obsahovat číslice, malá a velká písmena','^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,15}$')
      			->addRule([$this, 'currentPassValidator'],'Nesmíte použít svoje staré heslo',$this->getUser()->getIdentity()->hash);

		$form->addPassword('confirm', 'Potvrzení', 20)
	        ->setRequired(FALSE)
      		->addRule(Form::EQUAL,'Zadaná hesla se neschodují',$form['password'])
      		->addCondition(Form::FILLED)
      			->addRule(Form::MIN_LENGTH,'Heslo musí mít alespoň %d znaků',8);

		$form->addCheckbox('sendMail','Poslat novému členu mail s přihlašovacími údaji')
			->setDefaultValue(TRUE);

		$form->addGroup('Kontakty');

		$form->addText('mail', 'E-mail', 30)
			->setType('email')
			->addRule([$this, 'uniqueValidator'], 'V databázi se již vyskytuje osoba se stejnou emailovou adresou')
			->setRequired('Vyplňte %label');

		$form->addText('telefon', 'Telefon', 30)
			->setRequired('Vyplňte %label')
			->addRule(Form::LENGTH,'%label musí mít %d znaků',9);

		$form->addGroup('Adresa');

        $form->addText('ulice', 'Ulice', 30)
			->setAttribute('spellcheck', 'true')	
      		->setRequired('Vyplňte ulici');

      	$form->addText('mesto', 'Město', 30)
			->setAttribute('spellcheck', 'true')	
      		->setRequired('Vyplňte %label');

    	$form->setCurrentGroup(null);

		$form['date_add'] = new \DateInput('Datum registrace');
		$form['date_add']->setRequired('Vyplňte datum registrace')
			->setDefaultValue(new DateTime());

		$form->addUpload('image','Nový obrázek')
    		->addCondition(Form::FILLED)    			
        		->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 5 MB.',5 * 1024 * 1024 /* v bytech */)
        		->addRule(Form::IMAGE, 'Fotografie musí být ve formátu JPEG')
        	->endCondition();

    	$form->addTextArea('text', 'Poznámka', 30)
			->setAttribute('spellcheck', 'true');

        $form->addSubmit('ok', 'Ulož');
		$form->onSuccess[] = [$this, 'memberFormSubmitted'];

    	return $form;
	}

	public function memberFormSubmitted(Form $form){
		$id = (int) $this->getParameter('id');

		$values = $form->getValues();
		
		$sendMail = $values->sendMail;
		unset($values['sendMail']);

		if ($values->password) {
			$values->hash = Passwords::hash($values->password);
		}

		unset($values['password']);
		unset($values['confirm']);	

		$values['mail'] = Strings::lower($values['mail']);

		if (($form['image']->isFilled()) and ($values->image->isOK())){
			$image = $values->image->toImage();
			$image->resize(250, NULL, Image::SHRINK_ONLY);
			$image->save(WWW_DIR.'/img/portrets/'.$id.'.jpg', 80, Image::JPEG);
        } 
        
        unset($values->image);

        if (!$values->text) unset($values->text);

        $values->date_update = new DateTime();

		if ($id) {
          	$this->memberService->getMemberById($id)->update($values);
          	$this->flashMessage('Osobní profil byl změněn');
          	$this->redirect('Member:view',$id);
        }else {
			$values->hash = '';
			$member = $this->memberService->addMember($values);

			if ($sendMail) {
				$session = $this->memberService->addPasswordSession($member->id, '24 HOUR');
				$this->sendLogginMail($member, $session);
			}

			$this->memberService->addMemberLogin($member->id, new DateTime());

          	$this->flashMessage('Byl přidán nový člen');
          	$this->redirect('Member:view',$member->id);
		}		
	}
}