<?php

require_once __DIR__ . '/Autoloader.php';
Autoloader::init();

use Keldagrim\CLI\Oracle;

$oracle = new Oracle;
$oracle->run($argv);

