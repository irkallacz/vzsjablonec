<?php

require __DIR__ . '/../vendor/autoload.php';

Testbench\Bootstrap::setup(__DIR__ . '/_temp', function (\Nette\Configurator $configurator) {
	$configurator->createRobotLoader()->addDirectory([
		__DIR__ . '/../app',
	])->register();

	$configurator->addParameters([
		'appDir' => __DIR__ . '/../app',
		'wwwDir' => __DIR__ . '/../www/vzsjablonec',
	]);

	$configurator->addConfig(__DIR__ . '/../app/config/config.neon');
	$configurator->addConfig(__DIR__ . '/tests.neon');
});