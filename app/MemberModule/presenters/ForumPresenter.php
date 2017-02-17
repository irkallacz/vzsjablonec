<?php

namespace MemberModule;

use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Html;
use Nette\Utils\Paginator;
use Nette\Utils\Strings;
use Nette\Diagnostics\Debugger;
use Nette\Database\SqlLiteral;
use Nette\DateTime;

class ForumPresenter extends LayerPresenter{

	const postPerPage = 30;
	const topicPerPage = 30;

	/** @var \ForumService @inject */
	public $forumService;

	/** @var ActiveRow */
	private $topic;

	public function renderDefault(){
		$this->template->forum = $this->forumService->getForum();
		$this->template->registerHelper('timeAgoInWords', 'Helpers::timeAgoInWords');
	}

	public function showPost($post){
		$page = ceil($post->row_number/self::postPerPage);	
		$this->redirect("Forum:view#p$post->id", array($post->forum_topic_id, 'vp-page' => $page));
	}
	
	public function actionViewLast($id){
		$post = $this->forumService->getPostsByTopicId($id)->order('row_number DESC')->fetch();
		$this->showPost($post);
	}

	public function actionViewPost($id){
		$post = $this->forumService->getPostById($id);		
		$this->showPost($post);
	}

	public function renderTopic($id, $q = null){
		$forum = $this->forumService->getForumById($id);
		$this->template->forum = $forum;

		$vp = new \VisualPaginator($this, 'vp');
		
		$count = $this->forumService->getTopicsByForumId($id, $q)->count();

		$paginator = $vp->getPaginator();
		$paginator->setItemsPerPage(self::topicPerPage);
		$paginator->setItemCount($count);

		$this['searchForm']['q']->setDefaultValue($q);
	}

	protected function createComponentTopicList(){
		$id = $this->getParameter('id');
		$search = $this->getParameter('q');

		$topics = $this->forumService->getTopicsByForumId($id, $search);

		$paginator = $this['vp']->getPaginator();
		$topics->limit($paginator->getLength(), $paginator->getOffset());

		return new \TopicsListControl($topics, $search);
	}

	public function checkTopic($topic, $locked = FALSE){		
		if ((!$topic)or($topic->row_number == 0)) {
			$this->flashMessage('Téma neexistuje','error');
			$this->redirect('default');
		}

		if ($locked and $topic->locked) {
        	$this->flashMessage('Toto téma bylo uzavřeno','error');
        	$this->redirect('view',$topic->id);
        } 
	}

	public function createComponentPostsList(){
		$search = $this->getParameter('q');

		$offset = $this['vp']->getPaginator()->getOffset();
		$limit = $this['vp']->getPaginator()->getLength();

		$posts = $this->forumService->getPostsByTopicId($this->topic->id, $search);
		$posts->limit($limit,$offset);
		$posts->order('row_number');

		$isLocked = $this->topic->locked;

		return new \PostsListControl($posts, $isLocked, $search);
	}

	public function actionView($id, $q = null){
		$topic = $this->forumService->getTopicById($id);
		$this->checkTopic($topic);
		$this->topic = $topic;
	}

	public function renderView($id, $q = null){
		$this->template->topic = $this->topic;
		$this->template->title = $this->topic->title;

		$count = $this->forumService->getPostsByTopicId($id, $q)->count();

		$vp = new \VisualPaginator($this, 'vp');
		$paginator = $vp->getPaginator();
		$paginator->setItemsPerPage(self::postPerPage);
		$paginator->setItemCount($count);
		$this->template->paginator = $paginator;

    	$this->template->registerHelper('timeAgoInWords', 'Helpers::timeAgoInWords');

    	$this['addPostForm']['forum_topic_id']->setDefaultValue($id);  
    	$this['addPostForm']['forum_id']->setDefaultValue($this->topic->forum_id);

		$this['searchForm']['q']->setDefaultValue($q);
	}

	public function renderSearch($q, $forum_id = null, $subject = 'posts'){
		$vp = new \VisualPaginator($this, 'vp');
		$vp->getPaginator()->setItemsPerPage(self::postPerPage);

		$this->template->subject = $subject;
		$this->template->forum_id = $forum_id;
		$this->template->q = $q;

		$this['searchForumForm']['q']->setDefaultValue($q);
		$this['searchForumForm']['forum_id']->setDefaultValue($forum_id);
		$this['searchForumForm']['subject']->setDefaultValue($subject);
	}

	protected function createComponentSearchPostsList(){
		$q = $this->getParameter('q');
		$forum_id = $this->getParameter('forum_id');

		$posts = $this->forumService->searchPosts($q,$forum_id);
		$paginator = $this['vp']->getPaginator();
		$paginator->setItemCount(count($posts));

		$offset = $paginator->getOffset();
		$limit = $paginator->getLength();

		$posts->limit($limit, $offset);
		$posts->order('date_add DESC');

		return new \PostsListControl($posts, TRUE, $q);
	}

	protected function createComponentSearchTopicsList(){
		$q = $this->getParameter('q');
		$forum_id = $this->getParameter('forum_id');

		$topics = $this->forumService->searchTopics($q,$forum_id);
		$paginator = $this['vp']->getPaginator();
		$paginator->setItemCount(count($topics));

		$offset = $paginator->getOffset();
		$limit = $paginator->getLength();

		$topics->limit($limit, $offset);
		$topics->order('date_add DESC');

		return new \TopicsListControl($topics, $q);
	}

	protected function createComponentSearchForm(){
		$form = new Form;

		$form->addText('q', NULL, 20)
			->setAttribute('placeholder','Hledaný výraz')
			->setRequired('Zadejte prosím hledaný výraz')
			->setType('search')
			->setAttribute('class','search');

		$form->addSubmit('ok', '')
			->setAttribute('class','myfont');

		$form->onSuccess[] = callback($this,'processSearchForm');

		$renderer = $form->getRenderer();
		$renderer->wrappers['controls']['container'] = NULL;
		$renderer->wrappers['pair']['container'] = NULL;
		$renderer->wrappers['label']['container'] = NULL;
		$renderer->wrappers['control']['container'] = NULL;

		return $form;
	}

	public function processSearchForm(Form $form){
		$action = $this->getAction();
		$values = $form->getValues();
		$id = $this->getParameter('id');

		$this->redirect($action, $id, $values->q);
	}

	protected function createComponentSearchForumForm(){
		$form = $this->createComponentSearchForm();

		$form->addSelect('forum_id','Kategorie:',
			$this->forumService->getForum()->fetchPairs('id','title')
		)->setPrompt('Všechny kategorie');

		$form->addSelect('subject','Hledat:', ['posts' => 'Příspěvky', 'topics' => 'Témata'])
			->setRequired('Zadejte prosím co vyhledávat');

		$form->onSuccess = [callback($this,'processSearchForumForm')];

		return $form;
	}

	public function processSearchForumForm(Form $form){
		$values = $form->getValues();
		$this->redirect('search', $values->q, $values->forum_id, $values->subject);
	}

	public function actionTexyPreview($class = FALSE){
	    if ($this->isAjax()){

			\TexyFactory::$root = $this->template->basePath;
			$texy = \TexyFactory::createForumTexy();

			$httpRequest = $this->context->getByType('Nette\Http\Request');

			$div = Html::el('div')->setHtml($texy->process($httpRequest->getPost('texy')));
			$div->id = 'texyPreview';

			$this->sendResponse(new \Nette\Application\Responses\TextResponse($div));
	    }
	}
	
	private function checkPost($post){		
		if ($post->row_number == 0) {
        	$this->flashMessage('Příspěvek neexistuje','error');
        	$this->redirect('view', $post->forum_topic_id->id);
        }
	}
	
	public function renderEdit($id){
		if (!$id) {
        	$this->flashMessage('Nebyl vybrán žádný příspěvek','error');
        	$this->redirect('default');
        }
        
		$post = $this->forumService->getPostById($id);
		
		$this->checkPost($post);

		if ((!$this->getUser()->isInRole($this->name))and($post->member_id!=$this->getUser()->getId())) {
            	$this->flashMessage('Nemáte práva na tuto akci','error');
            	$this->redirect('view',$post->forum_topic_id);
        }

		$this->template->isEdit = TRUE;
		
		if ($post->id == $post->forum_topic_id) {
			$topic = $post;
			$this['addTopicForm']->setDefaults($post);
			$this->template->isTopic = TRUE;
		}else{ 
			$topic = $post->ref('forum_post','forum_topic_id'); 
			$this['addPostForm']->setDefaults($post);	
		}	
    	
    	$this->template->topic = $topic;

  		$this->checkTopic($topic,TRUE);

        $this->template->title = $topic->title;
	}

	public function renderCitePost($id){
		if (!$id) {
        	$this->flashMessage('Nebyl vybrán žádný příspěvek','error');
        	$this->redirect('default');
        }

        $post = $this->forumService->getPostById($id);

        $this->checkPost($post);

		if ($post->id == $post->forum_topic_id) $topic = $post; else $topic = $post->ref('forum_post','forum_topic_id');
		$this->template->topic = $topic;

		$this->checkTopic($topic,TRUE);
    	
    	$this->setView('edit');

    	$text = '> '.$post->member->surname . ' ' . $post->member->name . " napsal(a):\n>\n";
    	$text .= preg_replace('~^.+~m','> $0',trim($post->text))."\n\n";
    	
		$this['addPostForm']['text']->setDefaultValue($text);  
    	$this['addPostForm']['forum_topic_id']->setDefaultValue($post->forum_topic_id);  
    	$this['addPostForm']['forum_id']->setDefaultValue($post->forum_id);

	}

	public function renderAdd($id){
		$topic = $this->forumService->getTopicById($id);

		$this->checkTopic($topic,TRUE);
		
		$this->template->topic = $topic;
    	$this->setView('edit');

    	$this['addPostForm']['forum_topic_id']->setDefaultValue($topic->id);  
    	$this['addPostForm']['forum_id']->setDefaultValue($topic->forum_id);
	}

	public function actionDelete($id){
	 $post = $this->forumService->getPostById($id);

	 if ((!$this->getUser()->isInRole($this->name))and($post->member_id!=$this->getUser()->getId())) {
            	$this->flashMessage('Nemáte práva na tuto akci','error');
            	$this->redirect('view',$post->forum_topic_id);
        }

	 if ($post->id != $post->forum_topic_id) {		
		$this->forumService->getPostsByTopicId($post->forum_topic_id)
			->where('row_number > ?', $post->row_number)
			->update(array('row_number' => new SqlLiteral('row_number - 1')));
	 	
	 	$post->update(array('row_number' => 0, 'hidden' => 1));

	 	$this->redirect('Forum:view', $post->forum_topic_id);
	 }
	 else {
	 	$this->forumService->getPostsByTopicId($post->id)->update(array('row_number' => 0, 'hidden' => 1));
	 	$this->redirect('Forum:topic',$post->forum_id);
	 }
	}

	public function actionLockTopic($id,$lock){
	 $post = $this->forumService->getPostById($id);
	 
	 if ((!$this->getUser()->isInRole($this->name))and($post->member_id!=$this->getUser()->getId())) {
            	$this->flashMessage('Nemáte práva na tuto akci','error');
            	$this->redirect('view',$post->forum_topic_id);
        }

	 $post->update(array('locked' => $lock));
	 $this->redirect('Forum:view',$post->forum_topic_id);
	}

	protected function createComponentTexylaJs(){
	    $files = new \WebLoader\FileCollection(WWW_DIR . '/texyla/js');
	    $files->addFiles(['texyla.js','selection.js','texy.js','buttons.js','cs.js','dom.js','view.js','window.js']);
	    $files->addFiles(['../plugins/emoticon/emoticon.js']);
	    $files->addFiles([WWW_DIR . '/js/texyla_forum.js']);
	    $files->addFiles([WWW_DIR . '/js/jquery-ui.custom.min.js']);

	    $compiler = \WebLoader\Compiler::createJsCompiler($files, WWW_DIR . '/texyla/temp');
	    $compiler->addFileFilter(new \Webloader\Filter\jsShrink);

	    return new \WebLoader\Nette\JavaScriptLoader($compiler, $this->template->basePath . '/texyla/temp');
	}

	protected function createComponentAddTopicForm(){
		$form = new Form;
		
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->addText('title')
			->setAttribute('placeholder','Název tématu')
			->setAttribute('spellcheck', 'true')			
			->setRequired('Zadejte prosím předmet');
		$form->addTextArea('text')
			->setRequired('Zadejte prosím text zprávy')
			->setAttribute('spellcheck', 'true')
			->setAttribute('class', 'texyla');

		$form->onSuccess[]= callback($this,'processAddTopicForm');
		return $form;
	}

	public function processAddTopicForm(Form $form){
		$values = $form->getValues();
		$datum = new DateTime();
		$values->date_update = $datum;

		$values->title = ucfirst($values->title);

		$akce = $this->getAction();

		if ($akce == 'edit'){
			$id = (int) $this->getParameter('id');
			$this->forumService->getTopicById($id)->update($values);
        	$this->flashMessage('Téma bylo upraveno');
        	$this->redirect('view',$id); 	
        }
		
		if ($akce == 'topic'){
			$values->date_add = $datum;
			$values->forum_id = (int) $this->getParameter('id');
			$values->member_id = $this->getUser()->getId();
			$this->forumService->addTopic($values);
        	$this->flashMessage('Bylo přidáno další téma');
        	$this->redirect('this'); 
		}         
	}
	
	protected function createComponentAddPostForm(){
		$form = new Form;
		
		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->addTextArea('text')
			->setRequired('Zadejte prosím text zprávy')
			->setAttribute('spellcheck', 'true')
			->setAttribute('class', 'texyla');

		$form->addHidden('id',0);
		$form->addHidden('forum_topic_id');
		$form->addHidden('forum_id');

		$form->onSuccess[]= callback($this,'processAddPostForm');
		return $form;
	}

	public function processAddPostForm(Form $form){
		$values = $form->values;
		$datum = new DateTime();
		$values->date_update = $datum;

		//$values->title = ucfirst($values->title);

		$id = (int) $values['id'];
		unset($values['id']);

		if ($id > 0) {
        	$row = $this->forumService->getPostById($id);
        	$row->update($values);
        	$this->flashMessage('Příspěvek byl upraven');
        } else {
        	$values->member_id = $this->getUser()->getIdentity()->getId();	
			$values->date_add = $datum;

        	$row = $this->forumService->addPost($values);
        	$this->flashMessage('Byl přidán další příspěvek');
        }

		$this->showPost($row);        
	}
}
