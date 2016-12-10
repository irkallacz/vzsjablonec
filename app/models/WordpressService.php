<?php

/**
 * Model base class.
 */
class WordpressService extends Nette\Object{

    const CATEGORY = 2;

    /** @var Nette\Database\Connection */
    public $database;

    public function __construct(Nette\Database\Context $database){
          $this->database = $database;
    }

    public function getLastNews(){
        return $this->database->query('SELECT post_content FROM nnx2_posts LEFT JOIN nnx2_term_relationships ON object_id = id WHERE post_status = ? AND term_taxonomy_id = ? ORDER BY post_date DESC','publish',self::CATEGORY)
            ->fetch();
    }
}

