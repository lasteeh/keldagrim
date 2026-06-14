<?php

require_once __DIR__ . '/../keldagrim/Autoloader.php';
Autoloader::init();

use Keldagrim\App;

ob_start();

$app = new App;
$app->run();

ob_end_flush();
