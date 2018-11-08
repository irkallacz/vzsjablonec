<?php
namespace App\CronModule\Presenters;

use App\Model\DokumentyService;
use Google_Service_Drive;
use Nette\Utils\DateTime;

/**
 * Class CronPresenter
 * @package App\CronModule\presenters
 */
class DrivePresenter extends BasePresenter {

	/** @var DokumentyService @inject */
	public $dokumentyService;

	/** @var Google_Service_Drive @inject */
	public $driveService;

	public function actionDefault() {
		$this->dokumentyService->beginTransaction();
		$this->dokumentyService->emptyTables();

		$this->dokumentyService->addDirectory([
			'id' => $this->dokumentyService->driveDir,
			'name' => 'Web',
			'parent' => NULL,
			'webViewLink' => '',
			'level' => 0,
		]);

		$files = $this->driveService->files->listFiles(self::getFileSearchQuery($this->dokumentyService->driveDir));
		$this->parseFiles($files->getFiles(), $this->dokumentyService->driveDir, 1);

		$this->dokumentyService->commitTransaction();

		$this->template->files = $this->dokumentyService->getDokumenty()->order('directory,name');
	}

	/**
	 * @param $dir
	 * @return array
	 */
	private static function getFileSearchQuery($dir) {
		if (!is_array($dir)) $dir = [$dir];

		$string = join("' or parents in '", $dir);
		$string = "parents in '" . $string . "'";

		return [
			'q' => $string,
			'fields' => 'files(id, name, description, mimeType, modifiedTime, parents, webContentLink, webViewLink, iconLink)',
			'orderBy' => 'folder,name'
		];
	}

	/**
	 * @param array $files
	 * @param null $parent
	 * @param int $level
	 */
	private function parseFiles(array $files, $parent = NULL, $level = 0) {
		foreach ($files as $file) {
			if ($file->mimeType == DokumentyService::DIR_MIME_TYPE) {
				$this->dokumentyService->addDirectory([
					'id' => $file->id,
					'name' => $file->name,
					'parent' => $parent,
					'webViewLink' => $file->webViewLink,
					'level' => $level,
				]);

				$result = $this->driveService->files->listFiles(self::getFileSearchQuery($file->id));
				$this->parseFiles($result->getFiles(), $file->id, $level + 1);
			} else {
				$this->dokumentyService->addFile([
					'id' => $file->id,
					'name' => $file->name,
					'directory' => $file->parents[0],
					'description' => $file->description,
					'modifiedTime' => new DateTime($file->modifiedTime),
					'mimeType' => $file->mimeType,
					'webContentLink' => $file->webContentLink,
					'webViewLink' => $file->webViewLink,
					'iconLink' => $file->iconLink,
				]);
			}
		}
	}
}