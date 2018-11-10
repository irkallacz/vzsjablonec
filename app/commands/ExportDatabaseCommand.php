<?php
namespace App\Console;

use App\Model\ConnectionConfig;
use Rah\Danpu\Dump;
use Rah\Danpu\Export;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

class ExportDatabaseCommand extends Command {

	/** @var ConnectionConfig */
	private $databaseConfig;

	/**
	 * ExportDatabaseCommand constructor.
	 * @param ConnectionConfig $databaseConfig
	 */
	public function __construct(ConnectionConfig $databaseConfig) {
		parent::__construct();
		$this->databaseConfig = $databaseConfig;
	}

	protected function configure() {
		$this->setName('database:export')
			->setDescription('Export database structure into .sql file')
			->addArgument('only_db', InputArgument::OPTIONAL, 'Specify the name of database (in config)');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$connections = $this->databaseConfig->getConnections();

		$only_db = $input->getArgument('only_db');
		if (($only_db)and(array_key_exists($only_db, $connections))) {
			$connections = [$only_db => $connections[$only_db]];
		}

		$count = count($connections);

		$bar = new ProgressBar($output, $count);
		$bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
		$bar->setMessage('');
		$bar->start();

		foreach ($connections as $name => $config) {
			$dump = new Dump();

			$dump->dsn($config['driver'] . ':host=' . $config['host'] . ';dbname=' . $config['dbname'])
				->user($config['user'])
				->pass($config['password'])
				->file(__DIR__ . '/dump/' . $name . '.sql')
				->data(FALSE);

			$bar->setMessage($name . '.sql');

			new Export($dump);

			$bar->advance();
		}

		$bar->finish();

		$output->writeln('');
		$output->writeln('<info>Finished exporting ' . $count . ' database(s)</info>');

		return 0; // zero return code means everything is ok
	}
}
