<?php


namespace App\MemberModule\Presenters;

use App\Model\AttendanceService;

final class AttendancePresenter extends LayerPresenter
{
	/** @var AttendanceService @inject */
	public $attendanceService;

	public function renderDefault()
	{
		$this->template->sessions = $this->attendanceService->getCurrentSessions();
		$this->template->attendance = $this->attendanceService->getAttendanceForUser($this->user->id);
	}

	public function renderView(int $id)
	{
		$this->template->session = $this->attendanceService->getSessions()->get($id);
		$this->template->attendances = $this->attendanceService->getAttendanceForSession($id);
	}
}