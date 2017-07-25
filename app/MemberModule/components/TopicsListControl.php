<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 29.11.2016
 * Time: 23:55
 */

namespace App\MemberModule\Components;

use Nette\Application\UI\Control;
use Nette\Database\Table\Selection;


class TopicsListControl extends Control{

    /** @var Selection */
    private $topics;

    /**@var string */
    private $search;

	/**
	 * PostsListControl constructor.
	 * @param Selection $posts
	 * @param string $search
	 */
	public function __construct(Selection $topics, $search = NULL){
		parent::__construct();
		$this->topics = $topics;
		$this->search = $search;
	}


	public function render(){
        $this->template->setFile(__DIR__ . '/TopicsListControl.latte');
	    $this->template->topics = $this->topics;
		$this->template->search = $this->search;

		$this->template->render();
    }

}