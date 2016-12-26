<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 26.12.2016
 * Time: 16:18
 */
class RatingService extends DatabaseService {

	/**
	 * @return \Nette\Database\Table\Selection
	 */
	public function getRating(){
		return $this->database->table('akce_rating_member');
	}

	/**
	 * @param $akce_id
	 * @return \Nette\Database\Table\Selection
	 */
	public function getRatingByAkceId($akce_id){
		return $this->getRating()->where('akce_id',$akce_id);
	}

	/**
	 * @param $akce_id
	 * @return array
	 */
	public function getRatingArrayByAkceId($akce_id){
		return $this->getRating()->where('akce_id',$akce_id)->where('rating IS NOT NULL')->fetchPairs('member_id','rating');
	}

	/**
	 * @param $akce_id
	 * @param $member_id
	 * @return bool|mixed|\Nette\Database\Table\IRow
	 */
	public function getRatingByAkceAndMemberId($akce_id, $member_id){
		return $this->getRating()->where('akce_id',$akce_id)->where('member_id',$member_id)->fetch();//get(array($akce_id,$member_id));
	}

	/**
	 * @param $akce_id
	 * @param $member_id
	 * @param $values
	 */
	public function addRatingByAkceAndMemberId($akce_id, $member_id, $values){
		$values['member_id'] = $member_id;
		$values['akce_id'] = $akce_id;
		$this->database->query('INSERT INTO `akce_rating_member`',$values);
	}

	/**
	 * @param $akce_id
	 * @param $member_id
	 * @param $values
	 */
	public function updateRatingByAkceAndMemberId($akce_id, $member_id, $values){
		//$this->database->table('akce_rating_member')->get(array($akce_id,$member_id))->update($values);
		$this->database->query('UPDATE `akce_rating_member` SET ? WHERE akce_id = ? AND member_id = ?',$values,$akce_id,$member_id);
	}
}