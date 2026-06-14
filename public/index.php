<?php

require_once __DIR__ . '/../keldagrim/Autoloader.php';
Autoloader::init();

use Keldagrim\App;

$app = new App;
$app->run();
