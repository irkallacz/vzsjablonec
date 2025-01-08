<?php


namespace App\MemberModule\Presenters;


use App\MemberModule\Components\YearPaginator;
use App\Model\RecordService;
use Nette\Application\BadRequestException;
use Nette\Utils\ArrayHash;
use Tracy\Debugger;

final class ServiceRecordPresenter extends LayerPresenter
{
	/**
	 * @var RecordService @inject
	 */
	public $recordService;

	/**
	 * @allow(member)
	 */
	public function renderDefault()
	{
		$year = $this['yp']->year;
		$list = $this->recordService->getList($year);

		$this->template->year = $year;
		$this->template->prev = null;
		$this->template->next = null;

		$this->template->serviceRecords = [];
		$this->template->boatRecords = [];
		$this->template->medicalRecords = [];
		$this->template->dayRecords = [];

		foreach ($list as $id => $day) {
			$record = $this->recordService->getRecord($year, $day);
			$this->template->serviceRecords[$day] = $record->serviceRecords;
			$this->template->boatRecords[$day] = $record->boatRecords;
			$this->template->medicalRecords[$day] = $record->medicalRecords;
			$this->template->dayRecords[$day] = ArrayHash::from([
				'attendance' => $record->attendance,
				'weather' => $record->weather,
				'author' => $record->author,
				'datetime' => $record->datetime,
				'dateStart' => intval(date_create($day)->setTime(8,0,0)->format('U')) * 1000,
				'dateEnd' => intval(date_create($day)->setTime(20,0,0)->format('U')) * 1000,
			]);
		}
	}

	/**
	 * @return YearPaginator
	 */
	public function createComponentYp() {
		return new YearPaginator(2023, 2024, 1, 2024);
	}

	/**
	 * @allow(member)
	 */
	public function renderView(string $day)
	{
		$this->template->addFilter('nl2br',function ($text) {
			return new \Latte\Runtime\Html(nl2br($text));
		});

		$date = \DateTimeImmutable::createFromFormat('Y-m-d', $day);
		$date = $date->setTime(8, 0, 0);

		$list = $this->recordService->getList($date->format('Y'));

		$this->template->list = $list;
		$this->template->year = $date->format('Y');
		$this->template->day = $day;
		$this->template->dateStart = intval($date->format('U')) * 1000;
		$this->template->dateEnd = (intval($date->format('U')) + 3600 * 12) * 1000;


		if (!in_array($day, $list)) {
			throw new BadRequestException('File not existed');
		}

		$id = array_search($day, $list);

		$this->template->prev = $list[$id-1] ?? null;
		$this->template->next = $list[$id+1] ?? null;

		$this->template->record = $this->recordService->getRecord($date->format('Y'), $day);
	}
}