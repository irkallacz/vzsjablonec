<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 22.3.2018
 * Time: 21:04
 */

namespace App\MemberModule\Components;

use Nette\Application\UI\Control;
use Tracy\Debugger;

class YearPaginator extends Control {

	/** @persistent */
	public $year;

	/** @var int */
	private $yearsStart;

	/** @var int */
	private $yearsEnd;

	/** @var int */
	private $yearsStep;

	/**
	 * YearPaginator constructor.
	 * @param $yearsStart
	 * @param null $yearsEnd
	 * @param int $yearsStep
	 */
	public function __construct(int $yearsStart, int $yearsEnd = NULL, int $yearsStep = 3, int $default = NULL) {
		parent::__construct();
		$this->yearsStart = $yearsStart;
		$this->yearsEnd = ($yearsEnd) ? $yearsEnd : intval(date('Y'));
		$this->yearsStep = $yearsStep;
		$this->year = $default;
	}


	/**
	 * Renders paginator.
	 * @return void
	 */
	public function render() {
		$this->template->year = $year = $this->year;

		if (!is_int($year)) $year = $this->yearsEnd;

		$count = 2 * $this->yearsStep;
		$start = $this->yearsStart + (($year - $this->yearsStart) - $this->yearsStep);
		$end = $start + $count;

		if ($end > $this->yearsEnd) {
			$start = $this->yearsEnd - $count;
			$end = $this->yearsEnd;
		}

		if ($start < $this->yearsStart) {
			$start = $this->yearsStart;
			$end = $this->yearsStart + $count - 1;
		}

		$this->template->years = range($start, $end);

		if (is_int($this->year)) $this->template->prev = (($year - 1) >= $this->yearsStart) ? ($year - 1) : NULL; else $this->template->prev = $this->yearsEnd;
		$this->template->next = (($year + 1) <= $this->yearsEnd) ? ($year + 1) : NULL;

		$this->template->setFile(__DIR__ . '/YearPaginator.latte');
		$this->template->render();
	}

	/**
	 * Loads state informations.
	 * @param  array
	 * @return void
	 */
	public function loadState(array $params) {
		parent::loadState($params);

		switch ($this->year) {
			case NULL:
			case 'NAN':
				$this->year = NAN;
				break;
			case 'INF':
				$this->year = INF;
				break;
			default:
				$this->year = intval($this->year);
		}

		if (is_int($this->year)) {
			if ($this->year < $this->yearsStart) $this->year = $this->yearsStart;
			if ($this->year > $this->yearsEnd) $year = $this->yearsEnd;
		}
	}
}