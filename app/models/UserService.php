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

	/**
	 * @param int $userLevel
	 * @return array
	 */
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
	public function isEmailUnique($mail, $userId) {
		return (bool)!$this->getUsers(self::DELETED_LEVEL)->where('mail = ? OR mail2 = ?', $mail, $mail)->where('NOT id', $userId)->fetch();
	}

	/**
	 * @param $mail
	 * @return bool|mixed|IRow
	 */
	public function getUserByEmail($mail) {
		return $this->getUsers()->select('id, hash, name, surname, mail, mail2, role')->where('mail = ? OR mail2 = ?', $mail, $mail)->fetch();
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
	 * @param IRow $user
	 * @return array
	 */
	public function getRightsForUser(IRow $user) {
		$roleList = $this->getRoleList();
		$rights = array_slice($roleList, 0, $user->role + 1);
		$rights = array_merge($rights, array_values($this->getRightsByUserId($user->id)));

		return $rights;
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
	 * @param string $search
	 * @param int $userLevel
	 * @return Selection
	 */
	public function searchUsers($search, $userLevel = self::DELETED_LEVEL) {
		$where = self::prepareSearchParams(['name', 'surname', 'zamestnani', 'mesto', 'ulice', 'mail', 'mail2', 'telefon', 'telefon2'], $search);

		return $this->getUsers($userLevel)->whereOr($where)->order('surname, name');
	}

	/**
	 * @param array $columns
	 * @param string $value
	 * @return array
	 */
	private static function prepareSearchParams(array $columns, $value) {
		$keys = array_map(function ($key) {
			return "$key LIKE";
		}, $columns);
		$values = array_fill(0, count($keys), "%$value%");

		return array_combine($keys, $values);
	}

}