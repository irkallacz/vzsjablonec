<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 14.12.2019
 * Time: 12:03
 */

namespace App\MemberModule\Components;

use App\Model\AkceService;
use Nette\Utils\DateTime;

class UserEventsControl extends LayerControl
{

	const DEFAULT_EVENT_OFFSET = 10;

	/**
	 * @var AkceService
	 */
	private $akceService;

	/**
	 * @var int
	 */
	private $eventsOffset = 0;

	/**
	 * @var int
	 */
	private $memberId;

	/**
	 * UserEventsControl constructor.
	 * @param AkceService $akceService
	 * @param int $memberId
	 */
	public function __construct(AkceService $akceService, int $memberId)
	{
		parent::__construct();

		$this->akceService = $akceService;
		$this->memberId = $memberId;
	}

	public function render()
	{
		$this->template->setFile(__DIR__ . '/UserEventsControl.latte');
		$this->template->memberId = $this->memberId;
		$events = $this->akceService->getAkceByMemberId($this->memberId)->limit(self::DEFAULT_EVENT_OFFSET, $this->eventsOffset);
		$count = $events->count();
		$this->template->now = new DateTime();
		$this->template->events = $events;
		$this->template->offset = ($count) ? $this->eventsOffset + self::DEFAULT_EVENT_OFFSET : 0;

		$this->template->render();
	}

	/**
	 * @param int $offset
	 */
	public function handleLoadMoreUserEvents(int $offset) {
		$this->eventsOffset = $offset;
		$this->redrawControl('events-table');
		$this->redrawControl('events-loadMore');
	}
}