<?php

namespace App\MemberModule\Presenters;

use App\Model\MemberService;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;
use Nette\Mail\IMailer;
use Nette\Security\Passwords;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use Nette\Utils\Image;
use Nette\Utils\DateTime;
use Google_Service_People;
use Tracy\Debugger;

class MemberPresenter extends LayerPresenter {

	/** @var MemberService @inject */
	public $memberService;

	/** @var IMailer @inject */
	public $mailer;

	/** @var Google_Service_People @inject */
	public $googleService;

	public function actionDefault($q = null) {
		$searchList = [];
		if ($q) {
			$searchList['members'] = $this->memberService->searchUsers($q);
			$this['memberSearchForm']['search']->setDefaultValue($q);
		} else {
			$searchList['members'] = $this->memberService->getUsers(FALSE);
		}

		$searchList['members']->order('surname, name');

		if ($this->getUser()->isInRole('board')) {
			$searchList['users'] = clone $searchList['members'];
			$searchList['users']->where('role', 0);

			$searchList['deleted'] = clone $searchList['members'];
			$searchList['deleted']->where('role IS NULL');
		}

		$searchList['members']->where('NOT role', 0);
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
			->getControlPrototype()->setName('button')->setHtml('<svg class="icon icon-search"><use xlink:href="'.$this->template->basePath.'/img/symbols.svg#icon-search"></use></svg>');

		$form->onSuccess[] = [$this, 'memberSearchFormSubmitted'];

		return $form;
	}

	public function memberSearchFormSubmitted(Form $form) {
		$values = $form->getValues();

		$searchList = [];
		$searchList['members'] = $this->memberService->searchUsers($values->search)->order('surname, name');

		if ($this->getUser()->isInRole('board')) {
			$searchList['users'] = clone $searchList['members'];
			$searchList['users']->where('role', 0);

			$searchList['deleted'] = clone $searchList['members'];
			$searchList['deleted']->where('role IS NULL');
		}

		$searchList['members']->where('NOT role', 0);

		$this->template->searchList = $searchList;

		$this->redrawControl();
	}

	/** @allow(admin) */
	public function actionUpdateCsv() {
		if (($handle = fopen('members_update.csv', 'r')) !== FALSE) {
			while (($data = fgetcsv($handle, 0, ",", '"')) !== FALSE) {
				$array = [
					'surname' => $data[1],
					'name' => $data[2],
					'date_born' => date_create($data[3]),
					'mesto' => $data[4],
					'ulice' => $data[5],
					'mail' => $data[6],
					'telefon' => $data[7]
				];

				$member = $this->memberService->getMemberById($data[0]);

				if ($member) {
					$member->update($array);
					$this->flashMessage('Záznam "' . $member->surname . ' ' . $member->name . '" aktualizován');
				}
			}
		}

		$this->redirect('default');
	}

	/** @allow(admin) */
	public function actionGoogle() {
		$people = $this->googleService->people->get('people/me', ['personFields' => 'names,emailAddresses']);

		Debugger::dump($people);
	}

	public function renderView($id) {
		$member = $this->memberService->getTable()->get($id);

		if (!$member) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		if ((!$member->role)and($this->getUser()->getId() != $id)and(!$this->getUser()->isInRole('board'))){
			throw new ForbiddenRequestException('Nemáte práva prohlížete tohoto uživatele');
		}

		$this->template->narozeni = $member->date_born->diff(date_create());
		$this->template->member = $member;
		$this->template->last_login = $member->related('user_log')->order('date_add DESC')->fetch();
		$this->template->fileExists = file_exists(WWW_DIR . '/img/portrets/' . $id . '.jpg');
		$this->template->title = $member->surname . ' ' . $member->name;
	}

	public function actionVcfArchive() {
		$zip = new \ZipArchive;
		$zip->open(WWW_DIR . '/archive.zip', \ZIPARCHIVE::CREATE | \ZIPARCHIVE::OVERWRITE);

		$template = $this->createTemplate();
		$template->setFile(APP_DIR . '/MemberModule/templates/Member.vcf.latte');
		$template->archive = TRUE;

		foreach ($this->memberService->getMembers()->order('surname, name') as $member) {
			$template->member = $member;
			$s = (string)$template;
			//$s = iconv('utf-8','cp1250',$s);
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

	public function renderCsv() {
		$this->template->members = $this->memberService->getMembers()->order('surname, name');

		$httpResponse = $this->context->getByType('Nette\Http\Response');
		$httpResponse->setHeader('Content-Disposition', 'attachment; filename="members.csv"');
	}

	/**
	 * @param int $id
	 * @allow(admin)
	 */
	public function actionActivate($id) {
		$member = $this->memberService->getTable()->get($id);

		if (!$member) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		$member->update(['role' => 0]);

		$this->flashMessage('Uživatel byl úspěšně přidán mezi aktivní');
		$this->redirect('view', $id);
	}

	/**
	 * @param int $id
	 * @allow(board)
	 */
	public function actionResetPassword($id) {
		$member = $this->memberService->getUserById($id);

		if (!$member) throw new BadRequestException('Uživatel nenalezen');

		$session = $this->memberService->addPasswordSession($member->id, '12 HOUR');

		$this->sendRestoreMail($member, $session);

		$this->flashMessage('Uživateli byl odelán email pro změnu hesla');
		$this->redirect('view', $member->id);
	}

	/**
	 * @param int $id
	 * @allow(board)
	 */
	public function actionDelete($id) {
		$member = $this->memberService->getUserById($id);

		if (!$member) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		$member->update(['role' => NULL]);

		$this->flashMessage('Člen byl úspěšně přidán mezi neaktivní');
		$this->redirect('default');
	}

	public function renderVcf($id) {
		$member = $this->memberService->getMemberById($id);

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
	public function actionEdit($id) {
		$form = $this['memberForm'];
		$form['name']->setAttribute('readonly');
		$form['surname']->setAttribute('readonly');

		$form->setCurrentGroup($form->getGroups()[' ']);

		if ($this->getUser()->isInRole('board')) {
			$form['date_add'] = new \DateInput('Datum registrace');
			$form['date_add']->setRequired('Vyplňte datum registrace');
			//$form['date_add']->setDefaultValue(new DateTime());
		}

		if ($this->getUser()->isInRole('admin')) {
			$form->addSelect('role', 'Role',
				$this->memberService->getRoleList()
			);
		}

		if ($this->getUser()->getId()!=$id) {
			unset($form['password']);
			unset($form['confirm']);
		}

		unset($form['sendMail']);
	}
		/**
	 * @param int $id
	 * @allow(user)
	 */
	public function renderEdit($id) {
		$form = $this['memberForm'];
		$member = $this->memberService->getUserById($id);

		if (!$member) {
			throw new BadRequestException('Uživatel nenalezen');
		}

		if ((!$this->getUser()->isInRole('admin')) and ($member->id != $this->getUser()->getId())) {
			throw new ForbiddenRequestException('Nemáte právo editovat tohoto uživatele');
		}

		$form->setDefaults($member);

		//$this->template->title = $member->surname .' '. $member->name;
	}


	/** @allow(board) */
	public function actionAdd() {
		$form = $this['memberForm'];
		unset($form['password']);
		unset($form['confirm']);
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

	/** @allow(board) */
	public function sendLogginMail($member, $session) {
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../templates/Mail/newMember.latte');
		$template->session = $session;

		$mail = $this->getNewMail();

		$mail->addTo($member->mail, $member->surname . ' ' . $member->name);
		$mail->setSubject('[VZS Jablonec] Vítejte v informačním systému VZS Jablonec nad Nisou');
		$mail->setHTMLBody($template);

		$this->mailer->send($mail);
	}

	public function uniqueValidator($item) {
		$id = (int)$this->getParameter('id');
		return (bool)!($this->memberService->getTable()->select('id')->where($item->name, $item->value)->where('NOT id', $id)->fetch());
	}

	public function currentPassValidator($item) {
		$id = $this->getParameter('id');
		$user = $this->memberService->getUserById($id);
		return !Passwords::verify($item->value, $user->hash);
	}

	protected function createComponentMemberForm() {
		$form = new Form;

		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->addGroup('Osobní data');

		$form->addText('name', 'Jméno', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label');

		$form->addText('surname', 'Příjmení', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label');

		$form['date_born'] = new \DateInput('Datum narození');
		$form['date_born']->setRequired('Vyplňte datum narození')
			->setDefaultValue(new DateTime());

		$form->addText('zamestnani', 'Zaměstnání/Škola', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label');

		$form->addGroup('Přihlašovací údaje');

		$form->addPassword('password', 'Nové heslo', 20)
			->addCondition(Form::FILLED)
			->addRule(Form::PATTERN, 'Heslo musí mít alespoň 8 znaků, musí obsahovat číslice, malá a velká písmena', '^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,15}$')
			->addRule([$this, 'currentPassValidator'], 'Nesmíte použít svoje staré heslo');

		$form->addPassword('confirm', 'Potvrzení', 20)
			->setRequired(FALSE)
			->addRule(Form::EQUAL, 'Zadaná hesla se neschodují', $form['password'])
			->addCondition(Form::FILLED)
			->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaků', 8);

		$form->addCheckbox('sendMail', 'Poslat novému členu mail s přihlašovacími údaji')
			->setDefaultValue(TRUE);

		$form->addGroup('Kontakty');

		$form->addText('mail', 'E-mail', 30)
			->setType('email')
			->addRule([$this, 'uniqueValidator'], 'V databázi se již vyskytuje osoba se stejnou emailovou adresou')
			->setRequired('Vyplňte %label');

		$form->addText('telefon', 'Telefon', 30)
			->setRequired('Vyplňte %label')
			->addRule(Form::LENGTH, '%label musí mít %d znaků', 9);

		$form->addGroup('Adresa');

		$form->addText('ulice', 'Ulice', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte ulici');

		$form->addText('mesto', 'Město', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label');

		$form->addGroup(' ');

		$form->setCurrentGroup(null);

		$form->addUpload('image', 'Nový obrázek')
			->addCondition(Form::FILLED)
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 5 MB.', 5 * 1024 * 1024 /* v bytech */)
			->addRule(Form::IMAGE, 'Fotografie musí být ve formátu JPEG')
			->endCondition();

		$form->addTextArea('text', 'Poznámka', 30)
			->setAttribute('spellcheck', 'true');

		$form->addSubmit('ok', 'Ulož');
		$form->onSuccess[] = [$this, 'memberFormSubmitted'];

		return $form;
	}

	public function memberFormSubmitted(Form $form) {
		$id = (int)$this->getParameter('id');

		$values = $form->getValues();

		$sendMail = (isset($values->sendMail)) ? $values->sendMail : NULL;
		unset($values->sendMail);

		if ((isset($values->password))and($values->password)) {
			$values->hash = Passwords::hash($values->password);
		}

		unset($values->password);
		unset($values->confirm);

		$values->mail = Strings::lower($values->mail);

		if ((isset($form->image)) and ($form->image->isFilled()) and ($values->image->isOK())) {
			$image = $values->image->toImage();
			$image->resize(250, NULL, Image::SHRINK_ONLY);
			$image->save(WWW_DIR . '/img/portrets/' . $id . '.jpg', 80, Image::JPEG);
		}

		unset($values->image);

		if ((isset($values->text))and(!$values->text)) unset($values->text);

		$values->date_update = new DateTime();

		if ($id) {
			$this->memberService->getUserById($id)->update($values);
			$this->flashMessage('Osobní profil byl změněn');
			$this->redirect('view', $id);
		} else {
			$values->hash = '';
			$member = $this->memberService->addUser($values);

			if ($sendMail) {
				$session = $this->memberService->addPasswordSession($member->id, '24 HOUR');
				$this->sendLogginMail($member, $session);
			}

			$this->memberService->addUserLogin($member->id, new DateTime());

			$this->flashMessage('Byl přidán nový člen');
			$this->redirect('view', $member->id);
		}
	}
}