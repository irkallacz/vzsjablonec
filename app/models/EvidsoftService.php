<?php

namespace App\Model;

use GuzzleHttp\Client;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;
use Nette\Utils\Strings;

class EvidsoftService
{
	const DATE_FORMAT = 'Y-m-d';

	/**
	 * @var string
	 */
	protected $username;

	/**
	 * @var string
	 */
	protected $password;

	/**
	 * @var Client
	 */
	protected $client;

	public function __construct(string $url, string $username, string $password)
	{
		$this->username = $username;
		$this->password = $password;

		$this->client = new Client(['base_uri' => $url, 'cookies' => true]);
	}

	public function authorize()
	{
		$response = $this->client->request('POST', 'login/process', [
			'form_params' => [
				'Name' => $this->username, 'Password' => $this->password
			]
		]);

		if (!$response->hasHeader('Set-Cookie')) {
			throw new \Exception('Session id not found');
		}
	}

	public function personList(bool $onlyActiveMembership = null, int $start = null, int $count = null, string $sort = null): object
	{
		$query = [
			'OnlyActiveMembership' => $onlyActiveMembership,
			'start' => $start,
			'count' => $count,
			'sort' => $sort,
		];

		$response = $this->client->request('GET', 'person-list/data-list', ['query' => array_filter($query)]);
		$response = $response->getBody()->getContents();

		return Json::decode($response);
	}

	public function getPerson(int $ID, bool $full = null): object
	{
		$query = [
			'ID' => $ID,
			'full' => $full,
		];

		$response = $this->client->request('GET', 'person-list/data-list', ['query' => array_filter($query)]);
		$response = $response->getBody()->getContents();

		return Json::decode($response);
	}

	public function updatePerson(array $person): object
	{
		$response = $this->client->request('POST', 'person-list/data-update', ['form_params' => ['data' => $person]]);
		$response = $response->getBody()->getContents();

		return Json::decode($response);
	}

	public function createPerson(array $person): object
	{
		$response = $this->client->request('POST', 'person-list/data-create', ['form_params' => ['data' => $person]]);
		$response = $response->getBody()->getContents();

		return Json::decode($response);
	}

	public function membershipList(int $start = null, int $count = null, string $sort = null): object
	{
		$query = [
			'start' => $start,
			'count' => $count,
			'sort' => $sort,
		];

		$response = $this->client->request('GET', 'membership/data-list', ['query' => array_filter($query)]);
		$response = $response->getBody()->getContents();

		return Json::decode($response);
	}

	public function updateMembership(int $membershipId, int $year, bool $active): object
	{
		$data = [
			'ID' => $membershipId,
			'Year' . $year => $active
		];

		$response = $this->client->request('GET', 'membership/data-update', ['query' => ['data' => Json::encode($data)]]);
		$response = $response->getBody()->getContents();

		return Json::decode($response);
	}


	public static function updatePersonFromMember(array $person, ActiveRow $member): array
	{
		if ($member->evidsoft_id) {
			$person['ID'] = $member->evidsoft_id;
		}

		$person['FirstName'] = $member->name;
		$person['LastName'] = $member->surname;
		$person['BirthDate'] = $member->date_born->format(self::DATE_FORMAT);

		if ($member->rc) {
			$person['PersonalNumber'] = str_replace('/', '', $member->rc);
			$person['Gender'] = (substr($member->rc, 2, 1) >= 5) ? 'female' : 'male';
		}

		$person['RegistrationNumber'] = $member->id;

		if ($member->vzsId) {
			$person['VZSNumber'] = $member->vzsId;
		}

		if ($member->proper_from) {
			$person['MembershipType'] = 'regular';
		}

		if (Strings::endsWith($member->mail, '@vzs-jablonec.cz')) {
			$person['Email'] = $member->mail;
		}

		//$person['Phone'] = $user->telefon;

		$person['address']['Street'] = $member->street;
		$person['address']['DescriptionNumber'] = $member->street_number;
		$person['address']['City'] = $member->city;
		$person['address']['ZipCode'] = $member->postal_code;

		return $person;
	}

	public static function createPersonData(ActiveRow $member = null): array
	{
		$person = [
			'ID' => '',
			'FirstName' => '',
			'LastName' => '',
			'BirthName' => '',
			'BirthDate' => '',
			'ForeignNationality' => '0',
			'PersonalNumber' => '',
			'Gender' => '',
			'IdentityCardNumber' => '',
			'RegistrationNumber' => '',
			'VZSNumber' => '',
			'MembershipType' => 'waiting',
			'Unit_ID' => '15',
			'File_ID' => '',
			'Phone' => '',
			'Email' => '',
			'TitleBefore' => '',
			'TitleAfter' => '',
			'Employment' => '',
			'Employer' => '',
			'BloodGroup' => '',
			'Insurance_ID' => '',
			'BankAccount' => '',
			'Note' => '',
			'address' => [
				'Street' => '',
				'DescriptionNumber' => '',
				'OrientationNumber' => '',
				'City' => '',
				'ZipCode' => '',
				'DeliveryPost' => ''
			],
			'dressCard' => [
				'Height' => '',
				'HeadCircumference' => '',
				'ShoeSize' => '',
				'ChestCircumference' => '',
				'GlovesSize' => '',
				'WaistCircumference' => '',
				'TshirtSize' => '',
				'CapSize' => '',
				'MaskSize' => '',
				'WalkingSuitSize' => '',
				'EmergencySuitSize' => '',
				'WorkSuitSize' => '',
				'FinSize' => ''
			],
			'neoprene' => [
				'HeadCircumference' => '',
				'NeckCircumference' => '',
				'ChestCircumference' => '',
				'WaistCircumference' => '',
				'HipCircumference' => '',
				'ThighCircumference' => '',
				'CalfCircumference' => '',
				'AnkleCircumference' => '',
				'BicepsCircumference' => '',
				'ForearmCircumference' => '',
				'WristCircumference' => '',
				'FromNeckToCrotch' => '',
				'FromCrotchToAnkle' => '',
				'FromSpineToWrist' => '',
				'FromShoulderBladeToWrist' => '',
				'Height' => '',
				'Mass' => '',
				'ShoeSize' => '',
				'GlovesSize' => '',
				'MuzzleSize' => ''
			],
			'personInUnit_datagrid_length' => '25',
			'personRole_datagrid_length' => '25',
			'personExpertise_datagrid_length' => '25',
			'personQualification_datagrid_length' => '25',
			'medicalDocumentation_datagrid_length' => '25',
			'personDrivingLicense_datagrid_length' => '25',
			'personDocuments_datagrid_length' => '25'
		];

		if ($member) {
			$person = self::updatePersonFromMember($person, $member);
		}

		return $person;
	}

}