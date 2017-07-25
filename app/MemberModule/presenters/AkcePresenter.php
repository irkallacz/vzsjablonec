<?php
namespace App\MemberModule\Presenters;

use App\Model;
use App\MemberModule\Components;
use Joseki\Webloader\JsMinFilter;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Mail\IMailer;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Tracy\Debugger;

class AkcePresenter extends LayerPresenter{
	const FORUM_AKCE_ID = 2;
	const YEARS_START = 2007;
	const YEARS_STEP = 3;

	/** @var Model\AkceService @inject */
	public $akceService;

	/** @var Model\ForumService @inject */
	public $forumService;

	/** @var Model\AnketyService @inject */
	public $anketyService;

	/** @var Model\RatingService @inject */
	public $ratingService;

	/** @var Model\GalleryService @inject */
	public $galleryService;

	/** @var Model\MemberService @inject */
	public $memberService;

	/** @var IMailer @inject */
	public $mailer;

	/** @var ActiveRow */
	private $akce;

	/** @var array */
	private $orgList;

	/** @var array */
	private $memberList;

	public function actionSendAkceMail($id){
		$akce = $this->akceService->getAkceById($id);
		$this->sendConfirmMail($akce);
		$this->redirect('view',$id);
	}

	public function renderExport($year = null){
		$akce = $this->akceService->getAkce()->where('enable',1)->where('confirm',1)->order('date_start ASC');

		if ($year) $akce->where('YEAR(date_start) = ?', $year);

		$this->template->akceList = $akce;
	}

	public function renderDefault($year = NULL){
		$YEARS_END = intval(date('Y'));

		switch ($year){
			case NULL:
				$year = NAN;
				break;
			case 'INF':
				$year = INF;
				break;
			default:
				$year = intval($year);
		}

		if (is_int($year)){
			if ($year < self::YEARS_START) $this->redirect('this', self::YEARS_START);
			if ($year > $YEARS_END) $this->redirect('this', $YEARS_END);

			$akce[0] = [];
		}else{
			$akce[0] = $this->akceService->getAkceByFuture(TRUE);
		}

		$akce[1] = (is_nan($year)) ? [] : $this->akceService->getAkceByFuture(FALSE);

		$this->template->year = $year;

        if (is_int($year)) $akce[1]->where('YEAR(date_start)', $year); else $year = $YEARS_END;

        $count = 2*self::YEARS_STEP;
        $start = self::YEARS_START + (($year - self::YEARS_START) - self::YEARS_STEP);
        $end = $start + $count;

        if ($end > $YEARS_END) {
		    $start = $YEARS_END - $count;
		    $end = $YEARS_END;
        }

        if ($start < self::YEARS_START) {
            $start = self::YEARS_START;
            $end = self::YEARS_START + $count;
        }

        $this->template->years = range($start, $end);

		if (is_int($this->template->year)) $this->template->prev = (($year-1) >= self::YEARS_START) ? ($year-1) : NULL; else $this->template->prev = $YEARS_END;
		$this->template->next = (($year+1) <= $YEARS_END) ? ($year+1) : NULL;

		$this->template->akceAllList = $akce;
		$this->template->memberList = $this->akceService->getAkceByMemberId($this->getUser()->getId());
		$this->template->orgList = $this->akceService->getAkceByMemberId($this->getUser()->getId(),TRUE);
    }

	public function actionView($id){
		if (!$id) $this->redirect('default');

		$this->akce = $this->akceService->getAkceById($id);

		if ((!$this->akce)or(!$this->akce->enable)) {
		  $this->flashMessage('Akce nenalezena!','error');
		  $this->redirect('default');
		}

		$this->orgList = $this->akceService->getMembersByAkceId($id,TRUE)->fetchPairs('id','id');
		$this->memberList = $this->akceService->getMembersByAkceId($id,FALSE)->fetchPairs('id','id');
	}

	public function renderView($id){
		$this->template->akce = $this->akce;
		$this->template->title = $this->akce->name;
		$this->template->akceIsOld = $this->akce->date_start < date_create();
		$this->template->moreOneDay = $this->akce->date_start->format('Y-m-d') != $this->akce->date_end->format('Y-m-d');

		$this->template->prev = $this->akceService->getAkcePrev($id,$this->akce->date_start);
		$this->template->next = $this->akceService->getAkceNext($id,$this->akce->date_start);

		$this->template->orgList = $this->orgList;
		$this->template->memberList = $this->memberList;

		if ($this->akce->anketa_id){
		  $anketa = $this->anketyService->getAnketaById($this->akce->anketa_id);
		  $this->template->anketa = $anketa;
		}

		if ($this->akce->forum_topic_id){
		  $this->template->topic = $this->forumService->getTopicById($this->akce->forum_topic_id);
		}
	}

	public function actionEdit($id){
		if (!$id) $this->redirect('default');

		$this->akce = $this->akceService->getAkceById($id);

		if ((!$this->akce)or(!$this->akce->enable)) {
			$this->flashMessage('Akce nenalezena!','error');
			$this->redirect('default');
		}
	}

	public function renderEdit($id){
		if (!$id) $this->redirect('default');

		$this->template->akce = $this->akce;
		$this->template->title = $this->akce->name;

		$form = $this['akceForm'];
		if (!$form->isSubmitted()) {
			$orgList = $this->akce->related('akce_member')->where('organizator',TRUE)->fetchPairs('member_id','member_id');

			if ((!array_key_exists($this->getUser()->getId(),$orgList))and(!$this->getUser()->isInRole($this->name))) {
				$this->flashMessage('Nemáte právo tuto akci editovat','error');
				$this->redirect('Akce:view',$id);
			}

			$form['organizator']->getLabelPrototype()->class('hide');
			$form['organizator']->getControlPrototype()->class('hide');

			$form->setDefaults($this->akce);

			if ($this->akce->message) $form['addMessage']->setDefaultValue(TRUE);
			else $form['message']->setDefaultValue($this->akceService->getAkceMessageDefault());
		}
	}

	public function renderAdd(){
		$this->template->nova = TRUE;
		$this->setView('edit');
	}

	public function actionDelete($id){
		$orgList = $this->akceService->getMemberListByAkceId($id,TRUE);

		if ((!array_key_exists($this->getUser()->getId(),$orgList))and(!$this->getUser()->isInRole($this->name))) {
			$this->flashMessage('Nemáte právo tuto akci smazat','error');
			$this->redirect('Akce:view',$id);
		}

		$this->akceService->getAkceById($id)->update(['enable' => 0]);
		$this->flashMessage('Akce byla smazána');
		$this->redirect('Akce:default');
	}

	public function actionAllow($id,$allow){
		if (!$this->getUser()->isInRole('Confirm')) {
			$this->flashMessage('Nemáte právo tuto akci povolit ani zakázat','error');
		}else {
			$values = ['confirm' => $allow];

			if ($allow) {
			  $this->flashMessage('Akce byla povolena');
			  $values['date_update'] = new DateTime();
			}
			else $this->flashMessage('Akce byla zakázána');

			$this->akceService->getAkceById($id)->update($values);
		}
		$this->redirect('view',$id);
	}

	public function createComponentPostsList(){
		$topic = $this->forumService->getTopicById($this->akce->forum_topic_id);
		if ($this->forumService->checkTopic($topic)){
			$isLocked = $topic->locked;

			$posts = $this->forumService->getPostsByTopicId($this->akce->forum_topic_id);
			$posts->order('row_number DESC');
			$posts->limit(5, 0);

			return new Components\PostsListControl($posts,$isLocked);
		}
	}

    protected function createComponentAlbum(){
        return new Components\AlbumPreviewControl($this->galleryService);
    }


	protected function createComponentRating(){
    	$userId = $this->getUser()->getId();
    	$isOrg = in_array($userId,$this->orgList);
    	$canComment = (in_array($userId,$this->memberList)or($isOrg));
		return new Components\RatingControl($this->akce->id,$this->ratingService,$userId,$isOrg,$canComment);
	}

//    public function renderVcal($id = 0,$future = false){
//	  if ($id) {
//	  $akce = $this->akceService->getAkceById($id);
//	  $this->template->akce = array($akce);
//	}
//	else $this->template->akce = $this->akceService->getAkceByFuture($future)->where('confirm',1);
//
//	  $httpResponse = $this->context->getByType('Nette\Http\Response');
//
//	  if ($id) $slug = Strings::truncate(Strings::webalize($akce->name),20,''); else $slug = 'akce';
//
//	  $httpResponse->setHeader('Content-Disposition','attachment; filename="'.$slug.'.ics"');
//
//	}

	public function sendConfirmMail($akce){
		$template = $this->createTemplate();
		$template->setFile(__DIR__ . '/../templates/Mail/akceConfirm.latte');
		$template->akce = $akce;

		$mail = $this->getNewMail();

		$member = $akce->member;

		$mail->addReplyTo($member->mail,$member->surname.' '.$member->name);

		foreach($this->memberService->getMembersByRole('Confirm') as $member)
		  $mail->addTo($member->mail,$member->surname.' '.$member->name);

		$mail->setSubject('[VZS Jablonec] Nová akce čeká na schválení');
		$mail->setHTMLBody($template);
		$this->mailer->send($mail);
	}

	protected function createComponentMembers(){
		return new Components\MembersListControl($this->akceService,$this->akce);
	}

	protected function createComponentOrganizators(){
		return new Components\MembersListControl($this->akceService,$this->akce,TRUE);
	}

	public function createComponentTexylaJs(){
		$files = new \WebLoader\FileCollection(WWW_DIR . '/texyla/js');
		$files->addFiles(['texyla.js','selection.js','texy.js','buttons.js','cs.js','dom.js','view.js','window.js']);
		$files->addFiles(['../plugins/table/table.js']);
		$files->addFiles(['../plugins/color/color.js']);
		$files->addFiles(['../plugins/symbol/symbol.js']);
		$files->addFiles(['../plugins/textTransform/textTransform.js']);
		$files->addFiles([WWW_DIR . '/js/texyla_akce.js']);
//		$files->addFiles([WWW_DIR . '/js/texyla_public.js']);

		$compiler = \WebLoader\Compiler::createJsCompiler($files, WWW_DIR . '/texyla/temp');
		$compiler->addFileFilter(new JsMinFilter());

		return new \WebLoader\Nette\JavaScriptLoader($compiler, $this->template->basePath . '/texyla/temp');
	}

	protected function createComponentAkceForm(){
		$datum = new Datetime();
		$form = new Form;

		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->addText('name', 'Název', 30)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label akce');

		$form->addText('place', 'Místo', 50)
			->setAttribute('spellcheck', 'true')
			->setRequired('Vyplňte %label akce');

		$form['date_start'] = new \DateTimeInput('Začátek');
		$form['date_start']->setRequired(TRUE);
		$form['date_start']->setDefaultValue($datum);

		$form['date_end'] = new \DateTimeInput('Konec');
		$form['date_end']->setRequired(TRUE);
		$form['date_end']->setDefaultValue($datum)
			->addRule(function ($item, $arg) {
				return $item->value >= $arg;
			}, 'Datum konce akce nesmí být menší než datum začátku akce', $form['date_start']);

		$form->addCheckbox('login_mem', 'Povoleno přihlašování účastníků')
			->setDefaultValue(TRUE)
			->setAttribute('onclick','doTheTrick()');

		$form->addCheckbox('login_org', 'Povoleno přihlašování organizátorů')
			->setDefaultValue(FALSE)
			->setAttribute('onclick','doTheTrick()');

		$form['date_deatline'] = new \DateTimeInput('Přihlášení do');
		$form['date_deatline']->setRequired(FALSE);
		$form['date_deatline']->setDefaultValue($datum)
			->addRule(function ($item, $arg) {
			  return $item->value <= $arg;
			  }, 'Datum přihlášení musí být menší než datum začátku akce', $form['date_start'])
			->addConditionOn($form['login_mem'],Form::EQUAL,TRUE)
			->addRule(Form::FILLED,'Vyplňte datum konce přihlašování');

		$form['date_deatline']
			->addConditionOn($form['login_org'],Form::EQUAL,TRUE)
				->addRule(Form::FILLED,'Vyplňte datum konce přihlašování');

		$form->addSelect('forum_topic_id','Fórum',
			$this->forumService->getTopicsByForumId(self::FORUM_AKCE_ID)->fetchPairs('id','title')
		)->setPrompt('');

		$form->addSelect('anketa_id','Anketa',
			$this->anketyService->getAnkety()->fetchPairs('id','title')
		)->setPrompt('');

		$form->addSelect('album_id','Album',
			$this->galleryService->getAlbums()->order('date_add DESC')->fetchPairs('id','name')
		)->setPrompt('');

		$form->addSelect('akce_for_id', 'Určeno',
			$this->akceService->getAkceForInArray()
		)->setDefaultValue(1);

		$form->addCheckbox('visible', 'Viditelná veřejnosti')
			->setDefaultValue(TRUE);

		$form->addSelect('member_id', 'Zodpovědná osoba',
				$this->akceService->getMembers()->fetchPairs('id','jmeno'))
			->setDefaultValue($this->getUser()->getId());

		$form->addSelect('organizator', 'Organizátor',
				$this->akceService->getMembers()->fetchPairs('id','jmeno'))
			->setDefaultValue($this->getUser()->getId())
			->setPrompt('není')
			->addConditionOn($form['login_org'],Form::EQUAL, FALSE)
				->addRule(FORM::FILLED,'Musíte vybrat organizátora');

		$form->addUpload('file','Soubor')
			->setRequired(FALSE)
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 10 MB.', 10 * 1024 * 1024);

		$form->addText('price', 'Cena', 7)
			->setType('number')
			->setOption('description', 'Kč')
			->addCondition(Form::FILLED)
			->addRule(Form::INTEGER, '%label musí být číslo');

		$form->addText('perex', 'Stručný popis', 50)
			->setAttribute('spellcheck', 'true')
			->setAttribute('class','perex');

		$form->addTextArea('description', 'Podrobný popis')
			->setAttribute('spellcheck', 'true')
			->setAttribute('class','texyla')
			->setRequired('Vyplňte %label akce');

		$text = $this->akceService->getAkceMessageDefault();

		$form->addCheckbox('addMessage','Připojit zprávu z akce')
			->setDefaultValue(FALSE)
			->addCondition(Form::EQUAL, TRUE)
			->toggle('frm-akceForm-message');

		$form->addTextArea('message','Zpráva z akce')
			->setAttribute('spellcheck', 'true')
			->setDefaultValue($text)
			->setAttribute('class','texyla');

		$form->addSubmit('save', 'Ulož')->setAttribute('class', 'default');
			$form->onSuccess[] = [$this, 'akceFormSubmitted'];

		return $form;
	}

	public function akceFormSubmitted(Form $form){
		$id = (int) $this->getParameter('id');

		$data = $form->getValues();
		$datum = new Datetime();

		$data->name = ucfirst($data->name);

		$data->date_update = $datum;

		if (!$data->price) unset($data->price);

		$org = $data->organizator;
		unset($data->organizator);

		if (!$data->addMessage) unset($data->message);
		unset($data->addMessage);

		if (($form['file']->isFilled()) and ($data->file->isOK())){
		  $data->file->move(WWW_DIR.'/doc/akce/'.$data->file->getSanitizedName());
		  $data->file = $data->file->getSanitizedName();
		}else unset($data->file);

		if ($id) {
		  $this->akceService->getAkceById($id)->update($data);
		  $this->flashMessage('Akce byla změněna');
		}else {
		  $data->date_add = $datum;

		  $row = $this->akceService->addAkce($data);

		  if ($org) $this->akceService->addMemberToAction($org,$row->id,TRUE);

		  $this->sendConfirmMail($row);

		  $this->flashMessage('Akce byla přidána');

		  $id = $row->id;
		}

		$this->redirect('Akce:view',$id);
	  }

	protected function createComponentUploadBillForm(){
		$form = new Form;

		$form->addUpload('file');
		  // ->addRule(Form::MIME_TYPE,'Uploadovaný soubor můsí být ve formátu .xls',
		  //   'application/vnd.ms-office,application/vnd.ms-excel,application/msexcel,application/x-msexcel,application/x-ms-excel,application/vnd.ms-excel,application/x-excel,application/x-dos_ms_excel,application/xls'
		  // );

		$form->addSubmit('ok', '')
		  ->setAttribute('class','myfont');

		$form->onSuccess[] = [$this, 'uploadBillFormSubmitted'];

		return $form;
	}

	public function uploadBillFormSubmitted(Form $form){
		$id = (int) $this->getParameter('id');
		$data = $form->getValues();

		$akce = $this->akceService->getAkceById($id);

		if (($form['file']->isFilled()) and ($data->file->isOK())){
			$data->file->move(WWW_DIR.'/doc/vyuctovani/'.$id.'-'.Strings::webalize($akce->name).'.xls');

			$akce->update(['bill' => 1]);
			$this->flashMessage('Vyúčtování nahráno');
			$this->redirect('Akce:view',$id);
		}
	}
}
