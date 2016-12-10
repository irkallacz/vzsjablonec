<?php
namespace MemberModule;

use Nette\Application\UI\Form;
use Nette\DateTime;
use Nette\Diagnostics\Debugger;

class DokumentyPresenter extends LayerPresenter{

    /** @var \DokumentyService @inject */
    public $dokumentyService;

    /** @var \Nette\Mail\IMailer @inject */
    public $mailer;

    public function renderDefault(){
		$this->template->category = $this->dokumentyService->getDokumentyCategory();
        $this->template->zapisy = $this->dokumentyService->getZapisy();
        $this->template->hlasovani = $this->dokumentyService->getHlasovani();
  	}

    public function renderAdd(){
        if (!$this->getUser()->isInRole($this->name)) {
            $this->flashMessage('Nemáte práva na tuto akci','error');
            $this->redirect('Dokumenty:');
        }
    }

	public function actionDelete($id){
        if (!$this->getUser()->isInRole($this->name)) {
            $this->flashMessage('Nemáte práva na tuto akci','error');
            $this->redirect('Dokumenty:');
        }else{
            $this->dokumentyService->getDokumentById($id)->delete();
            $this->flashMessage('Dokument byl smazán');
            $this->redirect('Dokumenty:');
        }
    }

    public function sendZapisMail($file,$datum){
        $template = $this->createTemplate();
        $template->setFile(__DIR__ . '/../templates/Mail/newZapis.latte');
        $template->datum = $datum;

        $mail = $this->getNewMail();

        $mail->addAttachment('schuze-'.$datum->format('Y-m-d').'.pdf', $file->getContents());

        $mail->addTo('predstavenstvo@vzs-jablonec.cz');
        $mail->setBody($template);

        $this->mailer->send($mail);
    }
    
    public function sendHlasovaniMail($file,$datum){
        $template = $this->createTemplate();
        $template->setFile(__DIR__ . '/../templates/Mail/newHlasovani.latte');
        $template->datum = $datum;

        $mail = $this->getNewMail();

        $mail->addAttachment('hlasovani.pdf', $file->getContents());
        
        $mail->addTo('predstavenstvo@vzs-jablonec.cz');

        $mail->setBody($template);

        $this->mailer->send($mail);
    }

    protected function createComponentAddDokumentForm(){
		$form = new Form;
		
        $form->addUpload('file','Soubor')
        	->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 16 MB.', 16 * 1024 * 1024)
        	->setRequired('Vyberte prosím soubor');
        
        $form->addText('title','Popisek souboru',40)
        	->setRequired('Vyplňte %label');

        $form->addSelect('dokumenty_category_id','Kategorie',
        		$this->dokumentyService->getDokumentyCategory()->fetchPairs('id','title')
        	)->setRequired('Vyplňte kategorii souboru')
        	->setDefaultValue(1);
        
        $form->addSubmit('ok', 'Nahrát');

		$form->onSuccess[] = callback($this, 'addDokumentFormSubmitted');

    return $form;
	}

    public function addDokumentFormSubmitted(Form $form){
        $values = $form->getValues();

        Debugger::barDump($values);
        
        $category = $this->dokumentyService->getDokumentyCategoryById($values->dokumenty_category_id);

        $values->filename = $values->file->getSanitizedName();

        if (($form['file']->isFilled()) and ($values->file->isOK())){
          $values->file->move(WWW_DIR.'/doc/'.$category->dirname.'/'.$values->filename);
        }        

        unset($values->file);

        $values->member_id = $this->getUser()->getId();

        $this->dokumentyService->addDokument($values);
        
        $this->flashMessage('Dokument byl úspěšně přidán');
        $this->redirect('Dokumenty:');
    }

    protected function createComponentAddZapisForm(){
        $form = new Form;
        
        $form->addUpload('file','Soubor')
            ->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 16 MB.', 16 * 1024 * 1024)
            //->addRule(Form::MIME_TYPE, 'Soubor musí být ve formátu .pdf.', 'application/pdf')
            ->setRequired('Vyberte prosím soubor');
        
        $form->addText('datum','Datum schůze',10)
            ->addRule(Form::PATTERN, 'Datum musí být ve formátu RRRR-MM-DD', '[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
            ->setType('date')
            ->setDefaultValue(date('Y-m-d'));

        $form->addCheckBox('mail', 'Poslat soubor představenstvu e-mailem')
            ->setDefaultValue(TRUE);       

        $form->addSubmit('ok', 'Nahrát');

        $form->onSuccess[] = callback($this, 'addZapisFormSubmitted');

    return $form;
    }

    protected function createComponentAddHlasovaniForm(){
        $form = new Form;
        
        $form->addUpload('file','Soubor')
            ->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 16 MB.', 16 * 1024 * 1024)
            //->addRule(Form::MIME_TYPE, 'Soubor musí být ve formátu .pdf.','application/pdf,application/x-pdf,application/acrobat,applications/vnd.pdf,text/pdf,text/x-pdf')
            ->setRequired('Vyberte prosím soubor');
        
        $form->addCheckBox('mail', 'Poslat soubor představenstvu e-mailem')
            ->setDefaultValue(TRUE);       

        $form->addSubmit('ok', 'Nahrát');

        $form->onSuccess[] = callback($this, 'addHlasovaniFormSubmitted');

    return $form;
    }


     public function addZapisFormSubmitted(Form $form){
        $values = $form->getValues();
        
        if (($form['file']->isFilled()) and ($values->file->isOK())){
            
            $datum = new Datetime($values->datum);
            
            $values->title = 'Schůze ' . $datum->format('d.m.Y');
            $values->filename = 'schuze-' . $datum->format('Y-m-d') .'.pdf';
            
            $values->member_id = $this->getUser()->getId();
            
            $values->file->move(WWW_DIR.'/doc/schuze/'.$values->filename);                        

            if ($values->mail) $this->sendZapisMail($values->file, $datum);
            
            unset($values->mail);
            unset($values->file);
            unset($values->datum);

            $this->dokumentyService->addZapis($values);
            $this->flashMessage('Byl úspěšně přidán nový zápis ze schůze');
            $this->redirect('Dokumenty:');
        }else {
            $form->addError('Chyba při nahrávání souboru');
        }
    }

    public function addHlasovaniFormSubmitted(Form $form){
        $values = $form->getValues();
        
        if (($form['file']->isFilled()) and ($values->file->isOK())){
            $values->file->move(WWW_DIR.'/doc/schuze/hlasovani.pdf');
            
            $datum = new DateTime();
            $values->member_id = $this->getUser()->getId();

            if ($values->mail) $this->sendHlasovaniMail($values->file, $datum);
            
            unset($values->mail);
            unset($values->file);
            
            $this->dokumentyService->getHlasovani()->update(['date_add' => $datum]);
            
            $this->flashMessage('Soubor s hlasováním byl aktualizován');
            $this->redirect('Dokumenty:');
        }else {
            $form->addError('Chyba při nahrávání souboru');
        }
    }
}