<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 14.12.2019
 * Time: 12:03
 */

namespace App\MemberModule\Components;

use App\Model\DatabaseService;
use Nette\Database\Table\Selection;

abstract class AbstractAjaxControl extends LayerControl
{
	const DEFAULT_COUNT = 10;

	/**
	 * @var DatabaseService
	 */
	protected $service;

	/**
	 * @var int
	 */
	protected $offset = 0;

	/**
	 * @var int
	 */
	protected $memberId;

	/**
	 * @var Selection
	 */
	protected $items;

	/**
	 * AbstractAjaxControl constructor.
	 * @param DatabaseService $service
	 * @param int $memberId
	 */
	public function __construct(DatabaseService $service, int $memberId)
	{
		parent::__construct();

		$this->service = $service;
		$this->memberId = $memberId;
	}

	public function render()
	{
		$this->template->memberId = $this->memberId;
		$this->template->items = $this->items;
		$count = $this->items->count();
		$this->template->offset = ($count) ? $this->offset + self::DEFAULT_COUNT : 0;
		$this->template->loadMore = $count == self::DEFAULT_COUNT;

		$this->template->render();
	}

	/**
	 * @param int $offset
	 */
	public function handleLoadMore(int $offset) {
		$this->offset = $offset;
		$this->redrawControl('table');
		$this->redrawControl('loadMore');
	}
}