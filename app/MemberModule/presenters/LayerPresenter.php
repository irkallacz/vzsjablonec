<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 22.11.2016
 * Time: 23:09
 */

namespace App\MemberModule\Presenters;

use App\MemberModule\Components\AnketaControl;
use Nette\Security\IUserStorage;

abstract class LayerPresenter extends BasePresenter{

    protected function beforeRender(){

        $mainMenu = [
        	[   'title' => 'novinky',      'link' => 'News:',      'current' => 'News:*',      'icon' => 'home'            ],
	        [   'title' => 'akce',         'link' => 'Akce:',      'current' => 'Akce:*',      'icon' => 'calendar'        ],
	        [   'title' => 'forum',        'link' => 'Forum:',     'current' => 'Forum:*',     'icon' => 'chat-empty'      ],
	        [   'title' => 'adresář',      'link' => 'Member:',    'current' => 'Member:*',    'icon' => 'address-book-o'  ],
	        [   'title' => 'dokumenty',    'link' => 'Dokumenty:', 'current' => 'Dokumenty:*', 'icon' => 'doc-text'        ],
	        [   'title' => 'ankety',       'link' => 'Ankety:',    'current' => 'Ankety:*',    'icon' => 'list-bullet'     ],
	        [   'title' => 'výsledky',     'link' => 'Times:',     'current' => 'Times:*',     'icon' => 'clock'           ],
	        [   'title' => 'hlasovani',    'link' => 'Hlasovani:', 'current' => 'Hlasovani:*', 'icon' => 'balance-scale'   ]
        ];

	    if ($this->getUser()->isInRole('Member:Mail'))
	    	$mainMenu[] = ['title' => 'email', 'link' => 'Mail:', 'current' => 'Mail:*', 'icon' => 'mail'];

	    $mainMenu[] = ['title' => 'galerie', 'link' => ':Photo:Myself:', 'current' => ':Photo:Myself:*', 'icon' => 'picture'];

	    $this->template->mainMenu = $mainMenu;
    }

    protected function startup(){
        parent::startup();

        if (!$this->user->isLoggedIn()) {
            if ($this->user->getLogoutReason() === IUserStorage::INACTIVITY){
                    $this->flashMessage('You have been signed out due to inactivity. Please sign in again.');
            }
            $backlink = $this->storeRequest();
            $this->redirect('Sign:in', ['backlink' => $backlink]);
        }
    }

    public function createComponentAnketa(){
        return new AnketaControl($this->anketyService);
	}

}
