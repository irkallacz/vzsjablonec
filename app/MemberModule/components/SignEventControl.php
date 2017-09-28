<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 4.8.2017
 * Time: 10:16
 */

namespace App\MemberModule\Components;


use App\Model\AkceService;
use App\Model\MemberService;
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

	/** @var MemberService */
	private $memberService;

	/** @var ActiveRow */
	private $akce;

	/** @var array */
	private $userList = [];

	/** @var array */
	private $orgList = [];

	/**
	 * SignEventControl constructor.
	 * @param AkceService $akceService
	 * @param MemberService $memberService
	 * @param ActiveRow $akce
	 */
	public function __construct(AkceService $akceService, MemberService $memberService, ActiveRow $akce) {
		parent::__construct();
		$this->akceService = $akceService;
		$this->memberService = $memberService;
		$this->akce = $akce;

		$this->userList = $this->getMemberList(self::USER)->fetchPairs('member_id', 'member_id');
		$this->orgList = $this->getMemberList(self::ORG)->fetchPairs('member_id', 'member_id');
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
	private function getMemberList($isOrg = NULL) {
		$list = $this->akce->related(AkceService::TABLE_AKCE_MEMBER_NAME);
		if (!is_null($isOrg)) $list->where('organizator', $isOrg);
		return $list;
	}

	/**
	 * @param bool $isOrg
	 */
	private function userIsInList($isOrg = NULL) {
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
	private function userIsAllowToLog($toOrg) {
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
	private function logUser($userId, $isOrg) {
		$this->akceService->addMemberToAction($userId, $this->akce->id, $isOrg);
		if ($isOrg) $this->orgList[$userId] = $userId;
		else $this->userList[$userId] = $userId;
	}

	/**
	 * @param int $userId
	 */
	private function unlogUser($userId) {
		$this->akceService->deleteMemberFromAction($userId, $this->akce->id);
		unset($this->orgList[$userId]);
		unset($this->userList[$userId]);
	}

	/**
	 * @param bool $isOrg
	 */
	public function handleLogSelf($isOrg) {
		$userId = $this->getPresenter()->getUser()->getId();

		if ($this->userIsAllowToLog($isOrg)) {
			if ($this->userIsInList()) $this->unlogUser($userId);
			$this->logUser($userId, $isOrg);
			$this->flashMessage('Byl jste přihlášen na akci');
			$this->redrawControl();
		} else {
			throw new ForbiddenRequestException('Na tuto akci se nemůžete přihlásit');
		}
	}

	/**
	 * @param bool $isOrg
	 */
	public function handleUnlogSelf($isOrg) {
		$userId = $this->getPresenter()->getUser()->getId();

		if ($this->userIsAllowToLog($isOrg)) {
			$this->unlogUser($userId);
			$this->flashMessage('Byl jste odhlášen z akce');
			$this->redrawControl();
		} else {
			throw new ForbiddenRequestException('Z této akce se nemůžete odhlásit');
		}
	}


	private function getLogginList(){
		$logList = $this->getLocalMemberList();

		$list = ($this->getPresenter()->getUser()->isInRole('admin')) ? $this->memberService->getUsers() : $this->memberService->getMembers();
		$list->select('id, CONCAT(surname," ",name)AS jmeno')->order('surname, name');
		if ($logList) $list->where('NOT id', $logList);

		return $list->fetchPairs('id', 'jmeno');
	}
	/**
	 * @return Form
	 */
	public function createComponentLogginForm() {
		$form = new Form;

		$form->getElementPrototype()->class = 'ajax';

		$list = $this->getLogginList();

		$form->addSelect('member', null, $list);
		$form->addCheckbox('organizator', 'Organizátor')
			->setDefaultValue(FALSE);
		$form->addSubmit('ok')
			->getControlPrototype()
				->setName('button')
				->setHtml('<svg class="icon icon-user-plus"><use xlink:href="'.$this->template->baseUri.'/img/symbols.svg#icon-user-plus"></use></svg> přidat');

		$form->onSuccess[] = [$this, 'processLogginForm'];

		$renderer = $form->getRenderer();
		$renderer->wrappers['controls']['container'] = NULL;
		$renderer->wrappers['pair']['container'] = NULL;
		$renderer->wrappers['label']['container'] = NULL;
		$renderer->wrappers['control']['container'] = NULL;

		return $form;
	}

	/**
	 * @param Form $form
	 */
	public function processLogginForm(Form $form) {
		if (($this->getPresenter()->getUser()->isInRole('admin')) or ($this->userIsInList(TRUE))) {
			$values = $form->getValues();

			if ($this->userIsInList()) $this->unlogUser($values->member);
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

		$form->getElementPrototype()->class = 'ajax';

		$list = $this->memberService->getMemberListForAkceComponent($this->akce->id);

		$form->addSelect('member', null, $list);
		$form->addSubmit('ok')
			->getControlPrototype()
				->setName('button')
				->setHtml('<svg class="icon icon-user-times"><use xlink:href="'.$this->template->baseUri.'/img/symbols.svg#icon-user-times"></use></svg> odebrat');

		$form->onSuccess[] = [$this, 'processUnLogginForm'];

		$renderer = $form->getRenderer();
		$renderer->wrappers['controls']['container'] = NULL;
		$renderer->wrappers['pair']['container'] = NULL;
		$renderer->wrappers['label']['container'] = NULL;
		$renderer->wrappers['control']['container'] = NULL;

		return $form;
	}

	/**
	 * @param Form $form
	 */
	public function processUnLogginForm(Form $form) {
		if (($this->getPresenter()->getUser()->isInRole('admin')) or ($this->userIsInList(TRUE))) {
			$values = $form->getValues();

			$this->unlogUser($values->member);

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