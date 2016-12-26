<?php

/**
 * Model base class.
 */
class AkceService extends DatabaseService
{
    /**
     * @return \Nette\Database\Table\Selection
     */
    public function getAkce(){
        return $this->database->table('akce');        
    }

    /**
     * @param bool $future
     * @return Nette\Database\Table\Selection
     */
    public function getAkceByFuture($future = false){
        $akce = $this->getAkce()->where('enable',1)->order('date_start DESC');
        
        if ($future) $akce->where('date_start > NOW()'); else $akce->where('date_start < NOW()');
        
        return $akce;
    }

    /**
     * @param $id
     * @return \Nette\Database\Table\IRow
     */
    public function getAkceById($id){
        return $this->getAkce()->get($id);
    }

    /**
     * @param \Nette\DateTime $date
     * @return \Nette\Database\Table\Selection
     */
    public function getAkceNews(\Nette\DateTime $date){
        return $this->getAkceByFuture(TRUE)
            ->where('confirm',TRUE)
            ->where('date_update > ?',$date);
    }

    /**
     * @param $id
     * @param \Nette\DateTime $date
     * @return bool|mixed|\Nette\Database\Table\IRow
     */
    public function getAkceNext($id, \Nette\DateTime $date){
        return $this->getAkce()
                ->where('enable',TRUE)
                ->where('confirm',TRUE)
                ->where('id != ?',$id)
                ->where('date_start >= ?',$date)
                ->order('date_start')
                ->limit(1)
                ->fetch();
    }

    /**
     * @param $id
     * @param \Nette\DateTime $date
     * @return bool|mixed|\Nette\Database\Table\IRow
     */
    public function getAkcePrev($id, \Nette\DateTime $date){
        return $this->getAkce()
                ->where('enable',TRUE)
                ->where('confirm',TRUE)
                ->where('id != ?',$id)
                ->where('date_start <= ?',$date)
                ->order('date_start DESC')
                ->limit(1)
                ->fetch();
    }

    /**
     * @param $values
     * @return bool|int|\Nette\Database\Table\IRow
     */
    public function addAkce($values){
        return $this->getAkce()->insert($values);
    }

    /**
     * @return array
     */
    public function getAkceForInArray(){
        return $this->database->table('akce_for')->select('id,text')->fetchPairs('id','text');
    }

    /**
     * @param $id
     * @param bool $org
     * @return \Nette\Database\Table\Selection
     */
    public function getMembersByAkceId($id, $org = false){
        return $this->database->table('member')->where(':akce_member.akce_id',$id)->where('organizator',$org);
    }

    /**
     * @param $id
     * @param bool $org
     * @return array
     */
    public function getMemberListByAkceId($id, $org = false){
        return $this->database->table('akce_member')->where('akce_id',$id)->where('organizator',$org)->fetchPairs('member_id','member_id');
    }

    public function getAkceMessageDefault(){
		return "Co se stalo:\n\nPočasí:\n\nZásahy (na břehu, ve vodě, na záchranu majetku):\n\nOšetření (drobné, větší, s odvozem):\n\nPoužitý materiál na akce v majetku místní skupiny:\n\nDalší použitý materiál:\n\nZtráty a poškození materiálu:\n\nDoprava na akci a způsob její úhrady:";
    }

    /**
     * @param $id
     * @param $org
     * @return mixed
     */
    public function getMemberListForAkceComponent($id, $org){
        return $this->getMembersByAkceId($id,$org)
            ->select('id,CONCAT(surname," ",name)AS jmeno')
            ->order(':akce_member.date_add')
            ->fetchPairs('id','jmeno');
    }

    /**
     * @param bool $activeOnly
     * @return \Nette\Database\Table\Selection
     */
    public function getMembers($activeOnly = TRUE){
        $members = $this->database->table('member')->select('id, CONCAT(surname, " ", name)AS jmeno')
                ->order('surname, name');
        if ($activeOnly) $members->where('active',1);
        return $members;
    }

    /**
     * @param $member_id
     * @param $akce_id
     * @param bool $org
     */
    public function addMemberToAction($member_id, $akce_id, $org = false){
        $values = ['member_id' => $member_id, 'akce_id' => $akce_id, 'organizator' => $org];
        $this->database->query('INSERT INTO `akce_member`',$values);
    }

    /**
     * @param $member_id
     * @param $akce_id
     * @param bool $org
     */
    public function deleteMemberFromAction($member_id, $akce_id, $org = false){
        $this->database->table('akce_member')
            ->where('member_id',$member_id)
            ->where('akce_id',$akce_id)
            ->where('organizator',$org)
            ->delete();
    }

    /**
     * @param $id
     * @param bool $org
     * @return array
     */
    public function getAkceByMemberId($id, $org = false){
        return array_values($this->database->table('akce_member')
            ->select('akce_id')
            ->where('member_id',$id)
            ->where('organizator',$org)
            ->fetchPairs('akce_id','akce_id'));
    }

    /**
     * @param \Nette\DateTime $date
     * @param $user_id
     * @return \Nette\Database\Table\Selection
     */
    public function getFeedbackRequests(\Nette\DateTime $date, $user_id){
        return $this->getAkceByFuture(FALSE)
            ->where('confirm',TRUE)
            ->where(':akce_member.member_id',$user_id)
            ->where(':akce_member.organizator',FALSE)
            ->where('date_end > ?',$date);
    }


	/**
	 * @param \Nette\DateTime $date
	 * @param $user_id
	 * @return \Nette\Database\Table\Selection
	 */
	public function getRatingNews(\Nette\DateTime $date, $user_id){
		return $this->getAkceByFuture(FALSE)
			->select('id, name, :akce_rating_member.member_id AS rating_member_id, :akce_rating_member.date_add AS rating_date_add')
			->where(':akce_member.member_id',$user_id)
			->where(':akce_member.organizator',TRUE)
			->where(':akce_rating_member.date_add > ?',$date);

	}

    /**
     * @param \Nette\DateTime $date
     * @param $user_id
     * @return \Nette\Database\Table\Selection
     */
    public function getReportRequests(\Nette\DateTime $date, $user_id){
        return $this->getAkceByFuture(FALSE)
            ->where('confirm',TRUE)
            ->where(':akce_member.member_id',$user_id)
            ->where(':akce_member.organizator',TRUE)
            ->where('report',FALSE)
            ->where('date_end > ?',$date);
    }

    /**
     * @param $id
     * @return \Nette\Database\Table\IRow
     */
    public function getReportById($id){
        return $this->database->table('report')->get($id);
    }

    /**
     * @param $values
     * @return bool|int|\Nette\Database\Table\IRow
     */
    public function addReport($values){
        return $this->database->table('report')->insert($values);
    }

    /**
     * @param $id
     * @return \Nette\Database\Table\Selection
     */
    public function getMembersByReportId($id){
        return $this->database->table('member')->where(':report_member.report_id',$id);
    }

    /**
     * @param array $values
     */
    public function addMemberToReport($values){
        $this->database->query('INSERT INTO `report_member`',$values);
    }

    /**
     * @return array
     */
    public function getReportTypes(){
        return $this->database->table('report_type')->order('id')->fetchPairs('id','title');
    }

}