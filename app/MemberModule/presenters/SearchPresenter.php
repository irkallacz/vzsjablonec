<?php


namespace App\MemberModule\Presenters;


use App\Model\AddressesService;
use Nette\Application\UI\Presenter;
use Nette\Database\Context;

class SearchPresenter extends Presenter
{
	/** @var AddressesService @inject */
	public $addressesService;

	public function actionCity(string $city = null)
	{
		$result = [];

		if ($city) {
			$result = $this->addressesService->getCity($city)
				->fetchPairs(null, 'city');
		}

		$this->sendJson($result);
	}

	public function actionStreet(string $city = null, string $street = null)
	{
		$result = [];

		if ($city) {
			$result = $this->addressesService->getStreet($city, $street)
				->fetchPairs(null, 'street');
		}

		$this->sendJson($result);
	}

	public function actionPostalCode(string $city = null, string $street = null)
	{
		$result = [];

		if (($city)&&($street)) {
			$result = $this->addressesService->getPostalCode($city, $street)
				->fetchPairs(null, 'postal_code');
		}

		$this->sendJson($result);
	}
}