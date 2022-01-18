<?php
namespace App\Console;

use App\Model\DokumentyService;
use Nette\Utils\DateTime;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile as DriveFile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

/**
 * Class CronPresenter
 * @package App\CronModule\presenters
 */
final class DriveCommand extends BaseCommand {

	const SHORTCUT_MIME_TYPE = 'application/vnd.google-apps.shortcut';

	const FILE_FIELDS = 'id, name, description, mimeType, modifiedTime, webContentLink, webViewLink, iconLink';

	/** @var DokumentyService */
	private $dokumentyService;

	/** @var Google_Service_Drive */
	private $driveService;

	/** @var string */
	private $driveDir;

	/**
	 * DriveCommand constructor.
	 * @param string $driveDir
	 * @param DokumentyService $dokumentyService
	 * @param Google_Service_Drive $driveService
	 */
	public function __construct(string $driveDir, DokumentyService $dokumentyService, Google_Service_Drive $driveService)
	{
		parent::__construct();
		$this->dokumentyService = $dokumentyService;
		$this->driveService = $driveService;
		$this->driveDir = $driveDir;
	}

	protected function configure() {
		$this->setName('cron:drive')
			->setDescription('Get documents and dir structure from Google Drive');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->writeln($output, '<info>Drive Commnand</info>');
		$this->dokumentyService->beginTransaction();

		$this->writeln($output, 'Truncate tables');
		$this->dokumentyService->emptyTables();

		$this->dokumentyService->addDirectory([
			'id' => $this->driveDir,
			'name' => 'Web',
			'parent' => NULL,
			'webViewLink' => '',
			'level' => 0,
		]);

		$this->writeln($output,'Parsing structure');
		$files = $this->getFilesByParent($this->driveDir);
		$this->parseFiles($files, $this->driveDir, $output, 1);

		$this->dokumentyService->commitTransaction();
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
	private function parseFiles(array $files, string $parent,  OutputInterface $output, int $level = 0) {
		foreach ($files as $file) {
			$this->writeln($output, 'File', $file->mimeType, $file->id, $file->name);

			if ($file->mimeType == DokumentyService::DIR_MIME_TYPE) {
				$this->dokumentyService->addDirectory([
					'id' => $file->id,
					'name' => $file->name,
					'parent' => $parent,
					'webViewLink' => $file->webViewLink,
					'level' => $level,
				]);

				$result = $this->getFilesByParent($file->id);
				$this->parseFiles($result, $file->id,  $output, $level + 1);
			} elseif ($file->mimeType == self::SHORTCUT_MIME_TYPE) {
				if ($file->getShortcutDetails()->getTargetMimeType() == DokumentyService::DIR_MIME_TYPE) {
					continue;
				}

				//$targetId = $file->getShortcutDetails()->getTargetId();
				//$target = $this->driveService->files->get($targetId, ['fields' => self::FILE_FIELDS]);
				//$target->getParents();
				//$this->driveService->files->delete($file->id);
				$this->dokumentyService->addFile([
					'id' => $file->shortcutDetails->targetId,
					'name' => $file->name,
					'directory' => $parent,
					'description' => $file->description,
					'modifiedTime' => new DateTime($file->modifiedTime),
					'mimeType' => $file->shortcutDetails->targetMimeType,
					'webContentLink' => $file->webContentLink,
					'webViewLink' => $file->webViewLink,
					'iconLink' =>  $file->iconLink,
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