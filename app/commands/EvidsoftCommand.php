<?php

namespace App\Console;

use App\Model\EvidsoftService;
use App\Model\UserService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class EvidsoftCommand extends BaseCommand
{
	/**
	 * @var UserService
	 */
	protected $userService;

	/**
	 * @var EvidsoftService
	 */
	protected $evidsoftService;

	/**
	 * @param UserService $userService
	 * @param EvidsoftService $evidsoftService
	 */
	public function __construct(UserService $userService, EvidsoftService $evidsoftService)
	{
		parent::__construct();

		$this->userService = $userService;
		$this->evidsoftService = $evidsoftService;
	}

	protected function configure() {
		$this->setName('evidsoft:persons')
			->setDescription('Sync actual userbase with evidsoft');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->writeln($output, '<info>Evidsoft persons command</info>');

		$this->evidsoftService->authorize();

		$members = $this->userService->getUsers(UserService::MEMBER_LEVEL)->where('evidsoft_id IS NOT NULL')->fetchPairs('evidsoft_id', 'id');
		$persons = $this->evidsoftService->personList();

		$this->writeln($output, 'Update current members');
		foreach ($persons->items as $person) {
			$this->writeln($output, $person->FullName);
			if (array_key_exists($person->ID, $members)) {
				$this->writeln($output,'', 'Update', $members[$person->ID], $person->ID);
				$member = $this->userService->getUserById($members[$person->ID], UserService::DELETED_LEVEL);
				$person = EvidsoftService::updatePersonFromMember($person, $member);
				$this->evidsoftService->updatePerson($person);
			} else {
				$this->writeln($output,'', 'Skipping');
			}
		}

		$this->writeln($output, 'Add new members');
		$newMembers = $this->userService->getUsers(UserService::MEMBER_LEVEL)->where('evidsoft_id IS NULL');

		foreach ($newMembers as $member) {
			$this->writeln($output, UserService::getFullName($member));
			$person = EvidsoftService::createPersonData($member);
			$response = $this->evidsoftService->createPerson($person);
			$id = $response->items[0]->ID;

			$member->update(['evidsoft_id' => $id]);

			$members[$id] = $member->id;
		}

		$this->writeln($output, 'Update membership');

		$year = (int) date('Y');
		$memberships = $this->evidsoftService->membershipList();
		foreach ($memberships->items as $membership) {
			$personID = (int) $membership->Person_ID;

			if ($membership->{'Year'.$year}) {
				//deaktivace
				if (!array_key_exists($personID, $members)) {
					$this->evidsoftService->updateMembership($membership->ID, $year, false);
					$this->writeln($output, $membership->FirstName . ' ' . $membership->LastName, $year, false);
				} else {
					//přidání zázanmu o registraci pro případ, že byla vyplněna v Evidsoftu a ne u nás
					$this->userService->addRegistration($members[$personID], $year);
				}
			} else {
				//aktivace
				if (array_key_exists($personID, $members)) {
					$this->evidsoftService->updateMembership($membership->ID, $year, true);
					$this->writeln($output, $membership->FirstName . ' ' . $membership->LastName, $year, true);
					$this->userService->addRegistration($members[$personID], $year);
				}
			}
		}
	}

}