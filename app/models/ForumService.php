<?php

/**
 * ForumService base class.
 */

use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class ForumService extends DatabaseService{
    /**
     * @return Selection
     */
    public function getForum(){
        return $this->database->table('forum')->order('ord')->where('hidden',0);
    }

    /**
     * @param $id
     * @return IRow
     */
    public function getForumById($id){
        return $this->getForum()->get($id);
    }

    /**
     * @return Selection
     */
    public function getPosts(){
        return $this->database->table('forum_post')->where('hidden',0);
    }

    /**
     * @param $q
     * @param $in
     * @return Selection
     */
    public function searchPosts($q, $in){
        $items = $this->getPosts()->where('MATCH('.$in.') AGAINST (?)',$q);
        //->order('5 * MATCH(title) AGAINST (?) + MATCH(text) AGAINST (?) DESC',$q);

        return $items;
    }

    /**
     * @param $in
     */
    public function createSearchIndex($in){
        //return $this->database->exec('ALTER TABLE forum_post ADD FULLTEXT vyhledavani ('.$in.')');
    }

    /**
     *
     */
    public function destroySearchIndex(){
        //return $this->database->exec('ALTER TABLE `forum_post` DROP INDEX `vyhledavani`');
    }


    /**
     * @param IRow $topic
     * @return bool
     */
    public function checkTopic(IRow $topic){
        if ((!$topic)or($topic->row_number == 0)) return FALSE;
        else return TRUE;
    }


    /**
     * @param $id
     * @return int
     */
    public function getPostsCountByTopicId($id){
        return $this->database->table('forum_post')->where('hidden',0)->where('forum_topic_id',$id)->count('id');
    }

    /**
     * @param $id
     * @return Selection
     */
    public function getPostsByTopicId($id){
        return $this->database->table('forum_post')->where('hidden',0)->where('forum_topic_id',$id);
    }

    /**
     * @param $id
     * @return IRow
     */
    public function getPostById($id){
        return $this->getPosts()->get($id);
    }

    /**
     * @param $values
     * @return bool|int|IRow
     */
    public function addPost($values){
        $values->row_number = $this->getPostsByTopicId($values->forum_topic_id)->max('row_number') + 1;
        return $this->getPosts()->insert($values);
    }

    /**
     * @param $values
     */
    public function addTopic($values){
        $values->forum_topic_id = 1;
        $values->row_number = 1;
        $row = $this->getTopics()->insert($values);
        $this->getPostById($row->id)->update(array('forum_topic_id'=>$row->id));
    }

    /**
     * @return Selection
     */
    public function getTopics(){
        return $this->getPosts()->where('forum_topic_id = id')->order('date_add DESC');
    }

    /**
     * @param $id
     * @return IRow
     */
    public function getTopicById($id){
        return $this->getTopics()->get($id);
    }

    /**
     * @param \Nette\DateTime $date
     * @return Selection
     */
    public function getTopicNews(\Nette\DateTime $date){
        return $this->getPosts()
            ->order('date_add DESC')
            ->where('date_add > ?',$date)
            ->group('forum_topic_id');
    }

    /**
     * @param $id
     * @return Selection
     */
    public function getTopicsByForumId($id){
        return $this->getTopics()->where('forum_id',$id)->where('forum_topic_id = id');
    }   
    
}