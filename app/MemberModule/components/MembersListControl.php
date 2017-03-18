<?php

use Nette\Application\UI\Form,
    Nette\Diagnostics\Debugger;

/**
 * Member list for logging on action
 *
 * @author     Jakub Mottl
 */
class MembersListControl extends Nette\Application\UI\Control{
    /** @var AkceService */
    private $model;
     
    /** @var   Nette\Database\Table\ActiveRow */
    private $akce;
    private $list;
    private $org;
    
    public function __construct(AkceService $model, $akce, $org = false){
        parent::__construct();
        $this->model = $model;
        $this->akce = $akce;
        $this->org = $org;
    }

    public function render(){
        $this->template->setFile(__DIR__ . '/MembersListControl.latte');
        $this->list = $this->getList();

        $this->template->members = $this->list;

        if (!$this->list) $this->list = array(0);
        
        if ($this->org) $orgList = $this->list; else 
            $orgList = $this->model->getMembersByAkceId($this->akce->id,TRUE)->fetchPairs('id','id');
        
        $this->template->isLogged = array_key_exists($this->presenter->user->getId(),$this->list);
        $this->template->akce = $this->akce;
        $this->template->isOrg = $this->org;

        $this->template->isAllow = $this->presenter->user->isInRole(get_class($this)) || in_array($this->presenter->user->id,array_keys($orgList));

        $this->template->isAllowLogin = $this->org ? $this->akce->login_org : $this->akce->login_mem;
        if ($this->akce->date_deatline < date_create()) $this->template->isAllowLogin = false;

        $this->template->render();
    }
    
    public function getList(){
        return $this->model->getMemberListForAkceComponent($this->akce->id,$this->org);
    }
    
    public function handleUnlogSelf(){
        $this->model->deleteMemberFromAction($this->presenter->user->getId(), $this->akce->id,$this->org);
        $this->flashMessage('Byl jste odhlášen z akce');
        $this->redrawControl();
    }

    public function handleLogSelf(){
        $this->model->addMemberToAction($this->presenter->user->getId(), $this->akce->id,$this->org);
        $this->flashMessage('Byl jste přihlášen na akci');
        $this->redrawControl();
    }
    
    public function handleUnlog($member_id){
        $this->model->deleteMemberFromAction($member_id, $this->akce->id,$this->org);
        $this->flashMessage('Osoba byla odebrána z akce');
        $this->redrawControl();
    }

    public function createComponentLogginForm(){
        $form = new Form;

        //$form->getElementPrototype()->class('ajax');

        if (!$this->list) $this->list = $this->getList();
        if (!$this->list) $this->list = array(0);

        $list = $this->model->getMembers()->where('NOT id',array_keys($this->list));
        $form->addSelect('member', null, $list->fetchPairs('id','jmeno'));
        $form->addSubmit('send','+');//->setAttribute('class','myfont');
        $form->onSuccess[] = [$this, 'processLogginForm'];

        return $form;            
    }
    
    public function processLogginForm(Form $form){
        $values = $form->getValues();
        $this->model->addMemberToAction($values['member'], $this->akce->id,$this->org);
        $this->flashMessage('Na akci byla přidána další osoba');

        $this->redrawControl();

    }

}
