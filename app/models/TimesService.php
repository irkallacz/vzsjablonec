<?php

/**
 * TimesService base class.
 */

use Nette\Database\Table\Selection;
use Nette\Database\Table\IRow;

class TimesService extends DatabaseService
{
    /**
     * @return Selection
     */
    public function getTimes(){
        return $this->database->table('times');
    }

    /**
     * @return Selection
     */
    public function getDefaultTimes(){
        return $this->getTimes()
        	->select('times.id, times_disciplina_id, times_disciplina.name AS disciplina, time, date, 
	           times.text, member_id, CONCAT(member.surname," ",member.name)AS jmeno')->where('member.active = 1');
    }

    /**
     * @param $id
     * @return IRow
     */
    public function getTimeById($id){
        return $this->getTimes()->get($id);
    }

    /**
     * @return array
     */
    public function getTimesDisciplineArray(){
        return $this->database->table('times_disciplina')->order('id')->fetchPairs('id','name');
    }

    /**
     * @param $values
     * @return bool|int|IRow
     */
    public function addTime($values){
        return $this->getTimes()->insert($values);
    }      

}