<?php

/**
 * MemberService base class.
 */

use Nette\DateTime;

class DokumentyService extends DatabaseService{

    const HLASOVANI = 31;
    const ZAPISY = 6;

    /**
     * @return \Nette\Database\Table\Selection
     */
    public function getDokumenty(){
        return $this->database->table('dokumenty');
    }

    /**
     * @return \Nette\Database\Table\Selection
     */
    public function getDokumentyCategory(){
        return $this->database->table('dokumenty_category')
            ->where('NOT id',self::ZAPISY)
	        ->where('parent_id IS NULL')
	        ->order('id');
    }

    /**
     * @param \Nette\DateTime $date
     * @return \Nette\Database\Table\Selection
     */
    public function getDokumentyNews(\Nette\DateTime $date){
        return $this->getDokumenty()
            ->where('date_add > ?',$date)
            ->order('date_add DESC');
    }

    /**
     * @param $id
     * @return \Nette\Database\Table\IRow
     */
    public function getDokumentyCategoryById($id){
        return $this->database->table('dokumenty_category')->get($id);
    }

    /**
     * @param $id
     * @return \Nette\Database\Table\IRow
     */
    public function getDokumentById($id){
        return $this->getDokumenty()->get($id);        
    }

    /**
     * @param $values
     * @return bool|int|\Nette\Database\Table\IRow
     */
    public function addDokument($values){
        $values->date_add = new DateTime();
        return $this->getDokumenty()->insert($values);        
    }

    /**
     * @return \Nette\Database\Table\Selection
     */
    public function getZapisy(){
        return $this->database->table('dokumenty_category')->where('id',self::ZAPISY);
    }

    /**
     * @param $values
     * @return bool|int|\Nette\Database\Table\IRow
     */
    public function addZapis($values){
        $values->dokumenty_category_id = self::ZAPISY;

        return $this->addDokument($values);
    }

    /**
     * @return \Nette\Database\Table\IRow
     */
    public function getHlasovani(){
        return $this->getDokumenty()->get(self::HLASOVANI);
    }            

}