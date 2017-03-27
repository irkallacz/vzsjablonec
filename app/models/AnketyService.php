<?php

/**
 * MemberService base class.
 */

namespace App\Model;

use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class AnketyService extends DatabaseService{

    /**
     * @return Selection
     */
    public function getAnkety(){
        return $this->database->table('anketa')->order('date_add DESC');
    }

    /**
     * @param $id
     * @return IRow
     */
    public function getAnketaById($id){
        return $this->getAnkety()->get($id);
    }

    /**
     * @param $id
     * @return Selection
     */
    public function getOdpovediByAnketaId($id){
        return $this->database->table('anketa_odpoved')->where('anketa_id',$id)->order('text');
    }

    /**
     * @param $id
     * @return array
     */
    public function getMemberListByAnketaId($id){
        return $this->database->table('anketa_member')->where('anketa_id',$id)->fetchPairs('member_id','anketa_odpoved_id');
    }

    public function getOdpovedIdByAnketaId($anketa_id,$user_id){
        $odpoved = $this->database->table('anketa_member')->where(['member_id', $user_id, 'anketa_id' => $anketa_id])->fetch();
        if ($odpoved) return $odpoved->anketa_odpoved_id; else return 0;
    }

    /**
     * @param $id
     * @return Selection
     */
    public function getMembersByAnketaId($id){
        return $this->database->table('member')
            ->select('id, CONCAT(surname," ",name)AS jmeno, :anketa_member.anketa_odpoved_id')
            ->where(':anketa_member.anketa_id',$id);
    }

    /**
     * @param \Nette\Utils\DateTime $date
     * @return Selection
     */
    public function getAnketyNews(\Nette\Utils\DateTime $date){
        return $this->getAnkety()->where('date_add > ?',$date);
    }

    /**
     * @param $id
     */
    public function deleteAnketaById($id){
        $this->database->table('anketa_member')->where('anketa_id',$id)->delete();
        $this->database->table('anketa_odpoved')->where('anketa_id',$id)->delete();
        $this->database->table('anketa')->where('id',$id)->delete();
    }

    /**
     * @param $values
     * @return bool|int|IRow
     */
    public function addAnketa($values){
        return $this->getAnkety()->insert($values);
    }

    /**
     * @param $id
     * @return IRow
     */
    public function getOdpovedById($id){
        return $this->database->table('anketa_odpoved')->get($id);
    }

    /**
     * @param $id
     */
    public function deleteOdpovediByAnketaId($id){
        $this->database->table('anketa_odpoved')->where('anketa_id',$id)->delete();
    }

    /**
     * @param $values
     * @return bool|int|IRow
     */
    public function addOdpoved($values){
        return $this->database->table('anketa_odpoved')->insert($values);
    }

    /**
     * @param $values
     * @return bool|int|IRow
     */
    public function addVote($values){
        $this->database->query('INSERT INTO anketa_member',$values);
    }

    /**
     * @param $id
     */
    public function deleteVotesByAnketaId($id){
        $this->database->table('anketa_member')->where('anketa_id',$id)->delete();    
    }

    /**
     * @param $anketa_id
     * @param $member_id
     */
    public function deleteMemberVote($anketa_id, $member_id){
        $this->database->table('anketa_member')->where('anketa_id',$anketa_id)->where('member_id',$member_id)->delete();    
    }

    /**
     * @param $anketa_id
     * @param $member_id
     * @return bool|mixed|IRow
     */
    public function getMemberVote($anketa_id, $member_id){
        return $this->database->table('anketa_member')->where('anketa_id',$anketa_id)->where('member_id',$member_id)->fetch(); 
    }            
}