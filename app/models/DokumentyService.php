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
        return $this->database->table('dokumenty_category');
    }

	public function getDokumentyCategoryParent(){
		return $this->database->table('dokumenty_category')
			->where('parent_id IS NULL')
			->order('id');
	}

	/**
	 * @return array
	 */
	public function getDokumentyCategoryList(){
		$result = $this->database->query("SELECT `id`, `title`, LENGTH(`dirname`) - LENGTH(REPLACE(`dirname`, '/', '')) AS `level`
		FROM `dokumenty_category`
		ORDER BY `dirname`");

		$array = [];
		foreach($result as $row){
			$array[$row->id] = Nette\Utils\Html::el()->setHtml(str_repeat('&nbsp;&nbsp;',$row->level).$row->title);
		}
	return $array;
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
        return $this->getDokumentyCategory()->get($id);
    }

	/**
	 * @param $values
	 */
	public function addDokumentyCategory($values){
		$this->getDokumentyCategory()->insert($values);
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
//
//    /**
//     * @return \Nette\Database\Table\Selection
//     */
//    public function getZapisy(){
//        return $this->database->table('dokumenty_category')->where('id',self::ZAPISY);
//    }
//
//	/**
//	 * @param $year
//	 * @return bool|mixed|\Nette\Database\Table\IRow
//	 */
//	public function getZapisCategoryByYear($year){
//		return $this->database->table('dokumenty_category')
//			->where('title',$year)
//			->where('parent_id',self::ZAPISY)
//			->fetch();
//	}
//
//    /**
//     * @return \Nette\Database\Table\IRow
//     */
//    public function getHlasovani(){
//        return $this->getDokumenty()->get(self::HLASOVANI);
//    }

}