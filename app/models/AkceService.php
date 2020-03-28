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
use Tracy\Debugger;

class AkceService extends DatabaseService {
	const TABLE_AKCE_NAME = 'akce';
	const TABLE_AKCE_MEMBER_NAME = 'akce_member';
	const TABLE_AKCE_REVISION = 'akce_revision';

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
	 * @return Selection
	 */
	public function getMembersByAkceId(int $id) {
		return $this->database->table(self::TABLE_AKCE_MEMBER_NAME)
			->where('akce_id', $id)
			->where('deleted_by', NULL);
	}

	/**
	 * @param int $id
	 * @param bool $org
	 * @return array
	 */
	public function getMemberListByAkceId(int $id, bool $org = FALSE) {
		return $this->getMembersByAkceId($id)->where('organizator', $org)->fetchPairs('user_id', 'user_id');
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
	public function addMemberToAction(int $user_id, int $akce_id, bool $org = FALSE, int $created_by = NULL) {
		$values = ['user_id' => $user_id, 'akce_id' => $akce_id, 'organizator' => $org, 'date_add' => new DateTime()];
		$values['created_by'] = ($created_by) ? $created_by : $user_id;
		$this->database->query('INSERT INTO ?name ?values', self::TABLE_AKCE_MEMBER_NAME, $values);
	}

	/**
	 * @param int $user_id
	 * @param int $akce_id
	 * @param int $deleted_by
	 */
	public function deleteMemberFromAction(int $user_id, int $akce_id, int $deleted_by) {
		 $this->database->table(self::TABLE_AKCE_MEMBER_NAME)
			->where('user_id', $user_id)
			->where('akce_id', $akce_id)
			->where('deleted_by ?', NULL)
			->update(['date_deleted' => new DateTime(), 'deleted_by' => $deleted_by]);
	}

	/**
	 * @param int $id
	 * @return Selection
	 */
	public function getAkceByMemberId(int $id) {
		return $this->database
			->table(self::TABLE_AKCE_MEMBER_NAME)
			->select('akce_id, akce.date_start AS date_start, akce.name AS title, organizator, akce_member.created_by, akce_member.date_add')
			->where('akce.enable', TRUE)
			->where('akce.confirm', TRUE)
			->where('deleted_by', NULL)
			->where('user_id', $id)
			->order('akce.date_start DESC');
	}

	/**
	 * @param int $id
	 * @param bool $org
	 * @return array
	 */
	public function getAkceListByMemberId(int $id, bool $org = FALSE) {
		return array_values($this->database->table(self::TABLE_AKCE_MEMBER_NAME)
			->select('akce_id')
			->where('user_id', $id)
			->where('organizator', $org)
			->where('deleted_by', NULL)
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
			->select('akce.id, name, :akce_rating_member.user_id AS rating_user_id, :akce_rating_member.date_add AS rating_date_add')
			->where(':'.self::TABLE_AKCE_MEMBER_NAME.'.user_id', $user_id)
			->where(':'.self::TABLE_AKCE_MEMBER_NAME.'.deleted_by', NULL)
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
			->where(':'.self::TABLE_AKCE_MEMBER_NAME.'.deleted_by', NULL)
			->where('message', NULL)
			->where('date_end BETWEEN ? AND NOW()', $date);
	}

	/**
	 * @return Selection
	 */
	public function getRevisions() {
		return $this->database->table(self::TABLE_AKCE_REVISION);
	}

	/**
	 * @param int $id
	 * @return false|ActiveRow
	 */
	public function getRevisionById(int $id) {
		return $this->getRevisions()->get($id);
	}

	/**
	 * @param $akceId
	 * @return Selection
	 */
	public function getRevisionsByAkceId($akceId) {
		return $this->getRevisions()->where('akce_id', $akceId)->order('date_add DESC');
	}

	/**
	 * @param $akceId
	 * @return false|ActiveRow
	 */
	public function getLastRevisionByAkceId($akceId) {
		return $this->getRevisionsByAkceId($akceId)->fetch();
	}

	public function addRevision(int $akceId, int $userId, DateTime $date, string $text){
		return $this->getRevisions()->insert([
			'akce_id' => $akceId,
			'created_by' => $userId,
			'date_saved' => $date,
			'date_add' => new DateTime(),
			'text' => $text,
		]);
	}
}