<?php


namespace App\Model;

use Tracy\Debugger;

final class AttendanceService extends DatabaseService {
	const TABLE_ATTENDANCE_NAME = 'attendance';
	const TABLE_ATTENDANCE_MEMBER_NAME = 'attendance_user';

	public function getSessions() {
		return $this->database->table(self::TABLE_ATTENDANCE_NAME);
	}

	public function getAttendance() {
		return $this->database->table(self::TABLE_ATTENDANCE_MEMBER_NAME);
	}

	public function getCurrentSessions() {
		return $this->getSessions()
			->where('date < CURDATE()')
			->order('date DESC');
	}

	public function getAttendanceByUser(int $userId)
	{
		return $this->getAttendance()
			->where('user_id', $userId);
	}

	public function getAttendanceForUser(int $userId, int $year) {
		return $this->getAttendanceByUser($userId)
			//->where('datetime < CURDATE()')
			->where('YEAR(datetime)', $year)
			->fetchPairs(null, 'attendance_id');
	}

	public function getAttendanceForSession(int $sessionId) {
		return $this->getAttendance()
			->where('attendance_id = ?', $sessionId)
			->order('datetime');
	}


}