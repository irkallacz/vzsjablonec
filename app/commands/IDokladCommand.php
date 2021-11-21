<?php
/**
 * Created by PhpStorm.
 * User: Vitek
 * Date: 17.2.2018
 * Time: 20:18
 */

namespace App\Console;

use App\Model\IdokladService;
use App\Model\UserService;
use DateTimeZone;
use malcanek\iDoklad\iDokladException;
use malcanek\iDoklad\request\iDokladFilter;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

/**
 * Class IdokladPresenter
 * @package App\CronModule\presenters
 */
final class IDokladCommand extends Command {

	/** @var UserService */
	private $userService;

	/** @var IdokladService */
	private $iDokladService;

	/**
	 * IDokladCommand constructor.
	 * @param UserService $userService
	 * @param IdokladService $iDokladService
	 */
	public function __construct(UserService $userService, IdokladService $iDokladService)
	{
		parent::__construct();
		$this->userService = $userService;
		$this->iDokladService = $iDokladService;
	}

	/**
	 * get all iDoklad contacts
	 *  - create new if iDokladId not exists
	 *  - update if there is a change
	 * @param bool $force
	 */

	protected function configure() {
		$this->setName('cron:idoklad')
			->setDescription('Sync contacts from database to iDoklad contacts');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$force = false;
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);

		$this->iDokladService->authenticate();
		$request = $this->iDokladService->requestsContacts();
		$request->setPageSize(IdokladService::PAGE_SIZE);
		$response = $this->iDokladService->sendRequest($request);
		$data = $this->iDokladService->getData($request, $response);

		$contacts = [];
		foreach ($data as $contact) {
			$contacts[$contact['Id']] = $contact;
		}

		foreach ($users as $user) {
			if (!$user->iDokladId || !array_key_exists($user->iDokladId, $contacts)) {
				$this->contactCreate($user);
				$output->writeln(join("\t", ['Created', UserService::getFullName($user)]), Output::VERBOSITY_VERBOSE);
				unset($users[$user->id]);
			} else {
				$update_time = new DateTime($contacts[$user->iDokladId]['DateLastChange']);
				$update_time->setTimezone(new DateTimeZone('+0100'));
				if ($force || $user->date_update > $update_time) {
					$this->iDokladService->updateContact($user->iDokladId, $user);
					$output->writeln(join("\t", ['Update', UserService::getFullName($user)]), Output::VERBOSITY_VERBOSE);
				} else {
					$output->writeln(join("\t", ['No change', UserService::getFullName($user)]), Output::VERBOSITY_VERBOSE);
				}
				unset($users[$user->id]);
			}
		}
		if (count($users)) {
			$output->writeln('ERROR - some users left without action', Output::VERBOSITY_VERBOSE);
		}
	}

	/**
	 * go through the iDoklad contacts one by one by comparing 'surname name' (beware of duplicates) with CompanyName then
	 *  - add iDokladId to our database
	 */
	public function actionDefaultSync() {
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);
		$this->iDokladService->authenticate();
		foreach ($users as $user) {
			$request = $this->iDokladService->requestsContacts();
			$filter = new iDokladFilter('CompanyName', '==', UserService::getFullName($user));
			$request->addFilter($filter);
			$response = $this->iDokladService->sendRequest($request);
			$person = $response->getData();
			if (count($person) != 1) {
				$this->log($user, 'NOT FOUND');
				continue;
			}
			$user->update(['iDokladId' => $person[0]['Id']]);
			$this->log($user, 'LOCALY UPDATED');
		}
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @return bool
	 * @throws iDokladException
	 */
	private function contactCreate($user) {
		$this->iDokladService->authenticate();
		$response = $this->iDokladService->createContact($user);
		$id = $response->getData()['Id'];
		return $user->update(['iDokladId' => $id]);
	}
}