<?php


namespace App\Console;

use App\Model\AddressesService;
use App\Model\UserService;
use Nette\Database\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


final class CompleteAddressCommand extends BaseCommand
{
	/** @var UserService */
	protected $userService;

	/** @var AddressesService */
	protected $addressService;

	/**
	 * @param UserService $userService
	 * @param AddressesService $addressService
	 */
	public function __construct(UserService $userService, AddressesService $addressService)
	{
		parent::__construct();
		$this->userService = $userService;
		$this->addressService = $addressService;
	}

	protected function configure()
	{
		$this->setName('complete:address')
			->setDescription('Complete user addresses');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$users = $this->userService->getUsers(UserService::DELETED_LEVEL)
			->where('NOT city', null)
			->where('NOT street', null)
			->where('postal_code', null);

		foreach ($users as $user) {
			$street = explode(' ', $user->street);

			if (!(strpbrk($street[count($street)-1], '0123456789'))) {
				continue;
			}

			$streetNumber = array_pop($street);
			$street = join(' ', $street);

			if ($result = $this->addressService->getPostalCode($user->city, $street)->fetch()) {
				$user->update([
					'street' => $street,
					'street_number' => $streetNumber,
					'postal_code' => $result->postal_code,
				]);

				$this->writeln($output, $user->city, $street, $streetNumber, $result->postal_code);
			}
		}

		return 0;
	}

}