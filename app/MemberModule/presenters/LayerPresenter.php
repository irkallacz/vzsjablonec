<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 22.11.2016
 * Time: 23:09
 */

namespace App\MemberModule\Presenters;

use App\MemberModule\Components\AnketaControl;
use Nette\Application\ForbiddenRequestException;
use Nette\Reflection\ClassType;
use Nette\Reflection\Method;
use Nette\Security\IUserStorage;
use Nette\Utils\ArrayHash;
use Nette\Utils\Reflection;
use Tracy\Debugger;

abstract class LayerPresenter extends BasePresenter {

	protected function startup() {
		parent::startup();

		$this->checkLogin();
	}

	protected function checkLogin(){
		if (!$this->user->isLoggedIn()) {
			if ($this->user->getLogoutReason() === IUserStorage::INACTIVITY) {
				$this->flashMessage('Byl jste odhlášen z důvodu neaktivity. Přihlaste se prosím znovu.');
			}
			$backlink = $this->storeRequest();
			$this->redirect('Sign:in', ['backlink' => $backlink]);
		}
	}

	public function checkRequirements($element) {
		$this->checkLogin();

		if ($element->hasAnnotation('allow')) {
			$role = $element->getAnnotation('allow');
			if (!$this->getUser()->isInRole($role)) {
				throw new ForbiddenRequestException('Na tuto akci nemáte právo');
			}
		}

		parent::checkRequirements($element);
	}

	protected function beforeRender() {
		$mainMenu = [
			['title' => 'novinky',  	'link' => 'News:',      	'current' => 'News:*',      	'role'=> 'user', 	'icon' => 'home'            ],
			['title' => 'akce',     	'link' => 'Akce:',      	'current' => 'Akce:*',      	'role'=> 'user', 	'icon' => 'calendar'        ],
			['title' => 'forum',    	'link' => 'Forum:',     	'current' => 'Forum:*',     	'role'=> 'user', 	'icon' => 'chat-empty'      ],
			['title' => 'adresář',  	'link' => 'Member:',    	'current' => 'Member:*',    	'role'=> 'user', 	'icon' => 'address-book-o'  ],
			['title' => 'dokumenty',	'link' => 'Dokumenty:', 	'current' => 'Dokumenty:*', 	'role'=> 'user', 	'icon' => 'doc-text'        ],
			['title' => 'ankety',   	'link' => 'Ankety:',    	'current' => 'Ankety:*',    	'role'=> 'member', 	'icon' => 'list-bullet'     ],
			['title' => 'výsledky', 	'link' => 'Times:',     	'current' => 'Times:*',     	'role'=> 'member', 	'icon' => 'clock'           ],
			['title' => 'hlasovani',	'link' => 'Hlasovani:', 	'current' => 'Hlasovani:*', 	'role'=> 'member', 	'icon' => 'balance-scale'   ],
			['title' => 'email', 		'link' => 'Mail:', 			'current' => 'Mail:*', 			'role'=> 'board',	'icon' => 'mail'			],
			['title' => 'galerie', 		'link' => ':Photo:Myself:', 'current' => ':Photo:Myself:*', 'role'=> 'member',	'icon' => 'picture'			],
		];

		$this->template->mainMenu = ArrayHash::from($mainMenu);
	}

	public function sendRestoreMail($member, $session) {
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../templates/Mail/restorePassword.latte');
		$template->session = $session;

		$mail = $this->getNewMail();

		$mail->addTo($member->mail, $member->surname . ' ' . $member->name);
		$mail->setSubject('[VZS Jablonec] Obnova hesla');
		$mail->setHTMLBody($template);

		$this->mailer->send($mail);
	}

}
