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
use App\Template\LatteFilters;

class PostsListControl extends Control {

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
	public function __construct(Selection $posts, bool $isLocked, string $search = NULL) {
		parent::__construct();
		$this->posts = $posts;
		$this->isLocked = $isLocked;
		$this->search = $search;
	}


	public function render() {
		LatteFilters::$root = $this->template->basePath;

		$this->template->setFile(__DIR__ . '/PostsListControl.latte');
		$this->template->posts = $this->posts;
		$this->template->isLocked = $this->isLocked;
		$this->template->search = $this->search;

		$this->template->render();
	}

}