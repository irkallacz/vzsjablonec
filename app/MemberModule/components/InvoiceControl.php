<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 14.12.2019
 * Time: 12:03
 */

namespace App\MemberModule\Components;


use App\Model\InvoiceService;

final class InvoiceControl extends AbstractAjaxControl
{
	/**
	 * @var InvoiceService
	 */
	protected $service;

	public function render()
	{
		$this->template->setFile(__DIR__ . '/InvoiceControl.latte');
		$this->items = $this->service->getInvoicesByUserId($this->memberId)
			->order('date_add DESC')
			->limit(self::DEFAULT_OFFSET, $this->offset);

		parent::render();
	}
}