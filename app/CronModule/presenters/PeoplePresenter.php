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
use Google_Service_PeopleService_Person;
use Google_Service_PeopleService_Name;
use Google_Service_PeopleService_Address;
use Google_Service_PeopleService_PhoneNumber;
use Google_Service_PeopleService_EmailAddress;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Tracy\Debugger;

/**
 * Class PeoplePresenter
 * @package App\CronModule\presenters
 */
class PeoplePresenter extends BasePresenter {

	const PERSON_FIELDS = 'names,emailAddresses,addresses,phoneNumbers';

	/** @var UserService @inject */
	public $userService;

	/** @var Google_Service_PeopleService @inject */
	public $peopleService;

	public function actionUpdate() {
		$this->setView('../Cron.default');

		$me = $this->peopleService->people_connections
			->listPeopleConnections('people/me', [
				'personFields' => self::PERSON_FIELDS . ',metadata'
			]);

		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL)->fetchPairs('mail');

		$items = [];
		foreach ($me->getConnections() as $person) {
			$email = $person->emailAddresses[0]->value;
			if (array_key_exists($email, $users)) {
				$user = $users[$email];
				$update_time = new DateTime($person->metadata->sources[0]->updateTime);

				if ($user->date_update > $update_time) {
					$person = self::setPerson($person, $user);
					$items[] = $this->peopleService->people->updateContact($person->resourceName, $person, [
						'updatePersonFields' => self::PERSON_FIELDS
					]);
				}
			}
		}

		$this->template->items = $items;
	}

	public function actionDefaultSync() {
		$this->setView('../Cron.default');

		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);

		$items = [];
		foreach ($users as $user) {
			$person = new Google_Service_PeopleService_Person;
			$person = self::setPerson($person, $user);
			$items[] = $this->peopleService->people->createContact($person);
		}

		$this->template->items = $items;
	}

	/**
	 * @param Google_Service_PeopleService_Person $person
	 * @param IRow $user
	 * @return Google_Service_PeopleService_Person
	 */
	private static function setPerson(Google_Service_PeopleService_Person $person, IRow $user) {
		$name = new Google_Service_PeopleService_Name;
		$name->setFamilyName($user->surname);
		$name->setGivenName($user->name);
		$name->setDisplayName($user->surname . ', ' . $user->name);

		$person->setNames([$name]);

		$phoneNumbers = [];
		$phoneNumber = new Google_Service_PeopleService_PhoneNumber;
		$phoneNumber->setType('home');
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

		return $person;
	}
}