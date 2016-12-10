<?php

namespace PhotoModule;

use Nette\Application\UI\Presenter;


abstract class BasePresenter extends Presenter{

    const photoDir = 'albums';

//    protected function startup(){
//        parent::startup();
//        $this->getUser()->getStorage()->setNamespace('photo');
//    }
//
//    protected function afterRender(){
//        parent::afterRender();
//
//        if (!$this->context->parameters['productionMode']) {
//            $this->template->basePath .= '/photo/';
//            $this->template->baseUri .= '/photo';
//        }
//
//    }

    protected function beforeRender(){
        parent::beforeRender();

        \LayoutHelpers::$thumbDirUri = self::photoDir.'/thumbs';
        $this->template->registerHelper('thumb', 'LayoutHelpers::thumb');
        $this->template->registerHelper('timeAgoInWords', 'Helpers::timeAgoInWords');

        $this->template->photoDir = self::photoDir;

        $mainMenu = ['News:'=>'novinky','Album:'=>'alba'];

        if ($this->getUser()->isLoggedIn()) {
            $mainMenu['Myself:'] = 'můj účet';
            $mainMenu[':Member:Feed:albums'] = 'rss';
            $mainMenu[':Member:News:'] = 'member';
            $mainMenu['Sign:out'] = 'odhlášení';
        } else {
          $mainMenu['Sign:in'] = 'přihlášení';
        }

        $this->template->mainMenu = $mainMenu;
    }

    public function getIdFromSlug($slug){
        //$slug = (string) $this->params['slug'];
        return (int) substr($slug,0,strstr($slug,'-')-1);
    }

    public static function nullString($value){
        return empty($value) ? null : $value;
    }

    public function checkUserLoggin(){
        if (!$this->getUser()->isLoggedIn()) {
            if ($this->getUser()->logoutReason === \Nette\Security\IUserStorage::INACTIVITY) {
                    $this->flashMessage('Byl jste odhlášen z důvodu dlouhé nečinosti. Přihlaste se prosím znovu.');
            }
            $backlink = $this->storeRequest();
            $this->redirect('Sign:in', array('backlink' => $backlink));
        }
    }


    protected function registerTexy(){
        $texy = \TexyFactory::createTexy();
        $this->template->registerHelper('texy', callback($texy, 'process'));
    }
}
