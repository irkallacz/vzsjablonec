<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 27.07.2017
 * Time: 15:42
 */

namespace App\Model;

use Nette\Object;

class ConnectionConfig extends Object {

	/** @var array */
	private $connections;

	/**
	 * ConnectionConfig constructor.
	 * @param array $connections
	 */
	public function __construct(array $connections) {
		$this->connections = $connections;
	}

	public function getConnections(){
		return $this->connections;
	}

}