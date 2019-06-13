<?php

// uncomment this line if you must temporarily take down your site for maintenance
//require __DIR__ . '/.maintenance.php'; 

require __DIR__ . '/../vendor/autoload.php';

setlocale(LC_ALL,'cs_CZ.utf8');

$configurator = new Nette\Configurator;

//$configurator->setDebugMode('23.75.345.200'); // enable for your remote IP
$configurator->enableTracy(__DIR__ . '/../log');

$configurator->setTimeZone('Europe/Prague');
$configurator->setTempDirectory(__DIR__ . '/../tmp');

$configurator->addParameters(['wwwDir' => dirname(__DIR__) . '/www/vzsjablonec']);

$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');
$configurator->addConfig(__DIR__ . '/config/config.local.neon');

$container = $configurator->createContainer();

return $container;
