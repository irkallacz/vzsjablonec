<?php

namespace App\CronModule\Presenters;

use App\Model\UserService;
use App\Model\MessageService;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
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

	/**
	 *
	 */
	public function actionSend() {
		$this->messageService->beginTransaction();
		$messages = $this->messageService->getMessagesToSend();
		$this->template->items = [];

		foreach ($messages as $message) {
			/** @var ActiveRow $message*/

			$mail = $this->getNewMail();
			$parameters = $message->param ? Json::decode($message->param, Json::FORCE_ARRAY) : [];

			$author = $this->userService->getUserById($message->user_id);

			$mail->addReplyTo($author->mail, self::getFullName($author));
			if ($message->message_type_id == MessageService\Message::CUSTOM_MESSAGE_TYPE) $mail->addBcc($author->mail, self::getFullName($author));

			$template = $this->createTemplate();
			$template->setFile(__DIR__ . '/../templates/Mail/newMail.latte');
			$template->text = $message->text;
			$mail->setHtmlBody($template);

			$mail->setSubject('[VZS Jablonec] ' . $message->subject);

			if ($message->message_type_id == MessageService\Message::VOTE_NEW_TYPE) {
				$mail->addTo('predstavenstvo@vzs-jablonec.cz');
			} else {
				foreach ($message->related('message_user') as $recipient) {
					$mail->addTo($recipient->user->mail, UserService::getFullName($recipient->user));
					if ($recipient->user->mail2 && $recipient->user->send_to_second) $mail->addCc($recipient->user->mail2);
				}
			}

			if (array_key_exists('filename', $parameters)) {
				$filename = WWW_DIR . MessageService::DIR_ATTACHMENTS . $parameters['filename'];
				$mail->addAttachment($filename);
			}
			$this->mailer->send($mail);

			$date = new DateTime();
			$message->update(['date_send' => $date]);

			$this->template->items[$message->id] = $date;
		}

		$this->messageService->commitTransaction();
	}

	/**
	 * @return Message
	 */
	private static function getNewMail() {
		$mail = new Message();
		$mail->setFrom('info@vzs-jablonec.cz', 'VZS Jablonec')
			->addBcc('info@vzs-jablonec.cz');

		return $mail;
	}

}