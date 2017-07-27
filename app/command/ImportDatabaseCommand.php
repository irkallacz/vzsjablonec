<?php
namespace App\Console;

use App\Model\ConnectionConfig;
use Rah\Danpu\Dump;
use Rah\Danpu\Import;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

class ImportDatabaseCommand extends Command{

	/** @var ConnectionConfig @inject*/
	public $databaseConfig;

	protected function configure(){
        $this->setName('database:import')
	        ->setDescription('Import database structure from .sql file');
    }

	protected function execute(InputInterface $input, OutputInterface $output) {
		Debugger::enable(Debugger::DEVELOPMENT);

		$count = count($this->databaseConfig->getConnections());

		$bar = new ProgressBar($output, $count);
		$bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
		$bar->setMessage('');
		$bar->start();

		foreach ($this->databaseConfig->getConnections() as $name => $config){
			$fileName = __DIR__ .'/dump/'.$name.'.sql';

			if (file_exists($fileName)) {
				$dump = new Dump();

				$dump->dsn($config['driver'] . ':host=' . $config['host'] . ';dbname=' . $config['dbname'])
					->user($config['user'])
					->pass($config['password'])
					->file($fileName);

				$bar->setMessage($name . '.sql');

				new Import($dump);
			}else{
				$output->writeln('<error>Chyba. Soubor '.$fileName.' nebyl nalezen</error>');
			}
			$bar->advance();
		}

		$bar->finish();

		$output->writeln('');
	    $output->writeln('<info>Finished importing '.$count.' database(s) from files</info>');

        return 0; // zero return code means everything is ok
    }
}