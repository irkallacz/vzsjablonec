<?php
namespace App\MemberModule\Presenters;

use App\Model\AnketyService;
use Joseki\Webloader\JsMinFilter;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class AnketyPresenter extends LayerPresenter{

	/** @var AnketyService @inject */
	public $anketyService;

	public function renderDefault(){
		$ankety = $this->anketyService->getAnkety();
		$this->template->ankety = $ankety;
		$this->template->addFilter('timeAgoInWords', 'Helpers::timeAgoInWords');
	}

	public function renderView($id){
		$anketa = $this->anketyService->getAnketaById($id);

		$user_id = $this->getUser()->getId();

		if (!$anketa) {
      		$this->flashMessage('Anekta nenalezena!','error');
      		$this->redirect('default');
    	}

		$this->template->anketa = $anketa;
		$this->template->members = $anketa->related('anketa_member');
		$this->template->odpovedi = $anketa->related('anketa_odpoved')->order('text');

		$this->template->title = $anketa->title;
	}

	public function renderAdd(){
		$this->setView('edit');
		$this->template->nova = TRUE;
	}

	public function renderEdit($id){
	    $this->template->nova = false;

	    $form = $this['anketaForm'];
	    if (!$form->isSubmitted()) {
	        $anketa = $this->anketyService->getAnketaById($id);

	        if (!$anketa) {
      			$this->flashMessage('Anekta nenalezena!','error');
      			$this->redirect('default');
    		}

		    if ((!$this->getUser()->isInRole($this->name))and($anketa->member_id!=$this->getUser()->getId())) {
            	$this->flashMessage('Nemáte práva na tuto akci','error');
            	$this->redirect('view',$id);
        	}

		    $odpovedi = $this->anketyService->getOdpovediByAnketaId($id);

		    $form->setDefaults($anketa);
		    //$form['users']->setValues($odpovedi);

		    $this->template->title = ucfirst($anketa->title);
	    }
	}

	public function handleVote($odpoved){
		$id = (int) $this->getParameter('id');
		$odpoved = (int) $odpoved;

		$anketa = $this->anketyService->getAnketaById($id);

		if ((!$anketa)or($anketa->locked)) {
            $this->flashMessage('V této anketě nemůžete hlasovat','error');
            $this->redirect('view',$id);
        }

		$odpovedi = $anketa->related('anketa_odpoved')->fetchPairs('id','id');

		if (!in_array($odpoved, $odpovedi)) {
            $this->flashMessage('Pro tuto odpověď nemůžete hlasovat','error');
            $this->redirect('view',$id);
        }

		$values = array(
			'member_id' => $this->getUser()->getId(),
			'anketa_id' => $id,
			'anketa_odpoved_id' => $odpoved,
			'date_add' => new Datetime
		);

		$this->anketyService->addVote($values);
		$this->redirect('view',$id);
	}

	public function actionDeleteVote($id){
		$anketa = $this->anketyService->getAnketaById($id);

		if ((!$anketa)or($anketa->locked)) {
            $this->flashMessage('V této anketě nemůžete zrušit hlas','error');
            $this->redirect('view',$id);
        }

		$vote = $this->anketyService->getMemberVote($id,$this->getUser()->getId());

		if (!$vote) {
            $this->flashMessage('V této anketě jste nehlasoval','error');
            $this->redirect('view',$id);
        }
		$this->anketyService->deleteMemberVote($id,$this->getUser()->getId());

		$this->redirect('Ankety:view',$id);
	}

	public function actionDelete($id){
		$anketa = $this->anketyService->getAnketaById($id);

		if ((!$this->getUser()->isInRole($this->name))and($anketa->member_id != $this->getUser()->getId())) {
            $this->flashMessage('Nemáte práva na tuto akci','error');
            $this->redirect('view',$id);
        }
		$this->anketyService->deleteAnketaById($id);

		$this->redirect('Ankety:default');
	}

	public function actionLock($id,$lock){
		$anketa = $this->anketyService->getAnketaById($id);

		if ((!$this->getUser()->isInRole($this->name))and($anketa->member_id != $this->getUser()->getId())) {
            $this->flashMessage('Nemáte práva na tuto akci','error');
            $this->redirect('view',$id);
        }

		$anketa->update(array('locked' => $lock));
		$this->redirect('Ankety:view',$id);
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

	    $users = $form->addMultiplier('users', function (\Nette\Forms\Container $user) {
	    	$user->addText('text', 'Odpověď', 30)
				->setAttribute('spellcheck', 'true');

	    	$user->addHidden('id');

	        $user->addButton('remove', '✖')
	        	->setAttribute('class','buttonLike')
	        	->setAttribute('title','Smazat odpověď')
	        	->setAttribute('onClick','removeRow(this)');

	    }, 0);

	    $users->addCreateButton('Přidat odpovědi');

		$form->addHidden('pocet',0);

		$form->addSubmit('save', 'Uložit')
			->onClick[] = [$this, 'addAnketaFormSubmitted'];

		$id = $this->getParameter('id');

		if ($id){
			$odpovedi = $this->anketyService->getOdpovediByAnketaId($id)->fetchPairs('id');
			$form->setDefaults(['users' => $odpovedi, 'pocet' => count($odpovedi)]);
		}

		return $form;
    }

    public function addAnketaFormSubmitted(){
    	$id = (int) $this->getParameter('id');

		$values = $this['anketaForm']->getValues();
		$datum = new DateTime();
		$values->date_update = $datum;

		$values->title = ucfirst($values->title);

		$pocet = $values->pocet;
		unset($values->pocet);
		$odpovedi = $values->users;
		unset($values->users);

		if ($id){
			$this->anketyService->getAnketaById($id)->update($values);
			$anketa_id = $id;
			$this->flashMessage('Anketa byla aktualizována');
		}
		else {
			$values->member_id = $this->getUser()->getId();
			$values->date_add = $datum;
			$values->locked = FALSE;

			$anketa = $this->anketyService->addAnketa($values);
			$anketa_id = $anketa->id;
			$this->flashMessage('Nová anketa byla v pořádku vytvořena');
		}

		if ($pocet != count($odpovedi)) {
			if ($id) {
				$this->anketyService->deleteVotesByAnketaId($id);
				$this->anketyService->deleteOdpovediByAnketaId($id);
			}

			foreach ($odpovedi as $odpoved) {
				$array = ['anketa_id' => $anketa_id, 'text' => ucfirst($odpoved->text)];
				$this->anketyService->addOdpoved($array);
			}
		}else
		{
			foreach ($odpovedi as $odpoved) {
				$this->anketyService->getOdpovedById($odpoved->id)->update(array('text' => ucfirst($odpoved->text)));
			}
		}

		$this->redirect('view',$anketa_id);
    }

}
    