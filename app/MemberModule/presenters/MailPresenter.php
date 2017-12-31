<?php

namespace App\MemberModule\Presenters;

use App\Model\AkceService;
use App\Model\UserService;
use App\Model\MessageService;
use Joseki\Webloader\JsMinFilter;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Mail\IMailer;
use Tracy\Debugger;
use WebLoader\Compiler;
use WebLoader\FileCollection;
use WebLoader\Nette\JavaScriptLoader;

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

	/** @allow(board) */
	public function renderAdd() {
		$users = $this->userService->getUsers()->order('surname,name');
		$this->template->members = $users;

		$form = $this['mailForm'];
		if (!$form->isSubmitted()) {
			$this->template->pocet = ceil(count($users) / 3);
		}
	}

	/** @allow(member) */
	public function renderDefault() {
		$messages = $this->messageService->getMessages()->order('date_add DESC');
		if (!$this->getUser()->isInRole('admin')) $messages->where(':message_user.user_id = ? OR message.user_id = ?', $this->user->id, $this->user->id);
		$this->template->messages = $messages;
	}

	/**
	 * @param int $id
	 * @allow(member)
	 */
	public function actionAkce($id) {
		$form = $this['mailForm'];
		$akce = $this->akceService->getAkceById($id);
		$users = $this->userService->getUsersByAkceId($id)->where('NOT role', NULL);

		$form['to']->setDefaultValue(join(',', $users->fetchPairs('id', 'mail')));
		$form['subject']->setDefaultValue($akce->name . ': ');

		$this->template->isAkce = TRUE;

		$this->template->pocet = ceil(count($users) / 3);
		$this->setView('default');
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
			->setRequired('Musíte vybrat alespoň jednoho příjemce');

		$form->addButton('open');

		$form->addCheckboxList('users', 'Příjemci')
			->setItems($this->userService->getUsersArray(UserService::USER_LEVEL));

		$form->addText('subject', 'Předmět', 50)
			->setRequired('Vyplňte %label')
			->setAttribute('spellcheck', 'true')
			->setAttribute('class', 'max');


		$form->addUpload('file', 'Příloha')
			->setAttribute('class', 'max')
			->addCondition(Form::FILLED)
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 16 MB.', 16 * 1024 * 1024 /* v bytech */);

		$form->addTextArea('text', 'Text e-mailu', 45)
			->setRequired('Vyplňte %label')
			->setAttribute('spellcheck', 'true')
			->setAttribute('class', 'texyla');

		//$form->addSubmit('ok', 'Odeslat');
		$form->onSuccess[] = [$this, 'mailFormSubmitted'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @allow(member)
	 */
	public function mailFormSubmitted(Form $form) {
		$values = $form->getValues();
		$param = [];

		$sender = $this->userService->getUserById($this->getUser()->getId());

		if ($this->getAction() == 'akce') {
			$akceId = (int)$this->getParameter('id');
			$members = $this->userService->getUsersByAkceId($akceId)->where('NOT role', NULL);
			$param['akce_id'] = $akceId;
			$messageType = 2;
		} else {
			$members = $this->userService->getUsers()->where('id', $values->users);
			$messageType = 1;
		}

		$members->where('NOT id', $sender->id);

		if (($form['file']->isFilled())and(!$values->file->isOK())) {
			$form->addError('Chyba při nahrávání souboru');
			$this->redirect('this');
		}

		$mail = $this->getNewMail();
		$mail->addReplyTo($sender->mail, $sender->surname . ' ' . $sender->name);
		$mail->addBcc($sender->mail, $sender->surname . ' ' . $sender->name);

		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../templates/Mail/newMail.latte');
		$template->text = $values->text;

		$mail->setSubject('[VZS Jablonec] ' . $values->subject)
			->setHtmlBody($template);

		foreach ($members as $member) {
			$mail->addTo($member->mail, $member->surname . ' ' . $member->name);
			if ($member->mail2 && $member->send_to_second) $mail->addCc($member->mail2);
		}

		if (($form['file']->isFilled()) and ($values->file->isOK())) {
			$filename = $values->file->getSanitizedName();
			$mail->addAttachment($filename, $values->file->getContents());
			$values->file->move(WWW_DIR . '/doc/message/' . $filename);
			$param['filename'] = $filename;
		}

		$this->mailer->send($mail);

		$this->messageService->addMessage(
			$values->subject,
			$values->text,
			$this->getUser()->getId(),
			$members->fetchPairs('id'),
			$param,
			$messageType
		);

		$this->flashMessage('Váš mail byl v pořádku odeslán');

		if (isset($akceId)) $this->redirect('Akce:view', $akceId); else $this->redirect('Mail:default');
	}

	/**
	 * @return JavaScriptLoader
	 * @allow(member)
	 */
	protected function createComponentTexylaJs() {
		$files = new FileCollection(WWW_DIR . '/texyla/js');
		$files->addFiles(['texyla.js', 'selection.js', 'texy.js', 'buttons.js', 'cs.js', 'dom.js', 'view.js', 'window.js']);
		$files->addFiles([WWW_DIR . '/js/texyla_mail.js']);

		$compiler = Compiler::createJsCompiler($files, WWW_DIR . '/texyla/temp');
		$compiler->addFileFilter(new JsMinFilter());

		return new JavaScriptLoader($compiler, $this->template->basePath . '/texyla/temp');
	}

}