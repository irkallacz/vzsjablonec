<?php

namespace App\MemberModule\Presenters;

use App\Model\AkceService;
use App\Model\UserService;
use Joseki\Webloader\JsMinFilter;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\MemberAccessException;
use Nette\Utils\DateTime;

class ReportPresenter extends LayerPresenter{

	/** @var AkceService @inject */
	public $akceService;


	public function renderView(int $id){
		$akce = $this->akceService->getReportById($id);
		$this->template->akce = $akce;
		$this->template->id = $id;
		$this->template->placeno = 0;
		$this->template->hodiny = 0;

		$this->template->members = $this->akceService->getMembersByReportId($id)
			->select(':report_member.date_start,:report_member.date_end,:report_member.hodiny,:report_member.placeno,name,surname');
	}



}
    