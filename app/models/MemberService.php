<?php

/**
 * MemberService base class.
 */

namespace App\Model;

use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Security\Passwords;
use Nette\Utils\DateTime;
use Nette\Database\SqlLiteral;

class MemberService extends DatabaseService {

	/**
	 * @return Selection
	 */
	public function getTable() {
		return $this->database->table('user');
	}

	/**
	 * @param bool $activeOnly
	 * @return Selection
	 */
	public function getUsers($activeOnly = TRUE) {
		$users = $this->getTable();
		if ($activeOnly) $users->where('role IS NOT NULL');
		return $users;
	}

	/**
	 * @param int $level
	 * @return Selection
	 */
	public function getMembers($level = 0) {
		return $this->getTable()->where('role > ?', $level);
	}

	/**
	 * @param bool $activeOnly
	 * @return array
	 */
	public function getMembersArray($activeOnly = TRUE) {
		$members = ($activeOnly) ? $this->getMembers() : $this->getUsers(FALSE);
		return $members->select('id, CONCAT(surname, " ", name)AS jmeno')
			->order('surname, name')
			->fetchPairs('id', 'jmeno');
	}

	/**
	 * @param $id
	 * @return IRow
	 */
	public function getUserById($id) {
		return $this->getUsers()->get($id);
	}

	/**
	 * @param $id
	 * @return IRow
	 */
	public function getMemberById($id) {
		return $this->getMembers()->get($id);
	}

	/**
	 * @param $username
	 * @param $password
	 * @return bool|mixed|IRow
	 */
	public function getMemberByAutentication($id, $password) {
		$member = $this->getUsers()
			->select('mail, hash')
			->get($id);

		return Passwords::verify($password, $member->hash) ? $member : FALSE;
	}

	/**
	 * @param $role
	 * @return Selection
	 */
	public function getMembersByRight($right) {
		return $this->getMembers()->where(':user_rights.rights_id', $this->database->table('rights')->where('name', $right));
	}

	/**
	 * @param $mail
	 * @return bool|mixed|IRow
	 */
	public function getMemberByEmail($mail) {
		return $this->getMembers()->where('mail', $mail)->fetch();
	}

	/**
	 * @param $login
	 * @return bool|mixed|IRow
	 */
	public function getMemberByLogin($login) {
		return $this->getUsers()->select('id, hash, name, surname, mail, role')->where('mail', $login)->fetch();
	}


	/**
	 * @param $id
	 * @param bool $org
	 * @return Selection
	 */
	public function getMembersByAkceId($id, $org = false) {
		return $this->getTable()->where(':akce_member.akce_id', $id)->where('organizator', $org);
	}

	/**
	 * @param $values
	 * @return bool|int|IRow
	 */
	public function addUser($values) {
		$values['role'] = 0;
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
	 * @param $org
	 * @return mixed
	 */
	public function getMemberListForAkceComponent($id, $org) {
		return $this->getMembersByAkceId($id, $org)
			->select('id,CONCAT(surname," ",name)AS jmeno')
			->order(':akce_member.date_add')
			->fetchPairs('id', 'jmeno');
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
	public function searchUsers($search) {
		return $this->getUsers(FALSE)
			->where('name LIKE ? OR surname LIKE ? OR zamestnani LIKE ? OR mesto LIKE ? OR ulice LIKE ? OR mail LIKE ? OR telefon LIKE ?', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
		//->where('(name, surname, zamestnani, mesto, ulice, mail, telefon, text) AGAINST (? IN BOOLEAN MODE)',$search);
		//->order('surname, name');
	}

}