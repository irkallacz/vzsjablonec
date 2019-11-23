<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 16.06.2019
 * Time: 17:42
 */

namespace App\CronModule\Presenters;

use App\Model\UserService;
use GuzzleHttp\Psr7\Request;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\ITemplate;
use Nette\Database\Table\IRow;
use Tracy\Debugger;

final class ContactsPresenter extends BasePresenter {

	const DOMAIN = 'vzs-jablonec.cz';

	/**
	 * @var \Google_Client @inject
	 */
	public $googleClient;

	/**
	 * @var UserService @inject
	 */
	public $userService;

	/**
	 * @var array
	 */
	private $updateContacts = [];

	/**
	 * @var ITemplate
	 */
	private $feedTemplate;

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 * @throws \Nette\Application\AbortException
	 */
	public function actionDefault()
	{
		$this->googleClient->setSubject('admin@vzs-jablonec.cz');
		$httpClient = $this->googleClient->authorize();
		$this->googleClient->fetchAccessTokenWithAssertion($httpClient);

		$response = $httpClient->request('GET', 'https://www.google.com/m8/feeds/contacts/' . self::DOMAIN . '/full');

		$body = (string) $response->getBody();
		Debugger::barDump($response->getHeaders());
		$this->sendResponse(new TextResponse(htmlentities($body)));
	}

	/**
	 *
	 */
	public function actionDirectory()
	{
		$service = new \Google_Service_Directory($this->googleClient);
		$users = $service->users->listUsers();
	}

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function actionUpdate()
	{
		//Všichni členové, kteřé nemají doménový účet
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL)
			->where('mail NOT LIKE ?', '%@' . self::DOMAIN)
			->where('mail2 NOT LIKE ?', '%@' . self::DOMAIN)
			->fetchAll();

		$this->googleClient->setSubject('admin@' . self::DOMAIN);
		$httpClient = $this->googleClient->authorize();
		$this->googleClient->fetchAccessTokenWithAssertion($httpClient);

		//Získáme seznam kontaktů
		$response = $httpClient->request('GET', 'https://www.google.com/m8/feeds/contacts/' . self::DOMAIN . '/full?v=3.0');
		$body = (string) $response->getBody();
		$xml = simplexml_load_string($body);

		//Připravíme šablonu pro hromadné úpravy
		$feedXml = null;
		$this->feedTemplate = $this->createTemplate();
		$this->feedTemplate->setFile(__DIR__ . '/../templates/Contacts.batch.latte');

		foreach ($xml->entry as $contact) {
			$updated = (string) $contact->updated;
			$updated = new \DateTime($updated);

			$contactId = (string) $contact->id;
			$etag = (string) $contact->attributes('gd', true)['etag'];
			$contact = $contact->children('gd', true);

			//Pokud kontakt nemá naše ID, nevšímat si ho
			if ((property_exists($contact, 'extendedProperty')) and (strval($contact->extendedProperty->attributes()['name'])=='id')) {
				$id = (int) $contact->extendedProperty;

				if (!array_key_exists($id, $users)) {
					//Není v seznamu užvatelů = delete
					$feedXml .= $this->createFeedEntry('delete', $id, null, $etag, $contactId);
				} elseif ($users[$id]->date_update >= $updated) {
					//Je v seznamu uživatelů a byl od posledně aktualizován = update
					$feedXml .= $this->createFeedEntry('update', $id, $users[$id], $etag, $contactId);
				}
			}
		}

		$differences = UserService::getDifferences(array_keys($users), array_keys($this->updateContacts));

		//Uřivatelé, kteří nejsou v adreáři googlu = insert
		foreach ($differences['add'] as $id) {
			$feedXml .= $this->createFeedEntry('insert', $id, $users[$id]);
		}

		if ($feedXml) {
			$feedXml = '<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:gd="http://schemas.google.com/g/2005" xmlns:batch="http://schemas.google.com/gdata/batch" xmlns:gContact="http://schemas.google.com/contact/2008"> <category scheme="http://schemas.google.com/g/2005#kind" term="http://schemas.google.com/g/2008#contact" />' . $feedXml;
			$feedXml .= '</feed>';

			$response = $httpClient->send(new Request('post', 'https://www.google.com/m8/feeds/contacts/'. self::DOMAIN .'/full/batch?v=3.0', ['Content-Type' => 'application/atom+xml'], $feedXml));
		}

		$this->template->items = $this->updateContacts;
	}

	/**
	 * @param string $type
	 * @param int $id
	 * @param IRow|null $member
	 * @param string|null $etag
	 * @param string|null $contactId
	 * @return string
	 */
	private function createFeedEntry(string $type, int $id, IRow $member = null, string $etag = null, string $contactId = null)
	{
		$this->feedTemplate->type = $type;
		$this->feedTemplate->etag = $etag;
		$this->feedTemplate->member = $member;
		$this->feedTemplate->id = ($contactId) ? $contactId : $id;
		$this->updateContacts[$id] = $type;
		return (string) $this->feedTemplate;
	}
}