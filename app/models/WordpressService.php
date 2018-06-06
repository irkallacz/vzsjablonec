<?php

/**
 * Model base class.
 */

namespace App\Model;

use Nette;

class WordpressService {
	use Nette\SmartObject;

    const CATEGORY = 2;

    /** @var Nette\Database\Connection */
    public $database;

    public function __construct(Nette\Database\Context $database){
          $this->database = $database;
    }

    public function getLastNews(){
        $query = $this->database->query("SELECT post_content FROM nnx2_posts LEFT JOIN nnx2_term_relationships ON object_id = id WHERE term_taxonomy_id = ? AND post_status = 'publish' ORDER BY post_date DESC LIMIT 1", self::CATEGORY);
        return $query->fetch();
    }
}

