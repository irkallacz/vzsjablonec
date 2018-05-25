<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 26.12.2016
 * Time: 16:18
 */

namespace App\Model;

use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;

class RatingService extends DatabaseService {

	/**
	 * @return Selection
	 */
	public function getRating() {
		return $this->database->table('akce_rating_member');
	}

	/**
	 * @param int $akce_id
	 * @return Selection
	 */
	public function getRatingByAkceId(int $akce_id) {
		return $this->getRating()->where('akce_id', $akce_id);
	}

	/**
	 * @param int $akce_id
	 * @return array
	 */
	public function getRatingArrayByAkceId(int $akce_id) {
		return $this->getRating()->where('akce_id', $akce_id)->where('rating IS NOT NULL')->fetchPairs('user_id', 'rating');
	}

	/**
	 * @param int $akce_id
	 * @param int $user_id
	 * @return bool|mixed|IRow
	 */
	public function getRatingByAkceAndMemberId(int $akce_id, int $user_id) {
		return $this->getRating()->where('akce_id', $akce_id)->where('user_id', $user_id)->fetch();//get(array($akce_id,$user_id));
	}

	/**
	 * @param int $akce_id
	 * @param int $user_id
	 * @param ArrayHash $values
	 */
	public function addRatingByAkceAndMemberId(int $akce_id, int $user_id, ArrayHash $values) {
		$values['user_id'] = $user_id;
		$values['akce_id'] = $akce_id;
		$this->database->query('INSERT INTO `akce_rating_member`', $values);
	}

	/**
	 * @param int $akce_id
	 * @param int $user_id
	 * @param ArrayHash $values
	 */
	public function updateRatingByAkceAndMemberId(int $akce_id, int $user_id, ArrayHash $values) {
		//$this->database->table('akce_rating_member')->get(array($akce_id,$user_id))->update($values);
		$this->database->query('UPDATE `akce_rating_member` SET ? WHERE akce_id = ? AND user_id = ?', $values, $akce_id, $user_id);
	}
}