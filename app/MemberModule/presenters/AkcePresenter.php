<?php
namespace App\MemberModule\Presenters;

use App\Model;
use App\MemberModule\Components;
use App\Template\LatteFilters;
use Caxy\HtmlDiff\HtmlDiff;
use Joseki\Webloader\JsMinFilter;
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
use Nette\Utils\Strings;
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

	/** @var Model\GalleryService @inject */
	public $galleryService;

	/** @var Model\UserService @inject */
	public $userService;

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
		$this->template->memberList = $this->akceService->getAkceByMemberId($this->user->id);
		$this->template->orgList = $this->akceService->getAkceByMemberId($this->user->id, TRUE);
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
	 * @param int $id
	 * @param int|NULL $second
	 */
	public function actionCompare(int $id, int $second = NULL){
		$revision = $this->akceService->getRevisionById($id);
		$this->akce = $revision->akce;

		$html1 = LatteFilters::texy($revision->text);

		if ($second) {
			$revision = $this->akceService->getRevisionById($second);
			$html2 = LatteFilters::texy($revision->text);
		} else {
			$text = $this->createRevision($this->akce);
			$html2 = LatteFilters::texy($text);
		}

		$htmlDiff = new HtmlDiff($html1, $html2);
		$this->template->html = $htmlDiff->build();
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

		return $this->akceService->addRevision($akce->id, $akce->date_update, $text);
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
			$member = Arrays::pick($akce,'user_id');

			try{
				$form['user_id']->setDefaultValue($member);
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
	 */
	public function renderAdd() {
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

	protected function createComponentRevisionForm() {
		$id = $this->getParameter('id');
		$second = $this->getParameter('second');

		$revisions = $this->akceService->getRevisionsByAkceId($this->akce->id);
		$items = [];
		foreach ($revisions as $revision){
			$items[$revision->id] = $revision->date_add->format('d.m.Y H:i');
		}

		$form = new Form();
		$form->addSelect('first', 'Porovnat', $items)
			->setDefaultValue($id);

		$form->addSelect('second', 'vs.', $items)
			->setDefaultValue($second)
			->setPrompt('Aktuální');

		$form->addSubmit('ok', 'OK');

		$form->onSubmit[] = function(Form $form) {
			$values = $form->getValues();
			$this->redirect('compare', ['id' => $values->first, 'id2' => $values->second]);
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
		return new Components\AlbumPreviewControl($this->galleryService);
	}

	/**
	 * @return Components\AnketaControl
	 */
	public function createComponentAnketa() {
		return new Components\AnketaControl($this->akce->anketa_id, $this->anketyService);
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
		$files = new WebLoader\FileCollection(WWW_DIR . '/texyla/js');
		$files->addFiles(['texyla.js', 'selection.js', 'texy.js', 'buttons.js', 'cs.js', 'dom.js', 'view.js', 'window.js']);
		$files->addFiles(['../plugins/table/table.js']);
		$files->addFiles(['../plugins/color/color.js']);
		$files->addFiles(['../plugins/symbol/symbol.js']);
		$files->addFiles(['../plugins/textTransform/textTransform.js']);
		$files->addFiles([WWW_DIR . '/js/texyla_akce.js']);

		$compiler = WebLoader\Compiler::createJsCompiler($files, WWW_DIR . '/texyla/temp');
		$compiler->addFileFilter(new JsMinFilter());

		return new WebLoader\Nette\JavaScriptLoader($compiler, $this->template->basePath . '/texyla/temp');
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
			->setDefaultValue($datum);

		/** @var \DateTimeInput $dateTimeInput*/
		$dateTimeInput = $form['date_end'] = new \DateTimeInput('Konec');
		$dateTimeInput->setRequired(TRUE)
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

		$form->addSelect('user_id', 'Zodpovědná osoba')
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
		$id = (int) $this->getParameter('id');

		$datum = new Datetime();

		$values->date_update = $datum;

		/** @var bool $org*/
		$org = $values->organizator;
		unset($values->organizator);

		if (!$values->addMessage) unset($values->message);
		unset($values->addMessage);

		if ((isset($values->file)) and ($values->file->isOK())) {
			$values->file->move(WWW_DIR . '/doc/akce/' . $values->file->getSanitizedName());
			$values->file = $values->file->getSanitizedName();
		} else unset($values->file);

		if ($id) {
			$akce = $this->akceService->getAkceById($id);

			if ($akce->date_start > $datum) {
				$revision = $this->akceService->getLastRevisionByAkceId($id);

				if ((!$revision)or($datum > $revision->date_add->modifyClone('+ 20 minutes'))){
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

	/**
	 * @allow(member)
	 */
	protected function createComponentUploadBillForm() {
		$form = new Form;
		$form->addUpload('file');
		$form->addSubmit('ok');
		$form->onSuccess[] = [$this, 'uploadBillFormSubmitted'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @allow(member)
	 * @throws AbortException
	 */
	public function uploadBillFormSubmitted(Form $form) {
		$id = (int) $this->getParameter('id');
		$data = $form->getValues();

		$akce = $this->akceService->getAkceById($id);

		if (($form['file']->isFilled()) and ($data->file->isOK())) {
			$filename = $id . '-' . Strings::webalize($akce->name) . '.xls';
			$data->file->move(WWW_DIR . '/doc/vyuctovani/' . $filename);

			$akce->update(['bill' => $filename]);
			$this->flashMessage('Vyúčtování nahráno');
			$this->redirect('Akce:view', $id);
		}
	}
}
