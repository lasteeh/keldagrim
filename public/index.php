<?php

require_once __DIR__ . '/../keldagrim/core/Autoloader.php';
Autoloader::init();

use Keldagrim\Core\App;

$app = new App;
$app->run();
