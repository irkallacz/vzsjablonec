<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 10.12.2017
 * Time: 19:00
 */

namespace App\AccountModule;

use Nette\SmartObject;

class StateCryptor {

	use SmartObject;

	const CRYPT_METHOD = 'AES-128-CBC';

	/** @var array $params */
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
	public function encryptState($state, $key) {
		$iv = $this->getIV($this->getID($key));
		$secret = $this->getSecret($key);

		$ciphertext = openssl_encrypt($state, self::CRYPT_METHOD, $secret, $options = OPENSSL_RAW_DATA, $iv);
		return base64_encode($ciphertext);
	}

	/**
	 * @param string $state
	 * @param string $key
	 * @return string
	 */
	public function decryptState($state, $key) {
		$iv = $this->getIV($this->getID($key));
		$secret = $this->getSecret($key);

		$ciphertext = base64_decode($state);
		return openssl_decrypt($ciphertext, self::CRYPT_METHOD, $secret, $options = OPENSSL_RAW_DATA, $iv);
	}

	/**
	 * @param string $string
	 * @return string
	 */
	private function getIV(string $string) {
		return mb_substr(str_pad($string, 16, '0'), 0, 16);
	}

	/**
	 * @param string $key
	 * @return string
	 */
	private function getID($key) {
		$params = $this->params[$key];
		$keys = array_keys($params);
		return $params[$keys[0]];
	}

	/**
	 * @param string $key
	 * @return string
	 */
	private function getSecret($key) {
		$params = $this->params[$key];
		$keys = array_keys($params);
		return $params[$keys[1]];
	}
}