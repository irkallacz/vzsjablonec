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
use Tracy\Debugger;

class AnketyService extends DatabaseService {
	const TABLE_ANKETA_NAME = 'anketa';
	const TABLE_ANKETA_MEMBER_NAME = 'anketa_member';
	const TABLE_ANKETA_ODPOVED_NAME = 'anketa_odpoved';

	/**
	 * @return Selection
	 */
	public function getAnkety() {
		return $this->database->table(self::TABLE_ANKETA_NAME)->order('date_add DESC');
	}

	/**
	 * @return Selection
	 */
	public function getOdpovedi() {
		return $this->database->table(self::TABLE_ANKETA_ODPOVED_NAME);
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
		return $this->getOdpovedi()->where('anketa_id', $id)->order('text');
	}

	/**
	 * @param int $id
	 * @return array
	 */
	public function getOdpovediCountByAnketaId(int $id) {
		return $this->getMembersByAnketaId($id)
			->select('anketa_odpoved_id, COUNT(user_id)AS pocet')
			->group('anketa_odpoved_id')
			->fetchPairs('anketa_odpoved_id', 'pocet');
	}

	/**
	 * @param int $anketa_id
	 * @param int $user_id
	 * @return int
	 */
	public function getOdpovedIdByAnketaId(int $anketa_id, int $user_id) {
		$odpoved = $this->getMemberVote($anketa_id, $user_id);

		if ($odpoved) return $odpoved->anketa_odpoved_id; else return 0;
	}

	/**
	 * @param int $id
	 * @return Selection
	 */
	public function getMembersByAnketaId(int $id) {
		$selection = $this->database->table(self::TABLE_ANKETA_MEMBER_NAME)
			->where('anketa_id', $id);

		return $selection;
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
		$this->deleteVotesByAnketaId($id);
		$this->deleteOdpovediByAnketaId($id);
		$this->getAnkety()->where('id', $id)->delete();
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
		return $this->getOdpovedi()->get($id);
	}

	/**
	 * @param int $id
	 */
	public function deleteOdpovediByAnketaId(int $id) {
		$this->getOdpovediByAnketaId($id)->delete();
	}

	/**
	 * @param array $values
	 * @return bool|int|IRow
	 */
	public function addOdpoved(array $values) {
		return $this->getOdpovedi()->insert($values);
	}

	/**
	 * @param array $values
	 * @return ResultSet
	 */
	public function addVote(array $values) {
		return $this->database->query('INSERT INTO '.self::TABLE_ANKETA_MEMBER_NAME, $values);
	}

	/**
	 * @param int $id
	 */
	public function deleteVotesByAnketaId(int $id) {
		$this->getMembersByAnketaId($id)->delete();
	}


	/**
	 * @param int $anketa_id
	 * @param int $user_id
	 * @return int
	 */
	public function deleteMemberVote(int $anketa_id, int $user_id) {
		return $this->getMemberVote($anketa_id, $user_id)->delete();
	}

	/**
	 * @param int $anketa_id
	 * @param int $user_id
	 * @return bool|mixed|IRow
	 */
	public function getMemberVote(int $anketa_id, int $user_id) {
		return $this->getMembersByAnketaId($anketa_id)->where('user_id', $user_id)->fetch();
	}
}