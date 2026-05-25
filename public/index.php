<?php

require_once __DIR__ . '/../keldagrim/Autoloader.php';
Autoloader::init();

use Keldagrim\Application;

$app = new Application;
$app->run();
