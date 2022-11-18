<?php

namespace App\MemberModule\Presenters;

use App\Model;
use Nette\Utils\DateTime;

class NewsPresenter extends LayerPresenter{

        /** @var Model\AkceService @inject */
        public $akceService;

        /** @var Model\WordpressService @inject */
        public $wordpressService;

        /** @var Model\ForumService @inject */
        public $forumService;

        /** @var Model\DokumentyService @inject */
        public $dokumentyService;

        /** @var Model\AnketyService @inject */
        public $anketyService;

		/** @var Model\MessageService @inject */
		public $messageService;

		/** @var Model\GalleryService @inject */
		public $galleryService;

		/** @var Model\ImageService @inject */
		public $imageService;

		/** @var Model\HlasovaniService @inject */
        public $hlasovaniService;

		/** @var Model\InvoiceService @inject */
		public $invoiceService;

		/** @var Model\YoutubeService @inject */
        public $youtubeService;

        public function renderDefault(){

                if ($this->context->parameters['productionMode']) {
                    $this->template->novinky = $this->wordpressService->getLastNews();
                }

                /**@var DateTime $datum */
                $datum = $this->user->identity->date_last;

				/**@var int $id */
				$user_id = $this->user->id;

                $this->template->lastDate = $datum;
                $this->template->nowDate = new DateTime();

                $this->template->akceList = $this->akceService->getAkceNews($datum);
                $this->template->forumList = $this->forumService->getTopicNews($datum);
                $this->template->dokumentyList = $this->dokumentyService->getDokumentyNews($datum);
                $this->template->anketyList = $this->anketyService->getAnketyNews($datum);
                $this->template->hlasovaniList = $this->hlasovaniService->getHlasovaniNews($datum, $this->user->isInRole('board'));

                $this->template->ratingList = $this->akceService->getRatingNews($datum, $user_id);

                $this->template->feedbackList = $this->akceService->getFeedbackRequests($datum, $user_id);
                $this->template->reportList = $this->akceService->getReportRequests($datum, $user_id);

                $this->template->messageList = $this->messageService->getMessagesNews($datum, $user_id);

                $this->template->imageService = $this->galleryService->getAlbumNews($datum);
                $this->template->albumList = $this->imageService;

                $this->template->invoiceList = $this->invoiceService->getInvoiceNews($datum, $user_id);

                $this->template->videoList = $this->youtubeService->getVideoNews($datum);
        }

}