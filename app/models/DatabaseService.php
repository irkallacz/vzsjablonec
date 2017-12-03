<?php

/**
 * Model base class.
 */

namespace App\Model;

use Nette;

abstract class DatabaseService extends Nette\Object {
	/** @var Nette\Database\Context */
	public $database;

	public function __construct(Nette\Database\Context $database) {
		$this->database = $database;
	}

	public function beginTransaction() {
		$this->database->beginTransaction();
	}

	public function commitTransaction() {
		$this->database->commit();
	}
}