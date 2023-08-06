<?php


namespace App\MemberModule\Presenters;


use App\Model\RecordService;
use Nette\Application\BadRequestException;
use Tracy\Debugger;

final class ServiceRecordPresenter extends LayerPresenter
{
	/**
	 * @var RecordService @inject
	 */
	public $recordService;

	public function renderDefault(int $year = 2023, string $day = null)
	{
		$this->template->addFilter('nl2br',function ($text) {
			return new \Latte\Runtime\Html(nl2br($text));
		});

		$list = $this->recordService->getList($year);

		$this->template->list = $list;
		$this->template->year = $year;
		$this->template->day = $day;

		if ($day) {
			if (!in_array($day, $list)) {
				throw new BadRequestException('File not existed');
			}

			$id = array_search($day, $list);

			$this->template->prev = $list[$id-1] ?? null;
			$this->template->next = $list[$id+1] ?? null;

			$this->template->record = $this->recordService->getRecord($year, $day);
		}
	}


}