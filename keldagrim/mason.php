<?php

require_once __DIR__ . '/core/Autoloader.php';
Autoloader::init();

use Keldagrim\CLI\Mason;

$mason = new Mason;
$mason->run($argv);
