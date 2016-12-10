<?php

namespace MemberModule;

use Nette\Application\UI\Form;
use Nette\Diagnostics\Debugger;
use Nette\DateTime;

class TimesPresenter extends LayerPresenter{

    /** @var \TimesService @inject */
    public $timesService;

    /** @var \MemberService @inject */
    public $memberService;

    /** @var array $order @persistent */
    public $order = array();
    
    /** @var array $where @persistent */
    public $where = array();

	  public function renderDefault(){
        $times = $this->timesService->getDefaultTimes();
        
        if ($this->order) $times->order(join(',',array_keys($this->order))); else $times->order('jmeno'); 

        if ($this->where) $times->where($this->where);

        $this->template->items = $times;
        $this->template->where = $this->where;
        $this->template->order = $this->order;

        $this->template->columLabels = array('jmeno'=>'Jméno','disciplina'=>'Disciplína','time'=>'Čas','date'=>'Datum','text'=>'Poznámka');
        $this->template->whereValues = array('member_id'=>'jmeno','times_disciplina_id'=>'disciplina','times.text'=>'text','date'=>'date','time'=>'time');        
  	}


    public function actionSetOrder($key,$add = false){
      if ($add) $this->order[$key] = true;
      else unset($this->order[$key]);

      $this->redirect('default');
    }  

    public function actionSetWhere($key,$value = null){
      if ($value) $this->where[$key] = $value;
      else unset($this->where[$key]);

      $this->redirect('default');
    }  

    public function renderCsv(){
        if (!$this->getUser()->isInRole($this->name)){
          $this->flashMessage('Na tuto akci máte práva','error');
          $this->redirect('default');
        }

        $times = $this->timesService->getDefaultTimes();
        
        if ($this->order) $times->order(join(',',array_keys($this->order))); else $times->order('jmeno'); 

        if ($this->where) $times->where($this->where);

        $this->template->items = $times;

        $httpResponse = $this->context->getByType('Nette\Http\Response');
        $httpResponse->setHeader('Content-Disposition','attachment; filename="vysledky.csv"');
    }

    public function renderAdd(){
        $this->order = array();
        $this->where = array();

        $this->template->nova = TRUE;

        $this->setView('edit');

        $lastTime = $this->getSession('lastTime');
        
        if (isset($lastTime->values)) {
            $form = $this['timeForm'];
            $lastTime->values->time = substr($lastTime->values->time, 3);
            $form->setDefaults($lastTime->values);
        }

        unset($lastTime->values);        
    }


    public function renderEdit($id){
        if (!$this->getUser()->isInRole($this->name)) {
          $this->flashMessage('Na tuto akci máte práva','error');
          $this->redirect('default');
        }

        $form = $this['timeForm'];
        if (!$form->isSubmitted()) {
            
            $time = $this->timesService->getTimeById($id);
            
            if (!$time) {
                $this->flashMessage('Záznam nenalezen!','error');
                $this->redirect('default');                                    
            }
            
            unset($form['another']);

            $form->setDefaults($time);

            $form['time']->setDefaultValue($time->time->format('i:s'));
            $form['date']->setDefaultValue($time->date->format('Y-m-d'));    
        }
    }

    public function actionDelete($id){
      if (!$this->getUser()->isInRole($this->name)) {
          $this->flashMessage('Na tuto akci máte práva','error');
      }else{
        $this->timesService->getTimeById($id)->delete();
        $this->flashMessage('Výsledek by smazán');
      }

      $this->redirect('default');  
    }

    protected function createComponentTimeForm(){
        $form = new Form();

        $form->addSelect('member_id','Jméno',
            $this->memberService->getMembersArray()
        )
            ->setRequired('Vyplňte jméno');
        
        $form->addSelect('times_disciplina_id','Disciplína',
            $this->timesService->getTimesDisciplineArray()
        )
            ->setRequired('Vyplňte disciplínu');
        
        $form->addText('date', 'Datum', 10)
          ->setRequired('Vyplňte datum')
          ->setType('date')
          ->setDefaultValue(date('Y-m-d'))  
          ->addRule(Form::PATTERN, 'Datum musí být ve formátu RRRR-MM-DD', '[1-2]{1}\d{3}-[0-1]{1}\d{1}-[0-3]{1}\d{1}')
          ->setAttribute('class','date');
    
        $form->addText('time','Výsledný čas',5)
          ->setRequired('Vyplňte %label')
          ->addRule(Form::LENGTH, 'Čas musí mít právě %d znaků',5)
          ->addRule(Form::PATTERN, 'Čas musí být ve formátu MM:SS', '[0-5]{1}\d{1}:[0-5]{1}\d{1}')
          ->setType('time')
          ->setDefaultValue('00:00')
          ->setAttribute('class','time')
          ->getLabelPrototype()->class('hint')->title = 'Čas musí být ve formátu MM:SS';

        $form->addText('text','Poznámka',30);

        $form->addCheckBox('another','Vložit další záznam')
            ->setDefaultValue(TRUE);

        $form->addSubmit('save','Uložit');

        $form->onSuccess[] = callback($this, 'timeFormSubmitted');

        return $form;        
    }

    public function timeFormSubmitted(Form $form){
        $id = (int) $this->getParameter('id');

        $values = $form->getValues();

        if ($id) {
          unset($values->another);
          $values->time = '00:'.$values->time;
          $this->timesService->getTimeById($id)->update($values);
          $this->flashMessage('Výsledek byl změněn');
        }else {
          $values->date_add = new Datetime;

          $lastTime = $this->getSession('lastTime');
          
          if ($values->another) {
            $lastTime->values = $values;
            $another = $values->another;
          }else unset($lastTime->values);

          unset($values->another);
          $values->time = '00:'.$values->time;

          $this->timesService->addTime($values);
          
          $this->flashMessage('Výsledek byl v pořádku přidán'); 
        }
        
        if (isset($another)) $this->redirect('add'); else $this->redirect('default');
      }

}