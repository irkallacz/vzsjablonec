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

class PostsListControl extends Control{

    /** @var Selection */
    private $posts;

    /** @var boolean */
    private $isLocked;

    /**@var string */
    private $search;

	/**
	 * PostsListControl constructor.
	 * @param Selection $posts
	 * @param bool $isLocked
	 * @param string $search
	 */
	public function __construct(Selection $posts, $isLocked, $search = NULL){
		parent::__construct();
		$this->posts = $posts;
		$this->isLocked = $isLocked;
		$this->search = $search;
	}


	public function render(){
        \TexyFactory::$root = $this->template->basePath;
        $texy = \TexyFactory::createForumTexy();

        $this->template->setFile(__DIR__ . '/PostsListControl.latte');
        $this->template->addFilter('texy', [$texy, 'process']);
        $this->template->addFilter('timeAgoInWords', 'Helpers::timeAgoInWords');
	    $this->template->posts = $this->posts;
		$this->template->isLocked = $this->isLocked;
		$this->template->search = $this->search;

		$this->template->render();
    }

}