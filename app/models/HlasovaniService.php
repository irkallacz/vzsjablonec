<?php

/**
 * HlasovaniService base class.
 */
class HlasovaniService extends DatabaseService{

    /**
     * @return \Nette\Database\Table\Selection
     */
    public function getAnkety(){
        return $this->database->table('hlasovani')->order('date_add DESC');
    }

    /**
     * @param $id
     * @return \Nette\Database\Table\IRow
     */
    public function getAnketaById($id){
        $anketa = $this->getAnkety()->get($id);
        //if ($anketa->date_deatline < date_create()) $anketa->locked = 1;
        return $anketa;
    }

    /**
     * @param $id
     * @return \Nette\Database\Table\Selection
     */
    public function getOdpovediByAnketaId($id){
        return $this->database->table('hlasovani_odpoved')->where('hlasovani_id',$id)->order('text');
    }

    /**
     * @param $id
     * @return \Nette\Database\Table\Selection
     */
    public function getMembersByAnketaId($id){
        return $this->database->table('member')
            ->select('id, CONCAT(surname," ",name)AS jmeno, :hlasovani_member.hlasovani_odpoved_id')
            ->where(':hlasovani_member.hlasovani_id',$id);
    }

    /**
     * @param \Nette\Utils\DateTime $date
     * @param bool $isBoard
     * @return \Nette\Database\Table\Selection
     */
    public function getHlasovaniNews(\Nette\Utils\DateTime $date, $isBoard = FALSE){
        $hlasovani = $this->getAnkety()->where('date_add > ?',$date);
        if ($isBoard) $hlasovani->where('date_deatline < NOW() OR locked = ?', 1);
        return $hlasovani;
    }

    /**
     * @param $id
     */
    public function deleteAnketaById($id){
        $this->database->table('hlasovani_member')->where('hlasovani_id',$id)->delete();
        $this->database->table('hlasovani_odpoved')->where('hlasovani_id',$id)->delete();
        $this->database->table('hlasovani')->where('id',$id)->delete();
    }

    /**
     * @param $values
     * @return bool|int|\Nette\Database\Table\IRow
     */
    public function addAnketa($values){
        return $this->getAnkety()->insert($values);
    }

    /**
     * @param $id
     * @return \Nette\Database\Table\IRow
     */
    public function getOdpovedById($id){
        return $this->database->table('hlasovani_odpoved')->get($id);
    }

    /**
     * @param $id
     */
    public function deleteOdpovediByAnketaId($id){
        $this->database->table('hlasovani_odpoved')->where('hlasovani_id',$id)->delete();
    }


    /**
     * @param $values
     * @return bool|int|\Nette\Database\Table\IRow
     */
    public function addOdpoved($values){
        return $this->database->table('hlasovani_odpoved')->insert($values);
    }

    /**
     * @param $values
     * @return bool|int|\Nette\Database\Table\IRow
     */
    public function addVote($values){
        return $this->database->table('hlasovani_member')->insert($values);
    }

    /**
     * @param $id
     */
    public function deleteVotesByAnketaId($id){
        $this->database->table('hlasovani_member')->where('hlasovani_id',$id)->delete();    
    }   
}