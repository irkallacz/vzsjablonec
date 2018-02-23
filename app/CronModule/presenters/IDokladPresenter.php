<?php
/**
 * Created by PhpStorm.
 * User: Vitek
 * Date: 17.2.2018
 * Time: 20:18
 */

namespace App\CronModule\Presenters;

use App\Model\UserService;
use malcanek\iDoklad\auth\iDokladCredentials;
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
	 * check if credetials are still valid, reauthenticate if not
	 */
	public function iDokladAuthenticate() {
		$this->iDoklad->setCredentialsCallback(function ($credentials) {
			file_put_contents('credentials.json', $credentials->toJson());
		});
		if (!file_exists('credentials.json') && empty($_GET['code'])) {
			$this->iDoklad->authCCF();
		}
		$credentials = new iDokladCredentials(file_get_contents('credentials.json'), true);
		$this->iDoklad->setCredentials($credentials);
	}

	/**
	 * @param $id
	 * get all iDoklad contacts, when find match with ID in database
	 *  - update if there is a change
	 *  - create new if not exists
	 */
	public function actionUpdate(int $id = 200) {
		$this->setView('default');
		$items = [];
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);//->order('surname');
		$this->iDokladAuthenticate();
		$request = new iDokladRequest('Contacts');
		$request->setPageSize($id);
		$response = $this->iDoklad->sendRequest($request);
		$contacts = $response->getData();
		foreach ($users as $user) {
			foreach ($contacts as $contact) {
				$contact_id = $contact['Id'];
				if (!$contact_id) {
					if ($this->contactCreate($user)) {
						$items[$user->id] = $user->surname . " " . $user->name . " - CREATED";
					} else {
						$items[$user->id] = $user->surname . " " . $user->name . " - CREATING FAILED";
					}
					unset($users[$user->id]);
					break;
				}
				if ($user->idoklad_id == $contact_id) {
					$update_time = new Datetime($contact['DateLastChange']); //one hour shifted?
					if ($user->date_update > $update_time) {
						$request = new iDokladRequest('Contacts/' . $contact_id);
						$request->addMethodType('PATCH');
						$data = $this->setContactData($user);
						$request->addPostParameters($data);
						$response = $this->iDoklad->sendRequest($request);
						if ($response->getCode() == 200) {
							$items[$user->id] = $user->surname . " " . $user->name . " - UPDATED";
						} else {
							$items[$user->id] = $user->surname . " " . $user->name . " - FAILED";
						}
					} else {
						$items[$user->id] = $user->surname . " " . $user->name . " - WITHOUT CHANGE";
					}
					unset($users[$user->id]);
					break;
				}
			}
		}
		if (!count($users)) {
			echo('ERROR - some users left without action<br><br>');
		}
		$this->template->items = $items;
	}

	/**
	 * go through the iDoklad contacts one by one by comparing "surname name" (beware of duplicates) with CompanyName then
	 *  - add idoklad_id to our database
	 */
	public function actionDefaultSync() {
		$this->setView('default');
		$items = [];
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);//->order('surname');
		$this->iDokladAuthenticate();
		foreach ($users as $user) {
			$request = new iDokladRequest('Contacts');
			$filter = new iDokladFilter('CompanyName', '==', $user->surname . " " . $user->name);
			$request->addFilter($filter);
			$response = $this->iDoklad->sendRequest($request);
			$person = $response->getData();
			if (count($person) != 1) {
				$items[$user->id] = $user->surname . " " . $user->name . " - NOT FOUND";
				continue;
			}
			$user->update(['idoklad_id' => $person[0]['Id']]);
			$items[$user->id] = $user->surname . " " . $user->name . " - LOCALY UPDATED";

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
			'Mobile' => $user->telefon,
			'Street' => $user->ulice,
			'Surname' => $user->name
		);
		return $data;
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @return bool
	 */
	public function contactCreate($user) {
		$this->iDokladAuthenticate();
		$request = new iDokladRequest('Contacts');
		$request->addMethodType('POST');
		$data = $this->setContactData($user);
		$request->addPostParameters($data);
		$response = $this->iDoklad->sendRequest($request);
		if ($response->getCode() == 200) {
			$id = $response->getData()['Id'];
			return $user->update(['idoklad_id' => $id]);
		} else {
			return false;
		}
	}
}