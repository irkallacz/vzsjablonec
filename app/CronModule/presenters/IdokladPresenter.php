<?php
/**
 * Created by PhpStorm.
 * User: Vitek
 * Date: 17.2.2018
 * Time: 20:18
 */

namespace App\CronModule\Presenters;

use App\Model\UserService;
use DateTimeZone;
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

	const PAGESIZE = 200;
	const CREDENTIALS_FILENAME = 'credentials.json';

	/** @var UserService @inject */
	public $userService;

	/** @var iDoklad\iDoklad @inject */
	public $iDoklad;

	/**
	 * check if credetials are still valid, reauthenticate if not
	 */
	public function iDokladAuthenticate() {
		$filePath = APP_DIR . '/../tmp/idoklad/' . self::CREDENTIALS_FILENAME;
		$this->iDoklad->setCredentialsCallback(function ($credentials) use ($filePath) {
			file_put_contents($filePath, $credentials->toJson());
		});
		if (!file_exists($filePath)) {
			$this->iDoklad->authCCF();
		}
		$credentials = new iDokladCredentials(file_get_contents($filePath), TRUE);
		$this->iDoklad->setCredentials($credentials);
	}

	/**
	 * get all iDoklad contacts
	 *  - create new if idoklad_id not exists
	 *  - update if there is a change
	 */
	public function actionUpdate() {
		$this->setView('default');
		$items = [];
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);//->order('surname');
		$this->iDokladAuthenticate();
		$request = new iDokladRequest('Contacts');
		$request->setPageSize(self::PAGESIZE);
		$response = $this->iDoklad->sendRequest($request);
		$data = $response->getData();
		$pages = $response->getTotalPages();
		for ($i = 2; $i <= $pages; ++$i) {
			$request->setPage($i);
			$response = $this->iDoklad->sendRequest($request);
			$data = array_merge($data, $response->getData());
		}
		$contacts = [];
		foreach ($data as $contact) {
			$contacts[$contact['Id']] = $contact;
		}
		foreach ($users as $user) {
			$idoklad_id = $user->idoklad_id;
			if (!$idoklad_id || !array_key_exists($idoklad_id, $contacts)) {
				if ($this->contactCreate($user)) {
					$items[$user->id] = $user->surname . " " . $user->name . " - CREATED";
				} else {
					$items[$user->id] = $user->surname . " " . $user->name . " - CREATING FAILED";
				}
				unset($users[$user->id]);
			} else {
				$update_time = new DateTime($contacts[$idoklad_id]['DateLastChange']);
				$update_time->setTimezone(new DateTimeZone('+0100'));
				if ($user->date_update > $update_time) {
					$request = new iDokladRequest('Contacts/' . $idoklad_id);
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
			}
		}
		if (count($users)) {
			echo('ERROR - some users left without action<br><br>');
			Debugger::barDump($users);
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
		$data = [
			'CompanyName' => $user->surname . " " . $user->name,
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
			return FALSE;
		}
	}
}