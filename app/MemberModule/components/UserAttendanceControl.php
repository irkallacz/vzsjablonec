<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 14.12.2019
 * Time: 12:03
 */

namespace App\MemberModule\Components;

use App\Model\AkceService;
use App\Model\AttendanceService;
use Nette\Utils\DateTime;

class UserAttendanceControl extends LayerControl
{

	const DEFAULT_OFFSET = 10;

	/**
	 * @var AttendanceService
	 */
	private $attendanceService;

	/**
	 * @var int
	 */
	private $offset = 0;

	/**
	 * @var int
	 */
	private $memberId;

	/**
	 * UserEventsControl constructor.
	 * @param AttendanceService $attendanceService
	 * @param int $memberId
	 */
	public function __construct(AttendanceService $attendanceService, int $memberId)
	{
		$this->attendanceService = $attendanceService;
		$this->memberId = $memberId;
	}

	public function render()
	{
		$this->template->setFile(__DIR__ . '/UserAttendanceControl.latte');
		$attendances = $this->attendanceService->getAttendanceByUser($this->memberId)
			->order('attendance_id DESC')
			->limit(self::DEFAULT_OFFSET, $this->offset);

		$count = $attendances->count();
		$this->template->attendances = $attendances;
		$this->template->offset = ($count) ? $this->offset + self::DEFAULT_OFFSET : 0;

		$this->template->render();
	}

	/**
	 * @param int $offset
	 */
	public function handleLoadMore(int $offset) {
		$this->offset = $offset;
		$this->redrawControl('loadMore');
		$this->redrawControl('table');
	}
}