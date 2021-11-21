<?php
namespace App\Console;

use App\Model\AkceService;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

final class ImportReportCommand extends Command {

	/** @var AkceService */
	private $akceService;

	/**
	 * ImportReportCommand constructor.
	 * @param AkceService $akceService
	 */
	public function __construct(AkceService $akceService)
	{
		parent::__construct();
		$this->akceService = $akceService;
	}


	protected function configure() {
		$this->setName('database:report')
			->setDescription('Export reports from database and insert it into event message');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {

		$reports = $this->akceService->getReport();

		$latte = new \Latte\Engine;
		$latte->addFilter(NULL, '\App\Template\LatteFilters::loader');

		foreach ($reports as $report) {

			$output->write($report->id);
			$output->write(' ');
			$akce = $this->akceService->getAkceById($report->id);

			if ($akce){
				$output->writeln($akce->name);
				$members = $report->related('report_member');
				$message = $latte->renderToString(__DIR__ . '/report.latte', [
					'report' => $report,
					'akce' => $akce,
					'members' => $members,
				]);

				$message = Strings::normalize(Strings::replace($message, '~\n\n\n+~', "\n\n"));
				//$message = Strings::replace($message, "~\n~m", '\n');
				//file_put_contents(__DIR__.'/report.txt', "UPDATE akce SET message = '$message' WHERE id = $akce->id;\n", FILE_APPEND);
				$akce->update(['message' => $message]);
			} else $output->writeln('');

		}

		$output->writeln('');
		$output->writeln('<info>Finished exporting reports(s)</info>');

		return 0; // zero return code means everything is ok
	}
}
