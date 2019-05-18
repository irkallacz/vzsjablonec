<?php

// absolute filesystem path to this web root
define('WWW_DIR', __DIR__);

// absolute filesystem path to the application root
define('APP_DIR', __DIR__ . '/../../app');

// uncomment this line if you must temporarily take down your site for maintenance
//require __DIR__ . '/.maintenance.php';

// load bootstrap file
$container = require APP_DIR . '/bootstrap.php';

$container->getByType(Nette\Application\Application::class)
	->run(); 