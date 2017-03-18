<?php

use Nette\Database\SqlLiteral;
/**
 * MemberService base class.
 */
class MemberService extends DatabaseService{

    /**
     * @param bool $activeOnly
     * @return \Nette\Database\Table\Selection
     */
    public function getMembers($activeOnly = TRUE){
        $member =  $this->database->table('member');
        if ($activeOnly) $member->where('active',TRUE);
        return $member;        
    }

    /**
     * @param bool $activeOnly
     * @return array
     */
    public function getMembersArray($activeOnly = TRUE){
        return $this->getMembers($activeOnly)
            ->select('id, CONCAT(surname, " ", name)AS jmeno')
            ->order('surname, name')
            ->fetchPairs('id','jmeno');
    }

    /**
     * @param $id
     * @return \Nette\Database\Table\IRow
     */
    public function getMemberById($id){
        return $this->getMembers()->get($id);        
    }

    /**
     * @param $username
     * @param $password
     * @return bool|mixed|\Nette\Database\Table\IRow
     */
    public function getMemberByAutentication($username, $password){
        $member = $this->getMembers()
	        ->select('login, hash')
            ->where('login',$username)
            ->fetch();

        return Nette\Security\Passwords::verify($password, $member->hash) ? $member : FALSE;
    }

    /**
     * @param $role
     * @return \Nette\Database\Table\Selection
     */
    public function getMembersByRole($role){
        return $this->getMembers()->where(':rights_member.rights_id',$this->database->table('rights')->where('name',$role));
    }

    /**
     * @param $mail
     * @return bool|mixed|\Nette\Database\Table\IRow
     */
    public function getMemberByEmail($mail){
        return $this->getMembers()->where('mail',$mail)->fetch();
    }

    /**
     * @param $login
     * @return bool|mixed|\Nette\Database\Table\IRow
     */
    public function getMemberByLogin($login){
        return $this->getMembers()->select('id, hash, login')->where('login',$login)->fetch();
    }

    /**
     * @param $values
     * @return bool|int|\Nette\Database\Table\IRow
     */
    public function addMember($values){
        return $this->getMembers()->insert($values);        
    }

    /**
     * @param $id
     * @return array
     */
    public function getRightsByMemberId($id){
        return $this->database->table('rights_member')->select('rights.name, rights_id')->where('member_id',$id)->fetchPairs('rights_id','name');
    }

    /**
     * @param $id
     * @return int
     */
    public function getLastLoginByMemberId($id){
        return $this->database->table('member_log')->where('member_id',$id)->max('date_add');
    }

    /**
     * @param $user_id
     * @param DateTime $datetime
     */
    public function addMemberLogin($user_id, DateTime $datetime){
        $this->database->query('INSERT INTO member_log VALUES(?, ?) ON DUPLICATE KEY UPDATE date_add = ?', $user_id, $datetime, $datetime);
    }

	/**
     * @param $member_id
     * @return bool|int|\Nette\Database\Table\IRow
     */
    public function addPasswordSession($member_id, $interval = '20 MINUTE'){
        $this->database->query('DELETE FROM `password_session` WHERE `member_id` = ?', $member_id);
        return $this->database->table('password_session')->insert(['member_id' => $member_id, 'date_end' => new SqlLiteral('NOW() + INTERVAL '.$interval)]);
    }

	/**
     * @param $pubkey
     * @return bool|mixed|\Nette\Database\Table\IRow
     */
    public function getPasswordSession($pubkey){
        $this->database->query('DELETE FROM `password_session` WHERE `date_end` < ?', new SqlLiteral('NOW()'));
        return $this->database->table('password_session')->where('pubkey', $pubkey)->fetch();
    }
}