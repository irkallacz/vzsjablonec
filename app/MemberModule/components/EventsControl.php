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

final class EventsControl extends AbstractAjaxControl
{
	/**
	 * @var AkceService
	 */
	protected $service;

	public function render()
	{
		$this->template->setFile(__DIR__ . '/EventsControl.latte');
		$this->items = $this->service->getAkceByMemberId($this->memberId)
			->limit(self::DEFAULT_OFFSET, $this->offset);

		$this->template->now = new DateTime();

		parent::render();
	}
}