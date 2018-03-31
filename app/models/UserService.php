<?php

/**
 * UserService base class.
 */

namespace App\Model;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;
use Nette\Utils\Arrays;
use Nette\Utils\DateTime;
use Nette\Database\SqlLiteral;
use Tracy\Debugger;

class UserService extends DatabaseService {

	const DELETED_LEVEL = 0;
	const USER_LEVEL = 1;
	const MEMBER_LEVEL = 2;
	const BOARD_LEVEL = 3;
	const ADMIN_LEVEL = 4;

	const LOGIN_METHOD_PASSWORD = 1;
	const LOGIN_METHOD_GOOGLE = 2;
	const LOGIN_METHOD_FACEBOOK = 3;


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
	public function getUsers(int $userLevel = self::USER_LEVEL) {
		$users = $this->getTable();
		if ($userLevel) $users->where('role >= ?', $userLevel - 1);
		return $users;
	}

	/**
	 * @param int $userLevel
	 * @return array
	 */
	public function getUsersArray(int $userLevel = self::USER_LEVEL) {
		$users = $this->getUsers($userLevel);
		return $users->select('id, CONCAT(surname, " ", name)AS jmeno')
			->order('surname, name')
			->fetchPairs('id', 'jmeno');
	}

	/**
	 * @param int $id
	 * @param int $userLevel
	 * @return IRow|ActiveRow
	 */
	public function getUserById(int $id, int $userLevel = self::USER_LEVEL) {
		return $this->getUsers($userLevel)->get($id);
	}

	/**
	 * @param int $id
	 * @param string $password
	 * @return bool|mixed|IRow
	 */
	public function getUserByAutentication(int $id, string $password) {

		/** @var ActiveRow $member */
		$member = $this->getUsers()
			->select('mail, hash')
			->get($id);

		return Passwords::verify($password, $member->hash) ? $member : FALSE;
	}

	/**
	 * @param string $right
	 * @return Selection
	 */
	public function getUsersByRight(string $right) {
		return $this->getUsers()->where(':user_rights.rights_id', $this->database->table('rights')->where('name', $right));
	}

	/**
	 * @param $mail
	 * @param int|NULL $userId
	 * @return bool|mixed|IRow
	 */
	public function isEmailUnique(string $mail, int $userId = NULL) {
		$user = $this->getUsers(self::DELETED_LEVEL)->where('mail = ? OR mail2 = ?', $mail, $mail);
		if ($userId) $user->where('NOT id', $userId);
		return (bool)!$user->fetch();
	}

	/**
	 * @param string $mail
	 * @param int $userLevel
	 * @return bool|mixed|IRow
	 */
	public function getUserByEmail(string $mail, int $userLevel = self::USER_LEVEL) {
		return $this->getUsers($userLevel)->where('mail = ? OR mail2 = ?', $mail, $mail)->fetch();
	}

	/**
	 * @param IRow|ActiveRow
	 * @return array
	 */
	public function getDataForUser($user){
		$array = $user->toArray();
		$keys = ['id', 'hash', 'name', 'surname', 'mail', 'mail2', 'role'];
		return array_intersect_key($array, array_flip($keys));
	}

	/**
	 * @param int $id
	 * @param bool|NULL $org
	 * @return Selection
	 */
	public function getUsersByAkceId(int $id, bool $org = NULL) {
		$members = $this->getTable()->where(':akce_member.akce_id', $id);
		if (!is_null($org)) $members->where('organizator', $org);
		return $members;
	}

	/**
	 * @param ArrayHash $values
	 * @param int $role
	 * @return bool|int|IRow|ActiveRow
	 */
	public function addUser(ArrayHash $values, int $role = self::MEMBER_LEVEL) {
		$values->role = ($role == self::DELETED_LEVEL) ? NULL : $role - 1;
		return $this->getTable()->insert($values);
	}

	/**
	 * @param int $id
	 * @return array
	 */
	public function getRightsByUserId(int $id) {
		return $this->database->table('user_rights')->select('rights.name, rights_id')->where('member_id', $id)->fetchPairs('rights_id', 'name');
	}

	/**
	 * @return array
	 */
	public function getRoleList() {
		return $this->database->table('roles')->order('id')->fetchPairs('id', 'name');
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @return array
	 */
	public function getRightsForUser(IRow $user) {
		$roleList = $this->getRoleList();
		$rights = array_slice($roleList, 0, $user->role + 1);
		$rights = array_merge($rights, array_values($this->getRightsByUserId($user->id)));

		return $rights;
	}

	/**
	 * @param int $id
	 * @return DateTime
	 */
	public function getLastLoginByUserId($id) {
		return $this->database->table('member_log')->where('member_id', $id)->max('date_add');
	}

	/**
	 * @param int $user_id
	 * @param DateTime $datetime
	 * @param string $method
	 */
	public function addUserLogin(int $user_id, $method = self::LOGIN_METHOD_PASSWORD) {
		$this->database->query('INSERT INTO user_log', [
			'member_id' => $user_id,
			'date_add' => new SqlLiteral('NOW()'),
			'method_id' => $method
		]);
	}

	/**
	 * @param int $user_id
	 * @param DateTime $datetime
	 * @param string $method
	 */
	public function addMemberLogin(int $user_id) {
		$this->database->query('INSERT INTO member_log', [
			'member_id' => $user_id,
			'date_add' => new SqlLiteral('NOW()')
		]);
	}


	/**
	 * @param int $user_id
	 * @return bool
	 */
	public function haveActivePasswordSession(int $user_id) {
		return (bool) $this->database->table('password_session')
			->where('member_id', $user_id)
			->where('date_end >', new SqlLiteral('NOW()'))
			->fetch();
	}

	/**
	 * @param int $user_id
	 * @param string $interval
	 * @return bool|int|IRow
	 */
	public function addPasswordSession(int $user_id, string $interval = '40 MINUTE') {
		$this->database->query('DELETE FROM `password_session` WHERE `member_id` = ?', $user_id);
		return $this->database->table('password_session')->insert([
			'member_id' => $user_id,
			'date_end' => new SqlLiteral('NOW() + INTERVAL ' . $interval)
		]);
	}

	/**
	 * @param string $pubkey
	 * @return bool|mixed|IRow|ActiveRow
	 */
	public function getPasswordSession(string $pubkey) {
		$this->database->query('DELETE FROM `password_session` WHERE `date_end` < ?', new SqlLiteral('NOW()'));
		return $this->database->table('password_session')->where('pubkey', $pubkey)->fetch();
	}

	/**
	 * @param string $user_id
	 * @return false|ActiveRow
	 */
	public function getPasswordAttempts(string $user_id) {
		$this->database->query('DELETE FROM `password_attempt` WHERE `date_end` < ?', new SqlLiteral('NOW()'));
		return $this->database->table('password_attempt')->get($user_id);
	}

	/**
	 * @param string $user_id
	 * @param int $count
	 * @return \Nette\Database\ResultSet
	 */
	public function addPasswordAttempts(string $user_id, int $count = 1) {
		return $this->database->query('INSERT INTO `password_attempt` SET `user_id` = ?, `count` = ?', $user_id, $count);
	}

	/**
	 * @param string $search
	 * @param int $userLevel
	 * @return Selection
	 */
	public function searchUsers(string $search, int $userLevel = self::DELETED_LEVEL) {
		$where = self::prepareSearchParams(['name', 'surname', 'zamestnani', 'mesto', 'ulice', 'mail', 'mail2', 'telefon', 'telefon2'], $search);

		return $this->getUsers($userLevel)->whereOr($where)->order('surname, name');
	}

	/**
	 * @param array $columns
	 * @param string $value
	 * @return array
	 */
	private static function prepareSearchParams(array $columns, string $value) {
		$keys = array_map(function ($key) {
			return "$key LIKE";
		}, $columns);
		$values = array_fill(0, count($keys), "%$value%");

		return array_combine($keys, $values);
	}


	/**
	 * @param IRow|ActiveRow $user
	 */
	public static function getFullName(IRow $user){
		return $user->surname . ' ' . $user->name;
	}
}