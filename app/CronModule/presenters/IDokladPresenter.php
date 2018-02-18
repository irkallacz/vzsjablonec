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
use Tracy\Debugger;

/**
 * Class IDokladPresenter
 * @package App\CronModule\presenters
 */
class IDokladPresenter extends BasePresenter {

	/** @var UserService @inject */
	public $userService;

	/** @var iDoklad\iDoklad @inject */
	public $iDoklad;

	/** @var iDokladRequest @inject */
	public $iDokladRequest;

	/**
	 * go through the contacts, use IdentificationNumber
	 *  - update if there is a change
	 *  - create new if not exists
	 */
	public function actionUpdate() {
		$this->setView('idoklad');
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);//->order('surname');
		foreach ($users as $user) {
			$this->iDoklad->authCCF();
			$request = new iDokladRequest('Contacts');
			$filter = new iDokladFilter('IdentificationNumber', '==', 'VZSJBC'.$user->id);
			$request->addFilter($filter);
			$response = $this->iDoklad->sendRequest($request);
			$person = $response->getData();
			if (count($person) == 3) {
				$this->contactCreate($user);
				echo $user->surname . " " . $user->name . '- CREATED<br />';
				continue;
			}
			if ($user->date_update > $person[0]['DateLastChange']) {
				$request = new iDokladRequest('Contacts/' . $person[0]['Id']);
				$request->addMethodType('PATCH');
				$data = $this->setContactData($user);
				$request->addPostParameters($data);
				$response = $this->iDoklad->sendRequest($request);
				if ($response->getCode() == 200) {
					echo $user->surname . " " . $user->name . " - UPDATED<br />";
				} else {
					echo $user->surname . " " . $user->name . " - FAILED<br />";
				}
			}
		}
	}

	/**
	 * go through the contacts, compare "surname name" with CompanyName
	 *  - always update
	 *  - does NOT create new if not exists
	 */
	public function actionDefaultSync() {
		$this->setView('idoklad');
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);//->order('surname');
		foreach ($users as $user) {
			$this->iDoklad->authCCF();
			$request = new iDokladRequest('Contacts');
			$filter = new iDokladFilter('CompanyName', '==', $user->surname . " " . $user->name);
			$request->addFilter($filter);
			$response = $this->iDoklad->sendRequest($request);
			$person = $response->getData();
			if (count($person) == 3) {
				echo $user->surname . " " . $user->name . '- NOT FOUND<br />';
				continue;
			}
			$request = new iDokladRequest('Contacts/' . $person[0]['Id']);
			$request->addMethodType('PATCH');
			$data = $this->setContactData($user);
			$request->addPostParameters($data);
			$response = $this->iDoklad->sendRequest($request);
			echo $response->getCode();
			if ($response->getCode() == 200) {
				echo $user->surname . " " . $user->name . " - UPDATED<br />";
			} else {
				echo $user->surname . " " . $user->name . " - FAILED<br />";
			}
		}
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
			'IdentificationNumber' => 'VZSJBC'.$user->id,
			'Mobile' => $user->telefon,
			'Street' => $user->ulice,
			'Surname' => $user->name,
		);
		return $data;
	}

	/**
	 * @param IRow|ActiveRow $user
	 */
	public function contactCreate($user) {
		$this->iDoklad->authCCF();
		$request = new iDokladRequest('Contacts');
		$request->addMethodType('POST');
		$data = $this->setContactData($user);
		$request->addPostParameters($data);
		$response = $this->iDoklad->sendRequest($request);
		//echo $response->getCode();
	}
}