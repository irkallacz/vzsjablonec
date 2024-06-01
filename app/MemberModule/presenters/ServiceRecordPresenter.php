<?php


namespace App\MemberModule\Presenters;


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
	public function renderDefault(int $year = 2023)
	{
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
			]);
		}
	}

	/**
	 * @allow(member)
	 */
	public function renderView(int $year, string $day)
	{
		$this->template->addFilter('nl2br',function ($text) {
			return new \Latte\Runtime\Html(nl2br($text));
		});

		$list = $this->recordService->getList($year);

		$this->template->list = $list;
		$this->template->year = $year;
		$this->template->day = $day;


		if (!in_array($day, $list)) {
			throw new BadRequestException('File not existed');
		}

		$id = array_search($day, $list);

		$this->template->prev = $list[$id-1] ?? null;
		$this->template->next = $list[$id+1] ?? null;

		$this->template->record = $this->recordService->getRecord($year, $day);

	}


}