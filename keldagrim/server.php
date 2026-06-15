<?php

require_once __DIR__ . '/Autoloader.php';
Autoloader::init();

use Keldagrim\Config;
Config::init();

$request_uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$static_file = 
  Config::HOME_DIR() . DIRECTORY_SEPARATOR . 
  Config::PUBLIC_DIR . DIRECTORY_SEPARATOR . 
  ltrim($request_uri, '/'); 

if (
  $request_uri !== '/' && 
  file_exists($static_file) &&
  !is_dir($static_file)
  ) return false;

require_once Config::HOME_DIR() . Config::INDEX;
