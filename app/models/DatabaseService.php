<?php

/**
 * Model base class.
 */
abstract class DatabaseService extends Nette\Object
{
    /** @var Nette\Database\Context */
    public $database;

    public function __construct(Nette\Database\Context $database){
          $this->database = $database;
    }
}