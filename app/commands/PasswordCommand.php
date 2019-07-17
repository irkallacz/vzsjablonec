<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 27.02.2018
 * Time: 13:23
 */

namespace App\Console;

use Nette\Security\Passwords;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PasswordCommand extends Command {

	/**
	 * @return void
	 */
	protected function configure() {
		$this->setName('password:hash')
			->setDescription('Generate hash for password')
			->addArgument('password',InputArgument::REQUIRED, 'Input the password');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return void
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {

		$password = trim($input->getArgument('password'));
		$output->writeln(Passwords::hash($password));
	}
}