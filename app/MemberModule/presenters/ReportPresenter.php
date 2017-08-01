<?php

namespace App\MemberModule\Presenters;

use App\Model\AkceService;
use Joseki\Webloader\JsMinFilter;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;

class ReportPresenter extends LayerPresenter{

	/** @var AkceService @inject */
	public $akceService;

	public function renderView($id){
		$akce = $this->akceService->getReportById($id);
		$this->template->akce = $akce;
		$this->template->id = $id;
		$this->template->placeno = 0;
		$this->template->hodiny = 0;

		$this->template->members = $this->akceService->getMembersByReportId($id)
			->select(':report_member.date_start,:report_member.date_end,:report_member.hodiny,:report_member.placeno,name,surname');
	}

	public function renderAdd($id){
		$report = $this->akceService->getReportById($id);

		if ($report) {
  			$this->flashMessage('Záznam již existuje, překontrolujte ho prosím');
      		$this->redirect('Report:edit',$id);
    	}

		$this->setView('edit');
		$this->template->novy = TRUE;

		$form = $this['reportForm'];
		if (!$form->isSubmitted()){
			$akce = $this->akceService->getAkceById($id);			
			$form->setDefaults($akce);
			$form['date_start']->setDefaultValue($akce->date_start->format('Y-m-d'));
			$form['time_start']->setDefaultValue($akce->date_start->format('H:i'));
			$form['date_end']->setDefaultValue($akce->date_end->format('Y-m-d'));
			$form['time_end']->setDefaultValue($akce->date_end->format('H:i'));
			$form['zos_id']->setDefaultValue($akce->member_id);

			$members = $this->akceService->getMembersByAkceId($id,[0,1])
				->order(':akce_member.organizator DESC, :akce_member.date_add DESC');

			$diff = round((intval($akce->date_end->format('U')) - intval($akce->date_start->format('U')))/3600,1);
				
			foreach ($members as $member) {				
				$form['users'][$member->id]['member_id']->setDefaultValue($member->id);
				$form['users'][$member->id]['date_start']->setDefaultValue($akce->date_start->format('Y-m-d'));
				$form['users'][$member->id]['date_end']->setDefaultValue($akce->date_end->format('Y-m-d'));
				$form['users'][$member->id]['time_start']->setDefaultValue($akce->date_start->format('H:i'));
				$form['users'][$member->id]['time_end']->setDefaultValue($akce->date_end->format('H:i'));
				$form['users'][$member->id]['hodiny']->setDefaultValue($diff);
			}				
		}
	}

	public function renderEdit($id){
		
		$form = $this['reportForm'];
		if (!$form->isSubmitted()){
			
			$akce = $this->akceService->getReportById($id);

			if (!$akce) {
      			throw new BadRequestException('Záznam nenalezen');
          		$this->redirect('Akce:view',$id);
        	}
			
			$form->setDefaults($akce);
			$form['date_start']->setDefaultValue($akce->date_start->format('Y-m-d'));
			$form['time_start']->setDefaultValue($akce->date_start->format('H:i'));
			$form['date_end']->setDefaultValue($akce->date_end->format('Y-m-d'));
			$form['time_end']->setDefaultValue($akce->date_end->format('H:i'));

			$members = $akce->related('report_member');

			foreach ($members as $member) {
				$form['users'][$member->member_id]['member_id']->setDefaultValue($member->member_id);
				$form['users'][$member->member_id]['hodiny']->setDefaultValue($member->hodiny);
				$form['users'][$member->member_id]['placeno']->setDefaultValue($member->hodiny);

				$form['users'][$member->member_id]['date_start']->setDefaultValue($member->date_start->format('Y-m-d'));
				$form['users'][$member->member_id]['time_start']->setDefaultValue($member->date_start->format('H:i'));
				$form['users'][$member->member_id]['date_end']->setDefaultValue($member->date_end->format('Y-m-d'));
				$form['users'][$member->member_id]['time_end']->setDefaultValue($member->date_end->format('H:i'));
			}

			$form['akce']->setDefaults($akce);
			$form['osetreni']->setDefaults($akce);
			$form['texts']->setDefaults($akce);														
		}
	}
	
	public function createComponentTexylaJs(){
      $files = new \WebLoader\FileCollection(WWW_DIR . '/texyla/js');
      $files->addFiles(['texyla.js','selection.js','texy.js','buttons.js','cs.js','dom.js','view.js','window.js']);
      $files->addFiles(['../plugins/symbol/symbol.js']);
      $files->addFiles(['../plugins/textTransform/textTransform.js']);
      $files->addFiles([WWW_DIR . '/js/texyla_public.js']);

      $compiler = \WebLoader\Compiler::createJsCompiler($files, WWW_DIR . '/texyla/temp');
      $compiler->addFileFilter(new JsMinFilter());

      return new \WebLoader\Nette\JavaScriptLoader($compiler, $this->template->basePath . '/texyla/temp');
  	}

	protected function createComponentReportForm(){
	    $form = new Form;	   	

	    $datum = new Datetime();

	    $reportTypes = $this->akceService->getReportTypes();
		
		$lidi = $this->akceService->getMembers(FALSE)->fetchPairs('id','jmeno');

	    $form->addText('name','Název',30)
	   		->setRequired('Vyplňte %label akce');

	    $form->addSelect('report_type_id','Typ akce',$reportTypes)
	    	//->setPrompt('jiná')
	    	->addCondition(Form::EQUAL, 0)
        		->toggle('frm-reportForm-type_text')
    		->elseCondition()
        		->toggle('frm-reportForm-type_text',FALSE);

		$form->addText('type_text','Vyplňte typ akce',30)
			->setAttribute('placeholder','Vyplňte typ akce')
			->addConditionOn($form['report_type_id'], Form::EQUAL, 0)
			->addRule(Form::FILLED, '%label');

	   	$form->addText('place','Místo konání',40)
	   		->setRequired('Vyplňte %label akce');
	    
	    $form->addSelect('zos_id','Zodpovědná osoba',$lidi);

	    $form->addTextArea('pocasi','Počasí',30)
	   		->setRequired('Vyplňte %label na akci');
	    
	    $form->addText('date_start', 'Začátkek', 10)
	      ->setRequired('Vyplňte datum začátku akce')
	      ->setType('date')
	      ->setDefaultValue($datum->format('Y-m-d'))
	      ->addRule(Form::PATTERN, 'Datum musí být ve formátu RRRR-MM-DD', '[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
	      ->setAttribute('class','date');
	    
	    $form->addText('time_start',null,5)
	      ->setRequired('Vyplňte čas začátku akce')
	      ->addRule(Form::LENGTH, 'Čas musí mít právě %d znaků',5)
	      ->addRule(Form::PATTERN, 'Čas musí být ve formátu HH:MM', '[0-2]{1}\d{1}:[0-5]{1}\d{1}')
	      ->setRequired('Vyplňte čas začátku akce')
	      ->setType('time')
	      ->setAttribute('class','time')
	      ->setDefaultValue($datum->format('H:i'));

		$form->addText('date_end', 'Konec', 10)
	      ->setRequired('Vyplňte datum konce akce')
	      ->setType('date')
	      ->setDefaultValue($datum->format('Y-m-d'))
	      ->addRule(Form::PATTERN, 'Datum musí být ve formátu RRRR-MM-DD', '[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
	      ->setAttribute('class','date');

		$form->addText('time_end',null,5)
	      ->setRequired('Vyplňte čas konce akce')
	      ->addRule(Form::LENGTH, 'Čas musí mít právě %d znaků',5)
	      ->addRule(Form::PATTERN, 'Čas musí být ve formátu HH:MM', '[0-2]{1}\d{1}:[0-5]{1}\d{1}')
	      ->setRequired('Vyplňte čas konce akce')
	      ->setType('time')
	      ->setAttribute('class','time')
	      ->setDefaultValue($datum->format('H:i'));

	    $form->addTextArea('popis','Popis akce')
	    	  ->setRequired('Vyplňte %label');

//		$form->addTextArea('public','Veřejný popis akce')
//	    	  ->setRequired('Vyplňte %label');

		$akceArray = ['breh' => 'Na břehu', 'voda' => 'Ve vodě', 'majetek' => 'Na záchranu majetku'];

		$akceContainer = $form->addContainer('akce');

		foreach ($akceArray as $key => $value) {
			$akceContainer->addText('akce_'.$key,$value,2)
				->setType('number')
		      	->setRequired('Vyplňte počet akcí '.$value)
		      	->addRule(FORM::INTEGER,'Počet akcí musí být celé číslo')
		      	->addRule(FORM::RANGE,'Počet akcí musí být číslo od %d do %d',array(0, 500))
		      	->setDefaultValue(0);
		}

		$osetreniArray = ['drobne' => 'Drobné', 'vetsi' => 'Větší', 'odvoz' => 'S odvozem'];

		$osetreniContainer = $form->addContainer('osetreni');

		foreach ($osetreniArray as $key => $value) {
			$osetreniContainer->addText('osetreni_'.$key,$value,2)
				->setType('number')
		      	->setRequired('Vyplňte počet akcí '.$value)
		      	->addRule(FORM::INTEGER,'Počet akcí musí být celé číslo')
		      	->addRule(FORM::RANGE,'Počet akcí musí být číslo od %d do %d',array(0, 500))
		      	->setDefaultValue(0);
		}

	    $users = $form->addMultiplier('users', function (\Nette\Forms\Container $user) use ($lidi) {
		    
			$user->addSelect('member_id')
			  ->setItems($lidi)
		      ->setRequired('Vyberte člena');
		    
		    $user->addText('date_start', 'od', 10)
		      ->setRequired('Vyplňte datum začátku akce')
		      ->setType('date')
		      //->setDefaultValue($datum->format('Y-m-d'))
		      ->addRule(Form::PATTERN, 'Datum musí být ve formátu RRRR-MM-DD', '[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
		      ->setAttribute('class','date');
		    
		    $user->addText('time_start',null,5)
		      ->setRequired('Vyplňte čas začátku akce')
		      ->addRule(Form::LENGTH, 'Čas musí mít právě %d znaků',5)
		      ->addRule(Form::PATTERN, 'Čas musí být ve formátu HH:MM', '[0-2]{1}\d{1}:[0-5]{1}\d{1}')
		      ->setType('time')
		      ->setAttribute('class','time');		      

			$user->addText('date_end', 'do', 10)
		      ->setRequired('Vyplňte datum konce akce')
		      ->setType('date')
		      ->addRule(Form::PATTERN, 'Datum musí být ve formátu RRRR-MM-DD', '[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
		      ->setAttribute('class','date');

		    $user->addText('time_end',null,5)
		      ->setRequired('Vyplňte čas konce akce')
		      ->addRule(Form::LENGTH, 'Čas musí mít právě %d znaků',5)
		      ->addRule(Form::PATTERN, 'Čas musí být ve formátu HH:MM', '[0-2]{1}\d{1}:[0-5]{1}\d{1}')
		      ->setType('time')
		      ->setAttribute('class','time');		        

	        $user->addText('hodiny','hodiny',3)	        
		      ->setType('number')
		      ->setRequired('Vyplňte počet hodin')
		      ->addRule(FORM::FLOAT,'Počet hodin musí být číslo')
		      ->addRule(FORM::RANGE,'Počet hodin musí být číslo od %d do %d',array(0, 500))		      
		      ->setDefaultValue(0);
		    
	        $user->addText('placeno','placeno',3)
	        ->setType('number')
		      ->setRequired('Vyplňte počet placených hodin')
		      ->addRule(FORM::FLOAT,'Počet placených hodin musí být číslo')
		      ->addRule(FORM::RANGE,'Počet placených hodin musí být číslo od %d do %d',array(0, 500))
		      ->setDefaultValue(0);		      
		    		    
	        $user->addButton('remove', '✖')
	        	->setAttribute('class','buttonLike')
	        	->setAttribute('title','Smazat účastníka')
	            ->setAttribute('onclick', 'removeRow(this)');
	    });

	    $users->addSubmit('add', '+ Účastníci')
	        ->setValidationScope(FALSE)
	        ->addCreateOnClick(TRUE); // metodu vytváří replicator		

		
		$textArray = [
			'material_ms' => 'Použitý materiál na akce v majetku místní skupiny', 
			'material_cizi' => 'Další použitý materiál',
			'material_ztraty' => 'Ztráty a poškození materiálu', 
			'doprava' => 'Doprava na akci a způsob její úhrady'
			];

		$textContainer = $form->addContainer('texts');

		foreach ($textArray as $key => $value) $textContainer->addTextArea($key,$value);
		
		$form->addSubmit('save', 'Uložit')
			->onClick[] = [$this, 'addReportFormSubmitted'];
		
		return $form;
    }

    public function addReportFormSubmitted(){
    	$id = (int) $this->getParameter('id');
		$action = $this->getAction();
		$values = $this['reportForm']->getValues();
		
		$members = $values->users;
		unset($values->users);

		$values->date_start = new Datetime($values->date_start.' '.$values->time_start);
        $values->date_end = new Datetime($values->date_end.' '.$values->time_end);
        unset($values->time_start);
        unset($values->time_end);	
		
		foreach ($values->osetreni as $key => $value) $values[$key] = $value;
		foreach ($values->akce as $key => $value) $values[$key] = $value;
		foreach ($values->texts as $key => $value) $values[$key] = $value;
		unset($values->akce);
		unset($values->osetreni);
		unset($values->texts);


		if ($action == 'edit')
		{
			$this->akceService->getReportById($id)->update($values);
			$this->flashMessage('Záznam z akce byl aktualizován');
			$this->akceService->getReportById($id)->related('report_member')->delete();			
		}
		elseif($action == 'add')
		{
			$values->date_add = new DateTime();
			$values->id = $id;
			$values->member_id = $this->getUser()->getId();
			
			$this->akceService->addReport($values);
			$this->akceService->getAkceById($id)->update(array('report' => 1));
			$this->flashMessage('Nový záznam z akce byl v pořádku vytvořen');
		}
    	
		foreach ($members as $member) {
			$member->report_id = $id;

			$member->date_start = new Datetime($member->date_start.' '.$member->time_start);
        	$member->date_end = new Datetime($member->date_end.' '.$member->time_end);
        	unset($member->time_start);
        	unset($member->time_end);	
			unset($member->remove);	

			$this->akceService->addMemberToReport($member);
		}
		
		$this->redirect('Akce:view',$id);
    }

}
    