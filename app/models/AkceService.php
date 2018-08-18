<?php

/**
 * Model base class.
 */

namespace App\Model;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;

class AkceService extends DatabaseService {
	const TABLE_AKCE_NAME = 'akce';
	const TABLE_AKCE_MEMBER_NAME = 'akce_member';

	/**
	 * @return Selection
	 */
	public function getAkce() {
		return $this->database->table(self::TABLE_AKCE_NAME);
	}

	/**
	 * @param bool $future
	 * @return Selection
	 */
	public function getAkceByFuture(bool $future = FALSE) {
		$akce = $this->getAkce()->where('enable', TRUE);

		if ($future) $akce->where('date_start > NOW()')->order('date_start ASC'); else $akce->where('date_start < NOW()')->order('date_start DESC');;

		return $akce;
	}

	/**
	 * @param int $id
	 * @return IRow|ActiveRow
	 */
	public function getAkceById(int $id) {
		return $this->getAkce()->get($id);
	}

	/**
	 * @param DateTime $date
	 * @return Selection
	 */
	public function getAkceNews(DateTime $date) {
		return $this->getAkceByFuture(TRUE)
			->where('confirm', TRUE)
			->where('date_update > ?', $date);
	}

	/**
	 * @param int $id
	 * @param DateTime $date
	 * @return bool|mixed|IRow
	 */
	public function getAkceNext(int $id, DateTime $date) {
		return $this->getAkce()
			->where('enable', TRUE)
			->where('confirm', TRUE)
			->where('NOT id', $id)
			->where('date_start > ?', $date)
			->order('date_start')
			->limit(1)
			->fetch();
	}

	/**
	 * @param int $id
	 * @param DateTime $date
	 * @return bool|mixed|IRow
	 */
	public function getAkcePrev(int $id, DateTime $date) {
		return $this->getAkce()
			->where('enable', TRUE)
			->where('confirm', TRUE)
			->where('NOT id', $id)
			->where('date_start < ?', $date)
			->order('date_start DESC')
			->limit(1)
			->fetch();
	}

	/**
	 * @param ArrayHash $values
	 * @return bool|int|IRow|ActiveRow
	 */
	public function addAkce(ArrayHash $values) {
		return $this->getAkce()->insert($values);
	}

	/**
	 * @return array
	 */
	public function getAkceForInArray() {
		return $this->database->table('akce_for')->select('id,text')->fetchPairs('id', 'text');
	}

	/**
	 * @param int $id
	 * @param bool $org
	 * @return array
	 */
	public function getMemberListByAkceId(int $id, bool $org = FALSE) {
		return $this->database->table(self::TABLE_AKCE_MEMBER_NAME)->where('akce_id', $id)->where('organizator', $org)->fetchPairs('user_id', 'user_id');
	}

	/**
	 * @return string
	 */
	public function getAkceMessageDefault() {
		return "<!-- Celý tento řádek smažte a napište sem, co se stalo -->\n\nPočasí:\n\nZásahy (na břehu, ve vodě, na záchranu majetku):\n\nOšetření (drobné, větší, s odvozem):\n\nPoužitý materiál na akce v majetku pobočného spolku:\n\nDalší použitý materiál:\n\nZtráty a poškození materiálu:\n\nDoprava na akci a způsob její úhrady:";
	}

	/**
	 * @param int $user_id
	 * @param int $akce_id
	 * @param bool $org
	 * @param int $created_by
	 */
	public function addMemberToAction(int $user_id, int $akce_id, bool $org = FALSE, int $created_by = null) {
		$values = ['user_id' => $user_id, 'akce_id' => $akce_id, 'organizator' => $org];
		$values['created_by'] = ($created_by) ? $created_by : $user_id;
		$this->database->query('INSERT INTO `'.self::TABLE_AKCE_MEMBER_NAME.'`', $values);
	}

	/**
	 * @param int $user_id
	 * @param int $akce_id
	 */
	public function deleteMemberFromAction(int $user_id, int $akce_id) {
		$this->database->table(self::TABLE_AKCE_MEMBER_NAME)
			->where('user_id', $user_id)
			->where('akce_id', $akce_id)
			->delete();
	}

	/**
	 * @param int $id
	 * @param bool $org
	 * @return array
	 */
	public function getAkceByMemberId(int $id, bool $org = FALSE) {
		return array_values($this->database->table(self::TABLE_AKCE_MEMBER_NAME)
			->select('akce_id')
			->where('user_id', $id)
			->where('organizator', $org)
			->fetchPairs('akce_id', 'akce_id'));
	}

	/**
	 * @param DateTime $date
	 * @param int $user_id
	 * @return Selection
	 */
	public function getFeedbackRequests(DateTime $date, int $user_id) {
		return $this->getAkce()
			->where('enable', TRUE)
			->where('confirm', TRUE)
			->where(':akce_member.user_id', $user_id)
			->where(':akce_member.organizator', FALSE)
			->where('date_end BETWEEN ? AND NOW()', $date);
	}


	/**
	 * @param DateTime $date
	 * @param int $user_id
	 * @return Selection
	 */
	public function getRatingNews(DateTime $date, int $user_id) {
		return $this->getAkceByFuture(FALSE)
			->select('id, name, :akce_rating_member.user_id AS rating_user_id, :akce_rating_member.date_add AS rating_date_add')
			->where(':'.self::TABLE_AKCE_MEMBER_NAME.'.user_id', $user_id)
			->where(':'.self::TABLE_AKCE_MEMBER_NAME.'.organizator', TRUE)
			->where(':akce_rating_member.date_add > ?', $date);

	}

	/**
	 * @param DateTime $date
	 * @param int $user_id
	 * @return Selection
	 */
	public function getReportRequests(DateTime $date, int $user_id) {
		return $this->getAkce()
			->where('enable', TRUE)
			->where('confirm', TRUE)
			->where(':'.self::TABLE_AKCE_MEMBER_NAME.'.user_id', $user_id)
			->where(':'.self::TABLE_AKCE_MEMBER_NAME.'.organizator', TRUE)
			->where('message', NULL)
			->where('date_end BETWEEN ? AND NOW()', $date);
	}

	/**
	 * @param int $id
	 * @return IRow
	 */
	public function getReportById(int $id) {
		return $this->database->table('report')->get($id);
	}

	/**
	 * @param ArrayHash $values
	 * @return bool|int|IRow
	 */
	public function addReport(ArrayHash $values) {
		return $this->database->table('report')->insert($values);
	}

	/**
	 * @param int $id
	 * @return Selection
	 */
	public function getMembersByReportId(int $id) {
		return $this->database->table(self::TABLE_AKCE_NAME)->where(':report_member.report_id', $id);
	}

	/**
	 * @param array ArrayHash $values
	 */
	public function addMemberToReport(ArrayHash $values) {
		$this->database->query('INSERT INTO `report_member`', $values);
	}

	/**
	 * @return array
	 */
	public function getReportTypes() {
		return $this->database->table('report_type')->order('id')->fetchPairs('id', 'title');
	}

}