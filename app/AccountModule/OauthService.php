<?php


namespace App\AccountModule;

final class OauthService
{
	/**
	 * @var array
	 */
	private $clients;

	/**
	 * OauthService constructor.
	 * @param array $clients
	 */
	public function __construct(array $clients)
	{
		$this->clients = $clients;
	}

	/**
	 * @param string $clientId
	 * @param string $redirectUrl
	 * @throws \Exception
	 */
	public function verifyClient(string $clientId, string $redirectUrl)
	{
		if ($app = $this->clients[$clientId] ?? null) {
			if (!in_array($redirectUrl, $app['redirectUrl'])) {
				throw new \Exception('Redirect url not allowed');
			}
		} else {
			throw new \Exception('Client id not found');
		}
	}

	/**
	 * @param string $clientId
	 * @param string $secret
	 * @throws \Exception
	 */
	public function verifyClientSecret(string $clientId, string $secret)
	{
		if ($app = $this->clients[$clientId] ?? null) {
			if ($app['secret'] != $secret) {
				throw new \Exception('Client secret do not match');
			}
		} else {
			throw new \Exception('Client id not found');
		}
	}





}