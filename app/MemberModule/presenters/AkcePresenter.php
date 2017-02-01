<?php
namespace MemberModule;

use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\Diagnostics\Debugger;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use Nette\DateTime;

class AkcePresenter extends LayerPresenter{
	const FORUM_AKCE_ID = 2;

	/** @var \AkceService @inject */
	public $akceService;

	/** @var \ForumService @inject */
	public $forumService;

	/** @var \AnketyService @inject */
	public $anketyService;

	/** @var \RatingService @inject */
	public $ratingService;

	/** @var \GalleryService @inject */
	public $galleryService;

	/** @var \MemberService @inject */
	public $memberService;

	/** @var \Nette\Mail\IMailer @inject */
	public $mailer;

	/** @var \Nette\Database\Table\ActiveRow */
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

	public function renderDefault($all = false){
		$this->template->all = $all;

		$akce[] = $this->akceService->getAkceByFuture(TRUE);
		$akce[] = $this->akceService->getAkceByFuture();

		if (!$all) $akce[1]->where('YEAR(date_start) = YEAR(NOW())');

		$this->template->akceAllList = $akce;
		$this->template->memberList = $this->akceService->getAkceByMemberId($this->getUser()->getId());
		$this->template->orgList = $this->akceService->getAkceByMemberId($this->getUser()->getId(),TRUE);

		$this->template->registerHelper('timeAgoInWords', 'Helpers::timeAgoInWords');
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

		$this->registerTexy();
		$this->template->registerHelper('timeAgoInWords', 'Helpers::timeAgoInWords');
		$this->template->registerHelper('durationInWords', 'Helpers::durationInWords');
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

			if ((!$this->getUser()->isInArray($orgList))and(!$this->getUser()->isInRole($this->name))) {
				$this->flashMessage('Nemáte právo tuto akci editovat','error');
				$this->redirect('Akce:view',$id);
			}

			$form['organizator']->getLabelPrototype()->class('hide');
			$form['organizator']->getControlPrototype()->class('hide');

			$form->setDefaults($this->akce);

			if (!$this->akce->message) $form['message']->setDefaultValue($this->akceService->getAkceMessageDefault());
		}
	}

	public function renderAdd(){
		$this->template->nova = TRUE;
		$this->setView('edit');
	}

	public function actionDelete($id){
		$orgList = $this->akceService->getMemberListByAkceId($id,TRUE);

		if ((!$this->getUser()->isInArray($orgList))and(!$this->getUser()->isInRole($this->name))) {
			$this->flashMessage('Nemáte právo tuto akci smazat','error');
			$this->redirect('Akce:view',$id);
		}

		$this->akceService->getAkceById($id)->update(array('enable'=>0));
		$this->flashMessage('Akce byla smazána');
		$this->redirect('Akce:default');
	}

	public function actionAllow($id,$allow){
		if (!$this->getUser()->isInRole('Confirm')) {
			$this->flashMessage('Nemáte právo tuto akci povolit ani zakázat','error');
		}else {
			$values = array('confirm' => $allow);

			if ($allow) {
			  $this->flashMessage('Akce byla povolena');
			  $values['date_update'] = new Datetime();
			}
			else $this->flashMessage('Akce byla zakázána');

			$this->akceService->getAkceById($id)->update($values);
		}
		$this->redirect('view',$id);
	}

	public function createComponentPostsList(){
		return new \PostsListControl(5, 0, $this->forumService, TRUE);
	}

    protected function createComponentAlbum(){
        return new \AlbumPreviewControl($this->galleryService);
    }


	protected function createComponentRating(){
    	$userId = $this->getUser()->getId();
    	$isOrg = in_array($userId,$this->orgList);
    	$canComment = (in_array($userId,$this->memberList)or($isOrg));
		return new \RatingControl($this->akce->id,$this->ratingService,$userId,$isOrg,$canComment);
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

		$mail->setBody($template);
		$this->mailer->send($mail);
	}

	protected function createComponentMembers(){
		return new \MembersListControl($this->akceService,$this->akce);
	}

	protected function createComponentOrganizators(){
		return new \MembersListControl($this->akceService,$this->akce,TRUE);
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
		$compiler->addFileFilter(new \Webloader\Filter\jsShrink);

		return new \WebLoader\Nette\JavaScriptLoader($compiler, $this->template->basePath . '/texyla/temp');
	}

	protected function createComponentAkceForm(){
		$datum = new Datetime();
		$form = new Form;

		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->addText('name', 'Název', 30)
		  ->setRequired('Vyplňte %label akce');

		$form->addText('place', 'Místo', 50)
		  ->setRequired('Vyplňte %label akce');

		$form['date_start'] = new \DateTimeInput('Začátek');
		$form['date_start']->setDefaultValue($datum);

		$form['date_end'] = new \DateTimeInput('Konec');
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

		$form->addCheckbox('visible', 'Viditelná veřejnosti')->setDefaultValue(TRUE);

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
		  ->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru je 10 MB.', 10 * 1024 * 1024);

		$form->addText('price', 'Cena', 7)
		  ->setType('number')
		  ->setOption('description', 'Kč')
		  ->addCondition(Form::FILLED)
			->addRule(Form::INTEGER, '%label musí být číslo');

		$form->addTextArea('perex', 'Stručný popis')
		->setAttribute('class','texyla');

		$form->addTextArea('description', 'Podrobný popis')
		  ->setAttribute('class','texyla')
		  ->setRequired('Vyplňte %label akce');

		$text = $this->akceService->getAkceMessageDefault();

		$form->addTextArea('message','Zpráva z akce')
			->setDefaultValue($text)
			->setAttribute('class','texyla');

		$form->addSubmit('save', 'Ulož')->setAttribute('class', 'default');
			$form->onSuccess[] = callback($this, 'akceFormSubmitted');

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

		$form->onSuccess[] = callback($this, 'uploadBillFormSubmitted');

		return $form;
	}

	public function uploadBillFormSubmitted(Form $form){
		$id = (int) $this->getParameter('id');
		$data = $form->getValues();

		$akce = $this->akceService->getAkceById($id);

		if (($form['file']->isFilled()) and ($data->file->isOK())){
			$data->file->move(WWW_DIR.'/doc/vyuctovani/'.$id.'-'.Strings::webalize($akce->name).'.xls');

			$akce->update(array('bill' => 1));
			$this->flashMessage('Vyúčtování nahráno');
			$this->redirect('Akce:view',$id);
		}
	}
}
