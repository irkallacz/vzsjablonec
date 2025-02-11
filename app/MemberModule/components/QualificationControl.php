<?php


namespace App\MemberModule\Components;


use App\Model\QualificationService;
use App\Model\UserService;
use Nette\Application\UI\Control;

class QualificationControl extends Control
{
	/**
	 * @var UserService
	 */
	protected $qualificationService;

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
	 * @param QualificationService $qualificationService
	 */
	public function __construct(QualificationService $qualificationService, int $memberId)
	{
		parent::__construct();
		$this->qualificationService = $qualificationService;
		$this->memberId = $memberId;
	}

	public function render()
	{
		$showButton = true;
		$qualifications = $this->qualificationService->getQualificationMemberByMemberId($this->memberId);

		if (!$this->showAll) {
			$qualifications->where('date_end IS NULL OR date_end > NOW()');
		}

		if (!$qualifications->count()) {
			$qualifications = $this->qualificationService->getQualificationMemberByMemberId($this->memberId);
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