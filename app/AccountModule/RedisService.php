<?php


namespace App\AccountModule;


use Nette\Utils\Random;

final class RedisService
{
	/**
	 * @var \Redis
	 */
	private $client;

	/**
	 * RedisService constructor.
	 */
	public function __construct(string $host = 'localhost', int $port = 6379)
	{
		$this->client = new \Redis();
		$this->client->connect($host, $port);
		$this->client->select(1);
	}

	/**
	 * @param string $clientId
	 * @param array $data
	 * @return string
	 */
	public function createAndStoreAuthorizationCode(string $clientId, array $data): string
	{
		$code = Random::generate(50);
		$key = self::index('authCode', $clientId, $code);
		$this->client->hMSet($key, $data);
		$this->client->expire($key, 10);

		return $code;
	}

	/**
	 * @param string $clientId
	 * @param string $code
	 * @return array|null
	 */
	public function getUserDataFromAuthorizationCode(string $clientId, string $code)
	{
		$key = self::index('authCode', $clientId, $code);
		$token = $this->client->hGetAll($key);

		if ($token) {
			$this->client->del($key);
			return $token;
		} else {
			return null;
		}
	}

	public function createAndStoreAccessToken(array $data): string
	{
		$accessToken = Random::generate(140);
		$key = self::index('accessToken', $accessToken);
		$this->client->hMSet($key, $data);
		$this->client->expire($key, $data['expires_in']);

		return $accessToken;
	}

	public function getUserDataFromAccessToken(string $token)
	{
		return $this->client->hGetAll(self::index('accessToken', $token));
	}

		/**
	 * @param ...$names
	 * @return string
	 */
	private static function index(...$names): string
	{
		return join(':', $names);
	}


}