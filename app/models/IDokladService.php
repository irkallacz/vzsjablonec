<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 13.3.2018
 * Time: 19:30
 */

namespace App\Model;

use malcanek\iDoklad\iDoklad;
use malcanek\iDoklad\iDokladException;
use malcanek\iDoklad\request\iDokladRequest;
use malcanek\iDoklad\request\iDokladResponse;
use malcanek\iDoklad\auth\iDokladCredentials;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Http\Response;
use Nette\SmartObject;

class IDokladService {
	use SmartObject;

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
	 * check if credentials are still valid, re-authenticate if not
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
	 * @throws iDokladException
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
	 * @throws iDokladException
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
	 * @throws iDokladException
	 */
	public function sendRequest(iDokladRequest $request) {
		$response = $this->iDoklad->sendRequest($request);

		if ($response->getCode() != Response::S200_OK)
			throw new iDokladException($response->getCode().' '.$response->getCodeText());

		return $response;
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @return array
	 */
	public static function setContactData($user) {
		$data = [
			'CompanyName' 	=> UserService::getFullName($user),
			'Firstname' 	=> $user->name,
			'Surname' 		=> $user->surname,
			'Email' 		=> $user->mail,
			'Mobile'		=> $user->telefon,
			'City'			=> $user->mesto,
			'Street'		=> $user->ulice,
			'CountryId' 	=> 2,
		];
		return $data;
	}

}