<?php

namespace App\CronModule\Presenters;

use App\Model\UserService;
use App\Model\MessageService;
use GuzzleHttp\Client;
use Nette\Application\UI\InvalidLinkException;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nette\Utils\Strings;
use Tracy\Debugger;

/**
 * Class MessagePresenter
 * @package App\CronModule\Presenters
 */
class MessagePresenter extends BasePresenter {

	/** @var UserService @inject */
	public $userService;

	/** @var MessageService @inject */
	public $messageService;

	/** @var IMailer @inject */
	public $mailer;

	/** @var ArrayHash */
	private $mailSettings;

	/** @var ArrayHash */
	private $messengerSettings;

	/**
	 * MessagePresenter constructor.
	 * @param array $mailSettings
	 */
	public function __construct(array $mailSettings, array $messengerSettings) {
		parent::__construct();
		$this->mailSettings = ArrayHash::from($mailSettings);
		$this->messengerSettings = ArrayHash::from($messengerSettings);
	}

	/**
	 *
	 * @throws JsonException
	 * @throws InvalidLinkException
	 */
	public function actionSend() {

		$client = new Client(['base_uri' => $this->messengerSettings->url]);

		$messages = $this->messageService->getMessagesToSend();
		$this->template->items = [];

		foreach ($messages as $message) {
			$this->messageService->beginTransaction();
			$mail = $this->createEmailMessage($message);
			$this->mailer->send($mail);

			$notification = $this->createMessengerMessage($message);
			if (count($notification['recipients'])){
				$response = $client->request('POST', '/', ['json' => ['notification' => $notification]]);
				//file_put_contents(__DIR__. '/notification-'.$message->id.'.json', Json::encode(['notification' => $notification]));
			}

			$date = new DateTime();
			$message->update(['date_send' => $date]);

			$this->template->items[$message->id] = $date;
			$this->messageService->commitTransaction();
		}
	}

	/**
	 * @param IRow|ActiveRow $message
	 * @return Message
	 * @throws JsonException
	 */
	private function createEmailMessage(IRow $message){
		$mail = new Message();
		$mail->setFrom($this->mailSettings->account, $this->mailSettings->title);
		$mail->addBcc($this->mailSettings->account);

		$parameters = $message->param ? Json::decode($message->param, Json::FORCE_ARRAY) : [];

		$author = $this->userService->getUserById($message->user_id, UserService::DELETED_LEVEL);

		$mail->addReplyTo($author->mail, UserService::getFullName($author));
		if ($message->message_type_id == MessageService\Message::CUSTOM_MESSAGE_TYPE) $mail->addBcc($author->mail, UserService::getFullName($author));

		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../../presenters/templates/Mail/newMail.latte');
		$template->text = $message->text;
		$mail->setHtmlBody($template);

		$mail->setSubject('['.$this->mailSettings->title.'] ' . $message->subject);

		if ($message->message_type_id == MessageService\Message::VOTE_NEW_TYPE) {
			$mail->addTo($this->mailSettings->board);
		} else {
			foreach ($message->related('message_user') as $recipient) {
				$mail->addTo($recipient->user->mail, UserService::getFullName($recipient->user));
				if ($recipient->user->mail2 && $recipient->user->send_to_second) $mail->addCc($recipient->user->mail2);
			}
		}

		if (array_key_exists('filename', $parameters)) {
			$filename = WWW_DIR .'/../member/'. MessageService::DIR_ATTACHMENTS .'/'. $parameters['filename'];
			$mail->addAttachment($filename);
		}

		return $mail;
	}

	/**
	 * @param IRow|ActiveRow $message
	 * @return array
	 * @throws JsonException
	 * @throws InvalidLinkException
	 */
	private function createMessengerMessage(IRow $message){
		$notification = [
			'botID' => (string) $this->messengerSettings->botID,
			'title' => $message->subject,
			'text' => Strings::truncate($message->text, 200),
			'url' => $this->link('//:Member:Mail:view', $message->id),
			'recipients' => [],
		];

		foreach ($message->related('message_user')->where('user.messengerId NOT', NULL) as $recipient) {
			if ($recipient->user->messengerId) $notification['recipients'][] = $recipient->user->messengerId;
		}

		$parameters = $message->param ? Json::decode($message->param, Json::FORCE_ARRAY) : [];

		if (array_key_exists('filename', $parameters)) {
			$filename = 'https://member.vzs-jablonec.cz/'. MessageService::DIR_ATTACHMENTS .'/'. $parameters['filename'];
			$notification['refer'] = $filename;
		}

		if (array_key_exists('session_id', $parameters)) {
			$session = $this->userService->getPasswordSessionId($parameters['session_id']);
			$notification['refer'] = $this->link('//:Account:Sign:restorePassword', ['pubkey' => $session->pubkey]);
		}

		if (array_key_exists('akce_id', $parameters)) {
			$notification['refer'] = $this->link('//:Member:Akce:view', $parameters['akce_id']);
		}

		if (array_key_exists('hlasovani_id', $parameters)) {
			$notification['refer'] = $this->link('//:Member:Hlasovani:view', $parameters['hlasovani_id']);
		}

		return $notification;
	}


}