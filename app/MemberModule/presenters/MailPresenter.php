<?php

namespace App\MemberModule\Presenters;

use App\MemberModule\Components\YearPaginator;
use App\MemberModule\Components\TinyMde;
use App\Model\AkceService;
use App\Model\UserService;
use App\Model\MessageService;
use App\Template\LatteFilters;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Mail\IMailer;
use Nette\Utils\ArrayHash;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nette\Utils\Strings;
use Tracy\Debugger;

/**
 * Class MailPresenter
 * @package App\MemberModule\Presenters
 * @allow(member)
 */
class MailPresenter extends LayerPresenter {

	/** @var UserService @inject */
	public $userService;

	/** @var AkceService @inject */
	public $akceService;

	/** @var MessageService @inject */
	public $messageService;

	/** @var IMailer @inject */
	public $mailer;

	/**
	 * @param array $recipients
	 * @allow(editor)
	 */
	public function renderAdd(array $recipients = [], int $eventId = NULL) {
		$users = $this->userService->getUsers()->order('surname,name')->fetchPairs('id');
		$this->template->users = $users;

		/** @var Form $form */
		$form = $this['mailForm'];

		if (!empty($recipients)) {
			$to = [];
			foreach ($recipients as $recipient) $to[] = $users[$recipient]->mail;
			sort($to);

			$form['users']->setDefaultValue($recipients);
			$form['to']->setDefaultValue(join(',', $to));
		}

		if ($eventId) {
			$event = $this->akceService->getAkceById($eventId);
			$form['subject']->setDefaultValue($event->name);
		}
	}

	/**
	 *
	 */
	public function renderDefault() {
		$year = $this['yp']->year;

		$messages = $this->messageService->getMessages()->where('date_send IS NOT NULL')->order('date_add DESC');
		if (!$this->getUser()->isInRole('admin')) $messages->where(':message_user.user_id = ?', $this->user->id);
		if ($year) $messages->where('YEAR(date_add) = ?', $year);
		$this->template->messages = $messages;
		$this->template->year = $year;
	}


	/**
	 * @return YearPaginator
	 */
	public function createComponentYp() {
		return new YearPaginator(2017, NULL, 1, intval(date('Y')));
	}

	/**
	 *
	 */
	public function renderSend() {
		$year = $this['yp']->year;

		$messages = $this->messageService->getMessages()->order('date_add DESC');
		if (!$this->getUser()->isInRole('admin')) $messages->where('user_id = ?', $this->user->id);
		if ($year) $messages->where('YEAR(date_add) = ?', $year);

		$this->template->messages = $messages;
		$this->template->year = $year;
		$this->template->nextSendTime = $this->messageService->getNextSendTime();

		$this->setView('default');
	}

	/**
	 * @param $id
	 * @throws AbortException
	 * @throws BadRequestException
	 */
	public function actionView($id) {
		$message = $this->messageService->getMessageById($id);
		if ($message) {
			$this->redirect('default#message/', ['id' => $id, 'yp-year' => $message->date_add->format('Y')]);
		}else {
			throw new BadRequestException('Zpráva nenalezena');
		}
	}

	/**
	 * @param int $id
	 * @throws BadRequestException
	 * @throws ForbiddenRequestException
	 */
	public function actionEdit(int $id) {
		$message = $this->messageService->getMessageById($id);

		if (!$message) {
			throw new BadRequestException('Zpráva nenalezena');
		}

		if ((!$this->getUser()->isInRole('admin'))and($message->user_id !== $this->user->id)) {
			throw new ForbiddenRequestException('Nemůžete editovat cizí zprávy');
		}

		if ($message->date_send) {
			throw new ForbiddenRequestException('Nemůžete editovat již odeslané zprávy');
		}

		$form = $this['mailForm'];
		$form['text']->setDefaultValue($message->text);
		$form['subject']->setDefaultValue($message->subject);

		$to = '';
		foreach ($this->messageService->getRecipients($message->id) as $recipient){
			$to .= $recipient->user->mail . ',';
		}
		$form['to']->setDefaultValue($to);

		$users = $message->related('message_user')->fetchPairs('id');
		$form['users']->setDefaultValue(array_keys($users));
		$form->onSuccess = [[$this, 'mailFormUpdate']];
		$this->setView('add');
	}

	/**
	 * @param int $id
	 * @throws BadRequestException
	 * @throws ForbiddenRequestException
	 * @throws AbortException
	 */
	public function actionDelete(int $id) {
		$message = $this->messageService->getMessageById($id);

		if (!$message) throw new BadRequestException('Zpráva nenalezena');
		if ((!$this->user->isInRole('admin')) and ($message->id !== $this->user->id)) throw new ForbiddenRequestException('Nemůžete mazat cizí zprávy');
		//if ($message->date_send) throw new ForbiddenRequestException('Nemůžete mazat již odeslané zprávy');

		$message->delete();

		$this->flashMessage('Zpráva byla smazána');
		$this->redirect('send');
	}


	/**
	 * @param int $id
	 * @allow(member)
	 * @throws AbortException
	 */
	public function renderAkce(int $id) {
		/** @var Form $form */
		$form = $this['mailForm'];

		$akce = $this->akceService->getAkceById($id);
		$users = $this->userService->getUsersByAkceId($id)->where('NOT role', NULL);

		if ($this->user->isInRole('editor')) {
			$users = array_values($users->fetchPairs('id', 'id'));
			$this->redirect('add', ['recipients' => $users, 'eventId' => $id]);
		}

		$form['to']->setDefaultValue(join(',', $users->fetchPairs('id', 'mail')));
		$form['subject']->setDefaultValue($akce->name);

		$this->template->isAkce = TRUE;
		$this->template->users = $users->fetchPairs('id');

		$this->template->pocet = ceil(count($users) / 3);
		$this->setView('add');
	}

	/**
	 * @return Form
	 * @allow(member)
	 */
	protected function createComponentMailForm() {
		$form = new Form;

		$form->addText('to', 'Příjemci', 50)
			->setAttribute('readonly')
			->setAttribute('class', 'max')
			->setOmitted()
			->setRequired('Musíte vybrat alespoň jednoho příjemce');

		$form->addButton('open')
			->setOmitted();

		$form->addButton('choose', 'vybrat')
			->setAttribute('class', 'buttonLike')
			->setHtmlId('choose-group')
			->setOmitted();

		$selection = $this->userService->getUsers(UserService::MEMBER_LEVEL);
		$threshold = new \DateTime('-18 years');

		$adults = clone $selection;
		$adults = $adults->where('date_born <= ? ', $threshold);

		$children = clone $selection;
		$children = $children->where('date_born > ? ', $threshold);

		$groups = [
			 'členové' => Json::encode($selection->fetchPairs(null, 'id')),
			 'dospělí' => Json::encode($adults->fetchPairs(null, 'id')),
			 'mládež' => Json::encode($children->fetchPairs(null, 'id')),
		];

		$form->addSelect('group', 'Skupina', array_flip($groups))
			->setHtmlId('group')
			->setOmitted();

		$form->addCheckboxList('users', 'Příjemci')
			->setItems($this->userService->getUsersArray(UserService::USER_LEVEL));

		$form->addText('subject', 'Předmět', 50)
			->setRequired('Vyplňte %label')
			->setAttribute('spellcheck', 'true')
			->setAttribute('class', 'max')
			->addFilter([$this, 'removeEmoji'])
			->addFilter([Strings::class, 'firstUpper']);

		$form->addUpload('file', 'Příloha')
			->setAttribute('class', 'max')
			->addCondition(Form::FILLED)
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 16 MB.', 16 * 1024 * 1024 /* v bytech */);

		$form->addComponent((new TinyMde('Text e-mailu'))
			->setRequired('Vyplňte %label')
			->setAttribute('spellcheck', 'true')
			->setHtmlAttribute('cols', 60)
			->setAttribute('class', 'editor'),
		'text');

		$form->addSubmit('ok', 'Odeslat');

		$form->onSuccess[] = [$this, 'mailFormSubmitted'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @param ArrayHash $values
	 * @allow(member)
	 */
	public function mailFormSubmitted(Form $form, ArrayHash $values) {
		$parameters = [];

		$message = new MessageService\Message();

		if ($this->getAction() == 'akce') {
			$akceId = (int) $this->getParameter('id');
			$members = $this->userService->getUsersByAkceId($akceId)->where('NOT role', NULL);
			$parameters['akce_id'] = $akceId;
			$message->setType(MessageService\Message::EVENT_MESSAGE_TYPE);
		} else {
			$members = $this->userService->getUsers()->where('user.id', $values->users);
			if ($eventId = $this->getParameter('eventId')){
				$message->setType(MessageService\Message::EVENT_MESSAGE_TYPE);
				$parameters['akce_id'] = $eventId;
			}else {
				$message->setType(MessageService\Message::CUSTOM_MESSAGE_TYPE);
			}
		}

		$members->where('NOT user.id', $this->user->id);
		$message->setRecipients($members);

		if (($form['file']->isFilled()) and (!$values->file->isOK())) {
			$form->addError('Chyba při nahrávání souboru');
			$this->redirect('this');
		}

		if (($form['file']->isFilled()) and ($values->file->isOK())) {
			$filename = $values->file->getSanitizedName();
			$values->file->move(WWW_DIR .'/'. MessageService::DIR_ATTACHMENTS .'/'. $filename);
			$parameters['filename'] = $filename;
		}

		$message->setSubject($values->subject);
		$message->setText($values->text);
		$message->setAuthor($this->user->id);
		$message->setParameters($parameters);
		$message->setSendAt(new \DateTime('+30 minutes'));

		$this->messageService->addMessage($message);

		$next = $this->messageService->getNextSendTime();
		$this->flashMessage('Váš mail bude odeslán ' . LatteFilters::timeAgoInWords($next));

		if (isset($akceId)) $this->redirect('Akce:view', $akceId); else $this->redirect('Mail:default');
	}

	/**
	 * @param Form $form
	 * @throws BadRequestException
	 * @throws ForbiddenRequestException
	 * @throws AbortException
	 * @throws JsonException
	 */
	public function mailFormUpdate(Form $form, ArrayHash $values) {
		$id = $this->getParameter('id');
		$message = $this->messageService->getMessageById($id);

		if (!$message) throw new BadRequestException('Zpráva nenalezena');
		if ((!$this->getUser()->isInRole('admin'))and($message->id !== $this->user->id)) throw new ForbiddenRequestException('Nemůžete editovat cizí zprávy');
		$parameters = ($message->param) ? Json::decode($message->param, JSON_OBJECT_AS_ARRAY) : [];

		$members = $this->userService->getUsers()->where('id', $values->users);

		if (($form['file']->isFilled())and(!$values->file->isOK())) {
			$form->addError('Chyba při nahrávání souboru');
			$this->redirect('this');
		}

		if (($form['file']->isFilled()) and ($values->file->isOK())) {
			$filename = $values->file->getSanitizedName();
			$values->file->move(WWW_DIR .'/'. MessageService::DIR_ATTACHMENTS .'/'. $filename);
			$parameters['filename'] = $filename;
		}

		unset($values->file);

		$users = $values->users;
		unset($values->users);

		$values->param = (!empty($parameters)) ? Json::encode($parameters, JSON_OBJECT_AS_ARRAY) : NULL;
		$message->update($values);

		$recipients = $this->messageService->getRecipients($id)->fetchPairs('user_id', 'user_id');

		foreach (MessageService::getDifferences($users, $recipients)['add'] as $recipient_id){
			$this->messageService->addRecipient($recipient_id, $id);
		}

		$this->messageService->getRecipients($id)
			->where('user_id', MessageService::getDifferences($users, $recipients)['delete'])
			->delete();

		$this->flashMessage('Zpráva byla uložena');
		$this->redirect("send#message/$id");
	}
}
