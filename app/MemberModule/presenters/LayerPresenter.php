<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 22.11.2016
 * Time: 23:09
 */

namespace App\MemberModule\Presenters;

use Nette\Application\AbortException;
use Nette\Application\ForbiddenRequestException;
use Nette\Security\IUserStorage;
use Nette\Utils\ArrayHash;
use Tracy\Debugger;

abstract class LayerPresenter extends BasePresenter {

	/**
	 *
	 * @throws AbortException
	 */
	protected function startup() {
		parent::startup();

		$this->checkLogin();
	}

	/**
	 * @throws AbortException
	 */
	protected function checkLogin(){
		if (!$this->user->isLoggedIn()) {
			if ($this->user->getLogoutReason() === IUserStorage::INACTIVITY) {
				$this->flashMessage('Byl jste odhlášen z důvodu neaktivity. Přihlaste se prosím znovu.');
			}
			$backlink = $this->storeRequest();
			$this->redirect('Sign:in', ['backlink' => $backlink]);
		}
	}

	/**
	 * @param $element
	 * @throws ForbiddenRequestException
	 * @throws AbortException
	 */
	public function checkRequirements($element) {
		parent::checkRequirements($element);

		$this->checkLogin();

		if ($element->hasAnnotation('allow')) {
			$role = $element->getAnnotation('allow');
			if (!$this->getUser()->isInRole($role)) {
				throw new ForbiddenRequestException('Na tuto akci nemáte právo');
			}
		}
	}

	/**
	 *
	 */
	protected function beforeRender() {
		$mainMenu = [
			['title' => 'novinky',  	'link' => 'News:',      	'current' => 'News:*',      	'role'=> 'user', 	'icon' => 'home'            ],
			['title' => 'akce',     	'link' => 'Akce:',      	'current' => 'Akce:*',      	'role'=> 'user', 	'icon' => 'calendar'        ],
			['title' => 'forum',    	'link' => 'Forum:',     	'current' => 'Forum:*',     	'role'=> 'user', 	'icon' => 'comments-o'      ],
			['title' => 'adresář',  	'link' => 'User:',    		'current' => 'User:*',    		'role'=> 'user', 	'icon' => 'address-book-o'  ],
			['title' => 'dokumenty',	'link' => 'Dokumenty:', 	'current' => 'Dokumenty:*', 	'role'=> 'user', 	'icon' => 'file-text-o'		],
			['title' => 'ankety',   	'link' => 'Ankety:',    	'current' => 'Ankety:*',    	'role'=> 'member', 	'icon' => 'list-ul'			],
			['title' => 'hlasovani',	'link' => 'Hlasovani:', 	'current' => 'Hlasovani:*', 	'role'=> 'member', 	'icon' => 'balance-scale'	],
			['title' => 'zprávy', 		'link' => 'Mail:', 			'current' => 'Mail:*', 			'role'=> 'member',	'icon' => 'envelope-o'		],
			['title' => 'galerie', 		'link' => ':Photo:Sign:in',	'current' => ':Photo:Album:*', 	'role'=> 'member',	'icon' => 'image'			],
		];

		$this->template->mainMenu = ArrayHash::from($mainMenu);
	}

}
