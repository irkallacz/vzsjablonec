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
        return $this->database->table('forum_post')->where('NOT row_number',0);
    }

    /**
     * @param $q
     * @param $forum
     * @return Selection
     */
    public function searchPosts($q, $forum = NULL){
        $posts = $this->getPosts()->where('`text` LIKE ?',"%$q%");
        if ($forum) $posts->where('forum_id',$forum);

	    return $posts;
    }

	/**
	 * @param $q
	 * @param $forum
	 * @return Selection
	 */
	public function searchTopics($q, $forum = NULL){
		$topics = $this->getTopics()->where('`title` LIKE ?',"%$q%");
		if ($forum) $topics->where('forum_id',$forum);

		return $topics;
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
        return $this->getPosts()->where('forum_topic_id',$id)->count('id');
    }

    /**
     * @param $id
     * @return Selection
     */
    public function getPostsByTopicId($id, $search = null){
        $posts = $this->getPosts()->where('forum_topic_id',$id);
	    if ($search) $posts->where('text LIKE ?',"%$search%");
	    return $posts;
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
        return $this->getPosts()->where('forum_topic_id = id');
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
	 * @param null $search
	 * @return Selection
	 */
	public function getTopicsByForumId($id, $search = null){
        $topics = $this->getTopics()->where('forum_id',$id)->where('forum_topic_id = id')->order('date_add DESC');
	    if ($search) $topics->where('title LIKE ?',"%$search%");
	    return $topics;
    }

	/**
	 * @param $id
	 * @return int
	 */
	public function getTopicsCountByForumId($id){
		return $this->getTopics()->where('forum_id',$id)->count('id');
	}

}