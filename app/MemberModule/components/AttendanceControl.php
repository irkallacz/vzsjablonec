<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 14.12.2019
 * Time: 12:03
 */

namespace App\MemberModule\Components;

use App\Model\AttendanceService;

final class AttendanceControl extends AbstractAjaxControl
{
	/**
	 * @var AttendanceService
	 */
	protected $service;

	public function render()
	{
		$this->template->setFile(__DIR__ . '/AttendanceControl.latte');
		$this->items = $this->service->getAttendanceByUser($this->memberId)
			->order('attendance_id DESC')
			->limit(self::DEFAULT_OFFSET, $this->offset);

		parent::render();
	}
}