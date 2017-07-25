<?php

namespace App\MemberModule\Presenters;

use App\Model\HlasovaniService;
use Joseki\Webloader\JsMinFilter;
use Nette\Application\UI\Form;
use Nette\Utils\Arrays;
use Nette\Utils\DateTime;

class HlasovaniPresenter extends LayerPresenter{

	/** @var HlasovaniService @inject */
	public $hlasovani;

	/** @var \Nette\Mail\IMailer @inject */
	public $mailer;

	public function renderDefault(){
		$ankety = $this->hlasovani->getAnkety();
		if (!$this->user->isInRole('Board')) $ankety->where('date_deatline < NOW() OR locked = ?',1);

		$this->template->ankety = $ankety;
	}

	public function renderView($id){		
		$anketa = $this->hlasovani->getAnketaById($id);

		$locked = $anketa->locked;
		if ($anketa->date_deatline < date_create()) $locked = 1;

		if (!$anketa) {
      		$this->flashMessage('Hlasování nenalezeno!','error');
      		$this->redirect('default');                                    
    	}

		if ((!$locked)and(!$this->user->isInRole('Board'))) {
      		$this->flashMessage('Nemáte právo prohlížet toto hlasování!','error');
      		$this->redirect('default');                                    
    	}

		$this->template->anketa = $anketa;
		$this->template->locked = $locked;
		$this->template->items = $anketa->related('hlasovani_odpoved')->order('text');
		
		$members = $this->hlasovani->getMembersByAnketaId($id)->order(':hlasovani_member.date_add');

		$this->template->members = $members;//$members->fetchPairs('id','jmeno');

		$this->template->celkem = count($members);

		$memberList = $members->fetchPairs('id','hlasovani_odpoved_id');
		$this->template->memberList = $memberList;
		$this->template->isLogged = Arrays::get($memberList, $this->getUser()->getId(),0);

		$this->template->title = $anketa->title;
	}

	public function renderAdd(){
		$form = $this['anketaForm'];
		$form['users'][0]['text']->setValue('Jsem pro');
		$form['users'][1]['text']->setValue('Jsem proti');
		$form['users'][2]['text']->setValue('Zdržuji se hlasovaní');

		$this->setView('edit');
		$this->template->nova = TRUE;
	}

	public function renderEdit($id){
	    $this->template->nova = false;

	    $form = $this['anketaForm'];
	    if (!$form->isSubmitted()) {
	        $anketa = $this->hlasovani->getAnketaById($id);
	        
	        if (!$anketa) {
      			$this->flashMessage('Hlasování nenalezeno!','error');
      			$this->redirect('default');                                    
    		}

			if (!$this->user->isInRole('Board')) {
				$this->flashMessage('Nemáte právo editovat toto hlasování!','error');
				$this->redirect('default');                                    
			}

		    if ((!$this->getUser()->isInRole($this->name))and($anketa->member_id!=$this->getUser()->getId())) {
            	$this->flashMessage('Nemáte právo editovat toto hlasování','error');
            	$this->redirect('default');
        	}

		    $odpovedi = $this->hlasovani->getOdpovediByAnketaId($id);

		    $form['pocet']->setDefaultValue(count($odpovedi));    
		    $form->setDefaults($anketa);
		    $form['users']->setValues($odpovedi);

		    $this->template->title = ucfirst($anketa->title);
	    }
	}

	public function handleVote($odpoved){
		$id = (int) $this->getParameter('id');
		$odpoved = (int) $odpoved;
		
		$anketa = $this->hlasovani->getAnketaById($id);

		if ((!$anketa)or($anketa->locked)or(!$this->user->isInRole('Board'))) {
            $this->flashMessage('V tomto hlasovaní nemůžete hlasovat','error');
            $this->redirect('default');
        }

		$odpovedi = $anketa->related('hlasovani_odpoved')->fetchPairs('id','id');
		
		if (!in_array($odpoved, $odpovedi)) {
            $this->flashMessage('Pro tuto odpověď nemůžete hlasovat','error');
            $this->redirect('view',$id);
        }

		$values = [
			'member_id' => $this->getUser()->getId(),
			'hlasovani_id' => $id,
			'hlasovani_odpoved_id' => $odpoved,
			'date_add' => new DateTime()
		];
		
		$this->hlasovani->addVote($values);
		$this->redirect('view',$id);
	}

	public function actionDelete($id){
		$anketa = $this->hlasovani->getAnketaById($id);
		
		if (!$this->user->isInRole('Board')) {
      		$this->flashMessage('Nemáte právo smazat toto hlasování!','error');
      		$this->redirect('default');                                    
    	}

		if ((!$this->getUser()->isInRole($this->name))and($anketa->member_id!=$this->getUser()->getId())) {
            $this->flashMessage('Nemáte práva na tuto akci','error');
            $this->redirect('view',$id);
        }
		
		$this->hlasovani->deleteAnketaById($id);
		
		$this->redirect('default');
	}

	public function actionLock($id,$lock){
		$anketa = $this->hlasovani->getAnketaById($id);
		
		if (!$this->user->isInRole('Board')) {
      		$this->flashMessage('Nemáte právo měnit toto hlasování!','error');
      		$this->redirect('default');                                    
    	}

		if ((!$this->getUser()->isInRole($this->name))and($anketa->member_id!=$this->getUser()->getId())) {
            $this->flashMessage('Nemáte práva na tuto akci','error');
            $this->redirect('view',$id);
        }

		$anketa->update(['locked' => $lock, 'date_update' => new Datetime]);
		$this->redirect('view',$id);
	}

	public function createComponentTexylaJs(){
      $files = new \WebLoader\FileCollection(WWW_DIR . '/texyla/js');
      $files->addFiles(['texyla.js','selection.js','texy.js','buttons.js','cs.js','dom.js','view.js','window.js']);
      $files->addFiles(['../plugins/table/table.js']);
      $files->addFiles(['../plugins/color/color.js']);
      $files->addFiles(['../plugins/symbol/symbol.js']);
      $files->addFiles(['../plugins/textTransform/textTransform.js']);
      $files->addFiles([WWW_DIR . '/js/texyla_anketa.js']);
      

      $compiler = \WebLoader\Compiler::createJsCompiler($files, WWW_DIR . '/texyla/temp');
      $compiler->addFileFilter(new JsMinFilter());

      return new \WebLoader\Nette\JavaScriptLoader($compiler, $this->template->basePath . '/texyla/temp');
  	}

	protected function createComponentAnketaForm(){
	    $form = new Form;

	   	$form->addText('title','Název',30)
			->setAttribute('spellcheck', 'true');

	    $form->addTextArea('text','Otázka',60)
			->setAttribute('spellcheck', 'true');

		$form['date_deatline'] = new \DateInput('Konec hlasování');
		$form['date_deatline']->setRequired('Vyplňte datum konce hlasování')
			->setDefaultValue(new DateTime());

	    $users = $form->addMultiplier('users', function (\Nette\Forms\Container $user) {
	    	$user->addText('text', 'Odpověď', 30);
		    $user->addHidden('id');

	        // $user->addButton('remove', '✖')
	        // 	->setAttribute('class','buttonLike')
	        // 	->setAttribute('title','Smazat odpověď')
	        // 	->setAttribute('onClick','removeRow(this)');
	            
	    }, 3);

		//$users->addCreateButton('Přidat odpovědi'); // metodu vytváří replicator

		$form->addHidden('pocet',0);

		$form->addSubmit('save', 'Uložit')
			->onClick[] = [$this, 'addAnketaFormSubmitted'];

		$id = $this->getParameter('id');

		if ($id){
			$odpovedi = $this->hlasovani->getOdpovediByAnketaId($id)->fetchPairs('id');
			$form->setDefaults(['users' => $odpovedi, 'pocet' => count($odpovedi)]);
		}


		return $form;
    }

    public function addAnketaFormSubmitted(){
    	$id = (int) $this->getParameter('id');

		$values = $this['anketaForm']->getValues();
		$datum = new Datetime();
		
		$values->title = ucfirst($values->title);
		
		$values->date_update = $datum;
		$pocet = $values->pocet;
		unset($values->pocet);
		$odpovedi = $values->users;
		unset($values->users);

		if ($id){
			$this->hlasovani->getAnketaById($id)->update($values);
			$anketa_id = $id;

			$this->flashMessage('Hlasování bylo aktualizováno');
		}
		else {
			$values->member_id = $this->getUser()->getId();
			$values->date_add = $datum;
			
			$anketa = $this->hlasovani->addAnketa($values);
			$anketa_id = $anketa->id;

			$this->sendHlasovaniMail($anketa,$odpovedi);
			$this->flashMessage('Nové hlasovaní bylo v pořádku vytvořeno');
		}
    	
		if ($pocet != count($odpovedi)) {
			if ($id) {
				$this->hlasovani->deleteVotesByAnketaId($id);
				$this->hlasovani->deleteOdpovediByAnketaId($id);
			}

			foreach ($odpovedi as $odpoved) {
				$array = array('hlasovani_id' => $anketa_id, 'text' => ucfirst($odpoved->text));
				$this->hlasovani->addOdpoved($array);
			}
		}else {
			foreach ($odpovedi as $odpoved) {
				$this->hlasovani->getOdpovedById($odpoved->id)->update(['text' => ucfirst($odpoved->text)]);
			}
		}

		$this->redirect('view',$anketa_id);
    }

    protected function sendHlasovaniMail($hlasovani,$odpovedi){
        $template = $this->createTemplate();
        $template->setFile(__DIR__ . '/../templates/Mail/newHlasovani.latte');
        $template->hlasovani = $hlasovani;
        $template->odpovedi = $odpovedi;

        $mail = $this->getNewMail();
        $mail->addTo('predstavenstvo@vzs-jablonec.cz');
		$mail->setSubject('[VZS Jablonec] Nové hlasování představenstva');
        $mail->setHTMLBody($template);

        $this->mailer->send($mail);

    }


}