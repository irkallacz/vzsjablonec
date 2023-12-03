<?php


namespace App\Console;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
	protected function writeln(OutputInterface $output, ...$arguments)
	{
		foreach ($arguments as $i => $argument) {
			if ($argument instanceof \DateTimeInterface) {
				$arguments[$i] = $argument->format('Y-m-d');
			}
		}

		$output->writeln(join("\t", $arguments), OutputInterface::VERBOSITY_VERBOSE);
	}
}