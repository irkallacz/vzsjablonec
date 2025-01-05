<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 24.10.2017
 * Time: 20:52
 */

namespace App\Model;

use App\Model\MessageService\Message;
use Nette\Database\Table\Selection;
use Nette\Database\Table\IRow;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class MessageService extends DatabaseService {
	const TABLE_MESSAGE = 'message';
	const TABLE_MESSAGE_TYPE = 'message_type';
	const TABLE_MESSAGE_USER = 'message_user';

	const DIR_ATTACHMENTS = 'doc/message';

	/**
	 * @param Message $message
	 */
	public function addMessage(Message $message) {
		$this->insertMessage(
			$message->getSubject(),
			$message->getText(),
			$message->getAuthor(),
			$message->getRecipients(),
			$message->getSendAt(),
			$message->getParameters(),
			$message->getType()
		);
	}

	/**
	 * @param string $subject
	 * @param string $text
	 * @param int $author_id
	 * @param \Iterator|array $recipients
	 * @param array|NULL $parameters
	 * @param int $type
	 */
	private function insertMessage(string $subject, string $text, int $author_id, $recipients, \DateTime $sendAt, array $parameters = NULL, int $type = Message::CUSTOM_MESSAGE_TYPE) {
		$parameters = $parameters ? Json::encode($parameters) : NULL;

		$this->database->query('INSERT INTO ' . self::TABLE_MESSAGE, [
			'message_type_id' => $type,
			'subject' => $subject,
			'user_id' => $author_id,
			'date_add' => new DateTime,
			'date_send_at' => $sendAt,
			'text' => $text,
			'param' => $parameters
		]);

		$message_id = $this->database->getInsertId();
		if ((is_array($recipients)) or ($recipients instanceof \Iterator)) {
			foreach ($recipients as $user_id => $recipient) {
				$this->addRecipient($user_id, $message_id);
			}
		}
	}

	/**
	 * @param int $user_id
	 * @param int $message_id
	 */
	public function addRecipient(int $user_id, int $message_id) {
		$this->database->query('INSERT INTO ' . self::TABLE_MESSAGE_USER, [
			'user_id' => $user_id,
			'message_id' => $message_id
		]);
	}

	/**
	 * @return Selection
	 */
	public function getMessages() {
		return $this->getTable();
	}


	/**
	 * @param int $id
	 * @return IRow|ActiveRow
	 */
	public function getMessageById(int $id){
		return $this->getMessages()->get($id);
	}

	/**
	 * @param int $message_id
	 * @return Selection
	 */
	public function getRecipients(int $message_id = NULL) {
		$recipients = $this->database->table(self::TABLE_MESSAGE_USER);
		if ($message_id) $recipients->where('message_id', $message_id);
		return $recipients;
	}


	/**
	 * @param DateTime $date
	 * @param int $user_id
	 * @return Selection
	 */
	public function getMessagesNews(DateTime $date, int $user_id) {
		return $this->getMessages()->where('date_send > ?', $date)->where(':message_user.user_id', $user_id);
	}

	/**
	 * @return Selection
	 */
	public function getMessagesToSend() {
		return $this->getMessages()
			->where('date_send_at <= NOW()')
			->where('date_send IS NULL')
			->order('date_send_at DESC')
			->order('date_add DESC');
	}


	/**
	 * @return Selection
	 */
	public function getTable() {
		return $this->database->table(self::TABLE_MESSAGE);
	}

	/**
	 * @return DateTime
	 */
	public function getNextSendTime() {
		$dateTime = new DateTime;
		$dateTime->setTime($dateTime->format('G'), 0);
		$dateTime->add(new \DateInterval('PT1H'));
		return $dateTime;
	}

}