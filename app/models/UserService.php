<?php

/**
 * UserService base class.
 */

namespace App\Model;

use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
use Nette\Database\SqlLiteral;

class UserService extends DatabaseService {

	const DELETED_LEVEL = 0;
	const USER_LEVEL = 1;
	const MEMBER_LEVEL = 2;
	const BOARD_LEVEL = 3;
	const ADMIN_LEVEL = 4;

	/**
	 * @return Selection
	 */
	public function getTable() {
		return $this->database->table('user');
	}

	/**
	 * @param int $userLevel
	 * @return Selection
	 */
	public function getUsers($userLevel = self::USER_LEVEL) {
		$users = $this->getTable();
		if ($userLevel) $users->where('role >= ?', $userLevel - 1);
		return $users;
	}

	public function getUsersArray($userLevel = self::USER_LEVEL) {
		$users = $this->getUsers($userLevel);
		return $users->select('id, CONCAT(surname, " ", name)AS jmeno')
			->order('surname, name')
			->fetchPairs('id', 'jmeno');
	}

	/**
	 * @param $id
	 * @return IRow
	 */
	public function getUserById($id, $userLevel = self::USER_LEVEL) {
		return $this->getUsers($userLevel)->get($id);
	}

	/**
	 * @param $username
	 * @param $password
	 * @return bool|mixed|IRow
	 */
	public function getUserByAutentication($id, $password) {
		$member = $this->getUsers()
			->select('mail, hash')
			->get($id);

		return Passwords::verify($password, $member->hash) ? $member : FALSE;
	}

	/**
	 * @param $role
	 * @return Selection
	 */
	public function getUsersByRight($right) {
		return $this->getUsers()->where(':user_rights.rights_id', $this->database->table('rights')->where('name', $right));
	}

	/**
	 * @param $mail
	 * @return bool|mixed|IRow
	 */
	public function getUserByEmail($mail) {
		return $this->getUsers()->where('mail', $mail)->fetch();
	}

	/**
	 * @param $login
	 * @return bool|mixed|IRow
	 */
	public function getUsersByLogin($login) {
		return $this->getUsers()->select('id, hash, name, surname, mail, role')->where('mail', $login)->fetch();
	}


	/**
	 * @param $id
	 * @param bool $org
	 * @return Selection
	 */
	public function getUsersByAkceId($id, $org = NULL) {
		$members = $this->getTable()->where(':akce_member.akce_id', $id);
		if (!is_null($org)) $members->where('organizator', $org);
		return $members;
	}

	/**
	 * @param $values
	 * @return bool|int|IRow
	 */
	public function addUser(ArrayHash $values) {
		$values->role = 1;
		return $this->getTable()->insert($values);
	}

	/**
	 * @param $id
	 * @return array
	 */
	public function getRightsByUserId($id) {
		return $this->database->table('user_rights')->select('rights.name, rights_id')->where('member_id', $id)->fetchPairs('rights_id', 'name');
	}

	/**
	 * @return array
	 */
	public function getRoleList() {
		return $this->database->table('roles')->order('id')->fetchPairs('id', 'name');
	}

	/**
	 * @param $id
	 * @return int
	 */
	public function getLastLoginByUserId($id) {
		return $this->database->table('user_log')->where('member_id', $id)->max('date_add');
	}

	/**
	 * @param $user_id
	 * @param DateTime $datetime
	 */
	public function addUserLogin($user_id, DateTime $datetime) {
		$this->database->query('INSERT INTO user_log VALUES(?, ?) ON DUPLICATE KEY UPDATE date_add = ?', $user_id, $datetime, $datetime);
	}

	/**
	 * @param $user_id
	 * @return bool|int|IRow
	 */
	public function addPasswordSession($user_id, $interval = '20 MINUTE') {
		$this->database->query('DELETE FROM `password_session` WHERE `member_id` = ?', $user_id);
		return $this->database->table('password_session')->insert(['member_id' => $user_id, 'date_end' => new SqlLiteral('NOW() + INTERVAL ' . $interval)]);
	}

	/**
	 * @param $pubkey
	 * @return bool|mixed|IRow
	 */
	public function getPasswordSession($pubkey) {
		$this->database->query('DELETE FROM `password_session` WHERE `date_end` < ?', new SqlLiteral('NOW()'));
		return $this->database->table('password_session')->where('pubkey', $pubkey)->fetch();
	}

	/**
	 * @param $search
	 * @return Selection
	 */
	public function searchUsers($search, $userLevel = self::DELETED_LEVEL) {
		return $this->getUsers($userLevel)
			->where('name LIKE ? OR surname LIKE ? OR zamestnani LIKE ? OR mesto LIKE ? OR ulice LIKE ? OR mail LIKE ? OR telefon LIKE ?', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%')
			->order('surname, name');
	}

}