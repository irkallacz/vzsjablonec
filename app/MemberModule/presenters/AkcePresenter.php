<?php
namespace App\MemberModule\Presenters;

use App\Model;
use App\MemberModule\Components;
use App\PhotoModule\ImageService;
use App\Template\LatteFilters;
use Caxy\HtmlDiff\HtmlDiff;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\InvalidArgumentException;
use Nette\Mail\IMailer;
use Nette\Utils\ArrayHash;
use Nette\Utils\Arrays;
use Nette\Utils\DateTime;
use WebLoader;
use Tracy\Debugger;

class AkcePresenter extends LayerPresenter {
	const FORUM_AKCE_ID = 2;

	/** @var Model\AkceService @inject */
	public $akceService;

	/** @var Model\ForumService @inject */
	public $forumService;

	/** @var Model\AnketyService @inject */
	public $anketyService;

	/** @var Model\MessageService @inject */
	public $messageService;

	/** @var Model\RatingService @inject */
	public $ratingService;

	/** @var Model\BillingService @inject */
	public $billingService;

	/** @var Model\GalleryService @inject */
	public $galleryService;

	/** @var ImageService @inject */
	public $imageService;

	/** @var Model\UserService @inject */
	public $userService;

	/** @var Components\TexylaJsFactory @inject */
	public $texylaJsFactory;

	/** @var IMailer @inject */
	public $mailer;

	/** @var ActiveRow */
	private $akce;

	/** @var array */
	private $orgList;

	/** @var array */
	private $memberList;

	public function renderDefault() {
		$year = $this['yp']->year;

		if (is_int($year)) {
			$akce[0] = [];
		} else {
			$akce[0] = $this->akceService->getAkceByFuture(TRUE);
		}

		$akce[1] = (is_nan($year)) ? [] : $this->akceService->getAkceByFuture(FALSE);

		$this->template->year = $year;

		if (is_int($year)) $akce[1]->where('YEAR(date_start)', $year);

		$this->template->akceAllList = $akce;
		$this->template->memberList = $this->akceService->getAkceListByMemberId($this->user->id);
		$this->template->orgList = $this->akceService->getAkceListByMemberId($this->user->id, TRUE);
	}

	public function createComponentYp() {
		return new Components\YearPaginator(2007);
	}

	/**
	 * @param int $id
	 * @throws BadRequestException
	 * @throws AbortException
	 */
	public function actionView(int $id) {
		if (!$id) $this->redirect('default');

		$this->akce = $this->akceService->getAkceById($id);

		if ((!$this->akce) or (!$this->akce->enable)) {
			throw new BadRequestException('Akce nenalezena!');
		}

		$this->orgList = $this->userService->getUsersByAkceId($id, TRUE)->fetchPairs('id', 'id');
		$this->memberList = $this->userService->getUsersByAkceId($id, FALSE)->fetchPairs('id', 'id');
	}

	/**
	 * @param int $id
	 */
	public function renderView(int $id) {
		$this->template->akce = $this->akce;
		$this->template->title = $this->akce->name;
		$this->template->akceIsOld = $this->akce->date_start < date_create();
		$this->template->moreOneDay = $this->akce->date_start->format('Y-m-d') != $this->akce->date_end->format('Y-m-d');

		$this->template->prev = $this->akceService->getAkcePrev($id, $this->akce->date_start);
		$this->template->next = $this->akceService->getAkceNext($id, $this->akce->date_start);

		$this->template->revision = $this->akceService->getLastRevisionByAkceId($id);

		$this->template->orgList = $this->orgList;
		$this->template->memberList = $this->memberList;

		if ($this->akce->anketa_id) {
			$anketa = $this->anketyService->getAnketaById($this->akce->anketa_id);
			$this->template->anketa = $anketa;
		}

		if ($this->akce->forum_topic_id) {
			$this->template->topic = $this->forumService->getTopicById($this->akce->forum_topic_id);
		}
	}

	/**
	 * @param int $what
	 * @param int|NULL $with
	 */
	public function actionCompare(int $what, int $with = NULL){
		$revision = $this->akceService->getRevisionById($what);
		$this->akce = $revision->akce;

		$members = $this->akceService->getMembersByAkceId($this->akce->id)
			->where('date_add < ?', $revision->date_saved)
			->order('date_add')
			->fetchPairs('user_id', 'organizator');

		$text = $revision->text;
		$text.= "\n\n**Účastníci**\n\n";

		foreach ($members as $memberId => $organizator) {
			if (!$organizator) {
				$member = $this->userService->getUserById($memberId, Model\UserService::DELETED_LEVEL);
				$text .= '-' . Model\UserService::getFullName($member) . "\n";
			}
		}

		$first = LatteFilters::texy($text);

		$members = $this->akceService->getMembersByAkceId($this->akce->id)
			->order('date_add');

		if ($with) {
			$revision = $this->akceService->getRevisionById($with);
			$text = $revision->text;
			$member->where('date_add < ?', $revision->date_saved);
		} else {
			$text = $this->createRevision($this->akce);
		}

		$members = $members->fetchPairs('user_id', 'organizator');

		$text.= "\n\n**Účastníci**\n\n";

		foreach ($members as $memberId => $organizator) {
			if (!$organizator) {
				$member = $this->userService->getUserById($memberId, Model\UserService::DELETED_LEVEL);
				$text .= '-' . Model\UserService::getFullName($member) . "\n";
			}
		}

		$second = LatteFilters::texy($text);

		$htmlDiff = new HtmlDiff($first, $second);
		$this->template->html = $htmlDiff->build();

		$this->template->id = $this->akce->id;
	}

	/**
	 * @param IRow|ActiveRow $akce
	 * @return string
	 */
	private function createRevision(IRow $akce) {
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../templates/Akce.revision.latte');
		$template->akce = $akce;

		return (string) $template;
	}

	/**
	 * @param IRow|ActiveRow $akce
	 * @return bool|int|ActiveRow
	 */
	private function addRevision(IRow $akce) {
		$text = $this->createRevision($akce);

		return $this->akceService->addRevision($akce->id, $akce->modified_by, $akce->date_update, $text);
	}


	/**
	 * @param int $id
	 * @throws AbortException
	 */
	public function actionSendAkceMail(int $id) {
		$akce = $this->akceService->getAkceById($id);
		$this->addConfirmMail($akce);
		$this->redirect('view', $id);
	}

	/**
	 * @param string|NULL $year
	 */
	public function renderExport($year = NULL) {
		$akce = $this->akceService->getAkce()->where('enable', 1)->where('confirm', 1)->order('date_start ASC');

		if ($year) $akce->where('YEAR(date_start) = ?', $year);

		$this->template->akceList = $akce;
	}

	/**
	 * @param int $id
	 * @allow(member)
	 * @throws BadRequestException
	 * @throws AbortException
	 */
	public function actionEdit(int $id) {
		if (!$id) $this->redirect('default');

		$this->akce = $this->akceService->getAkceById($id);

		if ((!$this->akce) or (!$this->akce->enable)) {
			throw new BadRequestException('Akce nenalezena!');
		}
	}

	/**
	 * @param int $id
	 * @allow(member)
	 * @throws ForbiddenRequestException
	 * @throws AbortException
	 */
	public function renderEdit(int $id) {
		if (!$id) $this->redirect('default');

		$this->template->akce = $this->akce;
		$this->template->title = $this->akce->name;

		/**@var Form $form */
		$form = $this['akceForm'];

		if (!$form->isSubmitted()) {
			$orgList = $this->akce->related('akce_member')->where('organizator', TRUE)->fetchPairs('user_id', 'user_id');

			if ((!array_key_exists($this->user->id, $orgList)) and (!$this->user->isInRole('admin'))) {
				throw new ForbiddenRequestException('Nemáte právo tuto akci editovat');
			}

			$form['organizator']->getLabelPrototype()->class('hide');
			$form['organizator']->getControlPrototype()->class('hide');

			$akce = $this->akce->toArray();
			$member = Arrays::pick($akce,'created_by');

			try{
				$form['created_by']->setDefaultValue($member);
			}catch (InvalidArgumentException $e){
				$this->flashMessage('Některé již neplatné hodnoty byly vynechány', 'error');
			}

			$form->setDefaults($akce);

			if ($this->akce->message) $form['addMessage']->setDefaultValue(TRUE);
			else $form['message']->setDefaultValue($this->akceService->getAkceMessageDefault());
		}
	}

	/**
	 * @allow(member)
	 * @param int|null $id
	 */
	public function renderAdd(int $id = NULL) {

		if ($id) {
			$akce = $this->akceService->getAkceById($id);
			$akce = $akce->toArray();
			unset($akce['created_by']);

			/**@var Form $form */
			$form = $this['akceForm'];
			$form->setDefaults($akce);
		}

		$this->template->nova = TRUE;
		$this->setView('edit');
	}

	/**
	 * @param int $id
	 * @allow(member)
	 * @throws ForbiddenRequestException
	 * @throws AbortException
	 */
	public function actionDelete(int $id) {
		$orgList = $this->akceService->getMemberListByAkceId($id, TRUE);

		if ((!array_key_exists($this->user->id, $orgList)) and (!$this->user->isInRole('admin'))) {
			throw new ForbiddenRequestException('Nemáte právo tuto akci smazat');
		}

		$akce = $this->akceService->getAkceById($id);
		$akce->update(['enable' => 0]);

		$this->flashMessage('Akce byla smazána');
		$this->redirect('Akce:default');
	}

	/**
	 * @param $id
	 * @param bool $allow
	 * @allow(confirm)
	 * @throws AbortException
	 */
	public function actionAllow(int $id, bool $allow) {
		$values = ['confirm' => $allow];

		if ($allow) {
			$this->flashMessage('Akce byla povolena');
			$values['date_update'] = new DateTime();
		} else $this->flashMessage('Akce byla zakázána');

		$akce = $this->akceService->getAkceById($id);
		$akce->update($values);
		$this->redirect('view', $id);
	}

	/**
	 * @return Form
	 */
	protected function createComponentRevisionForm() {
		$first = $this->getParameter('what');
		$second = $this->getParameter('with');

		$revisions = $this->akceService->getRevisionsByAkceId($this->akce->id);
		$items = [];
		foreach ($revisions as $revision){
			$items[$revision->id] = $revision->date_saved->format('d.m.Y H:i');
		}

		$form = new Form();
		$form->addSelect('first', 'Porovnat', $items)
			->setDefaultValue($first);

		$form->addSelect('second', 'vs.', $items)
			->setPrompt('Aktuální')
			->setDefaultValue($second);

		$form->addSubmit('ok', 'OK');

		$form->onSubmit[] = function(Form $form) {
			$values = $form->getValues();
			$this->redirect('compare', ['what' => $values->first, 'with' => $values->second]);
		};

		$renderer = $form->getRenderer();
		$renderer->wrappers['controls']['container'] = NULL;
		$renderer->wrappers['pair']['container'] = NULL;
		$renderer->wrappers['label']['container'] = NULL;
		$renderer->wrappers['control']['container'] = NULL;

		return $form;
	}

	/**
	 * @return Components\PostsListControl
	 */
	public function createComponentPostsList() {
		$topic = $this->forumService->getTopicById($this->akce->forum_topic_id);
		if ($this->forumService->checkTopic($topic)) {
			$isLocked = $topic->locked;

			$posts = $this->forumService->getPostsByTopicId($this->akce->forum_topic_id);
			$posts->order('row_number DESC');
			$posts->limit(5, 0);

			return new Components\PostsListControl($posts, $isLocked);
		}
	}

	/**
	 * @return Components\AlbumPreviewControl
	 */
	protected function createComponentAlbum() {
		return new Components\AlbumPreviewControl($this->galleryService, $this->imageService);
	}

	/**
	 * @return Components\AnketaControl
	 * @throws BadRequestException
	 */
	public function createComponentAnketa() {
		return new Components\AnketaControl($this->akce->anketa_id, $this->anketyService, $this->user->id);
	}

	/**
	 * @return Components\RatingControl
	 */
	protected function createComponentRating() {
		$userId = $this->user->id;
		$isOrg = in_array($userId, $this->orgList);
		$canComment = ($this->getUser()->isInRole('member') and (in_array($userId, $this->memberList) or ($isOrg)));
		return new Components\RatingControl($this->akce->id, $this->ratingService, $userId, $isOrg, $canComment);
	}


	/**
	 * @return Components\BillingControl
	 */
	protected function createComponentBilling() {
		$userId = $this->user->id;
		$canEdit = (($this->user->isInRole('admin')) or ((in_array($userId, $this->orgList)) and ($this->user->isInRole('member'))));
		return new Components\BillingControl($this->billingService, $this->akce, $userId, $canEdit);
	}

	/**
	 * @param IRow|ActiveRow $akce
	 * @allow(member)
	 */
	public function addConfirmMail(IRow $akce) {
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../../presenters/templates/Mail/akceConfirm.latte');
		$template->akce = $akce;

		$message = new Model\MessageService\Message(Model\MessageService\Message::EVENT_CONFIRM_TYPE);
		$message->setSubject('Nová akce čeká na schválení');
		$message->setText($template);
		$message->setAuthor($this->user->id);
		$message->setRecipients($this->userService->getUsersByRight('confirm'));
		$message->setParameters(['akce_id' => $akce->id]);

		$this->messageService->addMessage($message);
	}

	/**
	 * @return Components\SignEventControl
	 */
	protected function createComponentSignEvent() {
		return new Components\SignEventControl($this->akceService, $this->userService, $this->akce);
	}

	/**
	 * @allow(member)
	 * @return WebLoader\Nette\JavaScriptLoader
	 * @throws WebLoader\InvalidArgumentException
	 */
	public function createComponentTexylaJs() {
		return $this->texylaJsFactory->create('texyla_akce', $this->template->basePath, ['table', 'color', 'symbol', 'textTransform']);
	}

	/**
	 * @allow(member)
	 * @return Form
	 */
	protected function createComponentAkceForm() {
		$datum = new Datetime();
		$datum->setTime($datum->format('G'), 0);
		$form = new Form;

		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->addText('name', 'Název', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label akce')
			->addFilter(['\Nette\Utils\Strings', 'firstUpper']);

		$form->addText('place', 'Místo', 50)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label akce');

		/** @var \DateTimeInput $dateTimeInput*/
		$dateTimeInput = $form['date_start'] = new \DateTimeInput('Začátek');
		$dateTimeInput->setRequired(TRUE)
			->setHtmlId('event-start')
			->setDefaultValue($datum);

		/** @var \DateTimeInput $dateTimeInput*/
		$dateTimeInput = $form['date_end'] = new \DateTimeInput('Konec');
		$dateTimeInput->setRequired(TRUE)
			->setHtmlId('event-end')
			->setDefaultValue($datum)
			->addRule(function ($item, $arg) {
				return $item->value >= $arg;
			}, 'Datum konce akce nesmí být menší než datum začátku akce', $form['date_start']);

		$form->addCheckbox('login_mem', 'Povoleno přihlašování účastníků')
			->setDefaultValue(TRUE)
			->setAttribute('onclick', 'doTheTrick()');

		$form->addCheckbox('login_org', 'Povoleno přihlašování organizátorů')
			->setDefaultValue(FALSE)
			->setAttribute('onclick', 'doTheTrick()');

		/** @var \DateTimeInput $dateTimeInput*/
		$dateTimeInput = $form['date_deatline'] = new \DateTimeInput('Přihlášení do');
		$dateTimeInput->setRequired(FALSE)
			->setHtmlId('log-end')
			->setDefaultValue($datum)
			->addRule(function ($item, $arg) {
				return $item->value <= $arg;
			}, 'Datum přihlášení musí být menší než datum začátku akce', $form['date_start'])
			->addConditionOn($form['login_mem'], Form::EQUAL, TRUE)
			->addRule(Form::FILLED, 'Vyplňte datum konce přihlašování');

		$dateTimeInput
			->addConditionOn($form['login_org'], Form::EQUAL, TRUE)
			->addRule(Form::FILLED, 'Vyplňte datum konce přihlašování');

		$form->addSelect('forum_topic_id', 'Fórum')
			->setItems($this->forumService->getTopicsByForumId(self::FORUM_AKCE_ID)->fetchPairs('id', 'title'))
			->setPrompt('');

		$form->addSelect('anketa_id', 'Anketa')
			->setItems($this->anketyService->getAnkety()->fetchPairs('id', 'title'))
			->setPrompt('');

		$form->addSelect('album_id', 'Album')
			->setItems($this->galleryService->getAlbums()->order('date_add DESC')->fetchPairs('id', 'name'))
			->setPrompt('');

		$form->addSelect('akce_for_id', 'Určeno')
			->setItems($this->akceService->getAkceForInArray())
			->setDefaultValue(1);

		$form->addCheckbox('visible', 'Viditelná veřejnosti')
			->setDefaultValue(TRUE);

		$form->addSelect('created_by', 'Autor')
			->setItems($this->userService->getUsersArray(Model\UserService::MEMBER_LEVEL))
			->setDefaultValue($this->user->id);

		$form->addSelect('organizator', 'Organizátor')
			->setItems($this->userService->getUsersArray(Model\UserService::MEMBER_LEVEL))
			->setDefaultValue($this->user->id)
			->setPrompt('není')
			->addConditionOn($form['login_org'], Form::EQUAL, FALSE)
				->addRule(FORM::FILLED, 'Musíte vybrat organizátora');

		$form->addUpload('file', 'Soubor')
			->setRequired(FALSE)
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 10 MB.', 10 * 1024 * 1024);

		$form->addText('price', 'Cena', 7)
			->setType('number')
			->setNullable()
			->setOption('description', 'Kč')
			->addCondition(Form::FILLED)
				->addRule(Form::INTEGER, '%label musí být číslo');

		$form->addText('perex', 'Stručný popis', 50)
			->setAttribute('spellcheck', 'true')
			->setAttribute('class', 'perex')
			->setRequired('Vyplňte %label akce')
			->addFilter(['\Nette\Utils\Strings', 'firstUpper']);

		$form->addTextArea('description', 'Podrobný popis')
			->setAttribute('spellcheck', 'true')
			->setAttribute('class', 'texyla')
			->setRequired('Vyplňte %label akce')
			->addFilter(['\Nette\Utils\Strings', 'firstUpper']);

		$text = $this->akceService->getAkceMessageDefault();

		$form->addCheckbox('addMessage', 'Připojit zprávu z akce')
			->setDefaultValue(FALSE)
			->addCondition(Form::EQUAL, TRUE)
				->toggle('frm-akceForm-message');

		$form->addTextArea('message', 'Zpráva z akce')
			->setNullable()
			->setAttribute('spellcheck', 'true')
			->setAttribute('class', 'texyla')
			->setDefaultValue($text)
			->addFilter(['\Nette\Utils\Strings', 'firstUpper'])
			->setRequired(FALSE);

		$form->addSubmit('save', 'Ulož')
			->setAttribute('class', 'default');

		$form->onSuccess[] = [$this, 'akceFormSubmitted'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @allow(member)
	 * @throws AbortException
	 */
	public function akceFormSubmitted(Form $form, ArrayHash $values) {
		$datum = new Datetime();

		$values->date_update = $datum;
		$values->modified_by = $this->user->id;

		/** @var bool $org*/
		$org = $values->organizator;
		unset($values->organizator);

		if (!$values->addMessage) unset($values->message);
		unset($values->addMessage);

		if ((isset($values->file)) and ($values->file->isOK())) {
			$values->file->move(WWW_DIR . '/doc/akce/' . $values->file->getSanitizedName());
			$values->file = $values->file->getSanitizedName();
		} else unset($values->file);

		if ($this->getAction() == 'edit') {
			$id = (int) $this->getParameter('id');

			$akce = $this->akceService->getAkceById($id);

			if ($akce->date_start > $datum) {
				$revision = $this->akceService->getLastRevisionByAkceId($id);

				if ((!$revision)or($datum > $revision->date_add->modifyClone('+ 20 minutes'))or($revision->created_by !== $this->user->id)){
					$this->addRevision($akce);
				}
			}

			$akce->update($values);
			$this->flashMessage('Akce byla změněna');
		} else {
			$values->date_add = $datum;

			if ($this->user->isInRole('confirm')) $values->confirm = TRUE;

			$akce = $this->akceService->addAkce($values);

			if ($org) $this->akceService->addMemberToAction($org, $akce->id, TRUE, $this->user->id);

			$this->flashMessage('Akce byla přidána');

			if (!$akce->confirm) {
				$this->addConfirmMail($akce);
				$next = $this->messageService->getNextSendTime();
				$this->flashMessage('Email pro schválení akce bude odeslán '.LatteFilters::timeAgoInWords($next));
			}

			$id = $akce->id;
		}

		$this->redirect('Akce:view', $id);
	}
}
