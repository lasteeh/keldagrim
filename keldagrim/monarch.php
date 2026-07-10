<?php

require_once __DIR__ . '/core/Autoloader.php';
Autoloader::init();

use Keldagrim\CLI\Monarch;

$monarch = new Monarch; 
$monarch->run($argv);
