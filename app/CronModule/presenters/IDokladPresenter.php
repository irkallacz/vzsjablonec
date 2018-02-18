<?php
/**
 * Created by PhpStorm.
 * User: Vitek
 * Date: 17.2.2018
 * Time: 20:18
 */

namespace App\CronModule\Presenters;

use App\Model\UserService;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use malcanek\iDoklad;
use malcanek\iDoklad\request\iDokladRequest;
use malcanek\iDoklad\request\iDokladFilter;
use Nette\Utils\DateTime;
use Tracy\Debugger;

/**
 * Class IdokladPresenter
 * @package App\CronModule\presenters
 */
class IdokladPresenter extends BasePresenter {

	/** @var UserService @inject */
	public $userService;

	/** @var iDoklad\iDoklad @inject */
	public $iDoklad;

	/**
	 * get all iDoklad contacts from VZS by IdentificationNumber and then
	 *  - update if there is a change
	 *  - create new if not exists
	 */
	public function actionUpdate() {
		$this->setView('default');
		$items = [];
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);//->order('surname');
		$this->iDoklad->authCCF();
		$request = new iDokladRequest('Contacts');
		$filter = new iDokladFilter('IdentificationNumber', 'contains', 'VZSJBC');
		$request->addFilter($filter)->setPageSize($users->count());
		$response = $this->iDoklad->sendRequest($request);
		$contacts = $response->getData();
		foreach ($contacts as $contact) {
			$id = substr($contact['IdentificationNumber'], 6);
			$update_time = new Datetime($contact['DateLastChange']);
			if ($users[$id]->date_update > $update_time) {
				$request = new iDokladRequest('Contacts/' . $contact['Id']);
				$request->addMethodType('PATCH');
				$data = $this->setContactData($users[$id]);
				$request->addPostParameters($data);
				$response = $this->iDoklad->sendRequest($request);
				if ($response->getCode() == 200) {
					$items[$id] = "UPDATED";
				} else {
					$items[$id] = "FAILED";
				}
			}
			unset($users[$id]);
		}
		foreach ($users as $user) {
			if ($this->contactCreate($user) == 200) {
				$items[$user->id] = "CREATED";
			} else {
				$items[$user->id] = "CREATING FAILED";
			}
		}
		$this->template->items = $items;
	}

	/**
	 * go through the iDoklad contacts one by one by comparing "surname name" with CompanyName then
	 *  - always update
	 *  - does NOT create new if not exists
	 */
	public function actionDefaultSync() {
		$this->setView('default');
		$items = [];
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);//->order('surname');
		$this->iDoklad->authCCF();
		foreach ($users as $user) {
			$request = new iDokladRequest('Contacts');
			$filter = new iDokladFilter('CompanyName', '==', $user->surname . " " . $user->name);
			$request->addFilter($filter);
			$response = $this->iDoklad->sendRequest($request);
			$person = $response->getData();
			if (count($person) == 3) {
				$items[$user->id] = "NOT FOUND";
				continue;
			}
			$request = new iDokladRequest('Contacts/' . $person[0]['Id']);
			$request->addMethodType('PATCH');
			$data = $this->setContactData($user);
			$request->addPostParameters($data);
			$response = $this->iDoklad->sendRequest($request);
			//echo $response->getCode();
			if ($response->getCode() == 200) {
				$items[$user->id] = "UPDATED";
			} else {
				$items[$user->id] = "FAILED";
			}
		}
		$this->template->items = $items;
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @return array
	 */
	public function setContactData($user) {
		$data = array(
			'CompanyName' => $user->surname . " " . $user->name,
			'CountryId' => 2,
			'City' => $user->mesto,
			'Email' => $user->mail,
			'Firstname' => $user->name,
			'IdentificationNumber' => 'VZSJBC' . $user->id,
			'Mobile' => $user->telefon,
			'Street' => $user->ulice,
			'Surname' => $user->name,
		);
		return $data;
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @return int
	 */
	public function contactCreate($user) {
		$this->iDoklad->authCCF();
		$request = new iDokladRequest('Contacts');
		$request->addMethodType('POST');
		$data = $this->setContactData($user);
		$request->addPostParameters($data);
		$response = $this->iDoklad->sendRequest($request);
		return $response->getCode();
	}
}