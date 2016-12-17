<?php

namespace MemberModule;

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 22.11.2016
 * Time: 23:09
 */

abstract class LayerPresenter extends BasePresenter{

    protected function beforeRender(){
        $mainMenu = ['News:'=>'novinky','Akce:'=>'akce','Forum:'=>'fórum','Member:'=>'adresář',
            'Dokumenty:'=>'dokumenty', 'Ankety:'=>'ankety', 'Times:'=>'výsledky','Hlasovani:'=>'hlasování'];

        if ($this->getUser()->isInRole('Member:Mail')) $mainMenu['Mail:'] = 'email';

        $mainMenu[':Photo:Myself:default'] = 'galerie';

        $this->template->mainMenu = $mainMenu;
    }

    protected function startup(){
        parent::startup();

        \Kdyby\Extension\Forms\Replicator\Replicator::register();

        if (!$this->user->isLoggedIn()) {
            if ($this->user->getLogoutReason() === \Nette\Security\IUserStorage::INACTIVITY){
                    $this->flashMessage('You have been signed out due to inactivity. Please sign in again.');
            }
            $backlink = $this->storeRequest();
            $this->redirect('Sign:in', ['backlink' => $backlink]);
        }
    }

    public function createComponentAnketa(){
        return new \AnketaControl($this->anketyService);
}

}
