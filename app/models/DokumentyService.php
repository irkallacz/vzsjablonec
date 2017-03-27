<?php

/**
 * MemberService base class.
 */

namespace App\Model;

use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Nette\Utils\Html;

class DokumentyService extends DatabaseService{

    const HLASOVANI = 31;
    const ZAPISY = 6;

    /**
     * @return Selection
     */
    public function getDokumenty(){
        return $this->database->table('dokumenty');
    }

    /**
     * @return Selection
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
			$array[$row->id] = Html::el()->setHtml(str_repeat('&nbsp;&nbsp;',$row->level).$row->title);
		}
	return $array;
    }

    /**
     * @param DateTime $date
     * @return Selection
     */
    public function getDokumentyNews(DateTime $date){
        return $this->getDokumenty()
            ->where('date_add > ?',$date)
            ->order('date_add DESC');
    }

    /**
     * @param $id
     * @return IRow
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
     * @return IRow
     */
    public function getDokumentById($id){
        return $this->getDokumenty()->get($id);        
    }

    /**
     * @param $values
     * @return bool|int|IRow
     */
    public function addDokument($values){
        $values->date_add = new DateTime();
        return $this->getDokumenty()->insert($values);        
    }

	/**
	 * @param $year
	 * @return bool|mixed|IRow
	 */
	public function getZapisCategoryByYear($year){
		return $this->database->table('dokumenty_category')
			->where('title',$year)
			->where('parent_id',self::ZAPISY)
			->fetch();
	}

}