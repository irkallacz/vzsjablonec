<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 16.06.2019
 * Time: 17:42
 */

namespace App\CronModule\Presenters;

use GuzzleHttp\Client;
use Nette\Application\Responses\TextResponse;
use Nette\Utils\Json;
use Tracy\Debugger;

final class ContactsPresenter extends BasePresenter {

	/**
	 * @var \Google_Client @inject
	 */
	public $googleClient;

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 * @throws \Nette\Application\AbortException
	 * @throws \Nette\Utils\JsonException
	 */
	public function actionDefault() {
		$this->googleClient->setSubject('admin@vzs-jablonec.cz');

		$httpClient = $this->googleClient->authorize();
		$this->googleClient->fetchAccessTokenWithAssertion($httpClient);

		$response = $httpClient->request('GET', 'https://www.google.com/m8/feeds/contacts/vzs-jablonec.cz/full');

		Debugger::barDump($response);
		$body = (string) $response->getBody();
		//$json = Json::decode($body);
		//$xml = simplexml_load_string($body);
		//$xml->getNamespaces(true);
		//Debugger::dump($xml->entry[24]->children('http://schemas.google.com/g/2005'));
		$this->sendResponse(new TextResponse(htmlentities($body)));
	}

}