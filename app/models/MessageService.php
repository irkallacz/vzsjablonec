<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 24.10.2017
 * Time: 20:52
 */

namespace App\Model;


use Nette\Utils\DateTime;
use Nette\Utils\Json;

class MessageService extends DatabaseService {
	const TABLE_MESSAGE = 'message';
	const TABLE_MESSAGE_TYPE = 'message_type';
	const TABLE_MESSAGE_USER = 'message_user';

	public function addMessage($subject, $text, $user, array $users, $param = NULL, $type = 1){
		$param  = $param ? Json::encode($param) : NULL;
		$datetime = new DateTime;

		$this->database->query('INSERT INTO '.self::TABLE_MESSAGE, [
			'message_type_id' => $type,
			'subject' => $subject,
			'user_id' => $user,
			'date_add' => $datetime,
			'date_send' => $datetime,
			'text' => $text,
			'param' => $param
		]);

		$message_id = $this->database->getInsertId();

		foreach ($users as $user_id => $user){
			$this->database->query('INSERT INTO '.self::TABLE_MESSAGE_USER, [
				'user_id' => $user_id,
				'message_id' => $message_id
			]);
		}
	}

	public function getTable(){
		return $this->database->table(self::TABLE_MESSAGE);
	}
}