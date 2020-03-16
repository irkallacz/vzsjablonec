<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 4.8.2017
 * Time: 10:16
 */

namespace App\MemberModule\Components;


use App\Model\AkceService;
use App\Model\UserService;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Control;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;

class SignEventControl extends Control {

	const ORG = 1;
	const USER = 0;

	/** @var AkceService */
	private $akceService;

	/** @var UserService */
	private $userService;

	/** @var ActiveRow */
	private $akce;

	/** @var array */
	private $userList = [];

	/** @var array */
	private $orgList = [];

	/**
	 * SignEventControl constructor.
	 * @param AkceService $akceService
	 * @param UserService $userService
	 * @param ActiveRow $akce
	 */
	public function __construct(AkceService $akceService, UserService $userService, ActiveRow $akce) {
		parent::__construct();
		$this->akceService = $akceService;
		$this->userService = $userService;
		$this->akce = $akce;

		$this->userList = $this->getMemberList(self::USER)->fetchPairs('user_id', 'user_id');
		$this->orgList = $this->getMemberList(self::ORG)->fetchPairs('user_id', 'user_id');
	}

	public function render() {
		$this->template->setFile(__DIR__ . '/SignEventControl.latte');

		$this->template->items = $this->getMemberList();
		$this->template->akce = $this->akce;

		$this->template->hasUsers = !empty($this->getLocalMemberList());

		$this->template->userIsInUserList = $this->userIsInList(self::USER);
		$this->template->userIsInOrgList = $this->userIsInList(self::ORG);

		$this->template->isUserAllow = $this->userIsAllowToLog(self::USER);
		$this->template->isOrgAllow = $this->userIsAllowToLog(self::ORG);

		$this->template->render();
	}

	private function getLocalMemberList(){
		return array_merge($this->userList, $this->orgList);
	}

	/**
	 * @param bool $isOrg
	 */
	private function getMemberList(bool $isOrg = NULL) {
		$list = $this->akce
			->related(AkceService::TABLE_AKCE_MEMBER_NAME)
			->where('deleted_by ?', NULL);

		if (!is_null($isOrg)) $list->where('organizator', $isOrg);

		return $list;
	}

	/**
	 * @param bool $isOrg
	 */
	private function userIsInList(bool $isOrg = NULL) {
		$userId = $this->getPresenter()->getUser()->getId();
		if (is_null($isOrg)) {
			return in_array($userId, $this->getLocalMemberList());
		} else {
			return $isOrg ? in_array($userId, $this->orgList) : in_array($userId, $this->userList);
		}
	}

	/**
	 * @param bool $toOrg
	 */
	private function userIsAllowToLog(bool $toOrg) {
		return (
			($this->getPresenter()->getUser()->isInRole('member'))
			and
			(date_create() <= $this->akce->date_end)
			and
			(date_create() <= $this->akce->date_deatline)
			and
			(
				(($toOrg == self::ORG) and ($this->akce->login_org))
				or
				(($toOrg == self::USER)) and ($this->akce->login_mem)
			)
		);
	}

	/**
	 * @param int $userId
	 * @param bool $isOrg
	 */
	private function logUser(int $userId, bool $isOrg) {
		$this->akceService->addMemberToAction($userId, $this->akce->id, $isOrg, $this->presenter->user->id);
		if ($isOrg) $this->orgList[$userId] = $userId;
		else $this->userList[$userId] = $userId;
	}

	/**
	 * @param int $userId
	 */
	private function unLogUser(int $userId) {
		$this->akceService->deleteMemberFromAction($userId, $this->akce->id, $this->presenter->user->id);
		unset($this->orgList[$userId]);
		unset($this->userList[$userId]);
	}

	/**
	 * @param bool $isOrg
	 * @throws ForbiddenRequestException
	 */
	public function handleLogSelf(bool $isOrg) {
		$userId = $this->presenter->user->id;

		if ($this->userIsAllowToLog($isOrg)) {
			if ($this->userIsInList()) $this->unLogUser($userId);
			$this->logUser($userId, $isOrg);
			$this->flashMessage('Byl si přihlášen na akci');
			$this->redrawControl();
		} else {
			throw new ForbiddenRequestException('Na tuto akci se nemůžete přihlásit');
		}
	}

	/**
	 * @param bool $isOrg
	 * @throws ForbiddenRequestException
	 */
	public function handleUnlogSelf(bool $isOrg) {
		$userId = $this->presenter->user->id;

		if ($this->userIsAllowToLog($isOrg)) {
			$this->unLogUser($userId);
			$this->flashMessage('Byl si odhlášen z akce');
			$this->redrawControl();
		} else {
			throw new ForbiddenRequestException('Z této akce se nemůžete odhlásit');
		}
	}

	/**
	 * @return Form
	 */
	public function createComponentLogginForm() {
		$form = new Form;

		$userLevel = ($this->getPresenter()->getUser()->isInRole('admin')) ? UserService::USER_LEVEL : UserService::MEMBER_LEVEL;
		$list = $this->userService->getUsers($userLevel)->select('user.id, CONCAT(surname," ",name)AS jmeno')->order('surname, name');

		$logList = $this->getLocalMemberList();
		if ($logList) $list->where('NOT id', $logList);

		$list = $list->fetchPairs('id', 'jmeno');

		$form->addSelect('member', null, $list);
		$form->addCheckbox('organizator', 'Organizátor')
			->setDefaultValue(FALSE);
		$form->addSubmit('ok');
		$form->onSuccess[] = [$this, 'processLogginForm'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @throws ForbiddenRequestException
	 */
	public function processLogginForm(Form $form) {
		if (($this->getPresenter()->getUser()->isInRole('admin')) or ($this->userIsInList(TRUE))) {
			$values = $form->getValues();

			if ($this->userIsInList()) $this->unLogUser($values->member);
			$this->logUser($values->member, $values->organizator);

			$list = $form['member']->getItems();
			unset($list[$values->member]);
			$form['member']->setItems($list);

			$this->flashMessage('Na akci byla přidána další osoba');

			$this->redrawControl();
		} else {
			throw new ForbiddenRequestException('Na tuto akci nemůžete přidávat další osoby');
		}
	}

	/**
	 * @return Form
	 */
	public function createComponentUnLogginForm() {
		$form = new Form;

		$list = $this->userService->getUsersByAkceId($this->akce->id)
				->select('user.id,CONCAT(surname," ",name)AS jmeno')
				->order('surname, name')
				->fetchPairs('id', 'jmeno');

		$form->addSelect('member', null, $list);
		$form->addSubmit('ok');
		$form->onSuccess[] = [$this, 'processUnLogginForm'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @throws ForbiddenRequestException
	 */
	public function processUnLogginForm(Form $form) {
		if (($this->getPresenter()->getUser()->isInRole('admin')) or ($this->userIsInList(TRUE))) {
			$values = $form->getValues();

			$this->unLogUser($values->member);

			$this->flashMessage('Osoba byla odebrána z akce');

			$list = $form['member']->getItems();
			unset($list[$values->member]);
			$form['member']->setItems($list);

			$this->redrawControl();
		} else {
			throw new ForbiddenRequestException('Na tuto akci nemůžete přidávat další osoby');
		}
	}

}