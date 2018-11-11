<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 10.11.2018
 * Time: 15:18
 */

namespace App\Mailer;

use Nette\Mail\IMailer;
use Nette\Mail\Message;
use Nette\Mail\MimePart;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;

class GmailMailer implements IMailer {

	/**
	 * @var Google_Service_Gmail;
	 */
	private $service;

	/**
	 * GmailMailer constructor.
	 * @param Google_Service_Gmail $service
	 */
	public function __construct(Google_Service_Gmail $service){
		$this->service = $service;
	}

	/**
	 * @param Message $mail
	 */
	public function send(Message $mail){
		$mail->setEncoding(MimePart::ENCODING_BASE64);

		$mail = $mail->generateMessage();
		$mail = rtrim(strtr(base64_encode($mail), '+/', '-_'), '=');

		$message = new Google_Service_Gmail_Message();
		$message->setRaw($mail);

		$this->service->users_messages->send('me', $message);
	}

}