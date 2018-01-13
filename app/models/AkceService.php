<?php

/**
 * Model base class.
 */

namespace App\Model;

use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
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
	public function getAkceByFuture($future = false) {
		$akce = $this->getAkce()->where('enable', 1);

		if ($future) $akce->where('date_start > NOW()')->order('date_start ASC'); else $akce->where('date_start < NOW()')->order('date_start DESC');;

		return $akce;
	}

	/**
	 * @param $id
	 * @return IRow
	 */
	public function getAkceById($id) {
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
	 * @param $id
	 * @param DateTime $date
	 * @return bool|mixed|IRow
	 */
	public function getAkceNext($id, DateTime $date) {
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
	 * @param $id
	 * @param DateTime $date
	 * @return bool|mixed|IRow
	 */
	public function getAkcePrev($id, DateTime $date) {
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
	 * @param $values
	 * @return bool|int|IRow
	 */
	public function addAkce($values) {
		return $this->getAkce()->insert($values);
	}

	/**
	 * @return array
	 */
	public function getAkceForInArray() {
		return $this->database->table('akce_for')->select('id,text')->fetchPairs('id', 'text');
	}

	/**
	 * @param $id
	 * @param bool $org
	 * @return array
	 */
	public function getMemberListByAkceId($id, $org = false) {
		return $this->database->table(self::TABLE_AKCE_MEMBER_NAME)->where('akce_id', $id)->where('organizator', $org)->fetchPairs('member_id', 'member_id');
	}

	/**
	 * @return string
	 */
	public function getAkceMessageDefault() {
		return "<!-- Celý tento řádek smažte a napište sem, co se stalo -->\n\nPočasí:\n\nZásahy (na břehu, ve vodě, na záchranu majetku):\n\nOšetření (drobné, větší, s odvozem):\n\nPoužitý materiál na akce v majetku místní skupiny:\n\nDalší použitý materiál:\n\nZtráty a poškození materiálu:\n\nDoprava na akci a způsob její úhrady:";
	}

	/**
	 * @param $member_id
	 * @param $akce_id
	 * @param bool $org
	 */
	public function addMemberToAction($member_id, $akce_id, $org = false) {
		$values = ['member_id' => $member_id, 'akce_id' => $akce_id, 'organizator' => $org];
		$this->database->query('INSERT INTO `'.self::TABLE_AKCE_MEMBER_NAME.'`', $values);
	}

	/**
	 * @param $member_id
	 * @param $akce_id
	 * @param bool $org
	 */
	public function deleteMemberFromAction($member_id, $akce_id) {
		$this->database->table(self::TABLE_AKCE_MEMBER_NAME)
			->where('member_id', $member_id)
			->where('akce_id', $akce_id)
			->delete();
	}

	/**
	 * @param $id
	 * @param bool $org
	 * @return array
	 */
	public function getAkceByMemberId($id, $org = false) {
		return array_values($this->database->table(self::TABLE_AKCE_MEMBER_NAME)
			->select('akce_id')
			->where('member_id', $id)
			->where('organizator', $org)
			->fetchPairs('akce_id', 'akce_id'));
	}

	/**
	 * @param DateTime $date
	 * @param $user_id
	 * @return Selection
	 */
	public function getFeedbackRequests(DateTime $date, $user_id) {
		return $this->getAkce()
			->where('enable', TRUE)
			->where('confirm', TRUE)
			->where(':akce_member.member_id', $user_id)
			->where(':akce_member.organizator', FALSE)
			->where('date_end BETWEEN ? AND NOW()', $date);
	}


	/**
	 * @param DateTime $date
	 * @param $user_id
	 * @return Selection
	 */
	public function getRatingNews(DateTime $date, $user_id) {
		return $this->getAkceByFuture(FALSE)
			->select('id, name, :akce_rating_member.member_id AS rating_member_id, :akce_rating_member.date_add AS rating_date_add')
			->where(':'.self::TABLE_AKCE_MEMBER_NAME.'.member_id', $user_id)
			->where(':'.self::TABLE_AKCE_MEMBER_NAME.'.organizator', TRUE)
			->where(':akce_rating_member.date_add > ?', $date);

	}

	/**
	 * @param DateTime $date
	 * @param $user_id
	 * @return Selection
	 */
	public function getReportRequests(DateTime $date, $user_id) {
		return $this->getAkce()
			->where('enable', TRUE)
			->where('confirm', TRUE)
			->where(':'.self::TABLE_AKCE_MEMBER_NAME.'.member_id', $user_id)
			->where(':'.self::TABLE_AKCE_MEMBER_NAME.'.organizator', TRUE)
			->where('message', NULL)
			->where('date_end BETWEEN ? AND NOW()', $date);
	}

	/**
	 * @param $id
	 * @return IRow
	 */
	public function getReportById($id) {
		return $this->database->table('report')->get($id);
	}

	/**
	 * @param $values
	 * @return bool|int|IRow
	 */
	public function addReport($values) {
		return $this->database->table('report')->insert($values);
	}

	/**
	 * @param $id
	 * @return Selection
	 */
	public function getMembersByReportId($id) {
		return $this->database->table(self::TABLE_AKCE_NAME)->where(':report_member.report_id', $id);
	}

	/**
	 * @param array $values
	 */
	public function addMemberToReport($values) {
		$this->database->query('INSERT INTO `report_member`', $values);
	}

	/**
	 * @return array
	 */
	public function getReportTypes() {
		return $this->database->table('report_type')->order('id')->fetchPairs('id', 'title');
	}

}