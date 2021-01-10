<?php
namespace App\CronModule\Presenters;

use App\Model\DokumentyService;
use Nette\Utils\DateTime;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile as DriveFile;
use Tracy\Debugger;

/**
 * Class CronPresenter
 * @package App\CronModule\presenters
 */
class DrivePresenter extends BasePresenter {

	const SHORTCUT_MIME_TYPE = 'application/vnd.google-apps.shortcut';

	const FILE_FIELDS = 'id, name, description, mimeType, modifiedTime, webContentLink, webViewLink, iconLink';

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

		$files = $this->getFilesByParent($this->dokumentyService->driveDir);
		$this->parseFiles($files, $this->dokumentyService->driveDir, 1);

		$this->dokumentyService->commitTransaction();

		$this->template->files = $this->dokumentyService->getDokumenty()->order('directory,name');
	}

	/**
	 * @param string $parent
	 * @return DriveFile|DriveFile[]
	 */
	private function getFilesByParent(string $parent) {
		$result = $this->driveService->files->listFiles([
			'q' => "'$parent' in parents",
			'fields' => 'files(' . self::FILE_FIELDS . ', shortcutDetails)',
			'orderBy' => 'folder,name'
		]);

		return $result->getFiles();
	}

	/**
	 * @param DriveFile[] $files
	 * @param string $parent
	 * @param int $level
	 */
	private function parseFiles(array $files, string $parent, int $level = 0) {
		foreach ($files as $file) {
			if ($file->mimeType == DokumentyService::DIR_MIME_TYPE) {
				$this->dokumentyService->addDirectory([
					'id' => $file->id,
					'name' => $file->name,
					'parent' => $parent,
					'webViewLink' => $file->webViewLink,
					'level' => $level,
				]);

				$result = $this->getFilesByParent($file->id);
				$this->parseFiles($result, $file->id, $level + 1);
			} elseif ($file->mimeType == self::SHORTCUT_MIME_TYPE) {
				if ($file->getShortcutDetails()->getTargetMimeType() == DokumentyService::DIR_MIME_TYPE) {
					continue;
				}
				
				$targetId = $file->getShortcutDetails()->getTargetId();
				$target = $this->driveService->files->get($targetId, ['fields' => self::FILE_FIELDS]);
				//$target->getParents();
				//$this->driveService->files->delete($file->id);
				$this->dokumentyService->addFile([
					'id' => $target->id,
					'name' => $file->name,
					'directory' => $parent,
					'description' => $file->description,
					'modifiedTime' => new DateTime($file->modifiedTime),
					'mimeType' => $target->mimeType,
					'webContentLink' => $target->webContentLink,
					'webViewLink' => $target->webViewLink,
					'iconLink' => $target->iconLink,
				]);
			} else {
				$this->dokumentyService->addFile([
					'id' => $file->id,
					'name' => $file->name,
					'directory' => $parent,
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