<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 29.11.2016
 * Time: 23:55
 */
class PostsListControl extends Nette\Application\UI\Control{

    /** @var int */
    private $limit;

    /** @var int */
    private $offset;

    /** @var ForumService */
    private $forumService;

    /**@var bool */
    private $reverseOrder;

    /**
     * PostsListControl constructor.
     * @param int $limit
     * @param int $offset
     * @param ForumService $forumService
     * @param bool $reverseOrder
     */
    public function __construct($limit, $offset, ForumService $forumService, $reverseOrder = FALSE){
        parent::__construct();
        $this->offset = $offset;
        $this->limit = $limit;
        $this->forumService = $forumService;
        $this->reverseOrder = $reverseOrder;
    }

    public function render($id){
        TexyFactory::$root = $this->template->basePath;
        $texy = TexyFactory::createForumTexy();

        $this->template->setFile(__DIR__ . '/PostsListControl.latte');
        $this->template->registerHelper('texy', callback($texy, 'process'));
        $this->template->registerHelper('timeAgoInWords', 'Helpers::timeAgoInWords');

        $topic = $this->forumService->getTopicById($id);
        if ($this->forumService->checkTopic($topic)){
            $this->template->topic = $topic;

            $posts = $this->forumService->getPostsByTopicId($id);

            if ($this->reverseOrder) $posts->order('row_number DESC'); else $posts->order('row_number');

            $posts->limit($this->limit, $this->offset);
            $this->template->posts = $posts;
        }else {
            $this->flashMessage('Téma neexistuje','error');
        }

        $this->template->render();
    }

}