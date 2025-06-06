<?php

namespace App\MemberModule\Presenters;

use App\MemberModule\Components\PostsListControl;
use App\MemberModule\Components\TopicsListControl;
use App\MemberModule\Components\TinyMde;
use App\Model\ForumService;
use App\Model\UserService;
use App\Template\LatteFilters;
use Nette\Application\AbortException;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Forms\Controls\SubmitButton;
use Nette\Utils\ArrayHash;
use Nette\Utils\Html;
use Nette\Database\SqlLiteral;
use Nette\Utils\DateTime;
use Nette\Utils\Paginator;
use Nette\Utils\Strings;
use Tracy\Debugger;

class ForumPresenter extends LayerPresenter {

	const ITEMS_PER_PAGE = 30;

	/** @var ForumService @inject */
	public $forumService;

	/** @var ActiveRow */
	private $topic;

	/**
	 *
	 */
	public function renderDefault() {
		$this->template->forum = $this->forumService->getForum();
	}

	/**
	 * @param IRow|ActiveRow $post
	 * @throws AbortException
	 */
	public function showPost(IRow $post) {
		$param = ['id' => $post->forum_topic_id];
		$page = ceil($post->row_number / self::ITEMS_PER_PAGE);
		if ($page > 1) $param['vp-page'] = $page;
		$this->redirect("Forum:topic#post/$post->id", $param);
	}

	/**
	 * @param int $id
	 * @throws BadRequestException
	 */
	public function actionPost(int $id) {
		$post = $this->forumService->getPostById($id);
		if ($post){
			$this->showPost($post);
		}else{
			throw new BadRequestException('Příspěvek neexistuje');
		}
	}

	/**
	 * @param int $id
	 * @param string|null $q
	 */
	public function renderCategory(int $id, string $q = null) {
		$forum = $this->forumService->getForumById($id);
		$this->template->forum = $forum;

		$count = $this->forumService->getTopicsByForumId($id, $q)->count();

		/** @var Paginator $paginator */
		$paginator = $this['vp']->getPaginator();
		$paginator->setItemCount($count);

		$this['searchForm']['q']->setDefaultValue($q);
	}

	protected function createComponentVp(){
		$vp = new \VisualPaginator();
		$vp->getPaginator()->setItemsPerPage(self::ITEMS_PER_PAGE);
		return $vp;
	}

	/**
	 * @return TopicsListControl
	 */
	protected function createComponentTopicList() {
		$id = $this->getParameter('id');
		$search = $this->getParameter('q');

		$topics = $this->forumService->getTopicsByForumId($id, $search);

		/** @var Paginator $paginator*/
		$paginator = $this['vp']->getPaginator();
		$topics->limit($paginator->getLength(), $paginator->getOffset());

		return new TopicsListControl($topics, $search);
	}

	/**
	 * @param IRow|ActiveRow|NULL $topic
	 * @param bool $locked
	 * @throws BadRequestException
	 */
	public function checkTopic($topic, bool $locked = FALSE) {
		if ((!$topic) or ($topic->row_number == 0)) {
			throw new BadRequestException('Téma neexistuje');
		}

		if ($locked and $topic->locked) {
			throw new BadRequestException('Toto téma bylo uzavřeno');
		}
	}

	/**
	 * @return PostsListControl
	 */
	public function createComponentPostsList() {
		$search = $this->getParameter('q');

		/** @var Paginator $paginator*/
		$paginator = $this['vp']->getPaginator();
		$offset = $paginator->getOffset();
		$limit = $paginator->getLength();

		$posts = $this->forumService->getPostsByTopicId($this->topic->id, $search);
		$posts->limit($limit, $offset);
		$posts->order('row_number');

		$isLocked = $this->topic->locked;

		return new PostsListControl($posts, $isLocked, $search);
	}

	/**
	 * @param int $id
	 * @param string|null $q
	 * @throws BadRequestException
	 */
	public function actionTopic(int $id, string $q = null) {
		$topic = $this->forumService->getTopicById($id);
		$this->checkTopic($topic);
		$this->topic = $topic;
	}

	/**
	 * @param int $id
	 * @param string|NULL $q
	 */
	public function renderTopic(int $id, string $q = null) {
		$this->template->topic = $this->topic;
		$this->template->title = $this->topic->title;

		$count = $this->forumService->getPostsByTopicId($id, $q)->count();

		/** @var Paginator $paginator */
		$paginator = $this['vp']->getPaginator();
		$paginator->setItemCount($count);

		$this['addPostForm']['forum_topic_id']->setDefaultValue($id);
		$this['addPostForm']['forum_id']->setDefaultValue($this->topic->forum_id);

		$this['searchForm']['q']->setDefaultValue($q);
	}

	/**
	 * @param string|NULL $q
	 * @param int|NULL $forum_id
	 * @param string $subject
	 */
	public function renderSearch(string $q = NULL, int $forum_id = NULL, string $subject = 'posts') {
		$this->template->subject = $subject;
		$this->template->forum_id = $forum_id;
		$this->template->q = $q;

		$this['searchForumForm']['q']->setDefaultValue($q);
		$this['searchForumForm']['forum_id']->setDefaultValue($forum_id);
		$this['searchForumForm']['subject']->setDefaultValue($subject);
	}

	/**
	 * @return PostsListControl
	 */
	protected function createComponentSearchPostsList() {
		$q = $this->getParameter('q');
		$forum_id = $this->getParameter('forum_id');

		$posts = $this->forumService->searchPosts($q, $forum_id);

		/* @var Paginator $paginator*/
		$paginator = $this['vp']->getPaginator();
		$paginator->setItemCount(count($posts));

		$offset = $paginator->getOffset();
		$limit = $paginator->getLength();

		$posts->limit($limit, $offset);
		$posts->order('date_add DESC');

		return new PostsListControl($posts, TRUE, $q);
	}

	/**
	 * @return TopicsListControl
	 */
	protected function createComponentSearchTopicsList() {
		$q = $this->getParameter('q');
		$forum_id = $this->getParameter('forum_id');

		$topics = $this->forumService->searchTopics($q, $forum_id);

		/** @var Paginator $paginator */
		$paginator = $this['vp']->getPaginator();
		$paginator->setItemCount(count($topics));

		$offset = $paginator->getOffset();
		$limit = $paginator->getLength();

		$topics->limit($limit, $offset);
		$topics->order('date_add DESC');

		return new TopicsListControl($topics, $q);
	}

	/**
	 * @return Form
	 */
	protected function createComponentSearchForm() {
		$form = new Form;

		$form->addText('q', NULL, 20)
			->setAttribute('placeholder', 'Hledaný výraz')
			->setRequired('Zadejte prosím hledaný výraz')
			->setType('search')
			->setAttribute('class', 'search');

		$form->addSubmit('ok')
			->getControlPrototype()->setName('button')->setHtml('<svg class="icon icon-search"><use xlink:href="' . $this->template->baseUri . '/img/symbols.svg#icon-search"></use></svg>');

		$form->onSuccess[] = [$this, 'processSearchForm'];

		$renderer = $form->getRenderer();
		$renderer->wrappers['controls']['container'] = NULL;
		$renderer->wrappers['pair']['container'] = NULL;
		$renderer->wrappers['label']['container'] = NULL;
		$renderer->wrappers['control']['container'] = NULL;

		return $form;
	}

	/**
	 * @param Form $form
	 * @throws AbortException
	 */
	public function processSearchForm(Form $form) {
		$action = $this->getAction();
		$values = $form->getValues();
		$id = $this->getParameter('id');

		$this->redirect($action, $id, $values->q);
	}

	/**
	 * @return Form
	 */
	protected function createComponentSearchForumForm() {
		$form = $this->createComponentSearchForm();

		$form->addSelect('forum_id', 'Kategorie:',
			$this->forumService->getForum()->fetchPairs('id', 'title')
		)->setPrompt('Všechny kategorie');

		$form->addSelect('subject', 'Hledat:', ['posts' => 'Příspěvky', 'topics' => 'Témata'])
			->setRequired('Zadejte prosím co vyhledávat');

		$form->onSuccess = [[$this, 'processSearchForumForm']];

		return $form;
	}

	/**
	 * @param Form $form
	 * @throws AbortException
	 */
	public function processSearchForumForm(Form $form) {
		$values = $form->getValues();
		$this->redirect('search', $values->q, $values->forum_id, $values->subject);
	}

	/**
	 * @param bool $class
	 * @allow(member)
	 * @throws AbortException
	 */
	public function actionTexyPreview(bool $class = FALSE) {
		if ($this->isAjax()) {

			$httpRequest = $this->context->getByType('Nette\Http\Request');
			$div = Html::el('div')->setHtml(LatteFilters::forumTexy($httpRequest->getPost('texy')));
			$div->id = 'texyPreview';

			$this->sendResponse(new TextResponse($div));
		}
	}

	/**
	 * @param IRow|ActiveRow $post
	 * @throws BadRequestException
	 */
	private function checkPost(IRow $post) {
		if ($post->row_number == 0) {
			throw new BadRequestException('Příspěvek neexistuje');
		}
	}

	/**
	 * @param int $id
	 * @allow(member)
	 * @throws BadRequestException
	 * @throws ForbiddenRequestException
	 */
	public function renderEdit(int $id) {
		if (!$id) {
			throw new BadRequestException('Nebyl vybrán žádný příspěvek');
		}

		/* @var ActiveRow $post*/
		$post = $this->forumService->getPostById($id);

		$this->checkPost($post);

		if ((!$this->getUser()->isInRole('admin')) and ($post->user_id != $this->getUser()->getId())) {
			throw new ForbiddenRequestException('Nemáte práva na tuto akci');
		}

		$this->template->isEdit = TRUE;

		if ($post->id == $post->forum_topic_id) {
			$topic = $post;
			$this['addTopicForm']->setDefaults($post);
			$this->template->isTopic = TRUE;
		} else {
			$topic = $post->ref('forum_post', 'forum_topic_id');
			$this['addPostForm']->setDefaults($post);
		}

		$this->template->topic = $topic;

		$this->checkTopic($topic, TRUE);

		$this->template->title = $topic->title;
	}

	/**
	 * @param int $id
	 * @allow(member)
	 * @throws BadRequestException
	 */
	public function renderCite(int $id) {
		if (!$id) {
			throw new BadRequestException('Nebyl vybrán žádný příspěvek');
		}

		/* @var ActiveRow $post*/
		$post = $this->forumService->getPostById($id);

		$this->checkPost($post);

		if ($post->id == $post->forum_topic_id) $topic = $post; else $topic = $post->ref('forum_post', 'forum_topic_id');
		$this->template->topic = $topic;

		$this->checkTopic($topic, TRUE);

		$this->setView('edit');

		$text = '> ' . UserService::getFullName($post->user) . " napsal(a):\n>\n";
		$text .= preg_replace('~^~m', '> $0', trim($post->text)) . "\n\n";

		$this['addPostForm']['text']->setDefaultValue($text);
		$this['addPostForm']['forum_topic_id']->setDefaultValue($post->forum_topic_id);
		$this['addPostForm']['forum_id']->setDefaultValue($post->forum_id);

	}

	/**
	 * @param int $id
	 * @allow(member)
	 * @throws BadRequestException
	 */
	public function renderAdd(int $id) {
		/* @var ActiveRow $topic*/
		$topic = $this->forumService->getTopicById($id);

		$this->checkTopic($topic, TRUE);

		$this->template->topic = $topic;
		$this->setView('edit');

		$this['addPostForm']['forum_topic_id']->setDefaultValue($topic->id);
		$this['addPostForm']['forum_id']->setDefaultValue($topic->forum_id);
	}

	/**
	 * @param int $id
	 * @allow(member)
	 * @throws ForbiddenRequestException
	 * @throws AbortException
	 */
	public function actionDelete(int $id) {
		/* @var ActiveRow $post*/
		$post = $this->forumService->getPostById($id);

		if ((!$this->getUser()->isInRole('admin')) and ($post->user_id != $this->getUser()->getId())) {
			throw new ForbiddenRequestException('Nemáte práva na tuto akci');
		}

		if ($post->id != $post->forum_topic_id) {
			$this->forumService->getPostsByTopicId($post->forum_topic_id)
				->where('row_number > ?', $post->row_number)
				->update(['row_number' => new SqlLiteral('row_number - 1')]);

			$post->update(['row_number' => 0, 'hidden' => 1]);

			$this->redirect('Forum:topic', $post->forum_topic_id);
		} else {
			$this->forumService->getPostsByTopicId($post->id)->update(['row_number' => 0, 'hidden' => 1]);
			$this->redirect('Forum:topic', $post->forum_id);
		}
	}

	/**
	 * @allow(member)
	 * @param int $id
	 * @param bool $lock
	 * @throws ForbiddenRequestException
	 * @throws AbortException
	 */
	public function actionLockTopic(int $id, bool $lock) {
		/* @var ActiveRow $post*/
		$post = $this->forumService->getPostById($id);

		if ((!$this->getUser()->isInRole('admin')) and ($post->user_id != $this->getUser()->getId())) {
			throw new ForbiddenRequestException('Nemáte práva na tuto akci');
		}

		$post->update(['locked' => $lock]);
		$this->redirect('topic', $post->forum_topic_id);
	}

	/**
	 * @allow(member)
	 * @return Form
	 */
	protected function createComponentAddTopicForm() {
		$form = new Form;

		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->addText('title')
			->addFilter([Strings::class, 'firstUpper'])
			->addFilter([$this, 'removeEmoji'])
			->setAttribute('placeholder', 'Název tématu')
			->setAttribute('spellcheck', 'true')
			->setRequired('Zadejte prosím předmet');

		//$form->addTextArea('text')
		$form->addComponent((new TinyMde())
			->setRequired('Zadejte prosím text zprávy')
			->setAttribute('spellcheck', 'true')
			->setAttribute('class', 'editor'), 'text');

		$form->addSubmit('submit', 'Odeslat')
			->onClick[] = [$this, 'processAddTopicForm'];

		return $form;
	}

	/**
	 * @param Form $form
	 * @allow(member)
	 * @throws AbortException
	 */
	public function processAddTopicForm(Form $form) {
		$values = $form->getValues();
		$datum = new DateTime();
		$values->date_update = $datum;

		$akce = $this->getAction();

		if ($akce == 'edit') {
			$id = (int) $this->getParameter('id');
			$this->forumService->getTopicById($id)->update($values);
			$this->flashMessage('Téma bylo upraveno');
			$this->redirect('topic', $id);
		}

		if ($akce == 'category') {
			$values->date_add = $datum;
			$values->forum_id = (int)$this->getParameter('id');
			$values->user_id = $this->getUser()->getId();
			$this->forumService->addTopic($values);
			$this->flashMessage('Bylo přidáno další téma');
			$this->redirect('this');
		}
	}

	/**
	 * @return Form
	 * @allow(member)
	 */
	protected function createComponentAddPostForm() {
		$form = new Form;

		$form->addProtection('Vypršel časový limit, odešlete formulář znovu');

		$form->addComponent((new TinyMde())
			->setRequired('Zadejte prosím text zprávy')
			->setAttribute('spellcheck', 'true')
			->setAttribute('class', 'editor'),
		'text');

		$form->addHidden('id', 0);
		$form->addHidden('forum_topic_id');
		$form->addHidden('forum_id');

		$form->addSubmit('submit', 'Odeslat')
			->onClick[] = [$this, 'processAddPostForm'];

		return $form;
	}

	/**
	 * @param SubmitButton $button
	 * @param ArrayHash $values
	 * @throws AbortException
	 * @allow(member)
	 */
	public function processAddPostForm(SubmitButton $button, ArrayHash $values) {
		$datum = new DateTime();
		$values->date_update = $datum;

		$id = (int)$values['id'];
		unset($values['id']);

		if ($id > 0) {
			$row = $this->forumService->getPostById($id);
			$row->update($values);
			$this->flashMessage('Příspěvek byl upraven');
		} else {
			$values->user_id = $this->getUser()->getIdentity()->getId();
			$values->date_add = $datum;

			$row = $this->forumService->addPost($values);
			$this->flashMessage('Byl přidán další příspěvek');
		}

		$this->showPost($row);
	}
}
