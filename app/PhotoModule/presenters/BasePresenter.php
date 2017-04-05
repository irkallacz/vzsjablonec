<?php

namespace App\PhotoModule\Presenters;

use Nette\Application\UI\Presenter;

abstract class BasePresenter extends Presenter{

    const photoDir = 'albums';

    protected function afterRender(){
        parent::afterRender();

        if (!$this->context->parameters['productionMode']) {
            $this->template->basePath .= '/photo/';
            $this->template->baseUri .= '/photo';
        }

    }

    protected function beforeRender(){
        parent::beforeRender();

        \LayoutHelpers::$thumbDirUri = self::photoDir.'/thumbs';
        $this->template->addFilter('thumb', 'LayoutHelpers::thumb');
        $this->template->addFilter('timeAgoInWords', 'Helpers::timeAgoInWords');

        $this->template->photoDir = self::photoDir;

	    $mainMenu = [
		    ['title' => 'novinky', 'link' => 'News:',  'current' => 'News:*'],
		    ['title' => 'alba',    'link' => 'Album:', 'current' => 'Album:*']
	    ];

	    if ($this->getUser()->isLoggedIn()) {
		    $mainMenu[] = ['title' => 'můj účet',     'link' => 'Myself:',                'current' => 'Myself:*'];
		    $mainMenu[] = ['title' => 'rss',          'link' => ':Member:Feed:albums',    'current' => ':Member:Feed:albums'];
		    $mainMenu[] = ['title' => 'member',       'link' => ':Member:News:',          'current' => ':Member:News:'];
		    $mainMenu[] = ['title' => 'odhlášení',    'link' => 'Sign:out',               'current' => 'Sign:out'];
	    }else{
		    $mainMenu[] = ['title' => 'přihlášení',   'link' => 'Sign:in',                'current' => 'Sign:in'];
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
            $this->redirect('Sign:in', ['backlink' => $backlink]);
        }
    }


    protected function registerTexy(){
        $texy = \TexyFactory::createTexy();
        $this->template->addFilter('texy', [$texy, 'process']);
    }
}
