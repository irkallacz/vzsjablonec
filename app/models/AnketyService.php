<?php

/**
 * userService base class.
 */

namespace App\Model;

use Nette\Database\ResultSet;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;

class AnketyService extends DatabaseService {

	/**
	 * @return Selection
	 */
	public function getAnkety() {
		return $this->database->table('anketa')->order('date_add DESC');
	}

	/**
	 * @param int $id
	 * @return IRow|ActiveRow
	 */
	public function getAnketaById(int $id) {
		return $this->getAnkety()->get($id);
	}

	/**
	 * @param int $id
	 * @return Selection
	 */
	public function getOdpovediByAnketaId(int $id) {
		return $this->database->table('anketa_odpoved')->where('anketa_id', $id)->order('text');
	}

	/**
	 * @param int $id
	 * @return array
	 */
	public function getOdpovediCountByAnketaId(int $id) {
		return $this->database->table('anketa_member')
			->select('anketa_odpoved_id, COUNT(member_id)AS pocet')
			->where('anketa_id', $id)
			->group('anketa_odpoved_id')
			->fetchPairs('anketa_odpoved_id', 'pocet');
	}

	/**
	 * @param int $anketa_id
	 * @param int $user_id
	 * @return int
	 */
	public function getOdpovedIdByAnketaId(int $anketa_id, int $user_id) {
		$odpoved = $this->database->table('anketa_member')
			->where('member_id', $user_id)
			->where('anketa_id', $anketa_id)
			->fetch();
		if ($odpoved) return $odpoved->anketa_odpoved_id; else return 0;
	}

	/**
	 * @param int $id
	 * @return Selection
	 */
	public function getMembersByAnketaId(int $id) {
		return $this->database->table('member')
			->select('id, CONCAT(surname," ",name)AS jmeno, :anketa_member.anketa_odpoved_id')
			->where(':anketa_member.anketa_id', $id);
	}

	/**
	 * @param DateTime $date
	 * @return Selection
	 */
	public function getAnketyNews(DateTime $date) {
		return $this->getAnkety()->where('date_add > ?', $date);
	}

	/**
	 * @param int $id
	 */
	public function deleteAnketaById(int $id) {
		$this->database->table('anketa_member')->where('anketa_id', $id)->delete();
		$this->database->table('anketa_odpoved')->where('anketa_id', $id)->delete();
		$this->database->table('anketa')->where('id', $id)->delete();
	}

	/**
	 * @param ArrayHash $values
	 * @return bool|int|IRow
	 */
	public function addAnketa(ArrayHash $values) {
		return $this->getAnkety()->insert($values);
	}

	/**
	 * @param int $id
	 * @return IRow|ActiveRow
	 */
	public function getOdpovedById(int $id) {
		return $this->database->table('anketa_odpoved')->get($id);
	}

	/**
	 * @param int $id
	 */
	public function deleteOdpovediByAnketaId(int $id) {
		$this->database->table('anketa_odpoved')->where('anketa_id', $id)->delete();
	}

	/**
	 * @param array $values
	 * @return bool|int|IRow
	 */
	public function addOdpoved(array $values) {
		return $this->database->table('anketa_odpoved')->insert($values);
	}

	/**
	 * @param array $values
	 * @return ResultSet
	 */
	public function addVote(array $values) {
		return $this->database->query('INSERT INTO anketa_member', $values);
	}

	/**
	 * @param int $id
	 */
	public function deleteVotesByAnketaId(int $id) {
		$this->database->table('anketa_member')->where('anketa_id', $id)->delete();
	}


	/**
	 * @param int $anketa_id
	 * @param int $member_id
	 * @return int
	 */
	public function deleteMemberVote(int $anketa_id, int $member_id) {
		return $this->database->table('anketa_member')->where('anketa_id', $anketa_id)->where('member_id', $member_id)->delete();
	}

	/**
	 * @param int $anketa_id
	 * @param int $member_id
	 * @return bool|mixed|IRow
	 */
	public function getMemberVote(int $anketa_id, int $member_id) {
		return $this->database->table('anketa_member')->where('anketa_id', $anketa_id)->where('member_id', $member_id)->fetch();
	}
}