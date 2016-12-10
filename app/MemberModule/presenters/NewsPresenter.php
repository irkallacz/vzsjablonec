<?php

namespace MemberModule;

use Nette\Diagnostics\Debugger;
use Nette\DateTime;

class NewsPresenter extends LayerPresenter{

        /** @var \AkceService @inject */
        public $akceService;

        /** @var \WordpressService @inject */
        public $wordpressService;

        /** @var \ForumService @inject */
        public $forumService;

        /** @var \DokumentyService @inject */
        public $dokumentyService;

        /** @var \AnketyService @inject */
        public $anketyService;

        /** @var \HlasovaniService @inject */
        public $hlasovaniService;

        public function renderDefault(){

                if ($this->context->parameters['productionMode']) {
                    $this->template->novinky = $this->wordpressService->getLastNews();
                }

                $datum = $this->getUser()->getIdentity()->date_last;
                $user_id = $this->getUser()->getId();

                $this->template->lastDate = $datum;
                $this->template->nowDate = new Datetime();

                $this->template->akceList = $this->akceService->getAkceNews($datum);
                $this->template->forumList = $this->forumService->getTopicNews($datum);
                $this->template->dokumentyList = $this->dokumentyService->getDokumentyNews($datum);
                $this->template->anketyList = $this->anketyService->getAnketyNews($datum);
                $this->template->hlasovaniList = $this->hlasovaniService->getHlasovaniNews($datum,
                    $this->user->isInRole('Board'));

                $this->template->ratingList = $this->akceService->getRatingNews($datum, $user_id);

                $this->template->feedbackList = $this->akceService->getFeedbackRequests($datum, $user_id);
                $this->template->reportList = $this->akceService->getReportRequests($datum, $user_id);

                $this->template->registerHelper('timeAgoInWords', 'Helpers::timeAgoInWords');
        }

}