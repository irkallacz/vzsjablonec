<?php

namespace App\MemberModule\Presenters;

use App\MemberModule\Components\UserGridControl;
use App\MemberModule\Forms\UserFormFactory;
use App\Model\AkceService;
use App\Model\AnketyService;
use App\Model\MessageService;
use App\Model\UserService;
use App\Template\LatteFilters;
use Nette\Application\BadRequestException;
use Nette\Application\AbortException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Form;
use Nette\Application\UI\InvalidLinkException;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;
use Nette\Utils\Html;
use Nette\Utils\Image;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Tracy\Debugger;

class UserPresenter extends LayerPresenter {

	/** @var UserService @inject */
	public $userService;

	/** @var MessageService @inject */
	public $messageService;

	/** @var AkceService @inject */
	public $akceService;

	/** @var AnketyService @inject */
	public $anketyService;

	/** @var UserFormFactory @inject */
	public $userFormFactory;

	/**
	 * @param string|null $q
	 */
	public function actionDefault(string $q = null) {
		$searchList = [];
		if ($q) {
			$searchList['members'] = $this->userService->searchUsers($q, UserService::MEMBER_LEVEL);
			$this['memberSearchForm']['search']->setDefaultValue($q);

			if ($this->getUser()->isInRole('board')) {
				$searchList['users'] = $this->userService->searchUsers($q)
					->where('role', 0);
			}
		}else{
			$searchList['members'] = $this->userService->getUsers(UserService::MEMBER_LEVEL);
			if ($this->getUser()->isInRole('board')) {
				$searchList['users'] = $this->userService->getUsers()
					->where('role', 0);
			}
			foreach ($searchList as $list) $list->order('surname, name');
		}

		$this->template->searchList = $searchList;
	}

	/**
	 * @return Form
	 */
	protected function createComponentMemberSearchForm() {
		$form = new Form;
		$form->getElementPrototype()->class('ajax');
		$form->addText('search', null, 30)
			->setType('search')
			->setRequired('Vyplňte hledanou frázi')
			->setHtmlId('member-search')
			->getControlPrototype()
			->title = 'Vyhledá v seznamu hledanou frázi';

		$form->addSubmit('ok')
			->setHtmlId('member-search-button')
			->getControlPrototype()
				->setName('button')
				->setHtml('<svg class="icon icon-search"><use xlink:href="'.$this->template->baseUri.'/img/symbols.svg#icon-search"></use></svg>');

		$form->onSuccess[] = [$this, 'memberSearchFormSubmitted'];

		return $form;
	}

	public function memberSearchFormSubmitted(Form $form) {
		$search = $form->getValues()->search;

		$searchList = [];
		$searchList['members'] = $this->userService->searchUsers($search, UserService::MEMBER_LEVEL);

		if ($this->getUser()->isInRole('board')) {
			$searchList['users'] = $this->userService->searchUsers($search)->where('role', 0);
		}

		$this->template->searchList = $searchList;

		$this->redrawControl();
	}

	/**
	 * @param int $id
	 * @throws BadRequestException
	 * @throws ForbiddenRequestException
	 */
	public function renderView(int $id) {
		$user = $this->userService->getUserById($id, UserService::DELETED_LEVEL);

		if (!$user) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		if ((!$user->role) and ($this->getUser()->getId() != $id) and (!$this->getUser()->isInRole('board'))){
			throw new ForbiddenRequestException('Nemáte práva prohlížete tohoto uživatele');
		}

		$this->template->age = ($user->date_born) ? $user->date_born->diff(new DateTime()) : NULL;
		$this->template->member = $user;
		$this->template->last_login = $user->related('user_log')->order('date_add DESC')->fetch();

		$this->template->title = UserService::getFullName($user);
	}

	/**
	 * @allow(member)
	 * @throws BadRequestException
	 * @throws AbortException
	 */
	public function actionVcfArchive() {
		$zip = new \ZipArchive;
		$zip->open(WWW_DIR . '/archive.zip', \ZIPARCHIVE::CREATE | \ZIPARCHIVE::OVERWRITE);

		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '../templates/User.vcf.latte');
		$template->archive = TRUE;

		foreach ($this->userService->getUsers(UserService::MEMBER_LEVEL)->order('surname, name') as $member) {
			$template->member = $member;
			$s = (string) $template;
			$zip->addFromString(Strings::toAscii($member->surname) . ' ' . Strings::toAscii($member->name) . '.vcf', $s);
		}

		$zip->close();

		$response = new FileResponse(
			WWW_DIR . '/archive.zip',
			'member-archive.zip',
			'application/zip'
		);

		$this->sendResponse($response);
	}

	/**
	 * @allow(board)
	 */
	public function actionTable() {

	}

	/**
	 * @param int $id
	 * @allow(admin)
	 * @throws BadRequestException
	 * @throws AbortException
	 */
	public function actionActivate(int $id) {
		$member = $this->userService->getUserById($id, UserService::DELETED_LEVEL);

		if (!$member) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		$member->update(['role' => 0]);
		$this->flashMessage('Uživatel byl úspěšně přidán mezi aktivní');

		$session = $this->userService->addPasswordSession($member->id, '24 HOUR');

		$this->addLoggingMail($member, $session);
		$datetime = $this->messageService->getNextSendTime();
		$this->flashMessage('Uživateli bude zaslán úvodní e-mail ' . LatteFilters::timeAgoInWords($datetime));

		$this->redirect('view', $id);
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @param IRow|ActiveRow $session
	 * @allow(board)
	 */
	public function addLoggingMail(IRow $user, IRow $session) {
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../../presenters/templates/Mail/newMember.latte');
		$template->session = $session;

		$message = new MessageService\Message(MessageService\Message::USER_NEW_TYPE);

		$message->setSubject('Vítejte v informačním systému VZS Jablonec nad Nisou');
		$message->setText($template);

		$message->setAuthor($this->user->id);
		$message->addRecipient($user->id);

		$filename = Strings::webalize(UserService::getFullName($user)).'.pdf';
		$filePath = WWW_DIR .'/'. MessageService::DIR_ATTACHMENTS .'/'. $filename;
		$this->registrationForm($user, 'F', $filePath);

		$message->setParameters(['user_id' => $user->id,'session_id' => $session->id, 'filename' => $filename]);

		$this->messageService->addMessage($message);
	}

	/**
	 * @param int $id
	 * @allow(board)
	 * @throws BadRequestException
	 * @throws AbortException
	 */
	public function actionResetPassword(int $id) {
		$user = $this->userService->getUserById($id);

		if (!$user) throw new BadRequestException('Uživatel nenalezen');

		$session = $this->userService->addPasswordSession($user->id, '12 HOUR');

		$this->addRestoreMail($user, $session);
		$next = $this->messageService->getNextSendTime();
		$this->flashMessage('Uživateli bude '.LatteFilters::timeAgoInWords($next).' odelán email pro změnu hesla');
		$this->redirect('view', $user->id);
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @param IRow|ActiveRow $session
	 */
	public function addRestoreMail(IRow $user, IRow $session) {
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../../presenters/templates/Mail/restorePassword.latte');
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

	/**
	 * @param int $id
	 * @allow(board)
	 * @throws BadRequestException
	 * @throws AbortException
	 */
	public function actionDelete(int $id) {
		$member = $this->userService->getUserById($id);

		if (!$member) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		$member->update(['role' => NULL]);

		$this->flashMessage('Člen byl úspěšně přidán mezi neaktivní');
		$this->redirect('default');
	}

	/**
	 * @param int $id
	 * @allow(member)
	 * @throws BadRequestException
	 */
	public function renderVcf(int $id) {
		$member = $this->userService->getUserById($id, UserService::DELETED_LEVEL);

		if (!$member) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		$this->template->member = $member;

		$httpResponse = $this->context->getByType('Nette\Http\Response');
		$httpResponse->setContentType('text/x-vcard');
		$httpResponse->setHeader('Content-Disposition', 'attachment; filename="' . $member->surname . ' ' . $member->name . '.vcf"');
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @param string $output (I, D, F, S)
	 * @param string|NULL $filename
	 * @return string
	 */
	private function registrationForm(IRow $user, string $output, string $filename = NULL) {
		$fields = $this->userService->getDataForPDF($user);

		$pdf = new \FPDM(WWW_DIR.'/template.pdf');
		$pdf->Load($fields, true);
		$pdf->Merge();

		if (!$filename) $filename = $fields['surname_name'].'.pdf';

		return $pdf->Output($output, $filename);
	}

	/**
	 * @param int $id
	 * @allow(board)
	 */
	public function actionRegistrationForm(int $id) {
		$user = $this->userService->getUserById($id, UserService::DELETED_LEVEL);
		$this->registrationForm($user, 'D');
	}

	/**
	 * @param int $id
	 * @allow(user)
	 */
	public function actionEdit(int $id) {
		/** @var Form $form*/
		$form = $this['editMemberForm'];
		$form['name']->setAttribute('readonly');
		$form['surname']->setAttribute('readonly');

		$form->setCurrentGroup($form->getGroups()[' ']);

		if ($this->getUser()->isInRole('board')) {
			$form['date_add'] = new \DateInput('Datum registrace');
			$form['date_add']->setRequired('Vyplňte datum registrace');
		}

		if ($this->getUser()->isInRole('admin')) {
			$form->addText('vzsId', 'VZS ID', 8)
				->setNullable()
				->addCondition(Form::FILLED)
				->addRule(Form::INTEGER, 'Osobní číslo musí obsahovat jen číslice')
				->addRule(Form::LENGTH, 'Osobní číslo musí mít %d číslic',6);

			$form->addText('cardId', 'ID Karty', 8)
				->setNullable()
				->addCondition(Form::FILLED)
				->addRule(Form::LENGTH, 'Číslo karty musí mít %d znaků',8);

			$form->addSelect('role', 'Role',
				$this->userService->getRoleList()
			);

		}

		if ($this->getUser()->getId() != $id) {
			unset($form['password']);
			unset($form['confirm']);
		}

		unset($form['sendMail']);
	}
	/**
	 * @param int $id
	 * @allow(user)
	 * @throws ForbiddenRequestException
	 */
	public function renderPassword(int $id) {
		if ($this->getUser()->getId() != $id) {
			throw new ForbiddenRequestException('Nemáte právo měnit heslo');
		}
	}

	/**
	 * @param int $id
	 * @allow(user)
	 * @throws BadRequestException
	 * @throws ForbiddenRequestException
	 */
	public function renderEdit(int $id) {
		$this->template->id = $id;

		$member = $this->userService->getUserById($id);

		if (!$member) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		if ((!$this->getUser()->isInRole('admin')) and ($member->id != $this->getUser()->getId())) {
			throw new ForbiddenRequestException('Nemáte právo editovat tohoto uživatele');
		}

		/**@var Form $form */
		$form = $this['editMemberForm'];
		$form->setDefaults($member);
	}


	/**
	 * @allow(board)
	 */
	public function renderAdd() {
		/**@var Form $form */
		$form = $this['addMemberForm'];
		if (!$form->hasErrors()) unset($form['skip']);

		$this->setView('edit');
	}


	/**
	 * @allow(user)
	 * @throws AbortException
	 */
	public function actionProfile() {
		$id = $this->getUser()->getId();
		$this->redirect('edit', $id);
	}

	public function currentPassValidator($item) {
		$id = $this->getParameter('id');

		$user = $this->userService->getUserById($id);
		return !Passwords::verify($item->value, $user->hash);
	}

	/**
	 * @return Form
	 */
	protected function createComponentPasswordForm() {
		$form = new Form;

		$form->addProtection('Odešlete prosím formulář znovu');

		$form->addPassword('password', 'Nové heslo', 30)
			->setRequired('Vyplňte heslo')
			->addCondition(Form::FILLED)
			->addRule(Form::PATTERN, 'Heslo musí mít alespoň 8 znaků, musí obsahovat číslice, malá a velká písmena', '^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,15}$')
			->addRule([$this, 'currentPassValidator'], 'Nesmíte použít svoje staré heslo');

		$form->addPassword('confirm', 'Potvrzení', 30)
			->setOmitted()
			->setRequired('Vyplňte kontrolu hesla')
			->addRule(Form::EQUAL, 'Zadaná hesla se neschodují', $form['password'])
			->addCondition(Form::FILLED)
			->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaků', 8);

		$form->addSubmit('ok', 'Ulož');
		$form->onSuccess[] = [$this, 'passwordFormSubmitted'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @throws AbortException
	 */
	public function passwordFormSubmitted(Form $form) {
		$id = $this->getParameter('id');
		$values = $form->getValues();
		
		if ($values->password) {
			$hash = Passwords::hash($values->password);

			$user = $this->userService->getUserById($id);
			$user->update(['hash' => $hash]);
			$this->flashMessage('Vaše heslo bylo změněno');
			$this->redirect('view', $id);
		}
	}

	/**
	 * @return Form
	 * @throws InvalidLinkException
	 */
	protected function createComponentEditMemberForm() {

		$this->userFormFactory->setUserId($this->getParameter('id'));
		$form = $this->userFormFactory->create();

		$form->addGroup(' ');

		$form->setCurrentGroup(NULL);

		$form->addUpload('image', 'Nová fotografie')
			->setOption('description', Html::el()->setHtml('Fotografie by měla splňovat <a target="_blank" href="'.$this->link('photo').'">náležitosti pasové fotky</a>'))
			->addCondition(Form::FILLED)
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 5 MB.', 5 * 1024 * 1024 /* v bytech */)
			->addRule(Form::IMAGE, 'Fotografie musí být ve formátu JPEG nebo PNG')
			->endCondition();

		$form->addTextArea('text', 'Poznámka', 30)
			->setNullable()
			->setAttribute('spellcheck', 'true');

		$form->addSubmit('ok', 'Ulož');
		$form->onSuccess[] = [$this, 'memberFormSubmitted'];

		return $form;
	}


	/**
	 * @return Form
	 */
	protected function createComponentAddMemberForm() {

		$form = $this->userFormFactory->create();

		$form->addGroup(' ');

		$form->setCurrentGroup(NULL);

		$input = $form['date_add'] = new \DateInput('Datum registrace');
		$input->setRequired('Vyplňte datum registrace');
		$input->setDefaultValue(new DateTime());

		$form->addCheckbox('skip', 'Ignorovat upozornění')
			->setDefaultValue(FALSE);

		$form->onValidate[] = [$this->userFormFactory, 'uniqueCredentialsValidator'];

		$form->addSubmit('ok', 'Ulož');
		$form->onSuccess[] = [$this, 'memberFormSubmitted'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @param ArrayHash $values
	 * @throws AbortException
	 */
	public function memberFormSubmitted(Form $form, ArrayHash $values) {
		$id = $this->getParameter('id');

		$values->date_update = new DateTime();

		if ($id) {
			$user = $this->userService->getUserById($id);

			if ((isset($values->image))and($values->image->isOK())) {
				/** @var Image $image  */
				$image = $values->image->toImage();
				$image->resize(800, NULL, Image::SHRINK_ONLY);
				$filename = WWW_DIR . self::getUserImageName($user);
				$image->save($filename, 90, Image::JPEG);
				$values->photo = basename($filename);
			}

			unset($values->image);

			$user->update($values);
			$this->flashMessage('Osobní profil byl změněn');
			$this->redirect('view', $id);
		} else {
			if (isset($values->skip)) unset($values->skip);

			$user = $this->userService->addUser($values);

			$this->flashMessage('Byl přidán nový člen');

			$session = $this->userService->addPasswordSession($user->id, '24 HOUR');

			$this->addLoggingMail($user, $session);
			$datetime = $this->messageService->getNextSendTime();
			$this->flashMessage('Uživateli bude zaslán úvodní e-mail ' . LatteFilters::timeAgoInWords($datetime));

			$this->redirect('view', $user->id);
		}
	}

	/**
	 * @param IRow|ActiveRow $user
	 * @return string
	 */
	private static function getUserImageName(IRow $user){
		return '/img/photos/' . Strings::webalize(UserService::getFullName($user)) . '-' . $user->id . '.jpg';
	}

	/**
	 * @return UserGridControl
	 */
	protected function createComponentUserGrid(){
		return new UserGridControl($this->userService, $this->getSession('userTable'));
	}

	/**
	 * @param int $id
	 * @throws AbortException
	 */
	public function actionEventAttendees(int $id = NULL) {
		if ($id) {
			$attendees = $this->akceService->getMembersByAkceId($id)->fetchPairs('user_id', 'user_id');
			$attendees = array_values($attendees);

			$this->sendJson($attendees);
		}
	}

	/**
	 * @param int $id
	 * @throws AbortException
	 */
	public function actionSurveyVotes(int $id = NULL) {
		if ($id) {
			$attendees = $this->anketyService->getMembersByAnketaId($id)->fetchPairs('user_id', 'user_id');
			$attendees = array_values($attendees);

			$this->sendJson($attendees);
		}
	}

}