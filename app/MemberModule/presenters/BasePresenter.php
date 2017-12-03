<?php

namespace App\MemberModule\Presenters;

use App\Template\LatteFilters;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Presenter;
use Nette\Utils\Html;

/**
 * @property-read \Nette\Bridges\ApplicationLatte\Template|\stdClass $template
 */
abstract class BasePresenter extends Presenter {

	protected function afterRender() {
		parent::afterRender();
		if (!$this->context->parameters['productionMode']) {
			parent::afterRender();
			$this->template->basePath .= '/member';
			$this->template->baseUri .= '/member';
		}
	}

	/**
	 * @param bool $class
	 */
	public function actionTexyPreview($class = false) {
		if ($this->isAjax()) {

			$httpRequest = $this->context->getByType('Nette\Http\Request');

			$div = Html::el('div')->setHtml(LatteFilters::texy($httpRequest->getPost('texy')));
			$div->id = 'texyPreview';
			if ($class) $div->class = 'texy';

			$this->sendResponse(new TextResponse($div));
		}
	}

	/**
	 * @return \Nette\Mail\Message
	 */
	public function getNewMail() {
		$mail = new \Nette\Mail\Message;
		$mail->setFrom('info@vzs-jablonec.cz', 'VZS Jablonec')
			->addBcc('info@vzs-jablonec.cz');

		return $mail;
	}

	public function sendRestoreMail($member, $session) {
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../templates/Mail/restorePassword.latte');
		$template->session = $session;

		$mail = $this->getNewMail();

		$mail->addTo($member->mail, $member->surname . ' ' . $member->name);
		if ($member->mail2 && $member->send_to_second) $mail->addCc($member->mail2);
		$mail->setSubject('[VZS Jablonec] Obnova hesla');
		$mail->setHTMLBody($template);

		$this->mailer->send($mail);
	}

}
