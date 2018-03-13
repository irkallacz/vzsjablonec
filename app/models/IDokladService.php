<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 13.3.2018
 * Time: 19:30
 */

namespace App\Model;

use App\Model\UserService;
use malcanek\iDoklad\iDoklad;
use malcanek\iDoklad\request\iDokladRequest;
use malcanek\iDoklad\request\iDokladResponse;
use malcanek\iDoklad\auth\iDokladCredentials;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Object;

class IDokladService extends Object {

	const PAGE_SIZE = 200;

	/** @var iDoklad */
	private $iDoklad;

	/** @var string */
	private $credentialsFilePath;

	/**
	 * IDokladService constructor.
	 * @param iDoklad $iDoklad
	 * @param string $credentialsFilePath
	 */
	public function __construct($credentialsFilePath, iDoklad $iDoklad) {
		$this->iDoklad = $iDoklad;
		$this->credentialsFilePath = $credentialsFilePath;
	}

	/**
	 * check if credetials are still valid, reauthenticate if not
	 */
	public function authenticate() {
		$this->iDoklad->setCredentialsCallback(function ($credentials) {
			file_put_contents($this->credentialsFilePath, $credentials->toJson());
		});
		if (!file_exists($this->credentialsFilePath)) {
			$this->iDoklad->authCCF();
		}
		$credentials = new iDokladCredentials(file_get_contents($this->credentialsFilePath), TRUE);
		$this->iDoklad->setCredentials($credentials);
	}

	/**
	 * @return iDokladRequest
	 */
	public function requestsContacts() {
		$request = new iDokladRequest('Contacts');

		return $request;
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @return iDokladResponse
	 */
	public function createContact(IRow $user) {
		$request = $this->requestsContacts();
		$request->addMethodType('POST');

		$data = self::setContactData($user);
		$request->addPostParameters($data);

		$response = $this->sendRequest($request);
		return $response;
	}

	/**
	 * @param int $id
	 * @param IRow|ActiveRow $user
	 * @return iDokladResponse
	 */
	public function updateContact(int $id, IRow $user) {
		$request = new iDokladRequest('Contacts/' . $id);
		$request->addMethodType('PATCH');

		$data = self::setContactData($user);
		$request = $request->addPostParameters($data);

		$response = $this->sendRequest($request);
		return $response;
	}

	/**
	 * @param iDokladRequest $request
	 * @param iDokladResponse $response
	 * @return array
	 */
	public function getData(iDokladRequest $request, iDokladResponse $response){
		$data = $response->getData();
		for ($i = 2; $i <= $response->getTotalPages(); ++$i) {
			$request->setPage($i);
			$response = $this->iDokladService->sendRequest($request);
			$data = array_merge($data, $response->getData());
		}
		return $data;
	}

	/**
	 * @param iDokladRequest $request
	 * @return iDokladResponse
	 */
	public function sendRequest(iDokladRequest $request) {
		$response = $this->iDoklad->sendRequest($request);

		return $response;
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @return array
	 */
	public static function setContactData($user) {
		$data = [
			'CompanyName' => UserService::getFullName($user),
			'CountryId' => 2,
			'City' => $user->mesto,
			'Email' => $user->mail,
			'Firstname' => $user->name,
			'Mobile' => $user->telefon,
			'Street' => $user->ulice,
			'Surname' => $user->name
		];
		return $data;
	}

}