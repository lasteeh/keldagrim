<?php

require_once __DIR__ . '/core/Autoloader.php';
Autoloader::init();

use Keldagrim\CLI\Blacksmith;

$blacksmith = new Blacksmith;
$blacksmith->run($argv);
