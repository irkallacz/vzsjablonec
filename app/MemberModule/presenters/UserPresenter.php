<?php

namespace App\MemberModule\Presenters;

use App\MemberModule\Forms\UserFormFactory;
use App\Model\MessageService;
use App\Model\UserService;
use App\Template\LatteFilters;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Security\Passwords;
use Nette\Utils\Image;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Tracy\Debugger;

class UserPresenter extends LayerPresenter {

	/** @var UserService @inject */
	public $userService;

	/** @var MessageService @inject */
	public $messageService;

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
				$searchList['deleted'] = $this->userService->searchUsers($q)
					->where('role IS NULL');
			}
		}else{
			$searchList['members'] = $this->userService->getUsers(UserService::MEMBER_LEVEL);
			if ($this->getUser()->isInRole('board')) {
				$searchList['users'] = $this->userService->getUsers()
					->where('role', 0);
				$searchList['deleted'] = $this->userService->getUsers(UserService::DELETED_LEVEL)
					->where('role IS NULL');
			}
			foreach ($searchList as $list) $list->order('surname, name');
		}

		$this->template->searchList = $searchList;
	}

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
			$searchList['deleted'] = $this->userService->searchUsers($search)->where('role IS NULL');
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

		$fullName = $user->surname . ' ' . $user->name;
		$fileName = '/img/photos/' . Strings::webalize($fullName) . '.jpg';
		$this->template->filename = file_exists(WWW_DIR . $fileName) ? $fileName : NULL;
		$this->template->title = $fullName;
	}

	/**
	 * @allow(member)
	 */
	public function actionVcfArchive() {
		$zip = new \ZipArchive;
		$zip->open(WWW_DIR . '/archive.zip', \ZIPARCHIVE::CREATE | \ZIPARCHIVE::OVERWRITE);

		$template = $this->createTemplate();
		$template->setFile(APP_DIR . '/MemberModule/templates/User.vcf.latte');
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
	 * @allow(member)
	 */
	public function renderCsv() {
		$this->template->users = $this->userService->getUsers(UserService::MEMBER_LEVEL)->order('surname, name');

		$httpResponse = $this->context->getByType('Nette\Http\Response');
		$httpResponse->setHeader('Content-Disposition', 'attachment; filename="members.csv"');
	}

	/**
	 * @param int $id
	 * @allow(admin)
	 * @throws BadRequestException
	 */
	public function actionActivate(int $id) {
		$member = $this->userService->getUserById($id, UserService::DELETED_LEVEL);

		if (!$member) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		$member->update(['role' => 0]);

		$this->userService->addUserLogin($member->id, new DateTime());

		$this->flashMessage('Uživatel byl úspěšně přidán mezi aktivní');
		$this->redirect('view', $id);
	}

	/**
	 * @param int $id
	 * @allow(board)
	 * @throws BadRequestException
	 */
	public function actionSendLoggingMail(int $id){
		$member = $this->userService->getUserById($id);

		if (!$member) throw new BadRequestException('Uživatel nenalezen');

		$session = $this->userService->addPasswordSession($member->id, '24 HOUR');

		$this->addLoggingMail($member, $session);

		$this->flashMessage('Uživateli byl zaslán úvodní e-mail');
		$this->redirect('view', $member->id);
	}


	/**
	 * @param IRow|ActiveRow $user
	 * @param IRow|ActiveRow $session
	 * @allow(board)
	 */
	public function addLoggingMail(IRow $user, IRow $session) {
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../templates/Mail/newMember.latte');
		$template->session = $session;

		$message = new MessageService\Message(MessageService\Message::USER_NEW_TYPE);

		$message->setSubject('Vítejte v informačním systému VZS Jablonec nad Nisou');
		$message->setText($template);

		$message->setAuthor($this->user->id);
		$message->addRecipient($user->id);

		$message->setParameters(['user_id' => $user->id,'session_id' => $session->id]);

		$this->messageService->addMessage($message);
	}

	/**
	 * @param int $id
	 * @allow(board)
	 * @throws BadRequestException
	 */
	public function actionResetPassword(int $id) {
		$user = $this->userService->getUserById($id);

		if (!$user) throw new BadRequestException('Uživatel nenalezen');

		$session = $this->userService->addPasswordSession($user->id, '12 HOUR');

		$this->addRestoreMail($user, $session);
		$next = $this->messageService->getNextSendTime();
		$this->flashMessage('Uživateli bude '.LatteFilters::timeAgoInWords($next).' minut odelán email pro změnu hesla');
		$this->redirect('view', $user->id);
	}

	/**
	 * @param int $id
	 * @allow(board)
	 * @throws BadRequestException
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
	 * @param int $id
	 * @allow(user)
	 */
	public function actionEdit(int $id) {
		/** @var Form $form*/
		$form = $this['memberForm'];
		$form['name']->setAttribute('readonly');
		$form['surname']->setAttribute('readonly');

		$form->setCurrentGroup($form->getGroups()[' ']);

		if ($this->getUser()->isInRole('board')) {
			$form['date_add'] = new \DateInput('Datum registrace');
			$form['date_add']->setRequired('Vyplňte datum registrace');
		}

		if ($this->getUser()->isInRole('admin')) {
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

		/**@var Form $form */
		$form = $this['memberForm'];
		$member = $this->userService->getUserById($id);

		if (!$member) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		if ((!$this->getUser()->isInRole('admin')) and ($member->id != $this->getUser()->getId())) {
			throw new ForbiddenRequestException('Nemáte právo editovat tohoto uživatele');
		}

		$form->setDefaults($member);
	}


	/** @allow(board) */
	public function actionAdd() {
		/**@var Form $form */
		$form = $this['memberForm'];
		unset($form['image']);
		unset($form['text']);

		$form->setCurrentGroup($form->getGroups()[' ']);
		$form['date_add'] = new \DateInput('Datum registrace');
		$form['date_add']->setRequired('Vyplňte datum registrace');
		$form['date_add']->setDefaultValue(new DateTime());

	}

	/** @allow(board) */
	public function renderAdd() {
		$this->setView('edit');
	}

	/** @allow(user) */
	public function actionProfile() {
		$id = $this->getUser()->getId();
		$this->redirect('edit', $id);
	}

	public function currentPassValidator($item) {
		$id = $this->getParameter('id');

		$user = $this->userService->getUserById($id);
		return !Passwords::verify($item->value, $user->hash);
	}

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
	
	protected function createComponentMemberForm() {
		if ($this->action == 'edit') {
			$this->userFormFactory->setUserId($this->getParameter('id'));
		}

		$form = $this->userFormFactory->create();

		$form->addGroup(' ');

		$form->setCurrentGroup(NULL);

		$form->addUpload('image', 'Nový obrázek')
			->addCondition(Form::FILLED)
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 5 MB.', 5 * 1024 * 1024 /* v bytech */)
			->addRule(Form::IMAGE, 'Fotografie musí být ve formátu JPEG')
			->endCondition();

		$form->addTextArea('text', 'Poznámka', 30)
			->setNullable()
			->setAttribute('spellcheck', 'true');

		$form->addSubmit('ok', 'Ulož');
		$form->onSuccess[] = [$this, 'memberFormSubmitted'];

		return $form;
	}

	public function memberFormSubmitted(Form $form) {
		$id = $this->getParameter('id');

		$values = $form->getValues();

		$values->date_update = new DateTime();

		if ($id) {
			$user = $this->userService->getUserById($id);

			if ((isset($form->image)) and ($form->image->isFilled()) and ($values->image->isOK())) {
				/** @var Image $image  */
				$image = $values->image->toImage();
				$image->resize(1000, NULL, Image::SHRINK_ONLY);
				$image->save(WWW_DIR . '/img/photos/' . Strings::webalize($user->surname.' '.$user->name) . '.jpg', 90, Image::JPEG);
			}

			unset($values->image);

			$user->update($values);
			$this->flashMessage('Osobní profil byl změněn');
			$this->redirect('view', $id);
		} else {
			$user = $this->userService->addUser($values);

			$this->userService->addUserLogin($user->id, new DateTime());

			$this->flashMessage('Byl přidán nový člen');
			$this->redirect('view', $user->id);
		}
	}
}