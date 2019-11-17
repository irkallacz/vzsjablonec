<?php

/**
 * Model base class.
 */

namespace App\Model;

use Nette\Database\Context;
use Nette\SmartObject;

abstract class DatabaseService {
	use SmartObject;

	/** @var Context */
	public $database;

	/**
	 * DatabaseService constructor.
	 * @param Context $database
	 */
	public function __construct(Context $database) {
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
	 * In ['add'] return values witch are in first array but not in second
	 * In ['delete'] return values witch are in second array but not in first
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