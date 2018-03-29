<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 10.12.2017
 * Time: 19:00
 */

namespace App\SignModule;

use Nette\Object;

class StateCryptor extends Object {

	/**
	 * @var array $params
	 */
	private $params;

	/**
	 * StateCryptor constructor.
	 * @param array $params
	 */
	public function __construct(array $params) {
		$this->params = $params;
	}

	/**
	 * @param string $state
	 * @param string $key
	 * @return string
	 */
	public function encryptState($state, $key){
		$iv = mb_substr($this->getID($key), 0, 16);
		$secret = $this->getSecret($key);

		$ciphertext = openssl_encrypt($state, 'AES-128-CBC', $secret, $options=OPENSSL_RAW_DATA, $iv);
		return  base64_encode($ciphertext);
	}

	/**
	 * @param string $state
	 * @param string $key
	 * @return string
	 */
	public function decryptState($state, $key){
		$iv = mb_substr($this->getID($key), 0, 16);
		$secret = $this->getSecret($key);

		$ciphertext = base64_decode($state);
		return openssl_decrypt($ciphertext, 'AES-128-CBC', $secret, $options=OPENSSL_RAW_DATA, $iv);
	}

	/**
	 * @param string $key
	 * @return string
	 */
	private function getID($key){
		$params = $this->params[$key];
		$keys = array_keys($params);
		return $params[$keys[0]];
	}

	/**
	 * @param string $key
	 * @return string
	 */
	private function getSecret($key){
		$params = $this->params[$key];
		$keys = array_keys($params);
		return $params[$keys[1]];
	}
}