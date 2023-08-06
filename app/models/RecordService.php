<?php


namespace App\Model;


use Nette\FileNotFoundException;
use Nette\Utils\Json;
use Tracy\Debugger;

final class RecordService
{
	const FILE_EXTENSION = '.json';

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * RecordService constructor.
	 * @param string $dir
	 */
	public function __construct(string $dir)
	{
		$this->dir = $dir;
	}

	public function getList(int $year): array
	{
		$list = glob($this->getFilePath($year, '*'));
		return array_map(function ($value) {
			return rtrim(basename($value), self::FILE_EXTENSION);
		}, $list);
	}

	public function getRecord(int $year, string $day): \stdClass
	{
		$file = $this->getFilePath($year, $day);

		if (!file_exists($file)) {
			throw new FileNotFoundException('File not found');
		}

		return Json::decode(file_get_contents($file));
	}

	private function getFilePath(int $year, string $file): string
	{
		return $this->dir. DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $file . self::FILE_EXTENSION;
	}

}