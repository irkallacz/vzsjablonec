<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 23.1.2018
 * Time: 16:48
 */

namespace App\Model\MessageService;

use Nette\SmartObject;

class Message {
	use SmartObject;

	const CUSTOM_MESSAGE_TYPE	= 1;
	const EVENT_MESSAGE_TYPE	= 2;
	const REGISTRATION_NEW_TYPE	= 3;
	const EVENT_CONFIRM_TYPE	= 4;
	const USER_NEW_TYPE			= 5;
	const PASSWORD_RESET_TYPE	= 6;
	const VOTE_NEW_TYPE			= 7;

	/**
	 * @var string
	 */
	private $subject = '';

	/**
	 * @var string
	 */
	private $text = '';

	/**
	 * @var \Iterator|\ArrayAccess
	 */
	private $recipients = [];

	/**
	 * @var int|null
	 */
	private $author = NULL;

	/**
	 * @var array
	 */
	private $parameters = [];

	/**
	 * @var int
	 */
	private $type = self::CUSTOM_MESSAGE_TYPE;

	/**
	 * @var \DateTime
	 */
	private $sendAt = null;

	/**
	 * Message constructor.
	 * @param int $type
	 */
	public function __construct($type = self::CUSTOM_MESSAGE_TYPE) {
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getSubject() {
		return $this->subject;
	}

	/**
	 * @param string $subject
	 */
	public function setSubject(string $subject) {
		$this->subject = (string) $subject;
	}

	/**
	 * @return string
	 */
	public function getText() {
		return $this->text;
	}

	/**
	 * @param string $text
	 */
	public function setText(string $text) {
		$this->text = (string) $text;
	}

	/**
	 * @return \Iterator
	 */
	public function getRecipients() {
		return $this->recipients;
	}

	/**
	 * @param \Iterator $recipients
	 */
	public function setRecipients(\Iterator $recipients) {
		$this->recipients = $recipients;
	}

	/**
	 * @param int $id
	 */
	public function addRecipient(int $id) {
		$this->recipients[intval($id)] = intval($id);
	}

	/**
	 * @return int|null
	 */
	public function getAuthor() {
		return $this->author;
	}

	/**
	 * @param int $author
	 */
	public function setAuthor(int $author) {
		$this->author = $author;
	}

	/**
	 * @return array
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * @param array $parameters
	 */
	public function setParameters(array $parameters) {
		$this->parameters = $parameters;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param int $type
	 */
	public function setType(int $type) {
		$this->type = $type;
	}

	/**
	 * @return \DateTime
	 */
	public function getSendAt(): \DateTime
	{
		return $this->sendAt;
	}

	/**
	 * @param \DateTime $sendAt
	 */
	public function setSendAt(\DateTime $sendAt): void
	{
		$this->sendAt = $sendAt;
	}


}