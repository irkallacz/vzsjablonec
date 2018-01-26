<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 24.10.2017
 * Time: 20:52
 */

namespace App\Model;

use App\Model\MessageService\Message;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class MessageService extends DatabaseService {
	const TABLE_MESSAGE 		= 'message';
	const TABLE_MESSAGE_TYPE 	= 'message_type';
	const TABLE_MESSAGE_USER 	= 'message_user';

	const DIR_ATTACHMENTS 		= '/doc/message/';

	/**
	 * @param Message $message
	 */
	public function addMessage(Message $message){
		$this->insertMessage(
			$message->getSubject(),
			$message->getText(),
			$message->getAuthor(),
			$message->getRecipients(),
			$message->getParameters(),
			$message->getType()
		);
	}

	/**
	 * @param $subject
	 * @param $text
	 * @param $author_id
	 * @param \Iterator|array $recipients
	 * @param null $parameters
	 * @param int $type
	 */
	private function insertMessage($subject, $text, $author_id, $recipients, $parameters = NULL, $type = Message::CUSTOM_MESSAGE_TYPE){
		$parameters  = $parameters ? Json::encode($parameters) : NULL;

		$this->database->query('INSERT INTO '.self::TABLE_MESSAGE, [
			'message_type_id' => $type,
			'subject' => $subject,
			'user_id' => $author_id,
			'date_add' => new DateTime,
			'text' => $text,
			'param' => $parameters
		]);

		$message_id = $this->database->getInsertId();
		if ((is_array($recipients))or($recipients instanceof \Iterator)) {
			foreach ($recipients as $user_id => $recipient){
				$this->addRecipient($user_id, $message_id);
			}
		}
	}

	/**
	 * @param int $user_id
	 * @param int $message_id
	 */
	private function addRecipient($user_id, $message_id){
		$this->database->query('INSERT INTO '.self::TABLE_MESSAGE_USER, [
			'user_id' => $user_id,
			'message_id' => $message_id
		]);
	}
	/**
	 * @return \Nette\Database\Table\Selection
	 */
	public function getMessages(){
		return $this->getTable();
	}

	/**
	 * @param DateTime $date
	 * @return \Nette\Database\Table\Selection
	 */
	public function getMessagesNews(DateTime $date, $user_id){
		return $this->getMessages()->where('date_send > ?', $date)->where(':message_user.user_id', $user_id);
	}

	/**
	 * @return \Nette\Database\Table\Selection
	 */
	public function getMessagesToSend(){
		return $this->getMessages()->where('date_send IS NULL')->order('date_add DESC');
	}


	/**
	 * @return \Nette\Database\Table\Selection
	 */
	public function getTable(){
		return $this->database->table(self::TABLE_MESSAGE);
	}

	/**
	 * @return int
	 */
	public function getNextSendTime(){
		$now = new DateTime;
		return 60 - intval($now->format('i'));
	}

}