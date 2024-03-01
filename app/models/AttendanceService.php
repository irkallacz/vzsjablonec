<?php


namespace App\Model;

use Nette\Database\Table\Selection;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tracy\Debugger;

final class AttendanceService extends DatabaseService {
	const TABLE_ATTENDANCE_NAME = 'attendance';
	const TABLE_ATTENDANCE_MEMBER_NAME = 'attendance_user';

	public function getSessions(): Selection
	{
		return $this->database->table(self::TABLE_ATTENDANCE_NAME);
	}

	public function getAttendance(): Selection
	{
		return $this->database->table(self::TABLE_ATTENDANCE_MEMBER_NAME);
	}

	/**
	 * @param DateTime $date
	 * @return false|ActiveRow
	 */
	public function getPrevSession(DateTime $date)
	{
		return $this->getSessions()->where('date < ?', $date)->order('date DESC')->fetch();
	}

	/**
	 * @param DateTime $date
	 * @return false|ActiveRow
	 */
	public function getNextSession(DateTime $date)
	{
		return $this->getSessions()->where('date > ?', $date)->where('date < CURDATE()')->order('date')->fetch();
	}

	public function getCurrentSessions(): Selection
	{
		return $this->getSessions()
			->where('date < CURDATE()')
			->order('date DESC');
	}

	public function getAttendanceByUser(int $userId): Selection
	{
		return $this->getAttendance()
			->where('user_id', $userId);
	}

	public function getAttendanceForUser(int $userId, int $year): array
	{
		return $this->getAttendanceByUser($userId)
			//->where('datetime < CURDATE()')
			->where('YEAR(datetime)', $year)
			->fetchPairs(null, 'attendance_id');
	}

	public function getAttendanceForSession(int $sessionId): Selection
	{
		return $this->getAttendance()
			->where('attendance_id = ?', $sessionId)
			->order('datetime');
	}


}