<?php
/**
 * Created by PhpStorm.
 * User: Vitek
 * Date: 17.2.2018
 * Time: 20:18
 */

namespace App\CronModule\Presenters;

use App\Model\IdokladService;
use App\Model\UserService;
use DateTimeZone;
use malcanek\iDoklad\iDokladException;
use malcanek\iDoklad\request\iDokladFilter;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Tracy\Debugger;

/**
 * Class IdokladPresenter
 * @package App\CronModule\presenters
 */
class IdokladPresenter extends BasePresenter {

	/** @var UserService @inject */
	public $userService;

	/** @var IdokladService @inject */
	public $iDokladService;

	/**
	 * get all iDoklad contacts
	 *  - create new if iDokladId not exists
	 *  - update if there is a change
	 * @param bool $force
	 */
	public function actionUpdate(bool $force = FALSE) {
		$this->setView('default');
		$items = [];
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
				$items[$user->id] = UserService::getFullName($user) . ' - CREATED';
				unset($users[$user->id]);
			} else {
				$update_time = new DateTime($contacts[$user->iDokladId]['DateLastChange']);
				$update_time->setTimezone(new DateTimeZone('+0100'));
				if ($force || $user->date_update > $update_time) {
					$this->iDokladService->updateContact($user->iDokladId, $user);
					$items[$user->id] = UserService::getFullName($user) . ' - UPDATED';
				} else {
					$items[$user->id] = UserService::getFullName($user) . ' - WITHOUT CHANGE';
				}
				unset($users[$user->id]);
			}
		}
		if (count($users)) {
			echo('ERROR - some users left without action<br><br>');
		}
		$this->template->items = $items;
	}

	/**
	 * go through the iDoklad contacts one by one by comparing 'surname name' (beware of duplicates) with CompanyName then
	 *  - add iDokladId to our database
	 */
	public function actionDefaultSync() {
		$this->setView('default');
		$items = [];
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL);
		$this->iDokladService->authenticate();
		foreach ($users as $user) {
			$request = $this->iDokladService->requestsContacts();
			$filter = new iDokladFilter('CompanyName', '==', UserService::getFullName($user));
			$request->addFilter($filter);
			$response = $this->iDokladService->sendRequest($request);
			$person = $response->getData();
			if (count($person) != 1) {
				$items[$user->id] = UserService::getFullName($user) . ' - NOT FOUND';
				continue;
			}
			$user->update(['iDokladId' => $person[0]['Id']]);
			$items[$user->id] = UserService::getFullName($user) . ' - LOCALY UPDATED';

		}
		$this->template->items = $items;
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @return bool
	 * @throws iDokladException
	 */
	public function contactCreate($user) {
		$this->iDokladService->authenticate();
		$response = $this->iDokladService->createContact($user);
		$id = $response->getData()['Id'];
		return $user->update(['iDokladId' => $id]);
	}
}