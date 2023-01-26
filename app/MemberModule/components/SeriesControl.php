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

final class SeriesControl extends AbstractAjaxControl
{
	/**
	 * @var AkceService
	 */
	protected $service;

	/**
	 * @var int
	 */
	protected $eventId;

	/**
	 * @var int
	 */
	protected $seriesId;

	/**
	 * SeriesControl constructor.
	 * @param AkceService $service
	 * @param int $eventId
	 * @param int $seriesId
	 */
	public function __construct(AkceService $service, int $eventId, int $seriesId = null)
	{
		$this->service = $service;
		$this->eventId = $eventId;
		$this->seriesId = $seriesId;
	}

	public function render()
	{
		$this->template->setFile(__DIR__ . '/SeriesControl.latte');
		$this->items = $this->service->getAkceBySeries($this->seriesId)
			->limit(self::DEFAULT_COUNT, $this->offset);

		$this->template->eventId = $this->eventId;
		$this->template->seriesName = $this->service->getSerieById($this->seriesId)->name;
		$this->template->now = new DateTime();

		parent::render();
	}
}