<?php


namespace App\MemberModule\Presenters;

use App\MemberModule\Components\YearPaginator;
use App\Model\AttendanceService;

final class AttendancePresenter extends LayerPresenter
{
	/** @var AttendanceService @inject */
	public $attendanceService;

	public function renderDefault()
	{
		$year = $this['yp']->year;
		$this->template->year = $year;
		$this->template->sessions = $this->attendanceService->getCurrentSessions()
			->where('YEAR(date)', $year);
		$this->template->attendance = $this->attendanceService->getAttendanceForUser($this->user->id, $year);
	}

	/**
	 * @return YearPaginator
	 */
	public function createComponentYp() {
		return new YearPaginator(2021, NULL, 1, intval(date('Y')));
	}

	public function renderView(int $id)
	{
		$this->template->session = $this->attendanceService->getSessions()->get($id);
		$this->template->attendances = $this->attendanceService->getAttendanceForSession($id);
	}
}