<?php

namespace App\Model;

use Nette\Database\Table\Selection;

final class AddressesService extends DatabaseService
{
	protected function getAddresses(): Selection
	{
		return $this->database->table('addresses');
	}
	public function getCity(string $city, int $limit = 50): Selection
	{
		return $this->getAddresses()
			->select('DISTINCT city')
			->where('city LIKE ?', "$city%")
			->order('city')
			->limit($limit);
	}

	public function getStreet(string $city, string $street = null, int $limit = 50): Selection
	{
		$result = $this->getAddresses()
			->select('DISTINCT street')
			->where('city', $city);

		if ($street) {
			$result->where('street LIKE ?', "$street%");
		}

		$result->order('street')
			->limit($limit);

		return $result;
	}

	public function getPostalCode(string $city, string $street): Selection
	{
		return $this->getAddresses()
			->select('postal_code')
			->where('city', $city)
			->where('street', $street)
			->limit(10);
	}
}