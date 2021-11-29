<?php
namespace App\Console;

use App\PhotoModule\ImageService;
use Nette\Utils\Finder;
use Nette\Utils\Image;
use Nette\Utils\ImageException;
use Nette\Utils\Strings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

final class CreateThumbsCommand extends BaseCommand {

	/** @var ImageService */
	private $imageService;

	/**
	 * CreateThumbsCommand constructor.
	 * @param ImageService $imageService
	 */
	public function __construct(ImageService $imageService) {
		parent::__construct();
		$this->imageService = $imageService;
	}

	protected function configure() {
		$this->setName('thumbs:create')
			->setDescription('Create all thumbnails');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$files = Finder::findFiles('*.jpg', '*.jpeg', '*.gif')
			->from($this->imageService->getAbsoluteImageDir())
			->exclude($this->imageService->getThumbDir());

		foreach ($files as $file => $fileInfo) {
			$album = (int) basename(dirname($file));
			$filename = basename($file);
			$thumbname = pathinfo($filename, PATHINFO_FILENAME);
			$thumbname = Strings::webalize($thumbname).'.jpg';
			$thumbdir =  'www/vzsjablonec/albums/thumbs/' . $album;
			$thumbname = $thumbdir . '/' . $thumbname;

			$output->write($album . ' ' . $filename . ' ');

			if (file_exists($thumbname)) {
				$output->write('Skip: ');
				$output->writeln($thumbname);
			} else {
				$output->write('Generate: ');

				if (!file_exists($thumbdir)) {
					mkdir($thumbdir, 0755);
				}

				try {
					$image = Image::fromFile($file);
					$image->alphaBlending(FALSE);
					$image->saveAlpha(TRUE);
					$image->resize(150, 100, Image::EXACT)->sharpen();
					$image->save($thumbname, 80, Image::JPEG);

				} catch (ImageException $e) {
					$output->write('Error: ');
				}
				finally {
					$output->writeln(basename($thumbname));
				}
			}
		}

		$output->writeln('');
		$output->writeln('<info>Finished</info>');

		return 0; // zero return code means everything is ok
	}
}
