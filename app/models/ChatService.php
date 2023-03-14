<?php


namespace App\Model;


use Nette\Utils\Json;
use Nette\Utils\Random;
use Tracy\Debugger;

final class ChatService
{
	/**
	 * @var resource
	 */
	private $curl;

	/**
	 * @var string
	 */
	private $url;

	/**
	 * @var string
	 */
	private $key;

	/**
	 * @var string
	 */
	private $token;

	/**
	 * @var string
	 */
	private $space;

	/**
	 * @var bool
	 */
	private $active;

	/**
	 * ChatService constructor.
	 * @param string $url
	 * @param string $space
	 * @param string $key
	 * @param string $token
	 * @param bool $active
	 */
	public function __construct(string $url, string $space, string $key, string $token, bool $active)
	{
		$this->url = $url;
		$this->key = $key;
		$this->token = $token;
		$this->space = $space;
		$this->active = $active;

		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl, CURLOPT_POST, true);
		curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json; charset=UTF-8'
		]);
	}

	private function sendMessage(string $message): bool
	{
		if (!$this->active) {
			return false;
		}

		curl_setopt($this->curl, CURLOPT_URL, sprintf('%s/%s/messages?%s', $this->url, $this->space, http_build_query([
			'threadKey' => Random::generate(),
			'key' => $this->key,
			'token' => $this->token
		])));

		curl_setopt($this->curl, CURLOPT_POSTFIELDS, Json::encode(['text' => $message]));

		curl_exec($this->curl);

		curl_close($this->curl);

		return true;
	}

	public function newEventMessage(string $name, string $url, string $description)
	{
		$this->sendMessage(sprintf("Na web byla přidána nová akce: *%s*\n_%s_\n%s", $name, $description, $url));
	}


}