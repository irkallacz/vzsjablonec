<?php
namespace App\MemberModule\Presenters;

use App\Model\DokumentyService;
use Google_Service_Drive;
use Nette\Application\BadRequestException;
use Nette\Application\AbortException;
use Nette\Application\Responses\TextResponse;
use Nette\Http\Response;
use Tracy\Debugger;

class DokumentyPresenter extends LayerPresenter {

	/** @var Google_Service_Drive @inject */
	public $driveService;

	/** @var DokumentyService @inject */
	public $dokumentyService;

	/** @var Response @inject */
	public $httpResponse;

	/**
	 *
	 */
	public function renderDefault() {
		$this->template->TABLE_DOKUMENTY = DokumentyService::TABLE_DOKUMENTY;
		$this->template->TABLE_DIRECTORIES = DokumentyService::TABLE_DIRECTORIES;

		$this->template->dir = $this->dokumentyService->getDirectoryRoot();
	}

	/**
	 * @param string $id
	 * @throws BadRequestException
	 * @throws AbortException
	 */
	public function actionGetPdf(string $id) {
		$file = $this->dokumentyService->getDokumentById($id);

		if (!$file) throw new BadRequestException('Soubor nenalezen');

		$response = $this->driveService->files->export($id, 'application/pdf', ['alt' => 'media']);

		$this->httpResponse->setHeader('Content-Disposition', 'attachment; filename="' . $file->name . '.pdf"');
		$this->httpResponse->setHeader('Content-Type', 'application/pdf');
		$this->sendResponse(new TextResponse($response->getBody()->getContents()));
	}
}