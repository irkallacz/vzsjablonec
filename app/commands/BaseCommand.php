<?php


namespace App\Console;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
	protected function writeln(OutputInterface $output, ...$arguments)
	{
		$output->writeln(join("\t", $arguments), OutputInterface::VERBOSITY_VERBOSE);
	}
}