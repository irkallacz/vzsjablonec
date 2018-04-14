<?php

namespace App\MemberModule\Presenters;

use App\Model\AkceService;
use App\Model\AnketyService;
use App\Model\DokumentyService;
use App\Model\ForumService;
use App\Model\GalleryService;
use App\Model\HlasovaniService;
use App\Model\UserService;
use Nette\Http\Response;

class FeedPresenter extends BasePresenter {

	/** @var \Nette\Http\Response @inject */
	public $httpResponse;

	/** @var AkceService @inject */
	public $akceService;

	/** @var ForumService @inject */
	public $forumService;

	/** @var DokumentyService @inject */
	public $dokumentyService;

	/** @var AnketyService @inject */
	public $anketyService;

	/** @var HlasovaniService @inject */
	public $hlasovaniService;

	/** @var UserService @inject */
	public $userService;

	/** @var GalleryService @inject */
	public $galleryService;

	/**
	 *
	 */
	protected function startup() {
		parent::startup();

		if (!$this->getUser()->isLoggedIn()) {
			if (!isset($_SERVER['PHP_AUTH_USER'])) $this->prihlas(); else $this->zkontroluj();
		}
	}

	/**
	 *
	 */
	protected function zkontroluj() {
		$user = $this->userService->getUserByAutentication($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		if (!$user) {
			$this->httpResponse->setCode(Response::S401_UNAUTHORIZED);

			echo 'Chyba přihlášení';
			$this->terminate();
		}
	}

	/**
	 *
	 */
	protected function prihlas() {
		$this->httpResponse->setHeader('WWW-Authenticate', 'Basic realm="VZS member"');
		$this->httpResponse->setCode(Response::S401_UNAUTHORIZED);

		$this->terminate();
	}

	/**
	 *
	 */
	public function renderAnkety() {
		$this->template->items = $this->anketyService->getAnkety();
	}

	/**
	 *
	 */
	public function renderHlasovani() {
		$this->template->items = $this->hlasovaniService->getAnkety()->where('date_deatline < NOW() OR locked = ?', 1);
	}

	/**
	 *
	 */
	public function renderAkce() {
		$this->template->items = $this->akceService->getAkce()->where('confirm', TRUE)->where('enable', TRUE);
	}

	/**
	 *
	 */
	public function renderForum() {
		$topicId = $this->getParameter('topic');
		$forumId = $this->getParameter('category');

		$items = $this->forumService->getPosts()->order('date_add DESC');

		if ($topicId) {
			$items->where('forum_topic_id', $topicId);
			$this->template->topic = $this->forumService->getTopicById($topicId);
		}
		if ($forumId) {
			$items->where('forum_id', $forumId);
			$this->template->forum = $this->forumService->getForumById($forumId);
		}

		$items->limit(1000);

		$this->template->items = $items;
	}

	/**
	 *
	 */
	public function renderAlbums() {
		$this->template->items = $this->galleryService->getAlbums()->order('date_add DESC');
	}

}