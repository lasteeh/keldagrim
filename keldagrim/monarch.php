<?php

require_once __DIR__ . '/Autoloader.php';
Autoloader::init();

use Keldagrim\CLI\Monarch;

$monarch = new Monarch; 
$monarch->run($argv);
