<?php


namespace App\MemberModule\Presenters;

use App\MemberModule\Components\YearPaginator;
use App\Model\AkceService;
use App\Model\AttendanceService;
use Nette\Application\BadRequestException;

final class AttendancePresenter extends LayerPresenter
{
	/** @var AttendanceService @inject */
	public $attendanceService;

	/** @var AkceService @inject */
	public $akceService;

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
	public function createComponentYp()
	{
		return new YearPaginator(2021, NULL, 1, (int) date('Y'));
	}

	public function renderView(int $id)
	{
		if (!($session = $this->attendanceService->getSessions()->get($id))) {
			throw new BadRequestException('Training session does not exists');
		}
		if ($session->date > date_create('00:00:00')) {
			throw new BadRequestException('Training session is not over');
		}

		$this->template->session = $session;
		$events = $this->akceService->getAkceByDate($session->date);
		$this->template->events = $events;

		$this->template->eventsUser = $this->akceService->getEventsByMemberId($this->user->id, $events->fetchPairs(null, 'id'))->fetchPairs(null, 'akce_id');
		$this->template->attendances = $this->attendanceService->getAttendanceForSession($id);

		$this->template->prev = $this->attendanceService->getPrevSession($session->date);
		$this->template->next = $this->attendanceService->getNextSession($session->date);
	}

	/**
	 * @allow(admin)
	 */
	public function actionDelete(int $id)
	{
		if (!($session = $this->attendanceService->getSessions()->get($id))) {
			throw new BadRequestException('Training session does not exists');
		}

		$session->delete();
		$this->flashMessage('Docházka tréninku byla smazána');
		$this->redirect('default');
	}
}