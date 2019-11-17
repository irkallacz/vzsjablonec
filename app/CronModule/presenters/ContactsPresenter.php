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

final class ContactsPresenter extends BasePresenter
{

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
		$domain = 'vzs-jablonec.cz';

		$this->googleClient->setSubject('admin@vzs-jablonec.cz');
		$httpClient = $this->googleClient->authorize();
		$this->googleClient->fetchAccessTokenWithAssertion($httpClient);

		//$response = $httpClient->send(new Request('put', 'https://www.google.com/m8/feeds/contacts/vzs-jablonec.cz/full/7190f9458ccf994d/1573581443925000', ['Content-Type' => 'application/atom+xml'],);

		$response = $httpClient->request('GET', 'https://www.google.com/m8/feeds/contacts/' . $domain . '/full');

		Debugger::barDump($response);

		$body = (string)$response->getBody();
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
		$domain = 'vzs-jablonec.cz';

		//Všichni členové, kteřé nemají doménový účet
		$users = $this->userService->getUsers(UserService::MEMBER_LEVEL)
			->where('mail NOT LIKE ?', '%@' . $domain)
			->where('mail2 NOT LIKE ?', '%@' . $domain)
			->fetchAll();

		$this->googleClient->setSubject('admin@vzs-jablonec.cz');
		$httpClient = $this->googleClient->authorize();
		$this->googleClient->fetchAccessTokenWithAssertion($httpClient);

		//Získáme seznam kontaktů
		$response = $httpClient->request('GET', 'https://www.google.com/m8/feeds/contacts/' . $domain . '/full');

		$body = (string)$response->getBody();
		$xml = simplexml_load_string($body);

		$feedXml = null;
		$this->feedTemplate = $this->createTemplate();
		$this->feedTemplate->setFile(__DIR__ . '/../templates/Contacts.batch.latte');

		foreach ($xml->entry as $contact) {
			$updated = (string) $contact->updated;
			$updated = new \DateTime($updated);

			$updateLink = null;
			foreach ($contact->link as $link) {
				if ($link['rel'] == 'edit') {
					$updateLink = (string) $link;
				}
			}

			//Nenašel se updateLink = něco je špatně
			if (!$updateLink) {
				continue;
			}

			$contactId = (string) $contact->id;

			$contact = $contact->children('http://schemas.google.com/g/2005');

			//Pokud nemá naše ID, nevšímat si
			if (property_exists($contact, 'extendedProperty')) {
				$id = (int) $contact->extendedProperty;

				if (!array_key_exists($id, $users)) {
					//Není v seznamu užvatelů = delete
					$feedXml .= $this->createFeed('delete', $id, null, $updateLink, $contactId);
				} elseif ($users[$id]->date_update >= $updated) {
					//Je v seznamu uživatelů a byl od posledně aktualizován = update
					$feedXml .= $this->createFeed('update', $id, $users[$id], $updateLink, $contactId);
				}
			}
		}

		$differences = UserService::getDifferences(array_keys($users), array_keys($this->updateContacts));

		//Uřivatelé, kteří nejsou v adreáři googlu = insert
		foreach ($differences['add'] as $id) {
			$feedXml .= $this->createFeed('insert', $id, $users[$id]);
		}

		if ($feedXml) {
			$feedXml = '<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:gContact="http://schemas.google.com/contact/2008" xmlns:gd="http://schemas.google.com/g/2005" xmlns:batch="http://schemas.google.com/gdata/batch"><category scheme="http://schemas.google.com/g/2005#kind" term="http://schemas.google.com/g/2008#contact" />' . $feedXml;
			$feedXml .= '</feed>';

			$response = $httpClient->send(new Request('post', 'https://www.google.com/m8/feeds/contacts/' . $domain . '/full/batch', ['Content-Type' => 'application/atom+xml'], $feedXml));
		}

		$this->template->items = $this->updateContacts;
	}

	/**
	 * @param string $type
	 * @param int $id
	 * @param IRow|null $member
	 * @param string|null $updateLink
	 * @param string|null $contactId
	 * @return string
	 */
	private function createFeed(string $type, int $id, IRow $member = null, string $updateLink = null, string $contactId = null)
	{
		$this->feedTemplate->type = $type;
		$this->feedTemplate->link = $updateLink;
		$this->feedTemplate->member = $member;
		$this->feedTemplate->id = ($contactId) ? $contactId : $id;
		$this->updateContacts[$id] = $type;
		return (string) $this->feedTemplate;
	}
}