<?php

/**
 * Member list for logging on action
 *
 * @author     Jakub Mottl
 */

namespace App\MemberModule\Components;

use App\Model\MemberService;
use Nette\Application\UI\Form;
use Nette\Application\UI\Control;
use Nette\Database\Table\ActiveRow;
use App\Model\AkceService;

class MembersListControl extends Control {
	/** @var AkceService */
	private $akceService;

	/** @var MemberService */
	private $memberService;

	/** @var ActiveRow */
	private $akce;

	/** @var $list */
	private $list;

	/** @var bool $isOrgList */
	private $isOrgList;

	/**
	 * MembersListControl constructor.
	 * @param AkceService $akceService
	 * @param MemberService $memberService
	 * @param ActiveRow $akce
	 * @param $isOrgList
	 */
	public function __construct(AkceService $akceService, MemberService $memberService, ActiveRow $akce, $isOrgList = FALSE) {
		parent::__construct();
		$this->akceService = $akceService;
		$this->memberService = $memberService;
		$this->akce = $akce;
		$this->isOrgList = $isOrgList;
	}

	/**
	 *
	 */
	public function render() {
		$userId = $this->getPresenter()->getUser()->getId();
		$this->template->setFile(__DIR__ . '/MembersListControl.latte');

		$members = $this->memberService->getMembersByAkceId($this->akce->id, $this->isOrgList)->order(':akce_member.date_add');
		$this->template->members = clone $members;

		$this->list = $members->select('id,CONCAT(surname," ",name)AS jmeno')
			->fetchPairs('id', 'jmeno');

		if (!$this->list) $this->list = [0];

		if ($this->isOrgList) $orgList = $this->list; else
			$orgList = $this->memberService->getMembersByAkceId($this->akce->id, TRUE)->fetchPairs('id', 'id');

		$this->template->isLogged = array_key_exists($userId, $this->list);
		$this->template->akce = $this->akce;
		$this->template->org = $this->isOrgList;

		$this->template->userIsOrg = in_array($userId, array_keys($orgList));

		$this->template->isAllowLogin = $this->isOrgList ? $this->akce->login_org : $this->akce->login_mem;
		if ($this->akce->date_deatline < date_create()) $this->template->isAllowLogin = false;

		$this->template->render();
	}

	/**
	 *
	 */
	public function handleUnlogSelf() {
		$this->akceService->deleteMemberFromAction($this->getPresenter()->getUser()->getId(), $this->akce->id, $this->isOrgList);
		$this->flashMessage('Byl jste odhlášen z akce');
		$this->redrawControl();
	}

	/**
	 *
	 */
	public function handleLogSelf() {
		$this->akceService->addMemberToAction($this->getPresenter()->getUser()->getId(), $this->akce->id, $this->isOrgList);
		$this->flashMessage('Byl jste přihlášen na akci');
		$this->redrawControl();
	}

	/**
	 * @return Form
	 */
	public function createComponentLogginForm() {
		$form = new Form;

		$form->getElementPrototype()->class = 'ajax';

		if (!$this->list) $this->list = $this->memberService->getMemberListForAkceComponent($this->akce->id, $this->isOrgList);
		if (!$this->list) $this->list = [0];

		$list = ($this->getPresenter()->getUser()->isInRole('admin')) ? $this->memberService->getUsers() : $this->memberService->getMembers();
		$list->select('id,CONCAT(surname," ",name)AS jmeno')->order('surname, name');
		$list->where('NOT id', array_keys($this->list));
		$form->addSelect('member', null, $list->fetchPairs('id', 'jmeno'));
		$form->addSubmit('send', '+');//->setAttribute('class','myfont');
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
		$values = $form->getValues();
		$this->akceService->addMemberToAction($values->member, $this->akce->id, $this->isOrgList);
		$this->flashMessage('Na akci byla přidána další osoba');

		$this->redrawControl();

	}

	/**
	 * @param $member_id
	 */
	public function handleUnlog($member_id) {
		$this->akceService->deleteMemberFromAction($member_id, $this->akce->id, $this->isOrgList);
		$this->flashMessage('Osoba byla odebrána z akce');
		$this->redrawControl();
	}

	/**
	 * @return Form
	 */
	public function createComponentUnLogginForm() {
		$form = new Form;

		$form->getElementPrototype()->class = 'ajax';

		if (!$this->list) $this->list = $this->memberService->getMemberListForAkceComponent($this->akce->id, $this->isOrgList);
		$form->addSelect('member', null, $this->list);
		$form->addSubmit('send', '-');//->setAttribute('class','myfont');
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
		$values = $form->getValues();
		$this->akceService->deleteMemberFromAction($values->member, $this->akce->id, $this->isOrgList);
		$this->flashMessage('Osoba byla odebrána z akce');
		$this->redrawControl();
	}

}
