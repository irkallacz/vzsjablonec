<?php


namespace App\MemberModule\Components;


use App\Model\UserService;
use Nette\Application\UI\Control;

class QualificationControl extends Control
{
	/**
	 * @var UserService
	 */
	protected $userService;

	/**
	 * @var bool
	 */
	protected $showAll = false;

	/**
	 * @var int
	 */
	protected $memberId;

	/**
	 * QualificationControl constructor.
	 * @param UserService $userService
	 */
	public function __construct(UserService $userService, int $memberId)
	{
		parent::__construct();
		$this->userService = $userService;
		$this->memberId = $memberId;
	}

	public function render()
	{
		$showButton = true;
		$qualifications = $this->userService->getQualificationMemberByMemberId($this->memberId);

		if (!$this->showAll) {
			$qualifications->where('date_end IS NULL OR date_end > NOW()');
		}

		if (!$qualifications->count()) {
			$qualifications = $this->userService->getQualificationMemberByMemberId($this->memberId);
			$this->showAll = true;
			$showButton = false;
		}

		$qualifications->order('qualification_id DESC, date_start DESC');

		$this->template->setFile(__DIR__ . '/QualificationControl.latte');
		$this->template->qualifications = $qualifications;
		$this->template->showAll = $this->showAll;
		$this->template->showButton = $showButton;
		$this->template->render();
	}

	public function handleShowAll(bool $status)
	{
		$this->showAll = $status;
		$this->redrawControl('table');
	}

}