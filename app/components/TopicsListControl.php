<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 29.11.2016
 * Time: 23:55
 */
class TopicsListControl extends Nette\Application\UI\Control{

    /** @var \Nette\Database\Table\Selection */
    private $topics;

    /**@var string */
    private $search;

	/**
	 * PostsListControl constructor.
	 * @param \Nette\Database\Table\Selection $posts
	 * @param string $search
	 */
	public function __construct(\Nette\Database\Table\Selection $topics, $search = NULL){
		parent::__construct();
		$this->topics = $topics;
		$this->search = $search;
	}


	public function render(){
        $this->template->setFile(__DIR__ . '/TopicsListControl.latte');
        $this->template->registerHelper('timeAgoInWords', 'Helpers::timeAgoInWords');
	    $this->template->topics = $this->topics;
		$this->template->search = $this->search;

		$this->template->render();
    }

}