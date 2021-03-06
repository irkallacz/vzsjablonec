<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 6.1.2018
 * Time: 19:53
 */

namespace App\CronModule\Presenters;

use App\Model\UserService;
use Google_Service_PeopleService;
use Google_Service_PeopleService_Date;
use Google_Service_PeopleService_Person;
use Google_Service_PeopleService_Name;
use Google_Service_PeopleService_Address;
use Google_Service_PeopleService_PhoneNumber;
use Google_Service_PeopleService_EmailAddress;
use Google_Service_PeopleService_Birthday;
use Google_Service_PeopleService_UserDefined;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Tracy\Debugger;

/**
 * Class PeoplePresenter
 * @package App\CronModule\presenters
 */
class PeoplePresenter extends BasePresenter {

	const PERSON_FIELDS = 'names,emailAddresses,addresses,phoneNumbers,birthdays,userDefined';

	/** @var UserService @inject */
	public $userService;

	/** @var Google_Service_PeopleService @inject */
	public $peopleService;

	/**
	 * go through the contacts, update if there is a change
	 * work only on contact with ID field
	 * @param bool $force
	 */
	public function actionUpdate(bool $force = FALSE) {
		//$this->setView('../Cron.default');

		$me = $this->peopleService->people_connections->listPeopleConnections('people/me', ['personFields' => self::PERSON_FIELDS . ',metadata']);
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL)->fetchPairs('id');

		$persons = [];
		$items = [];

		foreach ($me->getConnections() as $person) {
			$id = self::getID($person);
			if ($id) {
				$persons[$id] = $person->resourceName;
				//only update user contacts
				if (array_key_exists($id, $users)) {
					$user = $users[$id];
					$update_time = self::getUpdateTime($person);
					//if there is a change
					if (($force)or($user->date_update > $update_time)) {
						$person = self::setPerson($person, $user);
						$this->peopleService->people->updateContact($person->resourceName, $person, ['updatePersonFields' => self::PERSON_FIELDS]);
						$item[$id] = $person->resourceName;
					}
				}
			}
		}

		$diffrerences = UserService::getDifferences(array_keys($users), array_keys($persons));

		//if user is not in contacts
		foreach ($diffrerences['add'] as $id) {
			$user = $users[$id];
			$person = new Google_Service_PeopleService_Person();
			$person = self::setID($person, $id);
			$person = self::setPerson($person, $user);
			$item[$id] = $this->peopleService->people->createContact($person)->resourceName;
		}

		//if contact exists but user is not member anymore
		foreach ($diffrerences['delete'] as $id) {
			$resourceName = $persons[$id];
			$this->peopleService->people->deleteContact($resourceName);
			$item[$id] = $resourceName;
		}

		$this->template->add('items', $items);
	}

	/**
	 * put all user to contacts
	 */
	public function actionDefaultSync() {
		//$this->setView('../Cron.default');

		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);

		foreach ($users as $user) {
			/** @var ActiveRow $user*/

			$person = new Google_Service_PeopleService_Person;
			$person = self::setID($person, $user->id);
			$person = self::setPerson($person, $user);
			print $user->id .' '. $this->peopleService->people->createContact($person)->resourceName . "<br>\n";
		}
	}

	/**
	 * @param Google_Service_PeopleService_Person $person
	 * @return string|null
	 */
	private function getID(Google_Service_PeopleService_Person $person) {
		foreach ($person->getUserDefined() as $userDefined) {
			if ($userDefined->getKey() == 'ID') return $userDefined->getValue();
		}
	}

	/**
	 * @param Google_Service_PeopleService_Person $person
	 * @param $id
	 * @return Google_Service_PeopleService_Person
	 */
	private function setID(Google_Service_PeopleService_Person $person, $id) {
		$userDefiended = new Google_Service_PeopleService_UserDefined();
		$userDefiended->setKey('ID');
		$userDefiended->setValue(strval($id));
		$person->setUserDefined($userDefiended);
		return $person;
	}

	/**
	 * @param Google_Service_PeopleService_Person $person
	 * @return DateTime
	 */
	private function getUpdateTime(Google_Service_PeopleService_Person $person) {
		return new DateTime($person->getMetadata()->getSources()[0]->updateTime);
	}

	/**
	 * @param Google_Service_PeopleService_Person $person
	 * @param IRow|ActiveRow $user
	 * @return Google_Service_PeopleService_Person
	 */
	private static function setPerson(Google_Service_PeopleService_Person $person, IRow $user) {
		$name = new Google_Service_PeopleService_Name;
		$name->setFamilyName($user->surname);
		$name->setGivenName($user->name);
		$name->setDisplayName(UserService::getFullName($user));

		$person->setNames([$name]);

		$phoneNumbers = [];
		$phoneNumber = new Google_Service_PeopleService_PhoneNumber;
		$phoneNumber->setType('mobile');
		$phoneNumber->setValue('+420' . $user->telefon);
		$phoneNumbers[] = $phoneNumber;

		if ($user->telefon2) {
			$phoneNumber = new Google_Service_PeopleService_PhoneNumber;
			$phoneNumber->setType('other');
			$phoneNumber->setValue('+420' . $user->telefon2);
			$phoneNumbers[] = $phoneNumber;
		}

		$person->setPhoneNumbers($phoneNumbers);

		$emailAddresses = [];

		$emailAddress = new Google_Service_PeopleService_EmailAddress;
		$emailAddress->setType('home');
		$emailAddress->setValue($user->mail);
		$emailAddresses[] = $emailAddress;

		if ($user->mail2) {
			$emailAddress = new Google_Service_PeopleService_EmailAddress;
			$emailAddress->setType('other');
			$emailAddress->setValue($user->mail2);
			$emailAddresses[] = $emailAddress;
		}

		$person->setEmailAddresses($emailAddresses);

		$address = new Google_Service_PeopleService_Address;
		$address->setStreetAddress($user->ulice);
		$address->setCity($user->mesto);
		$address->setCountry('Česká Republika');
		$address->setCountryCode('CZ');
		$address->setType('home');

		$person->setAddresses([$address]);

		$birthday = new Google_Service_PeopleService_Birthday;
		$birthdayDate = new Google_Service_PeopleService_Date;
		$birthdayDate->setYear($user->date_born->format('Y'));
		$birthdayDate->setMonth($user->date_born->format('m'));
		$birthdayDate->setDay($user->date_born->format('d'));
		$birthday->setDate($birthdayDate);

		$person->setBirthdays($birthday);

		return $person;
	}
}