<?php

namespace App\Console;

use App\Model\AkceService;
use App\Model\UserService;
use App\Model\MessageService;
use GuzzleHttp\Client;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

/**
 * Class MessagePresenter
 * @package App\CronModule\Presenters
 */
final class MessageCommand extends BaseCommand {

	const SEND_COUNT_LIMIT = 100;

	const SENDING_TYPE_ALL = 1;
	const SENDING_TYPE_PRIMARY = 2;
	const SENDING_TYPE_SECONDARY = 3;

	/** @var UserService */
	private $userService;

	/** @var MessageService */
	private $messageService;

	/** @var AkceService */
	private $akceService;

	/** @var IMailer */
	private $mailer;

	/** @var LinkGenerator */
	private $linkGenerator;

	/** @var ArrayHash */
	private $mailSettings;

	/** @var ArrayHash */
	private $messengerSettings;

	/** @var string */
	private $wwwDir;

	/** @var \Latte\Engine */
	private $latte;

	/**
	 * MessagePresenter constructor.
	 * @param array $mailSettings
	 * @param array $messengerSettings
	 */
	public function __construct(array $mailSettings, array $messengerSettings, string $wwwDir, UserService $userService, MessageService $messageService, AkceService $akceService, IMailer $mailer, LinkGenerator $linkGenerator) {
		parent::__construct();
		$this->mailSettings = ArrayHash::from($mailSettings);
		$this->messengerSettings = ArrayHash::from($messengerSettings);
		$this->messengerSettings = ArrayHash::from($messengerSettings);
		$this->wwwDir = $wwwDir;

		$this->userService = $userService;
		$this->messageService = $messageService;
		$this->akceService = $akceService;
		$this->mailer = $mailer;
		$this->linkGenerator = $linkGenerator;

		$this->latte = new \Latte\Engine();
		$this->latte->addFilter('texy', function (string $s) {
			$texy = new \Texy\Texy();
			$texy->headingModule->balancing = \Texy\Modules\HeadingModule::FIXED;

			return new \Latte\Runtime\Html($texy->process($s));
		});
	}

	protected function configure() {
		$this->setName('cron:message')
			->setDescription('Send messages from database to Google Mail API and Messenger API');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$client = new Client(['base_uri' => $this->messengerSettings->url]);

		$messages = $this->messageService->getMessagesToSend();

		$this->writeln($output,'<info>Sending messages</info>');

		foreach ($messages as $message) {
			$parameters = $message->param ? Json::decode($message->param, Json::FORCE_ARRAY) : [];

			//Pokud byla akce již schválená, neodesílat email
			if ($message->message_type_id == MessageService\Message::EVENT_CONFIRM_TYPE) {
				if (array_key_exists('akce_id', $parameters)) {
					$event = $this->akceService->getAkceById($parameters['akce_id']);
					if ($event->confirm) {
						$this->writeln($output, 'Message delete, action confirm ', $event->id);
						$message->delete();
						continue;
					}
				}
			}

			//Pokud již session pro obnovu hesla není aktivní, neodesílat email
			if (in_array($message->message_type_id, [MessageService\Message::USER_NEW_TYPE, MessageService\Message::PASSWORD_RESET_TYPE])) {
				if (array_key_exists('session_id', $parameters)) {
					$session = $this->userService->getPasswordSessionId($parameters['session_id']);
					if (!$session) {
						$message->delete();

						$this->writeln($output, 'Message delete', $message->id);
						continue;
					} elseif ($session->date_end < date_create()) {
						$session->delete();
						$message->delete();

						$this->writeln($output, 'Message delete, session ', $session->id);
						continue;
					}
				}
			}

			$this->messageService->beginTransaction();

			$recipients = 1;
			foreach ($message->related('message_user') as $recipient) {
				$recipients++;
				if ($recipient->user->mail2 && $recipient->user->send_to_second) {
					$recipients++;
				}
			}

			if ($recipients >= self::SEND_COUNT_LIMIT) {
				$sendingTypes = [self::SENDING_TYPE_PRIMARY, self::SENDING_TYPE_SECONDARY];
			} else {
				$sendingTypes = [self::SENDING_TYPE_ALL];
			}

			foreach ($sendingTypes as $type) {
				$mail = $this->createEmailMessage($message, $parameters, $type);
				$this->mailer->send($mail);
			}

			$this->writeln($output,' ', 'Sending message', $message->id);

			$notification = $this->createMessengerMessage($message, $parameters);
			if (count($notification['recipients'])){
				$response = $client->request('POST', '/', ['json' => ['notification' => $notification]]);
				$this->writeln($output, ' ', 'Sending to Messenger', $message->id);
				//file_put_contents(__DIR__. '/notification-'.$message->id.'.json', Json::encode(['notification' => $notification]));
			}

			$date = new DateTime();
			$message->update(['date_send' => $date]);

			$this->messageService->commitTransaction();
		}
	}

	/**
	 * @param IRow|ActiveRow $message
	 * @param array $parameters
	 * @param int $type
	 * @return Message
	 */
	private function createEmailMessage(IRow $message, array $parameters, $type = self::SENDING_TYPE_ALL) {
		$mail = new Message();
		$mail->setFrom($this->mailSettings->account, $this->mailSettings->title);
		$mail->addBcc($this->mailSettings->account);

		$author = $this->userService->getUserById($message->user_id, UserService::DELETED_LEVEL);

		$mail->addReplyTo($author->mail, UserService::getFullName($author));
		if ($message->message_type_id == MessageService\Message::CUSTOM_MESSAGE_TYPE) {
			$mail->addBcc($author->mail, UserService::getFullName($author));
		}

		$mail->setHtmlBody($this->latte->renderToString(__DIR__ .  '/../presenters/templates/Mail/newMail.latte', ['text' => $message->text]));

		$mail->setSubject(sprintf('[%s] %s', $this->mailSettings->title, $message->subject));

		if ($message->message_type_id == MessageService\Message::VOTE_NEW_TYPE) {
			$mail->addTo($this->mailSettings->board);
		} else {
			foreach ($message->related('message_user') as $recipient) {
				if ($type != self::SENDING_TYPE_SECONDARY) {
					$mail->addTo($recipient->user->mail, UserService::getFullName($recipient->user));
				}

				if ($type != self::SENDING_TYPE_PRIMARY) {
					if ($recipient->user->mail2 && $recipient->user->send_to_second) {
						$mail->addCc($recipient->user->mail2);
					}
				}
			}
		}

		if (array_key_exists('filename', $parameters)) {
			$filename = $this->wwwDir .'/'. MessageService::DIR_ATTACHMENTS .'/'. $parameters['filename'];
			$mail->addAttachment($filename);
		}

		return $mail;
	}

	/**
	 * @param IRow|ActiveRow $message
	 * @param array $parameters
	 * @return array
	 * @throws InvalidLinkException
	 */
	private function createMessengerMessage(IRow $message, array $parameters){
		$notification = [
			'botID' => (string) $this->messengerSettings->botID,
			'title' => $message->subject,
			'text' => Strings::truncate($message->text, 200),
			'url' => $this->linkGenerator->link('Member:Mail:view', ['id' => $message->id]),
			'recipients' => [],
		];

		foreach ($message->related('message_user')->where('user.messengerId NOT', NULL) as $recipient) {
			if ($recipient->user->messengerId) $notification['recipients'][] = $recipient->user->messengerId;
		}

		if (array_key_exists('filename', $parameters)) {
			$filename = 'https://member.vzs-jablonec.cz/'. MessageService::DIR_ATTACHMENTS .'/'. $parameters['filename'];
			$notification['refer'] = $filename;
		}

		if (array_key_exists('session_id', $parameters)) {
			$session = $this->userService->getPasswordSessionId($parameters['session_id']);
			$notification['refer'] = $this->linkGenerator->link('Account:Sign:restorePassword', ['pubkey' => $session->pubkey]);
		}

		if (array_key_exists('akce_id', $parameters)) {
			$notification['refer'] = $this->linkGenerator->link('Member:Akce:view', ['id' => $parameters['akce_id']]);
		}

		if (array_key_exists('hlasovani_id', $parameters)) {
			$notification['refer'] = $this->linkGenerator->link('Member:Hlasovani:view', ['id' => $parameters['hlasovani_id']]);
		}

		return $notification;
	}
}