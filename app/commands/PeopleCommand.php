<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 6.1.2018
 * Time: 19:53
 */

namespace App\Console;

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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

/**
 * Class PeoplePresenter
 * @package App\CronModule\presenters
 */
final class PeopleCommand extends BaseCommand {

	const PERSON_FIELDS = 'names,emailAddresses,addresses,phoneNumbers,birthdays,userDefined';

	/** @var UserService */
	private $userService;

	/** @var Google_Service_PeopleService */
	private $peopleService;

	/**
	 * PeopleCommand constructor.
	 * @param UserService $userService
	 * @param Google_Service_PeopleService $peopleService
	 */
	public function __construct(UserService $userService, Google_Service_PeopleService $peopleService)
	{
		parent::__construct();
		$this->userService = $userService;
		$this->peopleService = $peopleService;
	}

	protected function configure() {
		$this->setName('cron:people')
			->setDescription('Sync contacts from database to Google account');
	}

	/**
	 * go through the contacts, update if there is a change
	 * work only on contact with ID field
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$force = false;

		$me = $this->peopleService->people_connections->listPeopleConnections('people/me', ['personFields' => self::PERSON_FIELDS . ',metadata']);
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL)->fetchPairs('id');

		$persons = [];
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
						$this->writeln($output, 'Upadte', $id, $person->resourceName, $update_time);
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

			$this->writeln($output, 'Create', $id, $this->peopleService->people->createContact($person)->resourceName);
		}

		//if contact exists but user is not member anymore
		foreach ($diffrerences['delete'] as $id) {
			$resourceName = $persons[$id];
			$this->peopleService->people->deleteContact($resourceName);
			$this->writeln($output, 'Delete', $id, $resourceName);
		}
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
		$phoneNumber->setValue('+420' . $user->phone);
		$phoneNumbers[] = $phoneNumber;

		if ($user->phone2) {
			$phoneNumber = new Google_Service_PeopleService_PhoneNumber;
			$phoneNumber->setType('other');
			$phoneNumber->setValue('+420' . $user->phone2);
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
		$address->setCity($user->city);
		$address->setStreetAddress($user->street . ' ' . $user->street_number);
		//$address->setPostalCode($user->postal_code);
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