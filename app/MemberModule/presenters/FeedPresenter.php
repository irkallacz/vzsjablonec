<?php

namespace App\MemberModule\Presenters;

use Nette\Application\UI\Presenter;
use	Nette\Diagnostics\Debugger;

class FeedPresenter extends BasePresenter {

	/** @var \Nette\Http\Response @inject*/
	public $httpResponse;

	/** @var \AkceService @inject */
	public $akceService;

	/** @var \ForumService @inject */
	public $forumService;

	/** @var \DokumentyService @inject */
	public $dokumentyService;

	/** @var \AnketyService @inject */
	public $anketyService;

	/** @var \HlasovaniService @inject */
	public $hlasovaniService;

	/** @var \MemberService @inject */
	public $memberService;

	/** @var \GalleryService @inject */
	public $galleryService;

	protected function startup(){
		parent::startup();

		if (!$this->getUser()->isLoggedIn()){
			if (!isset($_SERVER['PHP_AUTH_USER'])) $this->prihlas(); else $this->zkontroluj();
		}
	}

	protected function zkontroluj(){
		$member = $this->memberService->getMemberByAutentication($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);

		if (!$member) {
			$this->httpResponse->setCode(\Nette\Http\Response::S401_UNAUTHORIZED);

		    echo 'Chyba přihlášení';
		    $this->terminate();	
		}
	}

	protected function prihlas(){
		$this->httpResponse->setHeader('WWW-Authenticate','Basic realm="VZS member"');
		$this->httpResponse->setCode(\Nette\Http\Response::S401_UNAUTHORIZED);
		
		$this->terminate();
	}	
	
	protected function beforeRender(){
		$this->registerTexy();
	}
	
	public function renderAnkety(){
		$this->template->items = $this->anketyService->getAnkety();
	}
	
	public function renderHlasovani(){
		$this->template->items = $this->hlasovaniService->getAnkety()->where('date_deatline < NOW() OR locked = ?', 1);
	}
	
	public function renderAkce(){
		$this->template->items = $this->akceService->getAkce()->where('confirm',TRUE)->where('enable',TRUE);
	}

	public function renderForum(){
		$topicId = $this->getParameter('tid');
		$forumId = $this->getParameter('fid');

		$items = $this->forumService->getPosts()->order('date_add DESC');
		
		if ($topicId) {
			$items->where('forum_topic_id',$topicId);
			$this->template->topic = $this->forumService->getTopicById($topicId);
		}
		if ($forumId) {
			$items->where('forum_id',$forumId);
			$this->template->forum = $this->forumService->getForumById($forumId);
		}
		
		$items->limit(1000);

		$this->template->items = $items;
	}

	public function renderAlbums(){
		$albums = $this->galleryService->getAlbums()->order('date_add DESC');
		$members = $this->memberService->getMembers(FALSE)->select('id,name,surname,mail')
			->where('id',array_keys($albums->fetchPairs('member_id','id')))
			->fetchPairs('id');
		
		$this->template->items = $albums;
		$this->template->members = $members;
	}

}