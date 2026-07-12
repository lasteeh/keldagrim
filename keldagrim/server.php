<?php

$start = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

require_once __DIR__ . '/core/Autoloader.php';
Autoloader::init();

use Keldagrim\Core\Config;

Config::init();

$request_uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$static_file =
  Config::HOME_DIR() . DIRECTORY_SEPARATOR .
  Config::PUBLIC_DIR . DIRECTORY_SEPARATOR .
  ltrim($request_uri, '/');

if (
  $request_uri !== '/' &&
  is_file($static_file) &&
  !is_dir($static_file)
) return false;

register_shutdown_function(function () use ($start, $request_uri) {
  $time = (new DateTimeImmutable)->format(DateTimeInterface::ATOM);
  $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
  $method = $_SERVER['REQUEST_METHOD'] ?? '-';
  $status = http_response_code();
  $duration = round((microtime(true) - $start) * 1000, 2);

  file_put_contents(
    "php://stderr",
    sprintf(
      "%s %-15s %-6s %-40s %3d %7.2fms\n",
      $time,
      $ip,
      $method,
      $request_uri,
      $status,
      $duration
    )
  );
});

require_once
  Config::HOME_DIR() . DIRECTORY_SEPARATOR .
  Config::PUBLIC_DIR . DIRECTORY_SEPARATOR .
  Config::INDEX_FILE;
