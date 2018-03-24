<?php

/**
 * ForumService base class.
 */

namespace App\Model;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;

class ForumService extends DatabaseService{
    /**
     * @return Selection
     */
    public function getForum(){
        return $this->database->table('forum')->order('ord')->where('hidden',0);
    }

    /**
     * @param int $id
     * @return IRow
     */
    public function getForumById(int $id){
        return $this->getForum()->get($id);
    }

    /**
     * @return Selection
     */
    public function getPosts(){
        return $this->database->table('forum_post')->where('NOT row_number',0);
    }

	/**
	 * @param string $q
	 * @param string|NULL $forum
	 * @return Selection
	 */
	public function searchPosts(string $q, string $forum = NULL){
        $posts = $this->getPosts()->where('`text` LIKE ?',"%$q%");
        if ($forum) $posts->where('forum_id',$forum);

	    return $posts;
    }

	/**
	 * @param string $q
	 * @param string $forum|NULL
	 * @return Selection
	 */
	public function searchTopics(string $q, string $forum = NULL){
		$topics = $this->getTopics()->where('`title` LIKE ?',"%$q%");
		if ($forum) $topics->where('forum_id',$forum);

		return $topics;
	}

	/**
     * @param IRow|ActiveRow $topic
     * @return bool
     */
    public function checkTopic(IRow $topic){
        if ((!$topic)or($topic->row_number == 0)) return FALSE;
        else return TRUE;
    }


    /**
     * @param int $id
     * @return int
     */
    public function getPostsCountByTopicId(int $id){
        return $this->getPosts()->where('forum_topic_id',$id)->count('id');
    }

    /**
     * @param int $id
     * @param string $search
     * @return Selection
     */
    public function getPostsByTopicId(int $id, string $search = null){
        $posts = $this->getPosts()->where('forum_topic_id',$id);
	    if ($search) $posts->where('text LIKE ?',"%$search%");
	    return $posts;
    }

    /**
     * @param int $id
     * @return IRow|ActiveRow
     */
    public function getPostById(int $id){
        return $this->getPosts()->get($id);
    }

    /**
     * @param ArrayHash $values
     * @return bool|int|IRow
     */
    public function addPost(ArrayHash $values){
        $values->row_number = $this->getPostsByTopicId($values->forum_topic_id)->max('row_number') + 1;
        return $this->getPosts()->insert($values);
    }

    /**
     * @param ArrayHash $values
     */
    public function addTopic(ArrayHash $values){
        $values->forum_topic_id = 1;
        $values->row_number = 1;

        /** @var ActiveRow $row*/
        $row = $this->getTopics()->insert($values);
        $this->getPostById($row->id)->update(['forum_topic_id'=>$row->id]);
    }

    /**
     * @return Selection
     */
    public function getTopics(){
        return $this->getPosts()->where('forum_topic_id = id');
    }

    /**
     * @param int $id
     * @return IRow|ActiveRow
     */
    public function getTopicById(int $id){
        return $this->getTopics()->get($id);
    }

    /**
     * @param DateTime $date
     * @return Selection
     */
    public function getTopicNews(DateTime $date){
        return $this->getPosts()
            ->order('date_add DESC')
            ->where('date_add > ?',$date)
            ->group('forum_topic_id');
    }

	/**
	 * @param int $id
	 * @param string|NULL $search
	 * @return Selection
	 */
	public function getTopicsByForumId(int $id, string $search = null){
        $topics = $this->getTopics()->where('forum_id',$id)->where('forum_topic_id = id')->order('date_add DESC');
	    if ($search) $topics->where('title LIKE ?',"%$search%");
	    return $topics;
    }

	/**
	 * @param int $id
	 * @return int
	 */
	public function getTopicsCountByForumId(int $id){
		return $this->getTopics()->where('forum_id',$id)->count('id');
	}

}