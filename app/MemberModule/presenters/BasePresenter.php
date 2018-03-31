<?php

namespace App\MemberModule\Presenters;

use App\Model\MessageService;
use App\Template\LatteFilters;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\Html;

/**
 * @property-read \Nette\Bridges\ApplicationLatte\Template|\stdClass $template
 */
abstract class BasePresenter extends Presenter {

	/**
	 *
	 */
	protected function afterRender() {
		parent::afterRender();
		if (!$this->context->parameters['productionMode']) {
			parent::afterRender();
			$this->template->basePath .= '/member';
			$this->template->baseUrl .= '/member';
		}
	}

	public function checkRequirements($element) {
		$this->getUser()->getStorage()->setNamespace('member');
		parent::checkRequirements($element);
	}

	/**
	 * @param bool $class
	 */
	public function actionTexyPreview(bool $class = FALSE) {
		if ($this->isAjax()) {

			$httpRequest = $this->context->getByType('Nette\Http\Request');

			$div = Html::el('div')->setHtml(LatteFilters::texy($httpRequest->getPost('texy')));
			$div->id = 'texyPreview';
			if ($class) $div->class = 'texy';

			$this->sendResponse(new TextResponse($div));
		}
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @param IRow|ActiveRow $session
	 */
	public function addRestoreMail(IRow $user, IRow $session) {
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../templates/Mail/restorePassword.latte');
		$template->session = $session;

		$message = new MessageService\Message();
		$message->setType(MessageService\Message::PASSWORD_RESET_TYPE);
		$message->setSubject('Obnova hesla');
		$message->setText($template);
		$message->setAuthor($this->getUser()->isLoggedIn() ? $this->user->id : $user->id);
		$message->addRecipient($user->id);
		$message->setParameters(['user_id' => $user->id,'session_id' => $session->id]);

		$this->messageService->addMessage($message);

	}

}
