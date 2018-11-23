<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 27.07.2017
 * Time: 15:42
 */

namespace App\Model;

use Nette\SmartObject;

class ConnectionConfig {
	use SmartObject;

	/** @var array */
	private $connections;

	/**
	 * ConnectionConfig constructor.
	 * @param array $connections
	 */
	public function __construct(array $connections) {
		$this->connections = $connections;
	}

	/**
	 * @return array
	 */
	public function getConnections() {
		return $this->connections;
	}

}