<?php

/**
 * Model base class.
 */

namespace App\Model;

use Nette;

abstract class DatabaseService extends Nette\Object {
	/** @var Nette\Database\Context */
	public $database;

	/**
	 * DatabaseService constructor.
	 * @param Nette\Database\Context $database
	 */
	public function __construct(Nette\Database\Context $database) {
		$this->database = $database;
	}

	/**
	 *
	 */
	public function beginTransaction() {
		$this->database->beginTransaction();
	}

	/**
	 *
	 */
	public function commitTransaction() {
		$this->database->commit();
	}

	/**
	 * @param array $new
	 * @param array $old
	 * @return array
	 */
	public static function getDifferences(array $new, array $old){
		$diff = [];
		$diff['add'] = array_values(array_diff($new, $old));
		$diff['delete'] = array_values(array_diff($old, $new));
		return $diff;
	}
}