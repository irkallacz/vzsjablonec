<?php

/**
 * My Application bootstrap file.
 */
use Nette\Diagnostics\Debugger;

define('LIBS_DIR', __DIR__ . '/../libs');
define('TEMP_DIR', __DIR__ . '/../tmp');

setlocale(LC_ALL,'cs_CZ.utf8');

// Load Nette Framework
require LIBS_DIR . '/Nette/loader.php';


// Configure application
$configurator = new Nette\Configurator();

// Enable Nette Debugger for error visualisation & logging
//$configurator->setDebugMode($configurator::AUTO);
$configurator->enableDebugger(__DIR__ . '/../log');

// Enable RobotLoader - this will load all classes automatically
$configurator->setTempDirectory(TEMP_DIR);
$configurator->createRobotLoader()
	->addDirectory(APP_DIR)
	->addDirectory(LIBS_DIR)
	->register();

// Create Dependency Injection container from config.neon file
$configurator->addConfig(__DIR__ . '/config.neon');
if (file_exists(__DIR__ . '/config.local.neon')) $configurator->addConfig(__DIR__ . '/config.local.neon');
$container = $configurator->createContainer();

//$container->application->errorPresenter = 'Error';

Nette\Security\User::extensionMethod('isInArray', function (Nette\Security\User $user, $array) {
	return in_array($user->getId(), $array);
});

// Configure and run the application!
$container->application->run();